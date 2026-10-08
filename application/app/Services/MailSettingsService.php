<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\MailSettingsRepository;
use DomainException;
use InvalidArgumentException;

final class MailSettingsService
{
    public function __construct(
        private readonly ?MailSettingsRepository $repository = null
    ) {
    }

    /** @return array<string,mixed> */
    public function effectiveConfig(): array
    {
        $config = (array) config('mail', []);
        $stored = $this->store()->get();

        return $stored === [] ? $config : array_replace($config, $stored);
    }

    /** @return array<string,mixed> No secret values are ever returned to a view. */
    public function viewModel(): array
    {
        $this->assertAdministrator();
        $config = $this->effectiveConfig();
        return [
            'driver' => in_array((string) ($config['driver'] ?? ''), ['mail', 'smtp'], true)
                ? (string) $config['driver'] : 'mail',
            'from_address' => (string) ($config['from_address'] ?? ''),
            'from_name' => (string) ($config['from_name'] ?? 'Barama Real Estate'),
            'host' => (string) ($config['host'] ?? ''),
            'port' => (int) ($config['port'] ?? 587),
            'encryption' => (string) ($config['encryption'] ?? 'tls'),
            // Do not render the local part of a Gmail SMTP account in HTML.
            // The server keeps the complete username in its encrypted store.
            'username' => self::maskedUsername((string) ($config['username'] ?? '')),
            'has_password' => (string) ($config['password'] ?? '') !== '',
            'saved_override' => $this->store()->exists(),
            'ready' => (string) ($config['from_address'] ?? '') !== ''
                && ((string) ($config['driver'] ?? '') !== 'smtp'
                    || (string) ($config['host'] ?? '') !== ''),
        ];
    }

    /** @param array<string,mixed> $input */
    public function save(array $input): void
    {
        $userId = $this->assertAdministrator();
        $config = $this->validatedInput($input);
        $this->store()->save($config);

        (new SecurityAuditService())->record(
            'admin.mail_settings_updated',
            $userId,
            null,
            ['driver' => (string) $config['driver']]
        );
    }

    /** Sends only to the signed-in administrator; never trusts recipient input. */
    public function sendTest(): void
    {
        $userId = $this->assertAdministrator();
        $user = auth_user();
        $to = strtolower(trim((string) ($user['email'] ?? '')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Your administrator account needs a valid email address.');
        }

        $mail = $this->effectiveConfig();
        if (!in_array((string) ($mail['driver'] ?? ''), ['mail', 'smtp'], true)) {
            throw new DomainException('Save a PHP Mail or SMTP configuration before sending a test.');
        }

        $sent = (new MailService($mail))->send(
            $to,
            'Barama Real Estate mail setup test',
            '<p>Barama Mail Setup successfully sent this message.</p>',
            'Barama Mail Setup successfully sent this message.'
        );
        if (!$sent) {
            throw new DomainException(
                'Mail test failed. Check the mail delivery logs and your hosting transport configuration.'
            );
        }

        (new SecurityAuditService())->record(
            'admin.mail_settings_test_sent',
            $userId,
            null,
            ['driver' => (string) $mail['driver']]
        );
    }

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    public function validatedInput(array $input): array
    {
        $existing = $this->effectiveConfig();
        $driver = strtolower(trim((string) ($input['mail_driver'] ?? 'mail')));
        $host = trim((string) ($input['mail_host'] ?? ''));
        $username = trim((string) ($input['mail_username'] ?? ''));
        $currentUsername = (string) ($existing['username'] ?? '');
        // An unchanged masked Gmail field is a display token, not a new
        // SMTP identity. Restore the server-side canonical username only
        // when it exactly matches this installation's existing account.
        if ($currentUsername !== '' && hash_equals(self::maskedUsername($currentUsername), $username)) {
            $username = $currentUsername;
        }
        $input['mail_username'] = $username;
        $newPassword = (string) ($input['mail_password'] ?? '');

        // Blank edit fields preserve secrets only when both account and server
        // are unchanged. Switching providers or account identity requires a
        // new password instead of accidentally reusing another host's secret.
        if ($driver === 'smtp' && $newPassword === ''
            && (string) ($existing['driver'] ?? '') === 'smtp'
            && hash_equals((string) ($existing['host'] ?? ''), $host)
            && hash_equals((string) ($existing['username'] ?? ''), $username)) {
            $newPassword = (string) ($existing['password'] ?? '');
        }

        $input['mail_password'] = $newPassword;
        // The installer already owns the mail transport validation contract.
        $validated = (new InstallerService())->validateMail(
            $input,
            (string) ($existing['from_address'] ?? ''),
            (string) ($existing['from_name'] ?? 'Barama Real Estate')
        );
        if ($validated['driver'] === 'smtp'
            && $validated['username'] !== ''
            && $validated['encryption'] === 'none') {
            throw new InvalidArgumentException(
                'Authenticated SMTP requires STARTTLS or SSL/TLS encryption.'
            );
        }
        if ($validated['driver'] === 'smtp'
            && $validated['username'] === ''
            && $validated['password'] !== '') {
            throw new InvalidArgumentException(
                'Enter an SMTP username or leave the SMTP password blank.'
            );
        }

        return $validated;
    }

    public static function maskedUsername(string $username): string
    {
        $username = trim($username);
        if (preg_match('/^[^@\s]+@gmail\.com$/i', $username) === 1) {
            return '••••@gmail.com';
        }
        return $username;
    }

    private function store(): MailSettingsRepository
    {
        return $this->repository ?? new MailSettingsRepository();
    }

    private function assertAdministrator(): int
    {
        $auth = new AuthService();
        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can('dashboard.access')) {
            throw new DomainException('Administrator access is required for Mail Setup.');
        }

        return (int) (auth_user()['id'] ?? 0);
    }
}
