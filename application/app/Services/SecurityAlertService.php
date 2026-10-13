<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\NotificationRepository;
use Barama\Repositories\SecurityCenterRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class SecurityAlertService
{
    private const SETTING_KEY = 'security.center.policy';

    /** @return array<string,mixed> */
    public function policy(): array
    {
        $defaults = $this->defaults();

        $statement = Database::connection()->prepare(
            'SELECT setting_value
             FROM application_settings
             WHERE setting_key = :setting_key
             LIMIT 1'
        );
        $statement->execute(['setting_key' => self::SETTING_KEY]);

        $value = $statement->fetchColumn();

        if (!is_string($value) || trim($value) === '') {
            return $defaults;
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        return $this->normalize(array_merge($defaults, $decoded));
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function savePolicy(array $input): array
    {
        $policy = $this->normalize([
            'enabled' => $this->boolInput($input, 'enabled'),
            'failed_login_threshold' =>
                $input['failed_login_threshold'] ?? null,
            'locked_login_threshold' =>
                $input['locked_login_threshold'] ?? null,
            'active_lockout_threshold' =>
                $input['active_lockout_threshold'] ?? null,
            'window_minutes' => $input['window_minutes'] ?? null,
            'cooldown_minutes' => $input['cooldown_minutes'] ?? null,
            'retention_days' => $input['retention_days'] ?? null,
            'in_app_alerts' =>
                $this->boolInput($input, 'in_app_alerts'),
            'email_alerts' =>
                $this->boolInput($input, 'email_alerts'),
        ]);

        $encoded = json_encode(
            $policy,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $statement = Database::connection()->prepare(
            'INSERT INTO application_settings
                (setting_key, setting_value, value_type)
             VALUES
                (:setting_key, :setting_value, "json")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = "json",
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'setting_key' => self::SETTING_KEY,
            'setting_value' => $encoded,
        ]);

        return $policy;
    }

    /**
     * Evaluate the current security window and create deduplicated
     * administrator alerts when configured thresholds are met.
     *
     * @return array{
     *   signals:array<string,int>,
     *   triggered:array<int,array<string,mixed>>,
     *   notifications:int,
     *   emails:int
     * }
     */
    public function evaluate(): array
    {
        $policy = $this->policy();
        $repository = new SecurityCenterRepository();

        $signals = $repository->signals(
            (int) $policy['window_minutes']
        );

        if (empty($policy['enabled'])) {
            return [
                'signals' => $signals,
                'triggered' => [],
                'notifications' => 0,
                'emails' => 0,
            ];
        }

        $definitions = [
            [
                'key' => 'failed_logins',
                'metric' => 'failed',
                'threshold' => (int) $policy['failed_login_threshold'],
                'title' => 'Repeated failed sign-ins detected',
                'message' => sprintf(
                    '%d failed sign-ins occurred during the last %d minutes.',
                    (int) $signals['failed'],
                    (int) $policy['window_minutes']
                ),
            ],
            [
                'key' => 'login_locks',
                'metric' => 'locked',
                'threshold' => (int) $policy['locked_login_threshold'],
                'title' => 'Login lock activity detected',
                'message' => sprintf(
                    '%d login-lock events occurred during the last %d minutes.',
                    (int) $signals['locked'],
                    (int) $policy['window_minutes']
                ),
            ],
            [
                'key' => 'active_lockouts',
                'metric' => 'active_lockouts',
                'threshold' => (int) $policy['active_lockout_threshold'],
                'title' => 'Active login lockouts detected',
                'message' => sprintf(
                    '%d active login lockouts are currently in effect.',
                    (int) $signals['active_lockouts']
                ),
            ],
        ];

        $triggered = array_values(array_filter(
            $definitions,
            static fn (array $definition): bool =>
                (int) $signals[$definition['metric']]
                    >= (int) $definition['threshold']
        ));

        if ($triggered === []) {
            return [
                'signals' => $signals,
                'triggered' => [],
                'notifications' => 0,
                'emails' => 0,
            ];
        }

        $administrators = $this->administrators();
        $notifications = 0;
        $emails = 0;
        $notificationRepository = new NotificationRepository();
        $cooldownSeconds = max(
            300,
            (int) $policy['cooldown_minutes'] * 60
        );
        $bucket = (int) floor(time() / $cooldownSeconds);

        foreach ($triggered as $definition) {
            foreach ($administrators as $administrator) {
                $dedupe = implode(':', [
                    'security',
                    (string) $definition['key'],
                    (string) $bucket,
                ]);

                $notificationId = null;

                if (!empty($policy['in_app_alerts'])) {
                    $notificationId =
                        $notificationRepository->createNotification([
                            'user_id' => (int) $administrator['id'],
                            'category' => 'security_alerts',
                            'type' =>
                                'security.' . $definition['key'],
                            'title' => (string) $definition['title'],
                            'message' => (string) $definition['message'],
                            'action_url' => '/dashboard/audit',
                            'entity_type' => 'security',
                            'entity_id' => null,
                            'dedupe_key' => $dedupe,
                        ]);

                    if ($notificationId !== null) {
                        $notifications++;
                    }
                }

                if (!empty($policy['email_alerts'])) {
                    $emailDedupe = $dedupe
                        . ':user:' . (int) $administrator['id']
                        . ':email';

                    $queued = $notificationRepository->queueEmail([
                        'user_id' => (int) $administrator['id'],
                        'notification_id' => $notificationId,
                        'to_address' =>
                            (string) $administrator['email'],
                        'subject' =>
                            '[Barama Security] '
                            . (string) $definition['title'],
                        'html_body' =>
                            '<p>'
                            . htmlspecialchars(
                                (string) $definition['message'],
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            . '</p><p><a href="/dashboard/audit">'
                            . 'Open Security Center</a></p>',
                        'text_body' =>
                            (string) $definition['message']
                            . "\n\nOpen Security Center: /dashboard/audit",
                        'dedupe_key' => $emailDedupe,
                    ]);

                    if ($queued !== null) {
                        $emails++;
                    }
                }
            }
        }

        return [
            'signals' => $signals,
            'triggered' => $triggered,
            'notifications' => $notifications,
            'emails' => $emails,
        ];
    }

    /** @return array<string,mixed> */
    private function defaults(): array
    {
        return [
            'enabled' => true,
            'failed_login_threshold' => 5,
            'locked_login_threshold' => 2,
            'active_lockout_threshold' => 1,
            'window_minutes' => 15,
            'cooldown_minutes' => 60,
            'retention_days' => 365,
            'in_app_alerts' => true,
            'email_alerts' => false,
        ];
    }

    /** @param array<string,mixed> $input */
    private function boolInput(array $input, string $key): bool
    {
        return in_array(
            strtolower(trim((string) ($input[$key] ?? ''))),
            ['1', 'true', 'on', 'yes'],
            true
        );
    }

    /**
     * @param array<string,mixed> $policy
     * @return array<string,mixed>
     */
    private function normalize(array $policy): array
    {
        return [
            'enabled' => (bool) ($policy['enabled'] ?? true),
            'failed_login_threshold' => $this->integer(
                $policy['failed_login_threshold'] ?? 5,
                2,
                100,
                'Failed-login threshold'
            ),
            'locked_login_threshold' => $this->integer(
                $policy['locked_login_threshold'] ?? 2,
                1,
                100,
                'Login-lock threshold'
            ),
            'active_lockout_threshold' => $this->integer(
                $policy['active_lockout_threshold'] ?? 1,
                1,
                100,
                'Active-lockout threshold'
            ),
            'window_minutes' => $this->integer(
                $policy['window_minutes'] ?? 15,
                5,
                1440,
                'Evaluation window'
            ),
            'cooldown_minutes' => $this->integer(
                $policy['cooldown_minutes'] ?? 60,
                5,
                1440,
                'Alert cooldown'
            ),
            'retention_days' => $this->integer(
                $policy['retention_days'] ?? 365,
                30,
                3650,
                'Audit retention horizon'
            ),
            'in_app_alerts' =>
                (bool) ($policy['in_app_alerts'] ?? true),
            'email_alerts' =>
                (bool) ($policy['email_alerts'] ?? false),
        ];
    }

    private function integer(
        mixed $value,
        int $min,
        int $max,
        string $label
    ): int {
        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => [
                'min_range' => $min,
                'max_range' => $max,
            ]]
        );

        if ($validated === false) {
            throw new InvalidArgumentException(
                $label . ' must be between '
                . $min . ' and ' . $max . '.'
            );
        }

        return (int) $validated;
    }

    /** @return array<int,array{id:int,name:string,email:string}> */
    private function administrators(): array
    {
        $statement = Database::connection()->query(
            'SELECT DISTINCT u.id, u.name, u.email
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = "administrator"
               AND u.status = "active"
             ORDER BY u.id ASC'
        );

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
