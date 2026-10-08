<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\FooterContentRepository;
use InvalidArgumentException;
use DomainException;

final class FooterContentService
{
    /** @param array<string,mixed> $input */
    public function update(array $input): void
    {
        $auth = new AuthService();
        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can('page.manage')) {
            throw new DomainException('Administrator page-management permission is required.');
        }

        $new = self::validate($input);
        $repository = new FooterContentRepository();
        if (!array_key_exists('footer_links_submitted', $input)) {
            // Older open forms must retain the stored links during upgrades.
            $new['links'] = $repository->get()['links'];
        }
        $repository->save($new);
        (new SecurityAuditService())->record(
            'admin.footer_content_updated',
            (int) (auth_user()['id'] ?? 0),
            null,
            ['social_count' => count(array_filter($new['social']))]
        );
    }

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    public static function validate(array $input): array
    {
        $defaults = FooterContentRepository::defaults();
        $maxima = [
            'brand_heading' => 60,
            'brand_description' => 400,
            'marketplace_heading' => 60,
            'compliance_heading' => 60,
            'help_heading' => 60,
            'policy_label' => 60,
            'privacy_label' => 60,
            'terms_label' => 60,
            'copyright' => 180,
            'social_heading' => 60,
        ];
        $config = [];
        foreach ($maxima as $key => $max) {
            $raw = $input[$key] ?? $defaults[$key];
            if (!is_string($raw)) {
                throw new InvalidArgumentException('Footer ' . $key . ' is invalid.');
            }
            $value = trim($raw);
            if ($value === '' || mb_strlen($value) > $max) {
                throw new InvalidArgumentException('Footer ' . $key . ' must be 1–' . $max . ' characters.');
            }
            // Newline-only text is not useful in compact footer fields.
            if ($key !== 'brand_description' && preg_match('/[\r\n\x00-\x08]/', $value) === 1) {
                throw new InvalidArgumentException('Footer text must be a single line.');
            }
            $config[$key] = $value;
        }

        $social = [];
        foreach ($defaults['social'] as $network => $_) {
            $key = 'social_' . $network;
            $raw = $input[$key] ?? '';
            if (!is_string($raw)) {
                throw new InvalidArgumentException('Social URL for ' . $network . ' is invalid.');
            }
            // Reject CRLF, whitespace and invisible controls in the *raw*
            // URL before trim() could silently sanitize a malicious value.
            if (preg_match('/[\x00-\x20\x7f]/', $raw) === 1) {
                throw new InvalidArgumentException('Social URL for ' . $network . ' contains invalid whitespace.');
            }
            $value = trim($raw);
            if ($value !== '') {
                if (strlen($value) > 500 || filter_var($value, FILTER_VALIDATE_URL) === false) {
                    throw new InvalidArgumentException('Social URL for ' . $network . ' must be a valid HTTPS address.');
                }
                $parts = parse_url($value);
                if (!is_array($parts)
                    || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                    || !is_string($parts['host'] ?? null)
                    || isset($parts['user']) || isset($parts['pass'])
                    || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
                    throw new InvalidArgumentException('Social URL for ' . $network . ' must use HTTPS without credentials.');
                }
            }
            $social[$network] = $value;
        }
        $config['social'] = $social;

        if (!array_key_exists('footer_links_submitted', $input)) {
            // The update method preserves stored links for legacy browsers;
            // pure validation remains deterministic and DB-independent.
            $config['links'] = FooterContentRepository::defaultLinks($config);
            return $config;
        }

        $submitted = $input['footer_links'] ?? [];
        if (!is_array($submitted)) {
            throw new InvalidArgumentException('Footer link list is invalid.');
        }
        foreach (array_keys($submitted) as $key) {
            if (!in_array($key, ['brand', 'marketplace', 'compliance', 'help'], true)) {
                throw new InvalidArgumentException('Unknown footer column.');
            }
        }

        $links = [];
        foreach (['brand', 'marketplace', 'compliance', 'help'] as $column) {
            $entries = $submitted[$column] ?? [];
            if (!is_array($entries) || count($entries) > 15) {
                throw new InvalidArgumentException(
                    'Footer ' . $column . ' supports a maximum of 15 menu items.'
                );
            }
            $links[$column] = [];
            foreach ($entries as $entry) {
                if (!is_array($entry) || !is_string($entry['label'] ?? null)
                    || !is_string($entry['url'] ?? null)) {
                    throw new InvalidArgumentException('Footer menu item must contain a label and URL.');
                }
                $label = trim($entry['label']);
                $href = $entry['url'];
                if ($label === '' || mb_strlen($label) > 80
                    || preg_match('/[\x00-\x1f\x7f]/', $label) === 1) {
                    throw new InvalidArgumentException('Footer menu label must be 1–80 characters.');
                }
                if (!self::validLink($href)) {
                    throw new InvalidArgumentException(
                        'Footer link must be a safe site path (starting /) or an HTTPS URL.'
                    );
                }
                $links[$column][] = ['label' => $label, 'url' => $href];
            }
        }
        $config['links'] = $links;
        return $config;
    }

    public static function validLink(string $url): bool
    {
        if ($url === '' || strlen($url) > 500 || str_contains($url, '\\')
            || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return false;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            // Preserve canonical Policy Center query fragments (?=slug).
            // Relative URLs must stay rooted at the installation.
            return !str_contains($url, '..') && !str_contains($url, '#');
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && is_string($parts['host'] ?? null)
            && !isset($parts['user']) && !isset($parts['pass']);
    }
    }
}
