<?php
declare(strict_types=1);

namespace Barama\Core {
    final class Csrf {
        public static function token(): string { return 'test-csrf'; }
    }
}

namespace {
    function e(mixed $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    function asset(string $path): string {
        return '/assets/' . ltrim($path, '/');
    }
    function url(string $path): string {
        return $path;
    }
    $view = dirname(__DIR__) . '/application/resources/views/workspace/auto-updater-simple.php';
    $assert = static function (bool $ok, string $what): void {
        if (!$ok) {
            fwrite(STDERR, "FAIL " . $what . PHP_EOL);
            exit(1);
        }
        echo "PASS " . $what . PHP_EOL;
    };
    $fixtures = [
        'not_checked' => 'Keep your website up to date',
        'eligible' => 'Your website is up to date',
        'available' => 'A new version is available',
        'ineligible' => 'Updates are not available',
        'unavailable' => 'Unable to check right now',
    ];
    foreach ($fixtures as $status => $expected) {
        $systemVersion = '1.0.0';
        $systemFeedReadiness = ['can_check' => true];
        $systemFeedResult = [
            'status' => $status,
            'version' => $status === 'available' ? '1.0.1' : '',
            'checked_at' => '2026-10-18 08:00 UTC',
        ];
        $systemPublisherCheck = ['state' => 'Active license verified'];
        $systemFeedFlash = '';
        ob_start();
        require $view;
        $html = (string) ob_get_clean();
        $assert(str_contains($html, $expected), "Rendered state " . $status);
        $assert(substr_count($html, '<form method="post"') === 1
            && str_contains($html, 'name="_token" value="test-csrf"'),
            "One CSRF-protected form: " . $status);
        $assert(!str_contains($html, 'private/') && !str_contains($html, 'activation_token'),
            "No internal credentials: " . $status);
        $assert(!str_contains($html, '<style') && !preg_match('/\sstyle\s*=/', $html),
            "External styling only: " . $status);
        if ($status === 'available') {
            $assert(str_contains($html, 'v1.0.1')
                && str_contains($html, 'type="button" disabled'),
                "Found release never enables untested install");
        } else {
            $assert(!str_contains($html, 'Update now</button>'),
                "No irrelevant install button: " . $status);
        }
    }
    $systemFeedResult = ['status'=>'eligible','version'=>'','checked_at'=>'Never'];
    $systemFeedReadiness = ['can_check'=>false];
    $systemPublisherCheck = [];
    $systemFeedFlash = '';
    ob_start();
    require $view;
    $html=(string)ob_get_clean();
    $assert(str_contains($html, 'Update service is not ready')
        && str_contains($html, 'type="submit" disabled'),
        'Publisher without trusted feed stays disabled');
    echo 'AUTO_UPDATER_SIMPLE_RENDER_SMOKE=PASS' . PHP_EOL;
}
