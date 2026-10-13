#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This runner is CLI-only.\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap/runtime.php';

use Barama\Services\SecurityIncidentService;

try {
    $result = (new SecurityIncidentService())->runAutomation();

    echo json_encode(
        [
            'ok' => true,
            'ran_at' => date('c'),
            'result' => $result,
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        json_encode(
            [
                'ok' => false,
                'ran_at' => date('c'),
                'error' => $exception->getMessage(),
            ],
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL
    );

    exit(1);
}
