<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use Barama\Core\Installation;
use RuntimeException;

/**
 * Root-CLI, staging-only preparation of paired private CODE-TARGET and SQL
 * snapshots for a FUTURE update. This is NOT a live updater, full-site backup,
 * SQL restore, worker, or proof that an update is safe to install.
 *
 * No web route or privilege escalation is provided. It never touches the
 * site or DB except to READ files and EXPORT SQL through existing services.
 * The caller must independently authenticate an operator before invoking it.
 */
final class OneClickRecoverySnapshotPairV1
{
    private const SITE = '/www/wwwroot/baramastaging.tonsamith.com';
    private const VAULT = '/root/Barama-OneClick-Recovery';
    private const APPROVAL = 'PREPARE_STAGING_PRIVATE_SNAPSHOTS_ONLY';
    private const SHA256 = '/^[a-f0-9]{64}$/D';

    /**
     * ONLY for a separately owner-approved root CLI run in staging.
     * Backup creation occurs when this function is invoked, NEVER upon
     * class load, preview, a dashboard request, or a publisher feed check.
     *
     * @param array{host:string,port:int,user:string,password:string,database:string,dump_binary:string} $database
     * @return array<string,mixed> No private paths, SQL, or credentials returned.
     */
    public static function prepareStaging(string $site, array $database, string $approval): array
    {
        self::guard($site, $approval);
        $vault = self::vault();

        if (!Installation::isInstalled()
            || !is_string($installedVersion = Installation::metadata()['version'] ?? null)
            || !defined('APP_VERSION') || !hash_equals((string) APP_VERSION, $installedVersion)
            || !is_file($site . '/VERSION') || is_link($site . '/VERSION')
            || trim((string) file_get_contents($site . '/VERSION')) !== $installedVersion) {
            throw new RuntimeException('RECOVERY_INSTALLED_VERSION_MISMATCH');
        }

        $dbName = $database['database'] ?? null;
        if (!is_string($dbName)
            || preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $dbName) !== 1
            || !is_string($database['password'] ?? null)
            || $database['password'] === '') {
            throw new RuntimeException('RECOVERY_DATABASE_CONFIG_INVALID');
        }

        // Reinspect the actual staged Ed25519 signed archive and the complete
        // file plan immediately before preparing either private snapshot.
        $loc = SignedReleaseInspector::locations();
        $plan = VerifiedReleaseFilePlanV2::inspect(
            $loc['manifest'], $loc['archive'], $installedVersion
        );
        if (($plan['status'] ?? null) !== 'verified_plan_only'
            || ($plan['installation_enabled'] ?? null) !== false
            || ($plan['installed_version'] ?? null) !== $installedVersion
            || !self::sha($plan['archive_sha256'] ?? null)
            || !is_string($plan['target_version'] ?? null)
            || version_compare($plan['target_version'], $installedVersion, '<=')) {
            throw new RuntimeException('RECOVERY_SIGNED_PLAN_INVALID');
        }

        $run = $vault . '/pair-' . bin2hex(random_bytes(16));
        if (!@mkdir($run, 0700)) {
            throw new RuntimeException('RECOVERY_PRIVATE_PAIR_CREATE_FAILED');
        }
        $codeRoot = $run . '/code';
        $dbRoot = $run . '/database';
        if (!@mkdir($codeRoot, 0700) || !@mkdir($dbRoot, 0700)) {
            // Never erase forensic/partial backups on preparation failure.
            throw new RuntimeException('RECOVERY_PRIVATE_PAIR_CREATE_FAILED');
        }

        // Failure leaves only private, unapproved fragments. No pair receipt
        // is committed until BOTH independent backup verifiers succeed.
        $code = VerifiedUpdateBackupV2::prepare($plan, $site, $codeRoot);
        if (($code['status'] ?? null) !== 'PRIVATE_FILE_BACKUP_VERIFIED'
            || ($code['installation_enabled'] ?? null) !== false
            || ($code['database_backup'] ?? null) !== 'NOT_INCLUDED'
            || !self::sha($code['receipt_sha256'] ?? null)
            || !self::job($code['backup_id'] ?? null)) {
            throw new RuntimeException('RECOVERY_CODE_BACKUP_NOT_VERIFIED');
        }
        $codePath = $codeRoot . '/' . $code['backup_id'];
        if (!VerifiedUpdateBackupV2::verify($codePath, $code['receipt_sha256'])) {
            throw new RuntimeException('RECOVERY_CODE_BACKUP_NOT_VERIFIED');
        }

        $sql = PrivateDatabaseBackupV2::prepare([
            'site_root' => $site,
            'vault_root' => $dbRoot,
            'database' => $dbName,
            'host' => $database['host'] ?? '',
            'port' => $database['port'] ?? 0,
            'user' => $database['user'] ?? '',
            'password' => $database['password'],
            'dump_binary' => $database['dump_binary'] ?? '',
        ]);
        if (($sql['status'] ?? null) !== 'PRIVATE_DATABASE_BACKUP_VERIFIED_NOT_RESTORED'
            || ($sql['restore_tested'] ?? null) !== false
            || ($sql['installation_enabled'] ?? null) !== false
            || !self::sha($sql['receipt_sha256'] ?? null)
            || !self::job($sql['job_id'] ?? null)) {
            throw new RuntimeException('RECOVERY_SQL_BACKUP_NOT_VERIFIED');
        }
        $dbPath = $dbRoot . '/' . $sql['job_id'];
        if (!PrivateDatabaseBackupV2::verify($dbPath, $sql['receipt_sha256'])) {
            throw new RuntimeException('RECOVERY_SQL_BACKUP_NOT_VERIFIED');
        }

