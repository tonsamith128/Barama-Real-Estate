<?php

declare(strict_types=1);

namespace Barama\Support;

final class RichText
{
    /** @var array<string, string> */
    private const TAG_ALIASES = [
        'b' => 'strong',
        'i' => 'em',
    ];

    /** @var array<string, true> */
    private const ALLOWED_TAGS = [
        'p' => true,
        'br' => true,
        'strong' => true,
        'em' => true,
        'u' => true,
        's' => true,
        'ul' => true,
        'ol' => true,
        'li' => true,
        'blockquote' => true,
        'h2' => true,
        'h3' => true,
        'a' => true,
        'hr' => true,
    ];

    public static function sanitizeForStorage(string $value, int $maxTextLength = 10000): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $sanitized = self::sanitizeHtml($value);
        $plainText = trim(html_entity_decode(
            strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $sanitized)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        if (mb_strlen($plainText) > $maxTextLength) {
            throw new \InvalidArgumentException(
                sprintf('description must not exceed %d characters.', $maxTextLength)
            );
        }

        return trim($sanitized) !== '' ? trim($sanitized) : null;
    }

    public static function render(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (!self::containsRichMarkup($value)) {
            return nl2br(e($value));
        }

        return self::sanitizeHtml($value);
    }

    private static function containsRichMarkup(string $value): bool
    {
        return preg_match(
            '/<\/?(?:p|br|strong|b|em|i|u|s|ul|ol|li|blockquote|h2|h3|a|hr)\b/i',
            $value
        ) === 1;
    }

    private static function sanitizeHtml(string $html): string
    {
        $html = preg_replace(
            '#<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?</\1\s*>#is',
            '',
            $html
        ) ?? '';

        $html = strip_tags(
            $html,
            '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h2><h3><a><hr>'
        );

        $html = preg_replace_callback(
            '/<\s*(\/?)\s*([a-z0-9]+)\b([^>]*)>/i',
            static function (array $matches): string {
                $closing = ($matches[1] ?? '') === '/';
                $tag = strtolower((string) ($matches[2] ?? ''));
                $attributes = (string) ($matches[3] ?? '');

                $tag = self::TAG_ALIASES[$tag] ?? $tag;

                if (!isset(self::ALLOWED_TAGS[$tag])) {
                    return '';
                }

                if ($closing) {
                    return in_array($tag, ['br', 'hr'], true)
                        ? ''
                        : '</' . $tag . '>';
                }

                if ($tag !== 'a') {
                    return '<' . $tag . '>';
                }

                $href = self::extractHref($attributes);

                if ($href === null) {
                    return '<a>';
                }

                return '<a href="' . e($href) . '" rel="noopener noreferrer">';
            },
            $html
        ) ?? '';

        return trim($html);
    }

    private static function extractHref(string $attributes): ?string
    {
        $href = null;

        if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $attributes, $match) === 1) {
            $href = (string) ($match[2] ?? '');
        } elseif (preg_match('/\bhref\s*=\s*([^\s>]+)/i', $attributes, $match) === 1) {
            $href = (string) ($match[1] ?? '');
        }

        if ($href === null) {
            return null;
        }

        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $href = preg_replace('/[\x00-\x1F\x7F]+/u', '', $href) ?? '';

        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return $href;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return null;
        }

        return $href;
    }

    private function __construct()
    {
    }
}
