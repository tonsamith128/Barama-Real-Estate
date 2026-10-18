<?php

declare(strict_types=1);

/**
 * Execute the ACTUAL publisher-private-delivery.php through its CLI bootstrap
 * inside a disposable site. Detects the APP_VERSION-before-Config bug.
 * No real publisher network calls, website writes or user data access.
 */
$root = sys_get_temp_dir() . '/barama-cli-bootstrap-' . bin2hex(random_bytes(8));
$site = $root . '/site';
$checks = 0;
$check = static function(bool $valid, string $label) use (&$checks): void {
    if (!$valid) throw new RuntimeException('CLI_BOOTSTRAP_TEST_FAILED:' . $label);
    ++$checks;
};
$clean = static function(string $path) use (&$clean): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') $clean($path . '/' . $name);
    }
    @rmdir($path);
};
$write = static function(string $path, string $content): void {
    $parent = dirname($path);
    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('CLI_FIXTURE_DIRECTORY_FAILED');
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('CLI_FIXTURE_WRITE_FAILED');
    }
};
try {
    mkdir($root, 0700);
    mkdir($site, 0700);
    $write($site.'/VERSION', "0.9.22-dev\n");
    $write($site.'/application/bin/publisher-private-delivery.php',
        (string)file_get_contents(dirname(__DIR__).'/application/bin/publisher-private-delivery.php'));
    $write($site.'/application/app/Support/helpers.php',
        "<?php\nfunction config(string \$key,mixed \$fallback=null):mixed{return \\Barama\\Core\\Config::get(\$key,\$fallback);}\n");
    $write($site.'/application/app/Core/RuntimeEnvironment.php',
        "<?php\nnamespace Barama\\Core; final class RuntimeEnvironment{public static function load():void{}}\n");
    $write($site.'/application/app/Core/Config.php',
        "<?php\nnamespace Barama\\Core; final class Config {private static array \$x=[];\n".
        "public static function load(string \$path):void{\$r=require \$path.'/app.php';".
        "if(!is_array(\$r)||(\$r['version']??null)!=='0.9.22-dev')throw new \\RuntimeException('CLI_TEST_VERSION_WRONG');self::\$x=\$r;}\n".
        "public static function get(string \$key,mixed \$default=null):mixed{return self::\$x[\$key]??\$default;}}\n");
    $write($site.'/application/config/app.php',
        "<?php\nif(!defined('APP_VERSION'))throw new \\RuntimeException('CLI_TEST_APP_VERSION_UNDEFINED');".
        "return ['version'=>APP_VERSION];\n");
    $write($site.'/application/app/Services/Updates/PublisherPrivateDeliveryV1.php',
        "<?php\nnamespace Barama\\Services\\Updates; final class PublisherPrivateDeliveryV1 {".
        "public static function readiness(string \$site):array { ".
        "if(!defined('APP_VERSION')||APP_VERSION!=='0.9.22-dev')throw new \\RuntimeException('CLI_TEST_VERSION_WRONG');".
        "return ['required_files_present'=>true,'publisher_can_check'=>true,'missing'=>[]];}}\n");

    $before = hash_file('sha256', $site . '/VERSION');
    $cmd = [PHP_BINARY, $site.'/application/bin/publisher-private-delivery.php', '--readiness'];
    $pipes = [];
    $proc = proc_open($cmd, [1=>['pipe','w'], 2=>['pipe','w']], $pipes, $site);
    if (!is_resource($proc)) throw new RuntimeException('CLI_TEST_PROCESS_FAILED');
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code = proc_close($proc);
    $check($code === 0, 'CLI readiness exit code');
    $check(str_contains($out, 'READINESS=PASS_DIAGNOSTIC_ONLY'), 'Readiness contract');
    $check(str_contains($out, 'TRUSTED_PUBLISHER_READY=YES'), 'Config and readiness loaded');
    $check(str_contains($out, 'LIVE_NETWORK_REQUESTS=NO'), 'No network requests');
    $check(str_contains($out, 'WEBSITE_WRITES=NO'), 'No website modifications');
    $check(str_contains($out, 'DATABASE_CONNECTIONS=NO'), 'No DB');
    $check(str_contains($out, 'AUTO_INSTALL=DISABLED'), 'No install');
    $check($err === '', 'No PHP fatal or warning');
    $check(hash_file('sha256',$site.'/VERSION') === $before, 'Installed VERSION preserved');
    $check(!is_dir($site.'/application/storage'), 'No runtime data created');
    echo "PUBLISHER_CLI_APP_VERSION_BOOTSTRAP=PASS\n";
    echo "REAL_PUBLISHER_REQUESTS=NO\n";
    echo "DATABASE_CONNECTIONS=NO\n";
    echo "APPLICATION_WRITES=NO\n";
    echo "AUTO_INSTALL=DISABLED\n";
    echo 'TEST_ASSERTIONS='.$checks."\n";
} finally {
    $clean($root);
}
