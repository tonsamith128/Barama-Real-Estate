<?php

declare(strict_types=1);

use Barama\Services\InstallerService;
use Barama\Services\MailService;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/runtime.php';

if (!function_exists('proc_open')) {
    throw new RuntimeException(
        'proc_open is required for deterministic SMTP QA.'
    );
}

$port = 25252;
$messagePath = sys_get_temp_dir()
    . '/barama-smtp-message-'
    . bin2hex(random_bytes(5))
    . '.txt';
$readyPath = sys_get_temp_dir()
    . '/barama-smtp-ready-'
    . bin2hex(random_bytes(5))
    . '.txt';
$username = 'barama-smtp-user';
$password = 'Barama-SMTP-QA-123!';

$command = implode(' ', [
    escapeshellarg(PHP_BINARY),
    escapeshellarg(__DIR__ . '/smtp-test-server.php'),
    escapeshellarg((string) $port),
    escapeshellarg($messagePath),
    escapeshellarg($readyPath),
    escapeshellarg($username),
    escapeshellarg($password),
]);

$pipes = [];
$process = proc_open(
    $command,
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes
);

if (!is_resource($process)) {
    throw new RuntimeException('SMTP QA fixture could not start.');
}

fclose($pipes[0]);

try {
    $ready = false;

    for ($attempt = 0; $attempt < 50; $attempt++) {
        if (is_file($readyPath)) {
            $ready = true;
            break;
        }

        $status = proc_get_status($process);

        if (!($status['running'] ?? false)) {
            break;
        }

        usleep(100000);
    }

    if (!$ready) {
        $stderr = stream_get_contents($pipes[2]);

        throw new RuntimeException(
            'SMTP QA fixture did not become ready. ' . trim($stderr)
        );
    }

    $settings = [
        'driver' => 'smtp',
        'from_address' => 'no-reply@barama.example.test',
        'from_name' => 'Barama Real Estate QA',
        'host' => '127.0.0.1',
        'port' => $port,
        'encryption' => 'none',
        'username' => $username,
        'password' => $password,
        'timeout' => 5,
    ];

    $mailer = new MailService($settings);

    if (!$mailer->send(
        'administrator@barama.example.test',
        'Barama SMTP transport QA',
        '<p>SMTP transport QA HTML body.</p>',
        'SMTP transport QA text body.'
    )) {
        throw new RuntimeException(
            'SMTP transport failed: '
            . ($mailer->lastError() ?? 'unknown error')
        );
    }

    for ($attempt = 0; $attempt < 30 && !is_file($messagePath); $attempt++) {
        usleep(100000);
    }

    if (!is_file($messagePath)) {
        throw new RuntimeException(
            'SMTP fixture did not receive the test message.'
        );
    }

    $message = (string) file_get_contents($messagePath);

    foreach ([
        'To: <administrator@barama.example.test>',
        'Subject: Barama SMTP transport QA',
        'SMTP transport QA text body.',
        'SMTP transport QA HTML body.',
    ] as $needle) {
        if (!str_contains($message, $needle)) {
            throw new RuntimeException(
                'SMTP message is missing expected content: ' . $needle
            );
        }
    }

    $installer = new InstallerService();
    $valid = $installer->validateMail([
        'mail_driver' => 'smtp',
        'mail_from_address' => 'no-reply@barama.example.test',
        'mail_from_name' => 'Barama QA',
        'mail_host' => 'smtp.example.test',
        'mail_port' => 587,
        'mail_encryption' => 'tls',
        'mail_username' => 'qa-user',
        'mail_password' => 'qa-password',
    ]);

    $changed = $valid;
    $changed['password'] = 'different-password';

    if (
        $installer->mailFingerprint($valid)
        === $installer->mailFingerprint($changed)
    ) {
        throw new RuntimeException(
            'Mail configuration fingerprint did not change with credentials.'
        );
    }

    $invalidBlocked = false;

    try {
        $installer->validateMail([
            'mail_driver' => 'smtp',
            'mail_from_address' => 'not-an-email',
            'mail_from_name' => 'Barama QA',
            'mail_host' => '',
            'mail_port' => 70000,
            'mail_encryption' => 'invalid',
        ]);
    } catch (RuntimeException) {
        $invalidBlocked = true;
    }

    if (!$invalidBlocked) {
        throw new RuntimeException(
            'Invalid installer SMTP configuration was not rejected.'
        );
    }

    fwrite(
        STDOUT,
        '[PASS] SMTP smoke: AUTH LOGIN delivery, multipart message, installer mail validation and configuration fingerprinting verified.'
        . PHP_EOL
    );
} finally {
    if (is_resource($process)) {
        $status = proc_get_status($process);

        if ($status['running'] ?? false) {
            proc_terminate($process);
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }
    @unlink($messagePath);
    @unlink($readyPath);
}
