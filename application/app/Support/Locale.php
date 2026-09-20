<?php

declare(strict_types=1);

namespace Barama\Support;

use Barama\Core\Config;
use Barama\Core\Session;
use InvalidArgumentException;
use RuntimeException;

final class Locale
{
    private const SESSION_KEY = 'app.locale';
    private const SUPPORTED = ['en' => 'English', 'km' => 'ខ្មែរ', 'zh' => '中文'];

    /** @return array<string,string> */
    public static function supported(): array { return self::SUPPORTED; }

    public static function normalize(string $locale): ?string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        if ($locale === '') { return null; }
        $base = explode('-', $locale, 2)[0];
        return isset(self::SUPPORTED[$base]) ? $base : null;
    }

    public static function hasSession(): bool
    {
        return self::normalize((string) Session::get(self::SESSION_KEY, '')) !== null;
    }

    public static function current(): string
    {
        $session = self::normalize((string) Session::get(self::SESSION_KEY, ''));
        if ($session !== null) { return $session; }
        return self::normalize((string) Config::get('app.locale', 'en')) ?? 'en';
    }

    public static function set(string $locale): string
    {
        $normalized = self::normalize($locale);
        if ($normalized === null) { throw new InvalidArgumentException('Unsupported locale.'); }
        Session::put(self::SESSION_KEY, $normalized);
        return $normalized;
    }

    /** @param array<string,string|int|float> $replace */
    public static function translate(string $key, array $replace = []): string
    {
        $catalog = self::catalog(self::current());
        $english = self::catalog('en');
        $text = (string) ($catalog[$key] ?? $english[$key] ?? $key);
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }

    /** @return array<string,string> */
    private static function catalog(string $locale): array
    {
        static $catalogs = [];
        if (isset($catalogs[$locale])) { return $catalogs[$locale]; }
        $path = APP_PATH . '/resources/lang/' . $locale . '.php';
        if (!is_file($path)) { throw new RuntimeException('Locale catalog is missing: ' . $locale); }
        $catalog = require $path;
        if (!is_array($catalog)) { throw new RuntimeException('Locale catalog is invalid: ' . $locale); }
        foreach ($catalog as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new RuntimeException('Locale catalog entries must be strings.');
            }
        }
        return $catalogs[$locale] = $catalog;
    }

    private function __construct() {}
}
