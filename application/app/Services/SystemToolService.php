<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * BARAMA_SYSTEM_TOOL_V100: CSV data transfer and private database snapshots.
 * Never executes uploaded SQL, restores user-provided archives or invokes shell.
 */
final class SystemToolService
{
    private const DATASETS = ['locations', 'amenities', 'companies', 'projects', 'properties'];
    private const MAX_UPLOAD = 5242880;
    private const MAX_ROWS = 5000;
    private const MAX_BACKUP_DATA = 134217728;
    private const MAX_ZIP = 33554432;

    public function datasets(): array
    {
        return array_values(array_intersect(self::DATASETS, array_keys($this->tables())));
    }

    public function storageReady(): bool
    {
        try { return $this->vault(false) !== null; }
        catch (Throwable) { return false; }
    }

    public function backups(): array
    {
        $dir = $this->vault(false);
        if ($dir === null) return [];
        $list = [];
        foreach (glob($dir . '/backup-*.json') ?: [] as $file) {
            if (is_link($file) || !is_file($file) || filesize($file) > 4096) continue;
            $data = json_decode((string) file_get_contents($file), true);
            $id = (string) ($data['id'] ?? '');
            if (!self::validId($id) || $file !== $dir . '/backup-' . $id . '.json') continue;
            try {
                $zip = $this->verifiedBackup($id);
                $list[] = ['id' => $id, 'created_at' => (string) ($data['created_at'] ?? ''),
                    'size' => (int) filesize($zip)];
            } catch (Throwable) { continue; }
        }
        usort($list, static fn ($a, $b) => strcmp($b['id'], $a['id']));
        return array_slice($list, 0, 20);
    }

