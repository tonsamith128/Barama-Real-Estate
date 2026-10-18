<?php

declare(strict_types=1);

namespace {
    // Standalone hermetic ZIP double only where the PHP zip extension is absent.
    // With zip installed, tests use real ZIP files, as in GitHub CI.
    if (!class_exists('ZipArchive')) {
        final class ZipArchive {
            public const RDONLY = 16;
            public int $numFiles = 0;
            private array $files = [];
            public function open(string $path, int $flags): bool {
                if ($flags !== self::RDONLY) return false;
                $decoded = json_decode((string) file_get_contents($path), true);
                if (!is_array($decoded)) return false;
                $this->files = $decoded;
                $this->numFiles = count($decoded);
                return true;
            }
            public function statIndex(int $index): array|false {
                if (!isset($this->files[$index])) return false;
                $f = $this->files[$index];
                return ['name' => $f[0], 'size' => strlen($f[1])];
            }
            public function getStream(string $path) {
                foreach ($this->files as $f) {
                    if ($f[0] !== $path) continue;
                    $stream = fopen('php://temp', 'w+b');
                    fwrite($stream, $f[1]);
                    rewind($stream);
                    return $stream;
                }
                return false;
            }
            public function close(): bool { return true; }
        }
    }
}

namespace Barama\Services\Updates {
    // Only the signing-verifier boundary is mocked. Production code always
    // calls the actual SignedReleaseInspector::inspectPaths.
    final class SignedReleaseInspector {
        public static string $digest = '';
        public static bool $deny = false;
        public static int $calls = 0;
        public static function inspectPaths(string $envelope, string $archive, string $version): array {
            self::$calls++;
            if (self::$deny) throw new \RuntimeException('UPDATE_SIGNATURE_INVALID');
            return ['archive_sha256' => self::$digest, 'version' => '1.0.1'];
        }
    }
}

namespace {
    use Barama\Services\Updates\SignedReleaseInspector;
    use Barama\Services\Updates\VerifiedReleaseFilePlanV2;

    $dir = sys_get_temp_dir() . '/barama-v2-plan-' . bin2hex(random_bytes(9));
    mkdir($dir, 0700);
    define('BASE_PATH', $dir);
    require dirname(__DIR__) . '/application/app/Services/Updates/VerifiedReleaseFilePlanV2.php';
    $archive = $dir . '/archive.zip';
    $envelope = $dir . '/envelope.json';
    $check = static function (bool $pass, string $name): void {
        if (!$pass) {fwrite(STDERR, 'FAIL ' . $name . PHP_EOL); exit(1);}
        echo 'PASS ' . $name . PHP_EOL;
    };
    $create = static function (array $items) use ($archive): void {
        if (extension_loaded('zip')) {
            if (file_exists($archive)) unlink($archive);
            $zip = new \ZipArchive();
            if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create test ZIP');
            }
            foreach ($items as $item) $zip->addFromString($item[0], $item[1]);
            $zip->close();
        } else {
            file_put_contents($archive, json_encode($items, JSON_THROW_ON_ERROR));
        }
        SignedReleaseInspector::$digest = hash_file('sha256', $archive);
    };
    mkdir($dir.'/assets', 0700);
    file_put_contents($dir.'/assets/a.css', 'old');
    $valid = [
        ['VERSION', '1.0.1'],
        ['assets/a.css', 'new'],
        ['application/app/Services/Updates/New.php', '<?php return true;'],
    ];
    $create($valid);
    $plan = VerifiedReleaseFilePlanV2::inspect($envelope, $archive, '1.0.0');
    $check($plan['status']==='verified_plan_only' && !$plan['installation_enabled'],
        'Read-only plan never authorizes installation');
    $check($plan['target_version']==='1.0.1' && $plan['files']===3,
        'Signed version and target count');
    $check($plan['targets'][1]['installed_sha256']===hash('sha256','old'),
        'Detect installed-file baseline');
    $check($plan['targets'][1]['archive_sha256']===hash('sha256','new'),
        'Hash candidate file bytes');
    $check(SignedReleaseInspector::$calls===1,
        'Existing signature verifier must be called');
    $refuse = static function (array $files, string $expected) use ($create,$envelope,$archive,$check): void {
        $create($files);
        try { VerifiedReleaseFilePlanV2::inspect($envelope,$archive,'1.0.0'); }
        catch (\RuntimeException $e) {
            $check($e->getMessage()===$expected,'Reject '.$expected);
            return;
        }
        $check(false,'Expected refusal: '.$expected);
    };
    $refuse([['VERSION','1.0.1'],['application/database/migrations/0099.php','sql']],
        'UPDATE_MIGRATION_REVIEW_REQUIRED');
    $refuse([['VERSION','1.0.1'],['application/config/secrets.php','secret']],
        'UPDATE_TARGET_PROTECTED');
    $refuse([['VERSION','1.0.1'],['application/storage/secret.php','secret']],
        'UPDATE_TARGET_PROTECTED');
    $refuse([['VERSION','1.0.1'],['assets/../index.php','evil']],
        'UPDATE_TARGET_PATH_INVALID');
    $refuse([['VERSION','1.0.1'],['assets//one.css','evil']],
        'UPDATE_TARGET_PATH_INVALID');
    $refuse([['VERSION','1.0.1'],['assets/A.css','x'],['assets/a.css','y']],
        'UPDATE_TARGET_DUPLICATE');
    symlink('/tmp',$dir.'/assets/link');
    $refuse([['VERSION','1.0.1'],['assets/link/z.css','evil']],
        'UPDATE_TARGET_SYMLINK');
    unlink($dir.'/assets/link');
    $create($valid);
    SignedReleaseInspector::$deny=true;
    try {VerifiedReleaseFilePlanV2::inspect($envelope,$archive,'1.0.0'); $check(false,'Invalid signature');}
    catch (\RuntimeException $e) {
        $check($e->getMessage()==='UPDATE_SIGNATURE_INVALID',
            'Signed verifier denial remains fatal');
    }
    SignedReleaseInspector::$deny=false;
    file_put_contents($archive, 'corruption');
    try {VerifiedReleaseFilePlanV2::inspect($envelope,$archive,'1.0.0');$check(false,'Archive tamper');}
    catch (\RuntimeException $e) {
        $check($e->getMessage()==='UPDATE_ARCHIVE_CHANGED',
            'Reject archive changed after verification');
    }
    unlink($archive);
    unlink($dir.'/assets/a.css');
    rmdir($dir.'/assets');
    rmdir($dir);
    echo "BARAMA_SIGNED_FILE_PLAN_SMOKE=PASS\n";
}
