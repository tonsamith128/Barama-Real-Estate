<?php
declare(strict_types=1);
/** Exact customer asset contract for canonical Policy Center style bundles. */
$root = dirname(__DIR__);
$adminFile = $root . '/assets/css/policy-center.css';
$publicFile = $root . '/assets/css/policy-center-public.css';
$assert = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};
$assert(is_file($adminFile) && is_file($publicFile), 'Policy Center bundles missing');
$admin = file_get_contents($adminFile);
$public = file_get_contents($publicFile);
$sections = ['CORE · POLICY LIST / CATEGORIES / EDITOR', 'FEEDBACK · SUMMARY CELLS',
    'FEEDBACK · ANALYTICS', 'FEEDBACK · DETAIL', 'FEEDBACK · REVIEW',
    'LEGAL REVIEW · DECISIONS', 'LEGAL REVIEW · IMPACT',
    'PUBLISHING · VALIDATION FEEDBACK', 'PUBLISHING · FIRST-VISIT GUIDE'];
$at = 0;
foreach ($sections as $section) {
    $next = strpos($admin, 'SECTION: ' . $section, $at);
    $assert($next !== false, 'Missing/out-of-order section: ' . $section);
    $at = $next + strlen($section);
}
$assert(str_contains($admin, '.pc-guide-dialog::backdrop'), 'Guide CSS missing');
$assert(str_contains($public, '.pc-public'), 'Public policy styles missing');
$views = [
    'policy/index.php' => 'policy-center-public.css',
    'workspace/policies.php' => 'policy-center.css',
    'workspace/policy-categories.php' => 'policy-center.css',
    'workspace/policy-builder.php' => 'policy-center.css',
    'workspace/policy-feedback.php' => 'policy-center.css',
    'workspace/policy-feedback-detail.php' => 'policy-center.css',
    'workspace/policy-feedback-review.php' => 'policy-center.css',
];
foreach ($views as $view => $bundle) {
    $source = file_get_contents($root . '/application/resources/views/' . $view);
    preg_match_all('~asset\(\x27css/(policy-center[^\x27]*\.css)\x27\)~', $source, $match);
    $assert($match[1] === [$bundle], $view . ': expected one canonical stylesheet');
}
$styles = glob($root . '/assets/css/policy-center*.css');
$names = array_map('basename', $styles === false ? [] : $styles);
sort($names);
$assert($names === ['policy-center-public.css', 'policy-center.css'], 'Historical policy CSS still bundled: ' . implode(', ', $names));
echo "POLICY_CENTER_CSS_AREA_BUNDLES=PASS (2 bundles; 7 page contracts; 9 named sections)\n";
