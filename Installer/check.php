<?php

declare(strict_types=1);

// CLI-only, read-only environment preflight.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Installer preflight is CLI-only.');
}
require_once __DIR__.'/Setup.php';
echo "Barama Laravel 13 preflight (PHP ".PHP_VERSION.")\n";
$passed = true;
foreach (\Barama\Installer\Setup::requirements() as $name => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ').$name."\n";
    $passed = $passed && $ok;
}
echo $passed
    ? "PREFLIGHT_PASS — open /installer/ with the private setup token.\n"
    : "PREFLIGHT_FAIL — resolve failing requirements first.\n";
exit($passed ? 0 : 1);
