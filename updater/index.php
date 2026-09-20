<?php

declare(strict_types=1);

define('BARAMA_START', microtime(true));

require dirname(__DIR__) . '/application/bootstrap/runtime.php';

(new Barama\Http\Controllers\UpgradeController())->handle();
