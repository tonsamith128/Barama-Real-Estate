<?php

declare(strict_types=1);

namespace Barama\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class RichText
{
    /** @var array<string, true> */
    private const ALLOWED_TAGS = [
        'p' => true,
        'br' => true,
        'strong' => true,
        'em' => true,
        'u' => true,
        's' => true,
        'h3' => true,
        'h4' => true,
        'blockquote' => true,
        'ul' => true,
        'ol' => true,
        'li' => true,
        'a' => true,
        'hr' => true,
    ];

    /** @var array<string, string> */
    private const TAG_ALIASES = [
        'b' => 'strong',
        'i' => 'em',
        'strike' => 's',
        'del' => 's',
        'div' => 'p',
        'h1' => 'h3',
        'h2' => 'h3',
        'h5' => 'h4',
        'h6' => 'h4',
    ];

    /** @var array<string, true> */
    private const BLOCKED_TAGS = [
        'script' => true,
        'style' => true,
        'iframe' => true,
        'object' => true,
        'embed' => true,
        'svg' => true,
        'math' => true,
        'form' => true,
        'input' => true,
        'button' => true,
        'textarea' => true,
        'select' => true,
        'option' => true,
        'link' => true,
        'meta' => true,
        'base' => true,
        'template' => true,
        'noscript' => true,
        'canvas' => true,
        'video' => true,
        'audio' => true,
        'source' => true,
        'picture' => true,
        'img' => true,
    ];

    /** @var array<string, true> */
    private const ALIGNABLE_TAGS = [
        'p' => true,
        'h3' => true,
        'h4' => true,
        'blockquote' => true,
        'li' => true,
    ];

    public static function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = self::normalizePlainText(trim($value));

        if ($value === '') {
            return null;
        }

        if (!self::looksLikeHtml($value)) {
            return $value;
        }

        if (!class_exists(DOMDocument::class)) {
            return self::fallbackPlainText($value);
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
                . $value
                . '</body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            if ($loaded !== true) {
                return self::fallbackPlainText($value);
            }

            $body = $dom->getElementsByTagName('body')->item(0);

            if (!$body instanceof DOMElement) {
                return self::fallbackPlainText($value);
            }

            self::sanitizeChildren($dom, $body);

            $html = '';

            foreach (iterator_to_array($body->childNodes) as $child) {
                $html .= (string) $dom->saveHTML($child);
            }

            $html = trim($html);

            if ($html === '') {
                return null;
            }

            $textOnly = trim(
                html_entity_decode(
                    strip_tags($html),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )
            );

            if ($textOnly === '' && stripos($html, '<hr') === false) {
                return null;
            }

            if (
                preg_match(
                    '/<(?:p|h3|h4|blockquote|ul|ol|hr)\b/i',
                    $html
                ) !== 1
            ) {
                $html = '<p>' . $html . '</p>';
            }

            return $html;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public static function render(?string $value): string
    {
        $safe = self::sanitize($value);

        if ($safe === null) {
            return '';
        }

        if (self::looksLikeHtml($safe)) {
            return $safe;
        }

        return self::plainTextToHtml($safe);
    }

    private static function sanitizeChildren(DOMDocument $dom, DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $parent->removeChild($child);
                continue;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (isset(self::BLOCKED_TAGS[$tag])) {
                $parent->removeChild($child);
                continue;
            }

            if (isset(self::TAG_ALIASES[$tag])) {
                $child = self::replaceTag($dom, $child, self::TAG_ALIASES[$tag]);
                $tag = strtolower($child->tagName);
            }

            self::sanitizeChildren($dom, $child);

            if (!isset(self::ALLOWED_TAGS[$tag])) {
                self::unwrap($child);
                continue;
            }

            if (!self::sanitizeAttributes($child, $tag)) {
                self::unwrap($child);
            }
        }
    }

    private static function replaceTag(
        DOMDocument $dom,
        DOMElement $element,
        string $newTag
    ): DOMElement {
        $replacement = $dom->createElement($newTag);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $replacement->setAttribute($attribute->name, $attribute->value);
        }

        while ($element->firstChild !== null) {
            $replacement->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($replacement, $element);

        return $replacement;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): bool
    {
        $href = $tag === 'a' ? trim($element->getAttribute('href')) : '';
        $title = $tag === 'a' ? trim($element->getAttribute('title')) : '';
        $alignment = isset(self::ALIGNABLE_TAGS[$tag])
            ? self::extractAlignment($element)
            : null;

        while ($element->attributes->length > 0) {
            $attribute = $element->attributes->item(0);

            if ($attribute === null) {
                break;
            }

            $element->removeAttributeNode($attribute);
        }

        if ($alignment !== null) {
            $element->setAttribute('class', 'rt-align-' . $alignment);
        }

        if ($tag !== 'a') {
            return true;
        }

        $safeHref = self::sanitizeHref($href);

        if ($safeHref === null) {
            return false;
        }

        $element->setAttribute('href', $safeHref);

        if ($title !== '') {
            $element->setAttribute(
                'title',
                mb_substr(self::normalizePlainText($title), 0, 200)
            );
        }

        if (preg_match('#^https?://#i', $safeHref) === 1) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        return true;
    }

    private static function extractAlignment(DOMElement $element): ?string
    {
        $align = strtolower(trim($element->getAttribute('align')));

        if (in_array($align, ['left', 'center', 'right', 'justify'], true)) {
            return $align;
        }

        $style = $element->getAttribute('style');

        if (
            preg_match(
                '/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i',
                $style,
                $matches
            ) === 1
        ) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private static function sanitizeHref(string $href): ?string
    {
        $href = trim(self::normalizePlainText($href));

        if ($href === '') {
            return null;
        }

        if (preg_match('/^#[A-Za-z0-9._:-]+$/', $href) === 1) {
            return $href;
        }

        if (preg_match('#^/(?!/)#', $href) === 1) {
            return $href;
        }

        if (str_starts_with(strtolower($href), 'mailto:')) {
            $address = trim(explode('?', substr($href, 7), 2)[0]);

            return filter_var($address, FILTER_VALIDATE_EMAIL) !== false
                ? $href
                : null;
        }

        if (str_starts_with(strtolower($href), 'tel:')) {
            return preg_match('/^tel:[0-9+().\-\s]+$/i', $href) === 1
                ? $href
                : null;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return filter_var($href, FILTER_VALIDATE_URL) !== false
            ? $href
            : null;
    }

    private static function plainTextToHtml(string $value): string
    {
        $value = self::normalizePlainText($value);
        $paragraphs = preg_split('/\n{2,}/u', $value) ?: [];
        $html = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $html .= '<p>'
                . nl2br(
                    htmlspecialchars(
                        $paragraph,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ),
                    false
                )
                . '</p>';
        }

        return $html;
    }

    private static function fallbackPlainText(string $value): string
    {
        $value = preg_replace(
            '#<(?:br\s*/?|/p|/div|/li|/h[1-6]|/blockquote)>#i',
            "\n",
            $value
        ) ?? $value;

        return self::normalizePlainText(
            html_entity_decode(
                strip_tags($value),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );
    }

    private static function normalizePlainText(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $value);

        return preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $value
        ) ?? $value;
    }

    private static function looksLikeHtml(string $value): bool
    {
        return preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $value) === 1;
    }

    private function __construct()
    {
    }
}
