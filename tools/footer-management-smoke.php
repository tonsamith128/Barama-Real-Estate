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

$flex = $test;
$flex['footer_links_submitted'] = '1';
$flex['brand_heading'] = 'Explore Barama';
$flex['footer_links'] = [
    'brand' => [['label' => 'Our Story', 'url' => '/about']],
    'marketplace' => [
        ['label' => 'Explore Projects', 'url' => '/projects'],
        ['label' => 'Browse Properties', 'url' => '/properties'],
        ['label' => 'External Portal', 'url' => 'https://example.org/catalog'],
    ],
    'compliance' => [
        ['label' => 'Terms', 'url' => '/policies?=terms-of-service'],
        ['label' => 'Privacy', 'url' => '/policies?=privacy-policy'],
    ],
    'help' => [],
];
$config = FooterContentService::validate($flex);
$assert($config['brand_heading'] === 'Explore Barama', 'Custom Brand heading was not accepted.');
$assert(count($config['links']['brand']) === 1, 'Brand menu items cannot be edited.');
$assert($config['links']['marketplace'][0]['label'] === 'Explore Projects', 'Menu item order was not preserved.');
$assert($config['links']['compliance'][0]['label'] === 'Terms', 'Compliance reordering failed.');
$assert($config['links']['help'] === [], 'Removed links must remain removed.');
echo "[PASS] Custom headings, addition, removal, order and canonical policy URLs.\n";

foreach ([
    'javascript:alert(1)', '//evil.example', 'http://example.org',
    "https://example.org\r\n", "/../admin", '/foo bar',
    'https://user:pass@example.org',
] as $bad) {
    $attempt = $flex;
    $attempt['footer_links']['brand'][0]['url'] = $bad;
    $invalid(static fn () => FooterContentService::validate($attempt));
}
$tooMany = $flex;
$tooMany['footer_links']['brand'] = array_fill(0, 16, ['label' => 'Test', 'url' => '/about']);
$invalid(static fn () => FooterContentService::validate($tooMany));
$malformed = $flex;
$malformed['footer_links']['help'] = [['url' => '/contact']];
$invalid(static fn () => FooterContentService::validate($malformed));
echo "[PASS] Invalid link schemes, overflow and missing fields fail closed.\n";

$root = dirname(__DIR__);
$public = (string) file_get_contents($root . '/application/resources/views/layouts/app.php');
$admin = (string) file_get_contents($root . '/application/resources/views/workspace/footer.php');
$routes = (string) file_get_contents($root . '/application/routes/web.php');
$controller = (string) file_get_contents($root . '/application/app/Http/Controllers/DashboardController.php');
$mailCss = (string) file_get_contents($root . '/assets/css/admin-mail-settings-v100.css');

foreach ([
    "'compliance_heading'",
    "FooterContentRepository",
    'site-footer__social',
    'site-footer__brand-heading',
    'editableFooterLinks',
] as $need) {
    $assert(str_contains($public, $need), 'Public Footer missing required binding: ' . $need);
}
$defaultsSource = (string) file_get_contents($root . '/application/app/Repositories/FooterContentRepository.php');
$assert(
    str_contains($defaultsSource, "'/policies?=privacy-policy'")
    && str_contains($defaultsSource, "'/policies?=terms-of-service'"),
    'Legacy canonical Policy Center paths must remain available for migration.'
);
$assert(
    str_contains($admin, 'data-footer-add')
    && str_contains($admin, 'data-footer-remove')
    && str_contains($admin, 'data-footer-up')
    && str_contains($admin, 'data-footer-down')
    && str_contains($admin, 'footer_links_submitted'),
    'Footer editor is missing dynamic link controls.'
);
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
