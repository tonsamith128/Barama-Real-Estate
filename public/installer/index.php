<?php

declare(strict_types=1);

// Public bridge only; the independent Installer/ folder lives outside public/.
$wizard = dirname(__DIR__, 2).'/Installer/index.php';
if (!is_file($wizard)) {
    http_response_code(410);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Installer has been removed. Installation is unavailable.');
}
require $wizard;
