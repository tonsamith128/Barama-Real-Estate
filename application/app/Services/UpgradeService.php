<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Core\Installation;
use PDO;
use RuntimeException;
use Throwable;

final class UpgradeService
{
    public function __construct(
        private readonly ?PDO $connection = null
    ) {
    }

    public function basePath(): string
    {
        $script = str_replace(
            '\\',
            '/',
            (string) ($_SERVER['SCRIPT_NAME'] ?? '/updater/index.php')
        );
        $suffix = '/updater/index.php';

        if (str_ends_with($script, $suffix)) {
            $base = substr($script, 0, -strlen($suffix));
        } else {
            $base = dirname(dirname($script));
        }

        $base = '/' . trim((string) $base, '/');

        return $base === '/' ? '' : $base;
    }

    public function updaterUrl(): string
    {
        return $this->basePath() . '/updater/';
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        if (!Installation::isInstalled()) {
            return [
                'installed' => false,
                'current_version' => null,
                'target_version' => APP_VERSION,
                'pending_migrations' => [],
                'ready' => false,
                'up_to_date' => false,
                'issues' => ['Barama Real Estate is not installed.'],
                'site_url' => '',
            ];
        }

        $runtime = Installation::runtimeConfig();
        $license = Installation::licenseState();
        $issues = [];

        if ($runtime === []) {
            $issues[] = 'Installed runtime configuration is unavailable.';
        }

        if (!$this->localLicenseStateValid($license, $runtime)) {
            $issues[] = 'Installed license state is unavailable or invalid.';
        }

        if (!is_dir(APP_PATH . '/database/migrations')) {
            $issues[] = 'Database migrations are missing from this package.';
        }

        if (!is_writable(dirname(Installation::lockPath()))) {
            $issues[] = 'Application runtime storage is not writable.';
        }

        $current = trim((string) (Installation::metadata()['version'] ?? 'unknown'));
        $pending = [];

        try {
            $pdo = $this->pdo();
            $current = $this->currentVersion($pdo);
            $pending = $this->pendingMigrations($pdo);
        } catch (Throwable) {
            $issues[] = 'The installed database could not be inspected.';
        }

        return [
            'installed' => true,
            'current_version' => $current,
            'target_version' => APP_VERSION,
            'pending_migrations' => $pending,
            'ready' => $issues === [],
            'up_to_date' => $current === APP_VERSION && $pending === [],
            'issues' => $issues,
            'site_url' => rtrim((string) ($runtime['APP_URL'] ?? ''), '/'),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function upgrade(array $input): array
    {
        if (!Installation::isInstalled()) {
            throw new RuntimeException('Barama Real Estate is not installed.');
        }

        if ((string) ($input['backup_confirmed'] ?? '') !== '1') {
            throw new RuntimeException(
                'Confirm that you created a current database and files backup before upgrading.'
            );
        }

        $email = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $password = (string) ($input['admin_password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            throw new RuntimeException('Enter an administrator email and password.');
        }

        $directory = APP_PATH . '/storage/framework';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The upgrade lock directory could not be created.');
        }

        $lockPath = $directory . '/upgrade.lock';
        $handle = @fopen($lockPath, 'c+');

        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new RuntimeException('Another upgrade process is already running.');
        }

        try {
            return $this->performUpgrade(
                $this->pdo(),
                $email,
                $password,
                (string) ($input['ip'] ?? ''),
                (string) ($input['user_agent'] ?? '')
            );
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
            @unlink($lockPath);
        }
    }

    /** @return array<string,mixed> */
    private function performUpgrade(
        PDO $pdo,
        string $email,
        string $password,
        string $ip,
        string $userAgent
    ): array {
        $admin = $this->authenticateAdministrator($pdo, $email, $password);
        $runtime = Installation::runtimeConfig();
        $license = Installation::licenseState();

        $this->validateLicenseForUpgrade($license, $runtime);

        $current = $this->currentVersion($pdo);
        $target = APP_VERSION;

        if (
            $current !== ''
            && $current !== 'unknown'
            && version_compare($current, $target, '>')
        ) {
            throw new RuntimeException(
                'The installed version is newer than this package. Downgrades are not supported.'
            );
        }

        $pendingBefore = $this->pendingMigrations($pdo);

        if ($current === $target && $pendingBefore === []) {
            return [
                'upgraded' => false,
                'already_current' => true,
                'previous_version' => $current,
                'version' => $target,
                'migrations' => [],
            ];
        }

        $ran = (new InstallerService())->runMigrations($pdo);

        $remaining = $this->pendingMigrations($pdo);

        if ($remaining !== []) {
            throw new RuntimeException(
                'The upgrade did not apply every pending database migration.'
            );
        }

        $statement = $pdo->prepare(
            'INSERT INTO application_settings
                (setting_key, setting_value, value_type)
             VALUES
                ("installation.version", :version, "string")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute(['version' => $target]);

        Installation::updateInstalledMetadata([
            'previous_version' => $current,
            'version' => $target,
            'upgraded_at' => gmdate('c'),
            'last_upgrade_migrations' => $ran,
        ]);

        $this->recordUpgradeAudit(
            $pdo,
            (int) $admin['id'],
            $current,
            $target,
            $ran,
            $ip,
            $userAgent
        );

        return [
            'upgraded' => true,
            'already_current' => false,
            'previous_version' => $current,
            'version' => $target,
            'migrations' => $ran,
        ];
    }

    /** @return array<string,mixed> */
    private function authenticateAdministrator(
        PDO $pdo,
        string $email,
        string $password
    ): array {
        $statement = $pdo->prepare(
            'SELECT u.id, u.email, u.password_hash, u.status
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.email = :email
               AND r.slug = "administrator"
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($user)
            || (string) $user['status'] !== 'active'
            || !password_verify($password, (string) $user['password_hash'])
        ) {
            throw new RuntimeException('Administrator authentication failed.');
        }

        return $user;
    }

    /**
     * @param array<string,mixed> $license
     * @param array<string,mixed> $runtime
     */
    private function validateLicenseForUpgrade(array $license, array $runtime): void
    {
        if (!$this->localLicenseStateValid($license, $runtime)) {
            throw new RuntimeException('The installed license state is invalid.');
        }

        if (defined('BARAMA_CLI') && BARAMA_CLI === true) {
            return;
        }

        $token = trim((string) ($license['activation_token'] ?? ''));
        $domain = (string) ($runtime['APP_URL'] ?? $license['domain'] ?? '');
        $installationId = (string) ($license['installation_id'] ?? '');

        try {
            $remote = (new LicenseManagerClient())->validateActive(
                $token,
                $domain,
                $installationId
            );

            Installation::refreshLicenseState([
                'status' => (string) ($remote['status'] ?? 'active'),
                'last_validated_at' => gmdate('c'),
            ]);
        } catch (RuntimeException $exception) {
            $lastValidated = strtotime(
                (string) ($license['last_validated_at'] ?? $license['validated_at'] ?? '')
            );
            $graceHours = max(0, (int) config('license.offline_grace_hours', 72));

            if (
                $lastValidated !== false
                && (time() - $lastValidated) <= ($graceHours * 3600)
            ) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * @param array<string,mixed> $license
     * @param array<string,mixed> $runtime
     */
    private function localLicenseStateValid(array $license, array $runtime): bool
    {
        $expectedProduct = (string) config(
            'license.product_slug',
            'barama-real-estate'
        );
        $installationId = strtolower(trim(
            (string) ($license['installation_id'] ?? '')
        ));
        $stateDomain = LicenseManagerClient::canonicalDomain(
            (string) ($license['domain'] ?? '')
        );
        $runtimeDomain = LicenseManagerClient::canonicalDomain(
            (string) ($runtime['APP_URL'] ?? '')
        );
        $status = strtolower(trim((string) ($license['status'] ?? 'active')));

        return trim((string) ($license['activation_token'] ?? '')) !== ''
            && (string) ($license['product_slug'] ?? '') === $expectedProduct
            && preg_match('/^[a-f0-9]{32}$/', $installationId) === 1
            && $installationId === strtolower(Installation::installationId())
            && $stateDomain !== ''
            && $runtimeDomain !== ''
            && $stateDomain === $runtimeDomain
            && !in_array(
                $status,
                ['revoked', 'suspended', 'invalid', 'deactivated', 'expired'],
                true
            );
    }

    private function currentVersion(PDO $pdo): string
    {
        try {
            $statement = $pdo->query(
                'SELECT setting_value
                 FROM application_settings
                 WHERE setting_key = "installation.version"
                 LIMIT 1'
            );
            $value = trim((string) $statement->fetchColumn());

            if ($value !== '') {
                return $value;
            }
        } catch (Throwable) {
        }

        return trim((string) (Installation::metadata()['version'] ?? 'unknown'));
    }

    /** @return array<int,string> */
    private function pendingMigrations(PDO $pdo): array
    {
        $files = glob(APP_PATH . '/database/migrations/*.php') ?: [];
        sort($files, SORT_STRING);
        $expected = array_map('basename', $files);

        try {
            $applied = $pdo->query(
                'SELECT migration FROM schema_migrations ORDER BY id'
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            $applied = [];
        }

        $lookup = array_fill_keys(array_map('strval', $applied), true);

        return array_values(array_filter(
            $expected,
            static fn (string $name): bool => !isset($lookup[$name])
        ));
    }

    /** @param array<int,string> $migrations */
    private function recordUpgradeAudit(
        PDO $pdo,
        int $administratorId,
        string $previousVersion,
        string $version,
        array $migrations,
        string $ip,
        string $userAgent
    ): void {
        try {
            $metadata = json_encode([
                'previous_version' => $previousVersion,
                'version' => $version,
                'migrations' => $migrations,
            ], JSON_THROW_ON_ERROR);

            $statement = $pdo->prepare(
                'INSERT INTO security_audit_events
                    (actor_user_id, subject_user_id, event_type, ip_hash,
                     user_agent_hash, metadata_json)
                 VALUES
                    (:actor_user_id, :subject_user_id, "system.upgrade_completed",
                     :ip_hash, :user_agent_hash, :metadata_json)'
            );
            $statement->execute([
                'actor_user_id' => $administratorId,
                'subject_user_id' => $administratorId,
                'ip_hash' => $ip !== '' ? hash('sha256', $ip) : null,
                'user_agent_hash' => $userAgent !== ''
                    ? hash('sha256', $userAgent)
                    : null,
                'metadata_json' => $metadata,
            ]);
        } catch (Throwable) {
            // Upgrade completion must not fail because audit telemetry is unavailable.
        }
    }

    private function pdo(): PDO
    {
        return $this->connection ?? Database::connection();
    }
}
