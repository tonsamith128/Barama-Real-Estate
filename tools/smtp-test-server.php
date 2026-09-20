<?php

declare(strict_types=1);

$port = (int) ($argv[1] ?? 25252);
$messagePath = (string) ($argv[2] ?? '');
$readyPath = (string) ($argv[3] ?? '');
$username = (string) ($argv[4] ?? 'barama-user');
$password = (string) ($argv[5] ?? 'barama-password');

if (
    $port < 1
    || $port > 65535
    || $messagePath === ''
    || $readyPath === ''
) {
    fwrite(STDERR, "Invalid SMTP fixture arguments.\n");
    exit(2);
}

$errorNumber = 0;
$errorMessage = '';
$server = @stream_socket_server(
    'tcp://127.0.0.1:' . $port,
    $errorNumber,
    $errorMessage
);

if (!is_resource($server)) {
    fwrite(
        STDERR,
        'SMTP fixture could not listen: '
        . ($errorMessage !== '' ? $errorMessage : (string) $errorNumber)
        . PHP_EOL
    );
    exit(3);
}

file_put_contents($readyPath, 'ready', LOCK_EX);

$client = @stream_socket_accept($server, 15);

if (!is_resource($client)) {
    fclose($server);
    fwrite(STDERR, "SMTP fixture timed out waiting for a client.\n");
    exit(4);
}

stream_set_timeout($client, 15);

$write = static function ($stream, string $line): void {
    fwrite($stream, $line . "\r\n");
};

$read = static function ($stream): string {
    $line = fgets($stream, 4096);

    if ($line === false) {
        throw new RuntimeException(
            'SMTP fixture client disconnected unexpectedly.'
        );
    }

    return rtrim($line, "\r\n");
};

try {
    $write($client, '220 localhost Barama SMTP QA');

    while (true) {
        $line = $read($client);
        $upper = strtoupper($line);

        if (str_starts_with($upper, 'EHLO ') || str_starts_with($upper, 'HELO ')) {
            $write($client, '250-localhost');
            $write($client, '250-AUTH LOGIN');
            $write($client, '250 OK');
            continue;
        }

        if ($upper === 'AUTH LOGIN') {
            $write($client, '334 VXNlcm5hbWU6');
            $receivedUser = base64_decode($read($client), true);

            if ($receivedUser !== $username) {
                $write($client, '535 Authentication failed');
                break;
            }

            $write($client, '334 UGFzc3dvcmQ6');
            $receivedPassword = base64_decode($read($client), true);

            if ($receivedPassword !== $password) {
                $write($client, '535 Authentication failed');
                break;
            }

            $write($client, '235 Authentication successful');
            continue;
        }

        if (str_starts_with($upper, 'MAIL FROM:')) {
            $write($client, '250 Sender accepted');
            continue;
        }

        if (str_starts_with($upper, 'RCPT TO:')) {
            $write($client, '250 Recipient accepted');
            continue;
        }

        if ($upper === 'DATA') {
            $write($client, '354 End data with <CR><LF>.<CR><LF>');
            $message = [];

            while (true) {
                $dataLine = $read($client);

                if ($dataLine === '.') {
                    break;
                }

                if (str_starts_with($dataLine, '..')) {
                    $dataLine = substr($dataLine, 1);
                }

                $message[] = $dataLine;
            }

            file_put_contents(
                $messagePath,
                implode("\r\n", $message) . "\r\n",
                LOCK_EX
            );
            $write($client, '250 Message accepted');
            continue;
        }

        if ($upper === 'QUIT') {
            $write($client, '221 Goodbye');
            break;
        }

        $write($client, '250 OK');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    fclose($client);
    fclose($server);
    exit(5);
}

fclose($client);
fclose($server);
