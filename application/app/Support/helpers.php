<?php

declare(strict_types=1);

use Barama\Core\Config;
use Barama\Core\Csrf;
use Barama\Core\RuntimeEnvironment;
use Barama\Services\AuthService;
use Barama\Support\Locale;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        if (
            class_exists(RuntimeEnvironment::class)
            && RuntimeEnvironment::has($key)
        ) {
            return RuntimeEnvironment::get($key, $default);
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return APP_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $path = '/' . ltrim($path, '/');

        if ($path === '//') {
            $path = '/';
        }

        $routingMode = (string) config('app.routing_mode', 'clean');

        // Always enter the PHP front controller in compatibility mode, even
        // for Home. This avoids relying on DirectoryIndex or an aaPanel
        // placeholder index.html when rewrite rules are unavailable.
        if ($routingMode === 'query') {
            $pathOnly = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
            $query = (string) (parse_url($path, PHP_URL_QUERY) ?? '');
            $route = rawurlencode('/' . ltrim($pathOnly, '/'));
            $separator = $query !== '' ? '&' . $query : '';
            $relative = '/index.php?_route=' . $route . $separator;

            return $base !== '' ? $base . $relative : $relative;
        }

        if ($base === '') {
            return $path;
        }

        return $base . $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        $relative = '/assets/' . ltrim($path, '/');

        return $base !== '' ? $base . $relative : $relative;
    }
}

if (!function_exists('is_https')) {
    function is_https(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        return (!empty($https) && strtolower((string) $https) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('auth')) {
    function auth(): AuthService
    {
        static $service = null;

        if (!$service instanceof AuthService) {
            $service = new AuthService();
        }

        return $service;
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return auth()->user();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return auth()->check();
    }
}

if (!function_exists('auth_can')) {
    function auth_can(string $permission): bool
    {
        return auth()->can($permission);
    }
}


if (!function_exists('app_locale')) {
    function app_locale(): string { return Locale::current(); }
}
if (!function_exists('app_locales')) {
    /** @return array<string,string> */
    function app_locales(): array { return Locale::supported(); }
}
if (!function_exists('t')) {
    /** @param array<string,string|int|float> $replace */
    function t(string $key, array $replace = []): string { return Locale::translate($key, $replace); }
}

if (!function_exists('auth_workspace_can')) {
    function auth_workspace_can(string $workspace): bool
    {
        return auth()->canAccessWorkspace($workspace);
    }
}

if (!function_exists('app_icon')) {
    function app_icon(string $name, string $class = ''): string
    {
        $paths = [
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.93 4.93l2.12 2.12M16.95 16.95l2.12 2.12M2 12h3M19 12h3M4.93 19.07l2.12-2.12M16.95 7.05l2.12-2.12"/>',
            'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10M8 7h4M8 11h4M8 15h4M8 19h4M3 21h18"/>',
            'layers' => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>',
            'home' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10M9 21v-6h6v6"/>',
            'tag' => '<path d="M20 13 11 22 2 13V4h9l9 9Z"/><circle cx="7" cy="9" r="1.5"/>',
            'message' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z"/>',
            'pipeline' => '<circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="12" cy="18" r="3"/><path d="M8.5 7.5 10.8 15M15.5 7.5 13.2 15"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
            'map' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
            'compare' => '<path d="M4 7h11M11 3l4 4-4 4M20 17H9M13 13l-4 4 4 4"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M17 6l2 2M14 9l2 2"/>',
            'server' => '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/>',
            'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
            'language' => '<path d="M4 5h8M8 3v2M5 9c2-1 4-3 5-6M6 6c1 2 3 4 6 5M14 19l3-8 3 8M15 16h4"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
            'alert' => '<path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 9v4M12 17h.01"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92Z"/>',
            'video' => '<rect x="3" y="5" width="14" height="14" rx="2"/><path d="m17 10 4-2v8l-4-2v-4Z"/><path d="m9 9 4 3-4 3V9Z"/>',
            'youtube' => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9 5 3-5 3V9Z"/>',
            'gallery' => '<rect x="3" y="4" width="14" height="14" rx="2"/><path d="m6 15 3-3 2 2 2-2 4 4"/><path d="M7.5 8.5h.01"/><path d="M7 21h11a3 3 0 0 0 3-3V9"/>',
            'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        ];

        $path = $paths[$name] ?? $paths['grid'];
        $classAttribute = $class !== '' ? ' class="' . e($class) . '"' : '';

        return '<svg' . $classAttribute
            . ' width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true">' . $path . '</svg>';
    }
}

/* BARAMA_SINGLE_DETAIL_MEDIA_HELPERS_V100 */
if (!function_exists('barama_youtube_embed_url')) {
    function barama_youtube_embed_url(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $videoId = null;

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1) {
            $videoId = $value;
        } else {
            $parts = parse_url($value);

            if (!is_array($parts)) {
                return null;
            }

            $host = strtolower((string) ($parts['host'] ?? ''));
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            $path = trim((string) ($parts['path'] ?? ''), '/');

            if ($host === 'youtu.be') {
                $videoId = explode('/', $path)[0] ?? null;
            } elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
                if ($path === 'watch') {
                    parse_str((string) ($parts['query'] ?? ''), $query);
                    $videoId = isset($query['v']) ? (string) $query['v'] : null;
                } elseif (
                    preg_match('#^(?:embed|shorts)/([A-Za-z0-9_-]{11})#', $path, $matches) === 1
                ) {
                    $videoId = $matches[1];
                }
            }
        }

        if (!is_string($videoId) || preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) !== 1) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/' . $videoId
            . '?rel=0&modestbranding=1';
    }
}

