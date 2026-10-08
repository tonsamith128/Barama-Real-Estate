<?php

declare(strict_types=1);

use Barama\Repositories\FooterContentRepository;
use Barama\Services\FooterContentService;

define('BARAMA_CLI', true);
require dirname(__DIR__) . '/application/bootstrap/runtime.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$invalid = static function (callable $operation) use ($assert): void {
    $failed = false;
    try {
        $operation();
    } catch (InvalidArgumentException $exception) {
        $failed = true;
    }
    $assert($failed, 'Expected footer validation failure was not raised.');
};

$base = FooterContentRepository::defaults();
$test = $base;
foreach (array_keys($base['social']) as $network) {
    $test['social_' . $network] = '';
}
unset($test['social']);
$test['social_facebook'] = 'https://www.facebook.com/barama-real-estate';
$test['social_youtube'] = 'https://www.youtube.com/@barama';

$valid = FooterContentService::validate($test);
$assert($valid['compliance_heading'] === 'Compliance', 'Compliance heading default is incorrect.');
$assert($valid['copyright'] === 'Copyright {year} | Barama Real Estate', 'Footer bottom default mismatch.');
$assert(count($valid['social']) === 6, 'Social provider allowlist drifted.');
$assert($valid['social']['youtube'] !== '', 'Social provider URL was not retained.');
$assert($valid['brand_description'] === $base['brand_description'], 'Original brand description changed.');
$assert($valid['marketplace_heading'] === 'Marketplace', 'Marketplace column changed.');
$assert($valid['help_heading'] === 'Help', 'Help column changed.');
echo "[PASS] Original columns 1, 2, 4 preserved and Compliance defaults validated.\n";

foreach ([
    'javascript:alert(1)',
    '//evil.example',
    'http://social.example/path',
    'https://user:pass@social.example/path',
    'https://social.example/'.str_repeat('x', 501),
    "https://social.example/\r\n",
] as $bad) {
    $invalid(static fn () => FooterContentService::validate(array_replace($test, ['social_x' => $bad])));
}
$invalid(static fn () => FooterContentService::validate(array_replace($test, ['copyright' => ''])));
$invalid(static fn () => FooterContentService::validate(array_replace($test, ['compliance_heading' => str_repeat('x', 62)])));
echo "[PASS] HTTPS-only social link input and bounded footer text validation.\n";

$root = dirname(__DIR__);
$public = (string) file_get_contents($root . '/application/resources/views/layouts/app.php');
$admin = (string) file_get_contents($root . '/application/resources/views/workspace/footer.php');
$routes = (string) file_get_contents($root . '/application/routes/web.php');
$controller = (string) file_get_contents($root . '/application/app/Http/Controllers/DashboardController.php');
$mailCss = (string) file_get_contents($root . '/assets/css/admin-mail-settings-v100.css');

foreach ([
    "'?=privacy-policy'",
    "'?=terms-of-service'",
    "'compliance_heading'",
    "FooterContentRepository",
    'site-footer__social',
] as $need) {
    $assert(str_contains($public, $need), 'Public Footer missing required binding: ' . $need);
}
$assert(!str_contains($public, "url('/pages/privacy')"), 'Legacy Privacy URL still included.');
$assert(!str_contains($public, "url('/pages/terms')"), 'Legacy Terms URL still included.');
$assert(
    str_contains($routes, "'/dashboard/footer'")
        && str_contains($routes, "new PermissionMiddleware('page.manage')")
        && str_contains($routes, 'new CsrfMiddleware()'),
    'Footer editor routes are not protected.'
);
$assert(
    strpos($controller, "['label' => 'Navigation',")
        < strpos($controller, "['label' => 'Footer',"),
    'Footer menu is not below Navigation.'
);
$assert(substr_count($admin, 'csrf_field()') === 1, 'Footer edit must have exactly one CSRF-protected form.');
$assert(str_contains($mailCss, '[data-bs-theme="light"]')
    && str_contains($mailCss, '[data-bs-theme="dark"]')
    && str_contains($mailCss, 'background-color:#195df4!important'),
    'Mail Test recipient input theme and blue button CSS is incomplete.'
);
echo "[PASS] Protected admin Footer editor, nav order and canonical policy links.\n";
echo "[PASS] Theme-specific Test recipient field and blue action button.\n";
echo "FOOTER_MANAGEMENT_QA=PASS\n";
