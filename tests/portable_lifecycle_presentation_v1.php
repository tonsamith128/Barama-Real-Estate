<?php
declare(strict_types=1);
/** Read-only presentation regression fixtures; no DB, network, or updater POST. */
namespace Barama\Services\Updates {
    final class PublisherTrustPaths {
        public static function isProtectedStaging(): bool { return false; }
    }
    final class PortableCustomerUpdateV1 {
        public static function ready(): bool { return true; }
    }
}
namespace Barama\Core {
    final class Csrf {
        public static function token(): string { return str_repeat('a', 64); }
    }
}
namespace {
    function e(mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    function asset(string $path): string { return '/assets/' . $path; }
    function url(string $path): string { return $path; }

    $view = dirname(__DIR__) . '/application/resources/views/workspace/auto-updater-unified-v100.php';
    $css = dirname(__DIR__) . '/assets/css/barama-qa-busy-arrow-v105.css';
    if (!is_file($view) || !is_file($css)) {
        fwrite(STDERR, "VIEW_OR_CSS_MISSING\n");
        exit(1);
    }
    function renderPortable(array $status, array $feed = []): string {
        global $view;
        $systemVersion = '0.9.23-qa.1';
        $systemPortableStatus = $status;
        $systemFeedResult = $feed;
        $systemFeedReadiness = ['can_check' => true];
        $_SESSION = [];
        ob_start();
        include $view;
        return (string)ob_get_clean();
    }
    $cases = [
        ['completed-bound', ['state'=>'completed_file_only', 'version'=>'0.9.23-qa.1', 'pending'=>false], 'Completed · 4 of 4 confirmed', true],
        ['completed-mismatch', ['state'=>'completed_file_only', 'version'=>'0.9.99', 'pending'=>false], 'Completed · 4 of 4 confirmed', false],
        ['completed-missing-version', ['state'=>'completed_file_only', 'pending'=>false], 'Completed · 4 of 4 confirmed', false],
        ['completed-active-flag', ['state'=>'completed_file_only', 'version'=>'0.9.23-qa.1', 'pending'=>true], 'Completed · 4 of 4 confirmed', false],
        ['downloaded', ['state'=>'downloaded', 'version'=>'0.9.23-qa.1', 'pending'=>true], 'Step 1 of 4 confirmed', true],
        ['health-pending-bound', ['state'=>'health_pending', 'version'=>'0.9.23-qa.1', 'pending'=>true], 'Step 3 of 4 confirmed', true],
        ['health-pending-mismatch', ['state'=>'health_pending', 'version'=>'0.9.22-dev', 'pending'=>true], 'Step 3 of 4 confirmed', false],
        ['not-started', ['state'=>'not_started', 'pending'=>false], 'Completed · 4 of 4 confirmed', false],
    ];
    foreach ($cases as [$name, $status, $needle, $expected]) {
        $html = renderPortable($status);
        if (str_contains($html, $needle) !== $expected) {
            fwrite(STDERR, "PRESENTATION_CASE_FAILED=" . $name . "\n");
            exit(2);
        }
        if ($name === 'completed-bound') {
            foreach (['Backup creation', 'Rollback &amp; recovery', 'Final verification',
                       'bau-portable-complete', 'barama-qa-busy-arrow-v105.css'] as $marker) {
                if (!str_contains($html, $marker)) {
                    fwrite(STDERR, "COMPLETED_MARKER_MISSING=" . $marker . "\n");
                    exit(3);
                }
            }
        }
        if ($name === 'completed-mismatch' && !str_contains($html, 'Update status requires review')) {
            fwrite(STDERR, "MISMATCH_FAIL_CLOSED_MISSING\n");
            exit(4);
        }
    }
    $fresh = renderPortable(
        ['state'=>'completed_file_only', 'version'=>'0.9.23-qa.1', 'pending'=>false],
        ['status'=>'available', 'entitlement'=>'Eligible',
         'feed'=>'Signed release verified', 'at'=>time(), 'version'=>'0.9.24']
    );
    if (str_contains($fresh, 'Completed · 4 of 4 confirmed')) {
        fwrite(STDERR, "NEWER_RELEASE_STALE_COMPLETION\n");
        exit(5);
    }
    $style = file_get_contents($css);
    foreach (['bau-portable-complete[data-bau-busy="1"]', 'bau-qa-amber-arrow-orbit-v105',
              'prefers-reduced-motion'] as $cssMarker) {
        if (!str_contains((string)$style, $cssMarker)) {
            fwrite(STDERR, "BUSY_SPINNER_CSS_MARKER_MISSING\n");
            exit(6);
        }
    }
    echo "PORTABLE_LIFECYCLE_PRESENTATION_CASES=PASS\n";
    echo "PORTABLE_VERSION_BINDING_NEGATIVE_CASES=PASS\n";
    echo "QA_BUSY_SPINNER_CSS_PRESERVED=PASS\n";
}