        // Persist only non-secret, SHA-bound references. The two snapshots
        // are NOT a coherent full-site/SQL recovery checkpoint until an
        // independent restore/consistency test proves that.
        $receipt = [
            'schema' => 'barama-oneclick-recovery-pair-v1',
            'state' => 'paired_snapshots_not_restored',
            'site' => self::SITE,
            'installed_version' => $installedVersion,
            'target_version' => $plan['target_version'],
            'release_archive_sha256' => $plan['archive_sha256'],
            'database_name_sha256' => hash('sha256', $dbName),
            'code_job_id' => $code['backup_id'],
            'code_receipt_sha256' => $code['receipt_sha256'],
            'db_job_id' => $sql['job_id'],
            'db_receipt_sha256' => $sql['receipt_sha256'],
            'code_scope' => 'signed_plan_targets_only',
            'database_restore_tested' => false,
            'full_site_backup_verified' => false,
            'consistent_recovery_point_verified' => false,
            'installation_enabled' => false,
            'created_utc' => gmdate('c'),
        ];
        $encoded = json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        self::exclusiveWrite($run . '/pair.json', $encoded);
        $pairSha = hash_file('sha256', $run . '/pair.json');
        if (!self::sha($pairSha)) {
            throw new RuntimeException('RECOVERY_PAIR_COMMIT_FAILED');
        }
        return [
            'status' => 'PRIVATE_CODE_AND_SQL_SNAPSHOTS_VERIFIED_NOT_RESTORED',
            'pair_id' => basename($run),
            'pair_receipt_sha256' => $pairSha,
            'code_receipt_sha256' => $code['receipt_sha256'],
            'db_receipt_sha256' => $sql['receipt_sha256'],
            'signed_release_sha256' => $plan['archive_sha256'],
            'full_site_backup_verified' => false,
            'database_restore_tested' => false,
            'consistent_recovery_point_verified' => false,
            'installation_enabled' => false,
        ];
    }

    /**
     * Deliberately returns NO installation consent even if every receipt
     * matches. A separate root worker must later recheck actual DB identity,
     * complete recovery and operator consent under an exclusive update lock.
     *
     * @param array<string,mixed> $pair
     * @return array{status:string,ready_to_install:bool,blockers:list<string>}
     */
    public static function releaseGate(array $pair): array
    {
        $blockers = [
            'FULL_SITE_RESTORE_CAPABILITY_NOT_PROVEN',
            'SQL_RESTORE_REHEARSAL_NOT_PROVEN',
            'COHERENT_CODE_DATABASE_CHECKPOINT_NOT_PROVEN',
            'INDEPENDENT_INSTALL_AUTHORIZATION_NOT_PROVEN',
            'CRASH_SAFE_PRIVILEGED_WORKER_NOT_PROVEN',
        ];
        if (($pair['status'] ?? null) !== 'PRIVATE_CODE_AND_SQL_SNAPSHOTS_VERIFIED_NOT_RESTORED'
            || !self::sha($pair['pair_receipt_sha256'] ?? null)
            || !self::sha($pair['code_receipt_sha256'] ?? null)
            || !self::sha($pair['db_receipt_sha256'] ?? null)
            || !self::sha($pair['signed_release_sha256'] ?? null)
            || ($pair['installation_enabled'] ?? null) !== false
            || ($pair['full_site_backup_verified'] ?? null) !== false
            || ($pair['database_restore_tested'] ?? null) !== false
            || ($pair['consistent_recovery_point_verified'] ?? null) !== false) {
            $blockers[] = 'PAIR_RECEIPT_NOT_TRUSTED';
        }
        return [
            'status' => 'BACKUP_PAIR_ONLY_INSTALL_BLOCKED',
            'ready_to_install' => false,
            'blockers' => $blockers,
        ];
    }

    private static function guard(string $site, string $approval): void
    {
        if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid')
            || posix_geteuid() !== 0 || $site !== self::SITE
            || !defined('BASE_PATH') || BASE_PATH !== self::SITE
            || !defined('APP_PATH') || APP_PATH !== self::SITE . '/application'
            || $approval !== self::APPROVAL
            || is_link($site) || !is_dir($site) || realpath($site) !== $site) {
            throw new RuntimeException('RECOVERY_STAGING_ROOT_APPROVAL_REQUIRED');
        }
    }

    private static function vault(): string
    {
        if (is_link(self::VAULT) || !is_dir(self::VAULT)
            || realpath(self::VAULT) !== self::VAULT) {
            throw new RuntimeException('RECOVERY_PRIVATE_VAULT_NOT_READY');
        }
        $st = stat(self::VAULT);
        if (!is_array($st) || $st['uid'] !== 0 || ($st['mode'] & 0777) !== 0700) {
            throw new RuntimeException('RECOVERY_PRIVATE_VAULT_UNTRUSTED');
        }
        return self::VAULT;
    }

    private static function job(mixed $name): bool
    {
        return is_string($name) && preg_match('/^(?:backup|db)-[A-Za-z0-9-]{8,96}$/D', $name) === 1;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match(self::SHA256, $value) === 1;
    }

    private static function exclusiveWrite(string $path, string $contents): void
    {
        if (is_link($path) || file_exists($path)) {
            throw new RuntimeException('RECOVERY_PAIR_ALREADY_EXISTS');
        }
        $stream = @fopen($path, 'xb');
        if ($stream === false) throw new RuntimeException('RECOVERY_PAIR_WRITE_FAILED');
        try {
            if (!@chmod($path, 0600)
                || fwrite($stream, $contents) !== strlen($contents)
                || !fflush($stream)
                || (function_exists('fsync') && !fsync($stream))) {
                throw new RuntimeException('RECOVERY_PAIR_SYNC_FAILED');
            }
        } finally {
            fclose($stream);
        }
    }

    private function __construct()
    {
    }
}
