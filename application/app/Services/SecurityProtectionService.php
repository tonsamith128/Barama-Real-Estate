<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\SecurityProtectionRepository;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SecurityProtectionService
{
    private const SPAM_PATHS = ['/contact/messages', '/inquiries', '/subscribe'];
    private const RATE_STATE_LIMIT = 5000;

    /** @return array<string,mixed> */
    public function viewModel(): array
    {
        $config = (new SecurityProtectionRepository())->get();
        $stats = ['spam_24h' => 0, 'hack_24h' => 0, 'last_hack_at' => null];

        try {
            $statement = Database::connection()->query(
                "SELECT event_type, COUNT(*) AS total, MAX(created_at) AS latest
                 FROM security_audit_events
                 WHERE created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)
                   AND event_type IN ('security.spam_blocked','security.hack_blocked')
                 GROUP BY event_type"
            );
            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if ((string) $row['event_type'] === 'security.spam_blocked') {
                    $stats['spam_24h'] = (int) $row['total'];
                }
                if ((string) $row['event_type'] === 'security.hack_blocked') {
                    $stats['hack_24h'] = (int) $row['total'];
                    $stats['last_hack_at'] = $row['latest'] !== null ? (string) $row['latest'] : null;
                }
            }
        } catch (Throwable) {
            // Stats must never make Settings unavailable.
        }

        return [
            'config' => $config,
            'stats' => $stats,
            'rate_state_writable' => $this->rateStateWritable(),
        ];
    }

    /** @return array{enabled:bool,block_context_menu:bool,block_image_drag:bool,block_text_selection:bool} */
    public function publicContentProtection(): array
    {
        try {
            $right = (new SecurityProtectionRepository())->get()['right_click'] ?? [];
        } catch (Throwable) {
            $right = [];
        }

        return [
            'enabled' => !empty($right['enabled']),
            'block_context_menu' => !empty($right['block_context_menu']),
            'block_image_drag' => !empty($right['block_image_drag']),
            'block_text_selection' => !empty($right['block_text_selection']),
        ];
    }

    /**
     * Returns a blocking response when a high-confidence security rule fires.
     * Fails open if configuration/audit storage is temporarily unavailable.
     */
    public function guard(Request $request): ?Response
    {
        try {
            $config = (new SecurityProtectionRepository())->get();
        } catch (Throwable) {
            return null;
        }

        if (!empty($config['hack']['enabled'])) {
            $reason = $this->detectHack($request, (array) $config['hack']);
            if ($reason !== null) {
                $this->recordHackBlock($request, $reason, (array) $config['hack']);
                return $this->blockedResponse(403, 'Request blocked', 'This request was blocked by Barama security protection.');
            }
        }

        if (
            !empty($config['spam']['enabled'])
            && $request->method() === 'POST'
            && in_array($request->path(), self::SPAM_PATHS, true)
            && $this->spamBlocked($request, (array) $config['spam'])
        ) {
            (new SecurityAuditService())->record(
                'security.spam_blocked',
                null,
                null,
                ['path' => substr($request->path(), 0, 180)]
            );

            return $this->blockedResponse(
                429,
                'Please slow down',
                'Too many similar submissions were received. Please wait a few minutes and try again.'
            );
        }

        return null;
    }

    /** @param array<string,mixed> $input */
    public function saveSpam(array $input): void
    {
        $this->assertAdministrator();
        $repo = new SecurityProtectionRepository();
        $config = $repo->get();
        $config['spam'] = [
            'enabled' => $this->enabled($input, 'enabled'),
            'window_seconds' => $this->allowedInt($input, 'window_seconds', [300, 600, 900, 1800], 600),
            'max_requests' => $this->allowedInt($input, 'max_requests', [3, 5, 8, 10, 15], 8),
            'duplicate_max' => $this->allowedInt($input, 'duplicate_max', [2, 3, 5], 3),
            'block_seconds' => $this->allowedInt($input, 'block_seconds', [300, 900, 1800, 3600], 900),
        ];
        $repo->save($config);
        $this->auditAdmin('security.spam_settings_updated', ['enabled' => $config['spam']['enabled']]);
    }

    public function clearSpamCounters(): void
    {
        $this->assertAdministrator();
        $this->mutateRateState(static function (array $state): array {
            foreach (array_keys($state['counters'] ?? []) as $key) {
                if (str_starts_with((string) $key, 'spam:')) {
                    unset($state['counters'][$key]);
                }
            }
            return $state;
        });
        $this->auditAdmin('security.spam_counters_cleared');
    }

    /** @param array<string,mixed> $input */
    public function saveHack(array $input): void
    {
        $this->assertAdministrator();
        $repo = new SecurityProtectionRepository();
        $config = $repo->get();
        $email = strtolower(trim((string) ($input['alert_email'] ?? '')));
        $alerts = $this->enabled($input, 'alerts_enabled');

        if ($alerts && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid hacking-alert email address before enabling alerts.');
        }

        $config['hack'] = [
            'enabled' => $this->enabled($input, 'enabled'),
            'detect_path_probes' => $this->enabled($input, 'detect_path_probes'),
            'detect_traversal' => $this->enabled($input, 'detect_traversal'),
            'detect_injection' => $this->enabled($input, 'detect_injection'),
            'alerts_enabled' => $alerts,
            'alert_email' => $email,
            'alert_cooldown_seconds' => $this->allowedInt($input, 'alert_cooldown_seconds', [900, 1800, 3600, 21600], 1800),
        ];
        $repo->save($config);
        $this->auditAdmin('security.hack_settings_updated', [
            'enabled' => $config['hack']['enabled'],
            'alerts_enabled' => $alerts,
        ]);
    }

    public function sendTestHackAlert(): void
    {
        $this->assertAdministrator();
        $hack = (array) ((new SecurityProtectionRepository())->get()['hack'] ?? []);
        $email = strtolower(trim((string) ($hack['alert_email'] ?? '')));
        if (empty($hack['alerts_enabled']) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Enable hacking alerts and save a valid alert email first.');
        }
        $this->queueHackAlert($email, '/security-alert-test', 'manual_test', 'TEST-' . substr(hash('sha256', $email), 0, 12), true);
        $this->auditAdmin('security.hack_alert_tested');
    }

    /** @param array<string,mixed> $input */
    public function saveRightClick(array $input): void
    {
        $this->assertAdministrator();
        $repo = new SecurityProtectionRepository();
        $config = $repo->get();
        $config['right_click'] = [
            'enabled' => $this->enabled($input, 'enabled'),
            'block_context_menu' => $this->enabled($input, 'block_context_menu'),
            'block_image_drag' => $this->enabled($input, 'block_image_drag'),
            'block_text_selection' => $this->enabled($input, 'block_text_selection'),
        ];
        $repo->save($config);
        $this->auditAdmin('security.right_click_settings_updated', ['enabled' => $config['right_click']['enabled']]);
    }

    /** @param array<string,mixed> $hack */
    private function detectHack(Request $request, array $hack): ?string
    {
        $path = strtolower($request->path());

        if (!empty($hack['detect_path_probes'])) {
            foreach ([
                '/.env', '/.git', '/wp-admin', '/wp-login.php', '/phpmyadmin',
                '/vendor/phpunit', '/.aws', '/id_rsa', '/etc/passwd', '/proc/self/environ',
            ] as $probe) {
                if (str_contains($path, $probe)) {
                    return 'path_probe';
                }
            }
        }

        $authenticated = auth_user() !== null;
        $values = [];
        if (!$authenticated) {
            foreach ($request->all() as $key => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $keyText = strtolower((string) $key);
                if (preg_match('/password|passcode|token|secret|code|csrf|_token/', $keyText) === 1) {
                    continue;
                }
                $values[] = (string) $value;
            }
        }

        $haystacks = array_merge([$request->path()], $values);

        if (!empty($hack['detect_traversal'])) {
            foreach ($haystacks as $value) {
                $decoded = strtolower(rawurldecode($value));
                if (
                    str_contains($decoded, '../')
                    || str_contains($decoded, '..\\')
                    || str_contains($decoded, 'php://')
                    || str_contains($decoded, 'file://')
                    || str_contains($decoded, '/etc/passwd')
                ) {
                    return 'path_traversal';
                }
            }
        }

        if (!$authenticated && !empty($hack['detect_injection'])) {
            foreach ($values as $value) {
                $sample = substr($value, 0, 4000);
                if (
                    preg_match('/<\s*script\b|javascript\s*:|onerror\s*=|<\s*iframe\b|document\.cookie/i', $sample) === 1
                    || preg_match('/\bunion\s+(?:all\s+)?select\b|\bsleep\s*\(|\bbenchmark\s*\(|[\'\"\)]\s*(?:or|and)\s+\d+\s*=\s*\d+/i', $sample) === 1
                ) {
                    return 'injection_pattern';
                }
            }
        }

        return null;
    }

    /** @param array<string,mixed> $hack */
    private function recordHackBlock(Request $request, string $reason, array $hack): void
    {
        $sourceHash = hash('sha256', $request->ip());
        (new SecurityAuditService())->record(
            'security.hack_blocked',
            null,
            null,
            [
                'reason' => $reason,
                'path' => substr($request->path(), 0, 180),
                'source' => substr($sourceHash, 0, 16),
            ]
        );

        $email = strtolower(trim((string) ($hack['alert_email'] ?? '')));
        if (
            empty($hack['alerts_enabled'])
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            return;
        }

        $cooldown = max(900, (int) ($hack['alert_cooldown_seconds'] ?? 1800));
        if (!$this->claimAlertWindow($cooldown)) {
            return;
        }

        $this->queueHackAlert($email, $request->path(), $reason, substr($sourceHash, 0, 16), false);
    }

    private function queueHackAlert(string $email, string $path, string $reason, string $source, bool $test): void
    {
        try {
            $title = $test ? 'Barama hacking alert test' : 'Barama blocked a suspicious request';
            $safePath = substr($path, 0, 180);
            $reasonLabel = str_replace('_', ' ', $reason);
            $text = $title . "\n\nReason: {$reasonLabel}\nPath: {$safePath}\nSource hash: {$source}\n\nReview Security Audit in the Admin Dashboard.";
            $html = '<p><strong>' . $this->escape($title) . '</strong></p>'
                . '<p>Reason: ' . $this->escape($reasonLabel) . '<br>Path: ' . $this->escape($safePath)
                . '<br>Source hash: ' . $this->escape($source) . '</p>'
                . '<p>Review Security Audit in the Admin Dashboard.</p>';

            (new NotificationService())->notifyAddress($email, [
                'template_key' => 'security_hack_alert',
                'variables' => [
                    'title' => $title,
                    'reason' => $reasonLabel,
                    'path' => $safePath,
                    'source_hash' => $source,
                    'action_url' => url('/dashboard/audit'),
                ],
                'subject' => $title,
                'html' => $html,
                'text' => $text,
                'dedupe_key' => 'security-hack-alert:' . gmdate('YmdHi') . ':' . hash('sha256', $reason . $safePath . $source),
            ]);
        } catch (Throwable) {
            // Blocking must not depend on mail delivery.
        }
    }

    /** @param array<string,mixed> $spam */
    private function spamBlocked(Request $request, array $spam): bool
    {
        $ipHash = hash('sha256', $request->ip());
        $window = max(60, (int) ($spam['window_seconds'] ?? 600));
        $max = max(1, (int) ($spam['max_requests'] ?? 8));
        $duplicateMax = max(1, (int) ($spam['duplicate_max'] ?? 3));
        $block = max(60, (int) ($spam['block_seconds'] ?? 900));
        $path = $request->path();

        $generalKey = 'spam:general:' . hash('sha256', $path . ':' . $ipHash);
        if (!$this->hitRate($generalKey, $window, $max, $block)) {
            return true;
        }

        $safeBody = [];
        foreach ($request->all() as $key => $value) {
            if (!is_scalar($value) || (string) $key === '_token') {
                continue;
            }
            $keyText = strtolower((string) $key);
            if (preg_match('/password|token|secret|passcode/', $keyText) === 1) {
                continue;
            }
            $safeBody[$keyText] = substr(trim((string) $value), 0, 2000);
        }
        ksort($safeBody);
        $material = json_encode($safeBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $appKey = (string) env('APP_KEY', 'barama');
        $fingerprint = hash_hmac('sha256', $path . ':' . $material, $appKey);
        $duplicateKey = 'spam:duplicate:' . hash('sha256', $path . ':' . $ipHash . ':' . $fingerprint);

        return $this->hitRate($duplicateKey, max($window, 1800), $duplicateMax, $block);
    }

    private function hitRate(string $key, int $window, int $max, int $blockSeconds): bool
    {
        $allowed = true;
        $this->mutateRateState(static function (array $state) use ($key, $window, $max, $blockSeconds, &$allowed): array {
            $now = time();
            $item = is_array($state['counters'][$key] ?? null) ? $state['counters'][$key] : [];
            $blockedUntil = (int) ($item['blocked_until'] ?? 0);
            if ($blockedUntil > $now) {
                $allowed = false;
                $item['touched'] = $now;
                $state['counters'][$key] = $item;
                return $state;
            }

            $started = (int) ($item['started'] ?? 0);
            $count = (int) ($item['count'] ?? 0);
            if ($started < 1 || ($now - $started) >= $window) {
                $started = $now;
                $count = 0;
            }
            $count++;
            $blockedUntil = 0;
            if ($count > $max) {
                $allowed = false;
                $blockedUntil = $now + $blockSeconds;
            }
            $state['counters'][$key] = [
                'started' => $started,
                'count' => $count,
                'blocked_until' => $blockedUntil,
                'touched' => $now,
            ];
            return $state;
        });
        return $allowed;
    }

    private function claimAlertWindow(int $cooldown): bool
    {
        $claimed = false;
        $this->mutateRateState(static function (array $state) use ($cooldown, &$claimed): array {
            $now = time();
            $last = (int) ($state['alerts']['hack_last_sent'] ?? 0);
            if ($last < 1 || ($now - $last) >= $cooldown) {
                $state['alerts']['hack_last_sent'] = $now;
                $claimed = true;
            }
            return $state;
        });
        return $claimed;
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $callback */
    private function mutateRateState(callable $callback): void
    {
        $path = $this->rateStatePath();
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            return;
        }
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }
            rewind($handle);
            $raw = stream_get_contents($handle);
            $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
            $state = is_array($decoded) ? $decoded : ['counters' => [], 'alerts' => []];
            $state['counters'] = is_array($state['counters'] ?? null) ? $state['counters'] : [];
            $state['alerts'] = is_array($state['alerts'] ?? null) ? $state['alerts'] : [];

            $cutoff = time() - 86400;
            foreach ($state['counters'] as $key => $item) {
                if (!is_array($item) || (int) ($item['touched'] ?? 0) < $cutoff) {
                    unset($state['counters'][$key]);
                }
            }
            if (count($state['counters']) > self::RATE_STATE_LIMIT) {
                uasort($state['counters'], static fn (array $a, array $b): int => ((int) ($a['touched'] ?? 0)) <=> ((int) ($b['touched'] ?? 0)));
                $state['counters'] = array_slice($state['counters'], -self::RATE_STATE_LIMIT, null, true);
            }

            $state = $callback($state);
            $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json)) {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, $json);
                fflush($handle);
                @chmod($path, 0660);
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function rateStateWritable(): bool
    {
        $directory = dirname($this->rateStatePath());
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }
        return is_dir($directory) && is_writable($directory);
    }

    private function rateStatePath(): string
    {
        return APP_PATH . '/storage/security/protection-rate.json';
    }

    private function blockedResponse(int $status, string $title, string $message): Response
    {
        $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>' . e($title) . '</title></head>'
            . '<body><main>'
            . '<h1>' . e($title) . '</h1><p>' . e($message) . '</p></main></body></html>';
        return Response::html($html, $status);
    }

    /** @param array<string,mixed> $input */
    private function enabled(array $input, string $key): bool
    {
        return in_array((string) ($input[$key] ?? ''), ['1', 'true', 'on'], true);
    }

    /** @param array<string,mixed> $input @param array<int,int> $allowed */
    private function allowedInt(array $input, string $key, array $allowed, int $default): int
    {
        $value = (int) ($input[$key] ?? $default);
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('Select a supported security protection value.');
        }
        return $value;
    }

    /** @param array<string,mixed> $metadata */
    private function auditAdmin(string $event, array $metadata = []): void
    {
        $user = auth_user();
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        (new SecurityAuditService())->record($event, $userId > 0 ? $userId : null, null, $metadata);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function assertAdministrator(): void
    {
        $auth = new AuthService();
        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can('dashboard.access')) {
            throw new DomainException('Administrator access is required to manage platform security protection.');
        }
    }
}
