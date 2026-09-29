<?php

declare(strict_types=1);

namespace Barama\Services;

final class PageContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'h2', 'h3', 'h4',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'blockquote',
        'a', 'img', 'hr', 'pre', 'code', 'mark', 'sub', 'sup',
    ];

    public static function sanitize(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        if (!str_contains($content, '<')) {
            return mb_substr($content, 0, 100000);
        }

        $content = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|option)[^>]*>.*?</\\1>#is', '', $content) ?? '';
        $content = preg_replace('/<!--.*?-->/s', '', $content) ?? '';

        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $content = strip_tags($content, $allowed);

        $content = preg_replace_callback(
            '/<\\/?([a-z0-9]+)([^>]*)>/i',
            static function (array $match): string {
                $tag = strtolower((string) ($match[1] ?? ''));
                $raw = (string) ($match[0] ?? '');
                $attrs = (string) ($match[2] ?? '');

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    return '';
                }

                if (str_starts_with($raw, '</')) {
                    return in_array($tag, ['br', 'hr', 'img'], true) ? '' : '</' . $tag . '>';
                }

                if (in_array($tag, ['br', 'hr'], true)) {
                    return '<' . $tag . '>';
                }

                if ($tag === 'a') {
                    $href = self::attribute($attrs, 'href');

                    if ($href === '' || !self::safeUrl($href, true)) {
                        return '<a>';
                    }

                    return '<a href="' . self::escapeAttribute($href) . '" rel="noopener noreferrer">';
                }

                if ($tag === 'img') {
                    $src = self::attribute($attrs, 'src');
                    $alt = self::attribute($attrs, 'alt');

                    if ($src === '' || !self::safeUrl($src, false)) {
                        return '';
                    }

                    return '<img src="' . self::escapeAttribute($src) . '" alt="'
                        . self::escapeAttribute(mb_substr($alt, 0, 255))
                        . '" loading="lazy">';
                }

                if (in_array($tag, ['p', 'h2', 'h3', 'h4', 'blockquote'], true)) {
                    $align = strtolower(self::attribute($attrs, 'data-align'));

                    if (in_array($align, ['left', 'center', 'right', 'justify'], true)) {
                        return '<' . $tag . ' data-align="' . $align . '">';
                    }
                }

                return '<' . $tag . '>';
            },
            $content
        ) ?? '';

        return mb_substr(trim($content), 0, 100000);
    }

    public static function toHtml(string $stored): string
    {
        $stored = self::sanitize($stored);

        if ($stored === '') {
            return '';
        }

        if (preg_match('/<(?:p|h2|h3|h4|ul|ol|blockquote|pre|img|hr)\\b/i', $stored) === 1) {
            return $stored;
        }

        $parts = preg_split('/\\R{2,}/u', $stored) ?: [];
        $html = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $html[] = '<p>' . nl2br(htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
        }

        return implode("\n", $html);
    }

    private static function attribute(string $attrs, string $name): string
    {
        $pattern = '/\\b' . preg_quote($name, '/') . '\\s*=\\s*(["\\\'])(.*?)\\1/is';

        if (preg_match($pattern, $attrs, $match) === 1) {
            return html_entity_decode(trim((string) ($match[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    private static function safeUrl(string $url, bool $allowMail): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\\x00-\\x1F\\x7F]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true)) {
            return true;
        }

        return $allowMail && in_array($scheme, ['mailto', 'tel'], true);
    }

    private static function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