if (!function_exists('barama_detail_media_kind')) {
    /** @param array<string,mixed> $media */
    function barama_detail_media_kind(array $media): string
    {
        $mime = strtolower(trim((string) ($media['mime_type'] ?? '')));

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        if (
            $mime === 'text/x-youtube'
            && barama_youtube_embed_url((string) ($media['path'] ?? '')) !== null
        ) {
            return 'youtube';
        }

        return 'unsupported';
    }
}

if (!function_exists('barama_detail_media_stage')) {
    /**
     * @param array<int,array<string,mixed>> $gallery
     */
    function barama_detail_media_stage(
        array $gallery,
        string $title,
        string $emptyIcon = 'image'
    ): string {
        $items = [];

        foreach ($gallery as $media) {
            if (!is_array($media)) {
                continue;
            }

            $kind = barama_detail_media_kind($media);

            if ($kind === 'unsupported') {
                continue;
            }

            $media['barama_kind'] = $kind;
            $items[] = $media;
        }

        if ($items === []) {
            return '<div class="detail-media-stage detail-media-stage--empty">'
                . '<div class="detail-media-stage__empty">'
                . app_icon($emptyIcon)
                . '<strong>Media coming soon</strong>'
                . '<span>Photos, uploaded video, and YouTube media can appear here.</span>'
                . '</div></div>';
        }

        $photoCount = 0;
        $videoCount = 0;
        $youtubeCount = 0;

        foreach ($items as $item) {
            if ($item['barama_kind'] === 'image') {
                $photoCount++;
            } elseif ($item['barama_kind'] === 'video') {
                $videoCount++;
            } elseif ($item['barama_kind'] === 'youtube') {
                $youtubeCount++;
            }
        }

        $html = '<div class="detail-media-stage" data-detail-media-stage>';
        $html .= '<div class="detail-media-stage__viewer">';

        foreach ($items as $index => $media) {
            $kind = (string) $media['barama_kind'];
            $active = $index === 0 ? ' is-active' : '';
            $alt = (string) ($media['alt_text'] ?: $title);
            $html .= '<div class="detail-media-slide' . $active . '"'
                . ' data-detail-media-slide="' . $index . '"'
                . ' data-detail-media-kind="' . e($kind) . '"'
                . ($index === 0 ? '' : ' hidden')
                . '>';

            if ($kind === 'image') {
                $src = url('/media/' . rawurlencode((string) $media['uuid']));
                $html .= '<button class="detail-media-slide__image" type="button"'
                    . ' data-gallery-image'
                    . ' data-gallery-src="' . e($src) . '"'
                    . ' data-gallery-alt="' . e($alt) . '">'
                    . '<img src="' . e($src) . '" alt="' . e($alt) . '"'
                    . ($index === 0 ? ' fetchpriority="high"' : ' loading="lazy"')
                    . '>'
                    . '<span class="detail-media-slide__zoom">' . app_icon('image') . '<span>View photo</span></span>'
                    . '</button>';
            } elseif ($kind === 'video') {
                $src = url('/media/' . rawurlencode((string) $media['uuid']));
                $html .= '<video controls playsinline preload="metadata" aria-label="' . e($alt) . '">'
                    . '<source src="' . e($src) . '" type="' . e((string) $media['mime_type']) . '">'
                    . 'Your browser does not support this video.'
                    . '</video>';
            } else {
                $embed = barama_youtube_embed_url((string) ($media['path'] ?? ''));
                if ($embed !== null) {
                    $html .= '<iframe src="' . e($embed) . '"'
                        . ' title="' . e($alt) . '"'
                        . ' loading="lazy"'
                        . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
                        . ' referrerpolicy="strict-origin-when-cross-origin"'
                        . ' allowfullscreen></iframe>';
                }
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        $html .= '<div class="detail-media-stage__toolbar">';
        $html .= '<div class="detail-media-stage__counts">';
        if ($photoCount > 0) {
            $html .= '<span>' . app_icon('image') . e((string) $photoCount) . ' photo' . ($photoCount === 1 ? '' : 's') . '</span>';
        }
        if ($videoCount > 0) {
            $html .= '<span>' . app_icon('video') . e((string) $videoCount) . ' video' . ($videoCount === 1 ? '' : 's') . '</span>';
        }
        if ($youtubeCount > 0) {
            $html .= '<span>' . app_icon('youtube') . e((string) $youtubeCount) . ' YouTube</span>';
        }
        $html .= '</div>';
        $html .= '</div>';

        if (count($items) > 1) {
            $html .= '<div class="detail-media-thumbs" role="tablist" aria-label="Media gallery">';

            foreach ($items as $index => $media) {
                $kind = (string) $media['barama_kind'];
                $active = $index === 0 ? ' is-active' : '';
                $alt = (string) ($media['alt_text'] ?: $title);
                $label = match ($kind) {
                    'video' => 'Video ' . ($index + 1),
                    'youtube' => 'YouTube ' . ($index + 1),
                    default => 'Photo ' . ($index + 1),
                };

                $html .= '<button class="detail-media-thumb' . $active . '"'
                    . ' type="button" role="tab"'
                    . ' aria-selected="' . ($index === 0 ? 'true' : 'false') . '"'
                    . ' aria-label="' . e($label) . '"'
                    . ' data-detail-media-thumb="' . $index . '">';

                if ($kind === 'image') {
                    $src = url('/media/' . rawurlencode((string) $media['uuid']));
                    $html .= '<img src="' . e($src) . '" alt="" loading="lazy">';
                } else {
                    $html .= '<span class="detail-media-thumb__icon">'
                        . app_icon($kind === 'youtube' ? 'youtube' : 'video')
                        . '</span>';
                    $html .= '<small>' . e($kind === 'youtube' ? 'YouTube' : 'Video') . '</small>';
                }

                $html .= '</button>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}

/* BARAMA_DETAIL_PRICE_FORMAT_V102 */
if (!function_exists('barama_detail_price')) {
    function barama_detail_price(
        string $currency,
        float|int|string $amount,
        ?string $period = null
    ): string {
        $code = strtoupper(trim($currency));
        $symbols = [
            'USD' => '$',
            'KHR' => '៛',
            'EUR' => '€',
            'GBP' => '£',
            'THB' => '฿',
            'JPY' => '¥',
            'CNY' => '¥',
            'SGD' => 'S$',
            'AUD' => 'A$',
            'CAD' => 'C$',
        ];

        $symbol = $symbols[$code] ?? ($code !== '' ? $code . ' ' : '');
        $formatted = $symbol . number_format((float) $amount, 0);

        $period = strtolower(trim((string) $period));
        if ($period !== '') {
            $periodMap = [
                'monthly' => 'month',
                'month' => 'month',
                'per_month' => 'month',
                'weekly' => 'week',
                'week' => 'week',
                'per_week' => 'week',
                'daily' => 'day',
                'day' => 'day',
                'per_day' => 'day',
                'yearly' => 'year',
                'annual' => 'year',
                'year' => 'year',
                'per_year' => 'year',
            ];

            $formatted .= '/' . ($periodMap[$period] ?? str_replace('_', '-', $period));
        }

        return $formatted;
    }
}
