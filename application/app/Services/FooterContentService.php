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
        (new FooterContentRepository())->save($new);
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

        return $config;
    }
}
