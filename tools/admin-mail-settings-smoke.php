<?php

declare(strict_types=1);

use Barama\Repositories\MailSettingsRepository;
use Barama\Services\MailSettingsService;

define('BARAMA_CLI', true);
require dirname(__DIR__) . '/application/bootstrap/runtime.php';

$path = sys_get_temp_dir() . '/barama-mail-settings-' . bin2hex(random_bytes(8)) . '/mail.enc';
$originalAppKey = getenv('APP_KEY');
putenv('APP_KEY=' . base64_encode(random_bytes(32)));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectFailure = static function (callable $operation, string $message) use ($assert): void {
    $threw = false;
    try {
        $operation();
    } catch (RuntimeException $exception) {
        $threw = true;
    }
    $assert($threw, $message);
};

try {
    $repository = new MailSettingsRepository($path);
    $service = new MailSettingsService($repository);
    $assert($repository->get() === [], 'Missing settings must preserve installer mail configuration.');

    $input = [
        'mail_driver' => 'smtp',
        'mail_from_address' => 'noreply@example.test',
        'mail_from_name' => 'Barama QA',
        'mail_host' => 'smtp.example.test',
        'mail_port' => '587',
        'mail_encryption' => 'tls',
        'mail_username' => 'mailer@example.test',
        'mail_password' => 'qa-secret-do-not-display-123',
    ];
    $validated = $service->validatedInput($input);
    $assert($validated['driver'] === 'smtp', 'SMTP input validation failed.');
    $repository->save($validated);
    $raw = (string) file_get_contents($path);
    $assert(str_starts_with($raw, 'v1:'), 'Encrypted file marker missing.');
    $assert(!str_contains($raw, 'qa-secret-do-not-display-123'), 'SMTP credential stored as plaintext.');
    $assert((fileperms($path) & 0777) === 0600, 'Private mail settings must be mode 0600.');
    $assert($service->effectiveConfig()['password'] === 'qa-secret-do-not-display-123', 'Encrypted mail settings roundtrip failed.');
    echo "[PASS] Encrypted SMTP settings, private permissions and runtime override.\n";

    $blankPasswordEdit = array_replace($input, ['mail_password' => '']);
    $kept = $service->validatedInput($blankPasswordEdit);
    $assert($kept['password'] === 'qa-secret-do-not-display-123', 'Blank edit lost prior secret.');
    echo "[PASS] Blank password retains the matching SMTP host/account secret.\n";

    $changedServer = array_replace($blankPasswordEdit, ['mail_host' => 'different.example.test']);
    $expectFailure(
        static fn () => $service->validatedInput($changedServer),
        'Changing SMTP host without a new password must fail.'
    );
    echo "[PASS] Secret not reused for a different server.\n";

    $repository->save((new \Barama\Services\InstallerService())->validateMail([
        'driver' => 'mail',
        'from_address' => 'noreply@example.test',
        'from_name' => 'Barama QA',
    ]));
    $stored = $repository->get();
    $assert($stored['driver'] === 'mail' && $stored['password'] === '', 'PHP Mail must discard SMTP password.');
    echo "[PASS] Switching to PHP Mail discards SMTP credentials.\n";

    $repository->save($validated);
    $original = (string) file_get_contents($path);
    file_put_contents($path, 'v1:' . base64_encode(random_bytes(65)) . PHP_EOL);
    $expectFailure(
        static fn () => $repository->get(),
        'Corrupt mail settings must not silently fall back to PHP Mail.'
    );
    file_put_contents($path, $original);
    echo "[PASS] Corrupted ciphertext fails closed.\n";

    putenv('APP_KEY=' . base64_encode(random_bytes(32)));
    $expectFailure(
        static fn () => $repository->get(),
        'Wrong installation key must not decrypt stored SMTP credentials.'
    );
    echo "[PASS] Stored credentials are bound to installation APP_KEY.\n";

    // UI security assertions: no rendered password values or test-recipient controls.
    $view = (string) file_get_contents(dirname(__DIR__) . '/application/resources/views/workspace/settings-mail.php');
    $assert(
        str_contains($view, 'type="password" value=""')
        && !str_contains($view, "['password']")
        && !str_contains($view, 'name="recipient"'),
        'Mail Setup must never echo saved credentials or accept arbitrary test recipients.'
    );
    $assert(str_contains($view, 'csrf_field()'), 'Mail Setup forms must have CSRF fields.');
    echo "[PASS] View avoids password disclosure and requires CSRF.\n";

    echo "ADMIN_MAIL_SETTINGS_QA=PASS\n";
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] Admin Mail Setup: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($originalAppKey === false) {
        putenv('APP_KEY');
    } else {
        putenv('APP_KEY=' . $originalAppKey);
    }
    @unlink($path);
    @rmdir(dirname($path));
}
