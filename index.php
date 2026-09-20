<?php

declare(strict_types=1);

define('BARAMA_START', microtime(true));

$app = require __DIR__ . '/application/bootstrap/app.php';
$app->run();
