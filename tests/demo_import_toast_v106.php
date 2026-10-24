<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$view = file_get_contents($root.'/application/resources/views/workspace/demo-importer.php');
$css = file_get_contents($root.'/assets/css/barama-demo-premium-v103.css');
$js = file_get_contents($root.'/assets/js/barama-demo-premium-v103.js');

foreach ([
  'server-rendered scoped toast' => is_string($view) && str_contains($view, 'data-demo-feedback-toast'),
  'trusted server flash' => str_contains($view, "e((string)(\$demoFlash['message'] ?? ''))"),
  'correct live region' => str_contains($view, "role=\"<?= \$demoToastSuccess ? 'status' : 'alert' ?>\""),
  'persistent installation warning' => str_contains($view, "if (empty(\$demoReleaseAccepted))"),
  'exact CSS cache bust' => str_contains($view, "asset('css/barama-demo-premium-v103.css') . '?v=106'"),
  'exact JS cache bust' => str_contains($view, "asset('js/barama-demo-premium-v103.js') . '?v=106'"),
  'scoped fixed feedback' => is_string($css) && str_contains($css, 'BARAMA_DEMO_FEEDBACK_TOAST_V106') && str_contains($css, 'position:fixed'),
  'error progress compatibility' => is_string($js) && str_contains($js, '.bdemo-notice-toast--error'),
  'dismiss action' => str_contains($js, '[data-demo-toast-close]') && str_contains($js, 'feedbackToast.remove()'),
] as $name => $passed) {
    if (!$passed) {fwrite(STDERR, "FAIL=$name\n"); exit(1);}
    echo "PASS=$name\n";
}
echo "DEMO_TOAST_V106_CONTRACT=PASS\n";
