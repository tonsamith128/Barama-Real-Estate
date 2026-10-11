<?php

declare(strict_types=1);

namespace Barama\Security;

use RuntimeException;

/**
 * Private AES-256-GCM Turnstile settings storage.
 *
 * Unwired foundation: saving settings alone must NEVER claim a route is protected.
 * The settings file belongs in existing nonpublic application/storage/settings.
 * No keys or plaintext secrets are sent to views, logs, or source packages.
 */
final class TurnstileSettingsStore
{
    public const FORMS = [
        'login', 'register', 'forgot_password', 'contact', 'inquiry',
    ];

    public function __construct(
        private readonly ?string $path = null,
        private readonly ?string $appKey = null
    ) {
    }

    /** @return array{configured:bool,site_key:string,enabled:bool,forms:array<int,string>} */
    public function status(): array
    {
        $record = $this->read();
        return [
            'configured' => $record['secret'] !== '' && $record['site_key'] !== '',
            'site_key' => $record['site_key'],
            'enabled' => $record['enabled'],
            'forms' => $record['forms'],
        ];
    }

    /**
     * Private runtime credential access. Never serialize this into a view.
     * @return array{enabled:bool,site_key:string,secret:string,forms:array<int,string>}
     */
    public function read(): array
    {
        $path = $this->storagePath();
        $empty = ['enabled' => false, 'site_key' => '', 'secret' => '', 'forms' => []];

        if (!file_exists($path) && !is_link($path)) {
            return $empty;
        }
        if (is_link($path) || !is_file($path) || filesize($path) > 8192) {
            throw new RuntimeException('Turnstile configuration is unavailable.');
        }
        $raw = @file_get_contents($path);
        $record = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($record) || ($record['schema'] ?? null) !== 1 ||
            !isset($record['enabled'], $record['site_key'], $record['forms'], $record['secret']) ||
            !is_bool($record['enabled']) || !is_string($record['site_key']) ||
            !is_array($record['forms']) || !is_array($record['secret'])) {
            throw new RuntimeException('Turnstile configuration is invalid.');
        }
        $this->assertSiteKey($record['site_key']);
        $forms = $this->validatedForms($record['forms']);
        $secretParts = $record['secret'];
        foreach (['nonce', 'tag', 'ciphertext'] as $key) {
            if (!isset($secretParts[$key]) || !is_string($secretParts[$key])) {
                throw new RuntimeException('Turnstile encrypted credentials are invalid.');
            }
        }
        $nonce = base64_decode($secretParts['nonce'], true);
        $tag = base64_decode($secretParts['tag'], true);
        $ciphertext = base64_decode($secretParts['ciphertext'], true);
        if (!is_string($nonce) || strlen($nonce) !== 12 ||
            !is_string($tag) || strlen($tag) !== 16 ||
            !is_string($ciphertext) || $ciphertext === '' || strlen($ciphertext) > 4096) {
            throw new RuntimeException('Turnstile encrypted credentials are invalid.');
        }
        $secret = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->associatedData($record['enabled'], $record['site_key'], $forms)
        );
        if (!is_string($secret) || $secret === '' || strlen($secret) > 1024) {
            throw new RuntimeException('Turnstile credential decryption failed.');
        }

        if ($record['enabled'] && ($record['site_key'] === '' || $forms === [])) {
            throw new RuntimeException('Enabled Turnstile configuration is incomplete.');
        }
        return [
            'enabled' => $record['enabled'],
            'site_key' => $record['site_key'],
            'secret' => $secret,
            'forms' => $forms,
        ];
    }

    /** @param array<int,string> $forms */
    public function save(bool $enabled, string $siteKey, string $secret, array $forms): void
    {
        $siteKey = trim($siteKey);
        $this->assertSiteKey($siteKey);
        if ($secret === '' || strlen($secret) > 1024 ||
            preg_match('/^[A-Za-z0-9_-]+$/D', $secret) !== 1) {
            throw new RuntimeException('Turnstile secret is invalid.');
        }
        $forms = $this->validatedForms($forms);
        if ($enabled && ($siteKey === '' || $forms === [])) {
            throw new RuntimeException('Turnstile must be fully configured before enabling.');
        }

        $nonce = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt(
            $secret,
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->associatedData($enabled, $siteKey, $forms),
            16
        );
        if (!is_string($encrypted) || strlen($tag) !== 16) {
            throw new RuntimeException('Turnstile credential encryption failed.');
        }
        $json = json_encode([
            'schema' => 1,
            'enabled' => $enabled,
            'site_key' => $siteKey,
            'forms' => $forms,
            'secret' => [
                'nonce' => base64_encode($nonce),
                'tag' => base64_encode($tag),
                'ciphertext' => base64_encode($encrypted),
            ],
        ], JSON_THROW_ON_ERROR);

        $path = $this->storagePath();
        $dir = dirname($path);
        if (is_link($dir) || (file_exists($path) && (is_link($path) || !is_file($path)))) {
            throw new RuntimeException('Turnstile storage path is unsafe.');
        }
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Turnstile storage directory is unavailable.');
        }
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        if (@file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Turnstile configuration could not be written.');
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Turnstile configuration could not be saved.');
        }
        @chmod($path, 0600);
    }

    /** @param array<int,string> $forms */
    private function associatedData(bool $enabled, string $siteKey, array $forms): string
    {
        return json_encode([
            'context' => 'barama.turnstile.v1',
            'enabled' => $enabled,
            'site_key' => $siteKey,
            'forms' => $forms,
        ], JSON_THROW_ON_ERROR);
    }

    private function storagePath(): string
    {
        return $this->path ?? APP_PATH . '/storage/settings/turnstile.json';
    }

    private function encryptionKey(): string
    {
        $raw = $this->appKey;
        if ($raw === null) {
            $raw = (string) env('APP_KEY', '');
        }
        $decoded = base64_decode($raw, true);
        if (!is_string($decoded) || strlen($decoded) !== 32) {
            throw new RuntimeException('Application encryption key is unavailable.');
        }
        return hash_hkdf('sha256', $decoded, 32, 'barama-turnstile-settings-v1');
    }

    private function assertSiteKey(string $siteKey): void
    {
        if (strlen($siteKey) > 128 ||
            ($siteKey !== '' && preg_match('/^[A-Za-z0-9_-]+$/D', $siteKey) !== 1)) {
            throw new RuntimeException('Turnstile public site key is invalid.');
        }
    }

    /** @param array<int,mixed> $forms
     *  @return array<int,string>
     */
    private function validatedForms(array $forms): array
    {
        if (array_values($forms) !== $forms || count($forms) > count(self::FORMS)) {
            throw new RuntimeException('Turnstile protected form selection is invalid.');
        }
        foreach ($forms as $form) {
            if (!is_string($form) || !in_array($form, self::FORMS, true)) {
                throw new RuntimeException('Turnstile protected form selection is invalid.');
            }
        }
        if (count($forms) !== count(array_unique($forms))) {
            throw new RuntimeException('Turnstile protected forms must be unique.');
        }
        return $forms;
    }
}