    /** Admin-only, bound to a fixed set of marketplace data tables. */
    public function exportCsv(string $table): string
    {
        $this->dataset($table);
        $fields = array_keys($this->columns($table));
        $stream = fopen('php://temp/maxmemory:1048576', 'w+');
        if ($stream === false) throw new RuntimeException('CSV_OUTPUT_UNAVAILABLE');
        try {
            fputcsv($stream, $fields);
            $rows = Database::connection()->query('SELECT * FROM ' . self::ident($table) .
                ' ORDER BY ' . self::ident($fields[0]) . ' LIMIT 50001');
            $count = 0;
            while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (++$count > 50000) throw new RuntimeException('CSV_EXPORT_ROW_LIMIT');
                $output = [];
                foreach ($fields as $key) {
                    $text = $row[$key] === null ? '' : (string) $row[$key];
                    if (preg_match('/^[\x00-\x20]*[=+@]/', $text) === 1) $text = "'" . $text;
                    $output[] = $text;
                }
                fputcsv($stream, $output);
                if (ftell($stream) > 16777216) throw new RuntimeException('CSV_EXPORT_SIZE_LIMIT');
            }
            rewind($stream);
            return "\xEF\xBB\xBF" . (string) stream_get_contents($stream);
        } finally { fclose($stream); }
    }

    /**
     * Transactional insert-only import. Preview rolls back the same validation
     * transaction. Duplicate keys and foreign-key errors abort all rows.
     */
    public function importCsv(string $table, array $upload, bool $preview): array
    {
        $this->dataset($table);
        if ((int) ($upload['error'] ?? -1) !== UPLOAD_ERR_OK ||
            !is_string($upload['tmp_name'] ?? null) ||
            !is_uploaded_file($upload['tmp_name']) ||
            (int) ($upload['size'] ?? 0) < 1 ||
            (int) ($upload['size'] ?? 0) > self::MAX_UPLOAD) {
            throw new RuntimeException('CSV_UPLOAD_REJECTED_MAX_5MB');
        }
        $stream = fopen($upload['tmp_name'], 'rb');
        if ($stream === false) throw new RuntimeException('CSV_UNREADABLE');
        try {
            $header = fgetcsv($stream, 0, ',', '"', '\\');
            if (!is_array($header) || !$header) throw new RuntimeException('CSV_HEADER_MISSING');
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $allowed = $this->columns($table);
            if (count($header) !== count(array_unique($header))) throw new RuntimeException('CSV_DUPLICATE_COLUMNS');
            foreach ($header as $column) {
                if (!is_string($column) || !isset($allowed[$column])) {
                    throw new RuntimeException('CSV_COLUMN_NOT_IN_DATABASE');
                }
            }
            $db = Database::connection();
            $sql = 'INSERT INTO ' . self::ident($table) . ' (' .
                implode(',', array_map([self::class, 'ident'], $header)) .
                ') VALUES (' . implode(',', array_fill(0, count($header), '?')) . ')';
            $stmt = $db->prepare($sql);
            $count = 0;
            $db->beginTransaction();
            try {
                while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
                    if ($row === [null]) continue;
                    if (++$count > self::MAX_ROWS || count($row) !== count($header)) {
                        throw new RuntimeException('CSV_ROW_COUNT_OR_WIDTH_INVALID');
                    }
                    foreach ($row as $value) {
                        if (!is_string($value) || strlen($value) > 65536 ||
                            !preg_match('//u', $value)) throw new RuntimeException('CSV_VALUE_INVALID');
                    }
                    $stmt->execute($row);
                }
                if ($count === 0) throw new RuntimeException('CSV_HAS_NO_ROWS');
                if ($preview) $db->rollBack(); else $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
            return ['rows' => $count, 'columns' => count($header)];
        } finally { fclose($stream); }
    }

    /** Consistent logical snapshot of all InnoDB base tables, database only. */
    public function backup(): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZIP_EXTENSION_REQUIRED');
        $dir = $this->vault(true);
        $id = gmdate('YmdHis') . '-' . bin2hex(random_bytes(8));
        $tmp = $dir . '/work-' . $id;
        if (!mkdir($tmp, 0700)) throw new RuntimeException('PRIVATE_BACKUP_DIR_UNAVAILABLE');
        $target = $dir . '/backup-' . $id . '.zip';
        $manifest = ['format' => 'barama-db-snapshot-v1', 'created_at' => gmdate('c'), 'tables' => []];
        $db = Database::connection();
        $total = 0;
        $records = 0;
        try {
            $tables = $this->tables();
            if (!$tables || count($tables) > 200) throw new RuntimeException('BACKUP_TABLE_LIMIT');
            foreach ($tables as $engine) {
                if (strtoupper($engine) !== 'INNODB') throw new RuntimeException('BACKUP_INNODB_ONLY');
            }
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->beginTransaction();
            foreach ($tables as $name => $_engine) {
                $file = $tmp . '/' . $name . '.jsonl';
                $output = fopen($file, 'xb');
                if ($output === false) throw new RuntimeException('BACKUP_FILE_CREATE_FAILED');
                $rowCount = 0;
                $columns = array_keys($this->columns($name));
                try {
                    $stmt = $db->query('SELECT * FROM ' . self::ident($name));
                    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                        $cells = [];
                        foreach ($columns as $col) {
                            $cells[] = $row[$col] === null ? null : base64_encode((string) $row[$col]);
                        }
                        $line = json_encode($cells, JSON_THROW_ON_ERROR) . "\n";
                        $total += strlen($line);
                        if ($total > self::MAX_BACKUP_DATA) throw new RuntimeException('BACKUP_128MB_DATA_LIMIT');
                        if (fwrite($output, $line) !== strlen($line)) throw new RuntimeException('BACKUP_WRITE_FAILED');
                        ++$rowCount;
                        ++$records;
                    }
                } finally { fclose($output); }
                chmod($file, 0600);
                $manifest['tables'][$name] = ['columns' => $columns,
                    'structure' => $this->structure($name), 'rows' => $rowCount,
                    'sha256' => hash_file('sha256', $file)];
            }
            $db->commit();
            $zip = new ZipArchive();
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('BACKUP_ZIP_FAILED');
            }
            foreach ($manifest['tables'] as $name => $_) {
                if (!$zip->addFile($tmp . '/' . $name . '.jsonl', 'tables/' . $name . '.jsonl')) {
                    throw new RuntimeException('BACKUP_ADD_FILE_FAILED');
                }
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            if (!$zip->close()) throw new RuntimeException('BACKUP_CLOSE_FAILED');
            chmod($target, 0600);
            if (filesize($target) > self::MAX_ZIP) throw new RuntimeException('BACKUP_ZIP_32MB_LIMIT');
            $receipt = ['id' => $id, 'created_at' => $manifest['created_at'],
                'sha256' => hash_file('sha256', $target)];
            $receiptPath = $dir . '/backup-' . $id . '.json';
            if (file_put_contents($receiptPath, json_encode($receipt, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                throw new RuntimeException('BACKUP_RECEIPT_FAILED');
            }
            chmod($receiptPath, 0600);
            return ['id' => $id, 'tables' => count($tables), 'rows' => $records];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            @unlink($target);
            throw $e;
        } finally {
            foreach (glob($tmp . '/*.jsonl') ?: [] as $file) @unlink($file);
            @rmdir($tmp);
        }
    }

    public function verifiedBackup(string $id): string
    {
        if (!self::validId($id)) throw new RuntimeException('BACKUP_ID_INVALID');
        $dir = $this->vault(false);
        if ($dir === null) throw new RuntimeException('BACKUP_NOT_FOUND');
        $zip = $dir . '/backup-' . $id . '.zip';
        $receipt = $dir . '/backup-' . $id . '.json';
        if (!is_file($zip) || is_link($zip) || filesize($zip) > self::MAX_ZIP ||
            !is_file($receipt) || is_link($receipt) || filesize($receipt) > 4096) {
            throw new RuntimeException('BACKUP_UNAVAILABLE_OR_UNSAFE');
        }
        $meta = json_decode((string) file_get_contents($receipt), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($meta) || ($meta['id'] ?? '') !== $id ||
            !hash_equals((string) ($meta['sha256'] ?? ''), hash_file('sha256', $zip))) {
            throw new RuntimeException('BACKUP_INTEGRITY_MISMATCH');
        }
        return $zip;
    }

    /** Restore is database-only, exact-schema, all-or-nothing InnoDB DML. */
    public function restore(string $id): array
    {
        $path = $this->verifiedBackup($id);
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) throw new RuntimeException('BACKUP_ZIP_UNREADABLE');
        try {
            $raw = $zip->getFromName('manifest.json');
            if (!is_string($raw) || strlen($raw) > 524288) throw new RuntimeException('BACKUP_MANIFEST_INVALID');
            $manifest = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            $entries = $manifest['tables'] ?? null;
            $tables = $this->tables();
            if (($manifest['format'] ?? '') !== 'barama-db-snapshot-v1' ||
                !is_array($entries) || array_keys($entries) !== array_keys($tables)) {
                throw new RuntimeException('RESTORE_TABLE_SET_MISMATCH');
            }
            foreach ($entries as $name => $meta) {
                if (!is_array($meta) || strtoupper($tables[$name]) !== 'INNODB' ||
                    ($meta['columns'] ?? null) !== array_keys($this->columns($name)) ||
                    ($meta['structure'] ?? null) !== $this->structure($name) ||
                    !is_int($meta['rows'] ?? null) || $meta['rows'] < 0 ||
                    preg_match('/^[a-f0-9]{64}$/D', (string) ($meta['sha256'] ?? '')) !== 1) {
                    throw new RuntimeException('RESTORE_SCHEMA_MISMATCH');
                }
                $this->streamRows($zip, $name, $meta, null); // All archive bytes verified before writes.
            }
            // Mandatory fresh safety snapshot before changing existing rows.
            $safety = $this->backup();
            $db = Database::connection();
            $db->exec('SET FOREIGN_KEY_CHECKS=0');
            $db->beginTransaction();
            $rows = 0;
            try {
                foreach (array_keys($entries) as $name) {
                    $db->exec('DELETE FROM ' . self::ident($name));
                }
                foreach ($entries as $name => $meta) {
                    $fields = $meta['columns'];
                    $stmt = $db->prepare('INSERT INTO ' . self::ident($name) . ' (' .
                        implode(',', array_map([self::class, 'ident'], $fields)) . ') VALUES (' .
                        implode(',', array_fill(0, count($fields), '?')) . ')');
                    $rows += $this->streamRows($zip, $name, $meta, $stmt);
                }
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            } finally { $db->exec('SET FOREIGN_KEY_CHECKS=1'); }
            return ['tables' => count($entries), 'rows' => $rows, 'safety_backup' => $safety['id']];
        } finally { $zip->close(); }
    }

    private function streamRows(ZipArchive $zip, string $table, array $meta, ?\PDOStatement $stmt): int
    {
        $stream = $zip->getStream('tables/' . $table . '.jsonl');
        if ($stream === false) throw new RuntimeException('BACKUP_TABLE_FILE_MISSING');
        $hash = hash_init('sha256');
        $count = 0;
        try {
            while (($line = fgets($stream, 1048576)) !== false) {
                hash_update($hash, $line);
                if (++$count > 1000000) throw new RuntimeException('BACKUP_ROW_LIMIT');
                $encoded = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                if (!is_array($encoded) || count($encoded) !== count($meta['columns'])) {
                    throw new RuntimeException('BACKUP_ROW_INVALID');
                }
                $values = [];
                foreach ($encoded as $cell) {
                    if ($cell === null) { $values[] = null; continue; }
                    if (!is_string($cell) || ($value = base64_decode($cell, true)) === false) {
                        throw new RuntimeException('BACKUP_CELL_INVALID');
                    }
                    $values[] = $value;
                }
                if ($stmt !== null) $stmt->execute($values);
            }
        } finally { fclose($stream); }
        if ($count !== $meta['rows'] || !hash_equals($meta['sha256'], hash_final($hash))) {
            throw new RuntimeException('BACKUP_ROW_INTEGRITY_FAILED');
        }
        return $count;
    }

    private function dataset(string $table): void
    {
        if (!in_array($table, self::DATASETS, true) || !in_array($table, $this->datasets(), true)) {
            throw new RuntimeException('DATASET_UNAVAILABLE');
        }
    }

    /** @return array<string,string> */
    private function tables(): array
    {
        $rows = Database::connection()->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll();
        $tables = [];
        foreach ($rows as $r) {
            $name = (string) $r['TABLE_NAME'];
            self::ident($name);
            $tables[$name] = (string) $r['ENGINE'];
        }
        return $tables;
    }

    private function columns(string $table): array
    {
        $rows = Database::connection()->query('SHOW COLUMNS FROM ' . self::ident($table))->fetchAll();
        $columns = [];
        foreach ($rows as $row) {
            self::ident((string) $row['Field']);
            $columns[(string) $row['Field']] = $row;
        }
        return $columns;
    }

    private function structure(string $table): string
    {
        return hash('sha256', json_encode(array_values($this->columns($table)), JSON_THROW_ON_ERROR));
    }

    private static function ident(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $value)) {
            throw new RuntimeException('UNSAFE_TABLE_OR_COLUMN_NAME');
        }
        return chr(96) . $value . chr(96);
    }

    private static function validId(string $id): bool
    {
        return preg_match('/^\d{14}-[a-f0-9]{16}$/D', $id) === 1;
    }

    private function vault(bool $create): ?string
    {
        if (!defined('BASE_PATH')) throw new RuntimeException('APPLICATION_ROOT_UNAVAILABLE');
        $site = realpath((string) BASE_PATH);
        if ($site === false || !is_dir($site)) throw new RuntimeException('APPLICATION_ROOT_INVALID');
        $dir = dirname($site) . '/.barama-system-tools-' . substr(hash('sha256', $site), 0, 18);
        if (!file_exists($dir) && !is_link($dir)) {
            if (!$create) return null;
            if (!mkdir($dir, 0700)) throw new RuntimeException('PRIVATE_STORAGE_CREATE_FAILED');
        }
        if (!is_dir($dir) || is_link($dir) ||
            (fileperms($dir) & 0077) !== 0 ||
            !is_readable($dir) || ($create && !is_writable($dir))) {
            throw new RuntimeException('PRIVATE_STORAGE_PERMISSIONS_INVALID');
        }
        return $dir;
    }
}
