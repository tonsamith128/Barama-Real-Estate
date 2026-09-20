<?php

declare(strict_types=1);

use Barama\Core\Session;
use Barama\Support\Locale;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/runtime.php';

$expected = [
    'en' => 'English',
    'km' => 'ខ្មែរ',
    'zh' => '中文',
];

if (Locale::supported() !== $expected) {
    throw new RuntimeException('Supported locale contract is incorrect.');
}

$catalogs = [];

foreach (array_keys($expected) as $locale) {
    $path = APP_PATH . '/resources/lang/' . $locale . '.php';

    if (!is_file($path)) {
        throw new RuntimeException('Missing locale catalog: ' . $locale);
    }

    $catalog = require $path;

    if (!is_array($catalog) || $catalog === []) {
        throw new RuntimeException('Locale catalog is empty: ' . $locale);
    }

    $catalogs[$locale] = $catalog;
}

$englishKeys = array_keys($catalogs['en']);
sort($englishKeys);

foreach (['km', 'zh'] as $locale) {
    $keys = array_keys($catalogs[$locale]);
    sort($keys);

    if ($keys !== $englishKeys) {
        throw new RuntimeException('Locale catalog key mismatch: ' . $locale);
    }
}

Session::forget('app.locale');
Locale::set('km');

if (Locale::current() !== 'km' || t('Home') !== 'ទំព័រដើម') {
    throw new RuntimeException('Khmer locale did not render the canonical Home label.');
}

Locale::set('zh');

if (Locale::current() !== 'zh' || t('Home') !== '首页') {
    throw new RuntimeException('Chinese locale did not render the canonical Home label.');
}

Locale::set('en');

if (t('Home') !== 'Home' || t('missing.translation.key') !== 'missing.translation.key') {
    throw new RuntimeException('English locale or fallback behavior is incorrect.');
}

if (
    Locale::normalize('km-KH') !== 'km'
    || Locale::normalize('zh_CN') !== 'zh'
    || Locale::normalize('fr') !== null
) {
    throw new RuntimeException('Locale normalization contract is incorrect.');
}

$invalidRejected = false;

try {
    Locale::set('fr');
} catch (\InvalidArgumentException) {
    $invalidRejected = true;
}

if (!$invalidRejected || Locale::current() !== 'en') {
    throw new RuntimeException('Unsupported locale mutation was not rejected safely.');
}

fwrite(
    STDOUT,
    "[PASS] Localization contract: English, Khmer and Chinese catalogs, "
    . "normalization, fallback and unsupported-locale rejection verified.\n"
);
