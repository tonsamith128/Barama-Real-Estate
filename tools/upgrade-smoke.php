<?php

declare(strict_types=1);

require __DIR__ . '/upgrade-smoke-runner.php';

$result = run_barama_upgrade_smoke(
    dirname(__DIR__),
    'barama_upgrade_source_test'
);

fwrite(
    STDOUT,
    '[PASS] Source upgrade smoke: '
    . $result['previous_version']
    . ' -> '
    . $result['version']
    . ', administrator re-authentication, backup confirmation, '
    . 'pending migration, audit event and clean replay verified.'
    . PHP_EOL
);
