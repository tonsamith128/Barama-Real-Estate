<?php

declare(strict_types=1);

namespace App\Services\Policy;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

final class LegalHtmlSanitizer
{
    private const TAGS = [
        'h1','h2','h3','h4','p','div','br','hr','strong','b','em',
        'i','u','ul','ol','li','blockquote','a','table',
        'thead','tbody','tr','td','th',
    ];

    public function clean(string $source): string
    {
        if (strlen($source) > 100000) {
            throw new RuntimeException('Legal content must be under 100 KB.');
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $html = '<div data-barama-root="1">'.$source.'</div>';
            if (!$dom->loadHTML('<?xml encoding="UTF-8"?>'.$html,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET)) {
                throw new RuntimeException('Unable to parse legal content.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root instanceof DOMElement || $root->getAttribute('data-barama-root') !== '1') {
            throw new RuntimeException('Invalid legal document.');
        }
        $this->sanitizeChildren($root);
        $result = '';
        foreach ($root->childNodes as $node) {
            $result .= $dom->saveHTML($node);
        }
        return trim($result);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, self::TAGS, true)) {
                    $parent->removeChild($node);
                    continue;
                }
                $href = $tag === 'a' ? trim($node->getAttribute('href')) : '';
                while ($node->attributes->length > 0) {
                    $attribute = $node->attributes->item(0);
                    if ($attribute === null) {
                        break;
                    }
                    $node->removeAttributeNode($attribute);
                }
                if ($tag === 'a' &&
                    preg_match('~^https?://[a-z0-9]~i', $href) === 1 &&
                    filter_var($href, FILTER_VALIDATE_URL) !== false) {
                    $node->setAttribute('href', $href);
                    $node->setAttribute('rel', 'noopener noreferrer nofollow');
                }
                $this->sanitizeChildren($node);
            } elseif ($node->nodeType !== XML_TEXT_NODE) {
                $parent->removeChild($node);
            }
        }
    }
}
