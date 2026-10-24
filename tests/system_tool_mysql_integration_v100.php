<?php
declare(strict_types=1);

/** Isolated MySQL database; NEVER points at a real Barama installation. */
if (getenv('SYSTEM_TOOL_INTEGRATION') !== 'isolated-ci') {
    fwrite(STDERR, "STOP: isolated CI only\n");
    exit(1);
}
$base = sys_get_temp_dir() . '/barama-tool-test-' . bin2hex(random_bytes(6));
if (!mkdir($base, 0700) || !mkdir($base . '/site', 0700)) exit(1);
define('BASE_PATH', $base . '/site');

function config(string $name, mixed $default = null): mixed {
    $cfg = [
        'database.driver' => 'mysql',
        'database.host' => '127.0.0.1',
        'database.port' => 3306,
        'database.database' => 'barama_tool_test',
        'database.username' => 'root',
        'database.password' => 'tool_test_password',
        'database.charset' => 'utf8mb4',
    ];
    return $cfg[$name] ?? $default;
}
require dirname(__DIR__) . '/application/app/Core/Database.php';
require dirname(__DIR__) . '/application/app/Services/SystemToolService.php';

use Barama\Core\Database;
use Barama\Services\SystemToolService;

function pass(bool $ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "FAIL=$label\n"); exit(1); }
    echo "PASS=$label\n";
}
$db = Database::connection();
foreach (['locations', 'amenities', 'companies', 'projects', 'properties'] as $name) {
    $db->exec('CREATE TABLE ' . $name .
        ' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, note TEXT NULL) ENGINE=InnoDB');
}
$names = ['locations'=>'Battambang', 'amenities'=>'Wi-Fi', 'companies'=>'Demo Company',
    'projects'=>'Demo Project', 'properties'=>'Demo Property'];
foreach ($names as $table => $name) {
    $stmt = $db->prepare('INSERT INTO ' . $table . ' (name,note) VALUES (?,?)');
    $stmt->execute([$name, "Original note\n" . $name]);
}
$svc = new SystemToolService();
$csv = $svc->exportCsv('locations');
pass(str_contains($csv, 'Battambang') && str_contains($csv, 'id,name,note'), 'csv_export_real_mysql_data');
pass(count($svc->datasets()) === 5, 'allowed_catalog_datasets');
$bk = $svc->backup();
pass($bk['tables'] === 5 && $bk['rows'] === 5, 'consistent_full_db_snapshot');
$backup = $svc->verifiedBackup($bk['id']);
pass(is_file($backup) && count($svc->backups()) === 1, 'private_snapshot_receipt');
pass((fileperms(dirname($backup)) & 0077) === 0, 'private_vault_permissions');
$db->exec("UPDATE locations SET note='Modified after backup' WHERE id=1");
$db->exec("INSERT INTO locations (name,note) VALUES ('Extra location','Added')");
$result = $svc->restore($bk['id']);
pass($result['tables'] === 5 && $result['rows'] === 5, 'restore_completed_without_schema_changes');
pass((int)$db->query('SELECT COUNT(*) FROM locations')->fetchColumn() === 1, 'restore_removed_post_snapshot_rows');
pass(str_contains((string)$db->query('SELECT note FROM locations LIMIT 1')->fetchColumn(), 'Original note'), 'restore_original_row_contents');
pass(count($svc->backups()) === 2 && $result['safety_backup'] !== $bk['id'], 'pre_restore_safety_snapshot');
$blocked = false;
try { $svc->verifiedBackup('../../etc/passwd'); }
catch (RuntimeException) { $blocked = true; }
pass($blocked, 'path_traversal_rejected');
$blocked = false;
try { $svc->restore('bad-id'); }
catch (RuntimeException) { $blocked = true; }
pass($blocked, 'arbitrary_uploaded_archive_not_accepted');
echo "SYSTEM_TOOL_MYSQL_INTEGRATION=PASS\n";
