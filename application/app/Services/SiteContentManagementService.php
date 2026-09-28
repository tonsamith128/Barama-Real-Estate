<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\SiteContentRepository;
use DomainException;
use InvalidArgumentException;

final class SiteContentManagementService
{
    /** @param array<string,mixed> $input */
    public function update(array $input): void
    {
        $user = $this->administrator();
        $config = $this->validated($input);

        (new SiteContentRepository())->save($config);

        (new SecurityAuditService())->record(
            'admin.site_content_updated',
            (int) $user['id'],
            null,
            [
                'navigation_items' => count(array_filter(
                    $config['navigation'],
                    static fn (array $item): bool => !empty($item['enabled'])
                )),
                'homepage_sections' => count(array_filter(
                    $config['homepage']['sections'],
                    static fn (array $item): bool => !empty($item['enabled'])
                )),
            ]
        );
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if (
            $user === null
            || !$auth->hasRole('administrator')
            || !$auth->can('page.manage')
        ) {
            throw new DomainException('Administrator page management permission is required.');
        }

        return $user;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function validated(array $input): array
    {
        $eyebrow = $this->text($input['hero_eyebrow'] ?? '', 80, 'Homepage eyebrow');
        $title = $this->text($input['hero_title'] ?? '', 140, 'Homepage title');
        $description = $this->text(
            $input['hero_description'] ?? '',
            500,
            'Homepage description'
        );

        if ($title === '' || $description === '') {
            throw new InvalidArgumentException(
                'Homepage title and description are required.'
            );
        }

        $knownSections = ['listings', 'projects', 'companies'];
        $orderInput = is_array($input['section_order'] ?? null)
            ? $input['section_order']
            : [];
        $orderMap = [];

        foreach ($knownSections as $section) {
            $position = filter_var(
                $orderInput[$section] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 3]]
            );

            $orderMap[$section] = $position === false ? 99 : (int) $position;
        }

        if (
            in_array(99, $orderMap, true)
            || count(array_unique(array_values($orderMap))) !== count($knownSections)
        ) {
            throw new InvalidArgumentException(
                'Choose a unique homepage position from 1 to 3 for every section.'
            );
        }

        uasort(
            $orderMap,
            static fn (int $left, int $right): int => $left <=> $right
        );

        $sectionsInput = is_array($input['sections'] ?? null)
            ? $input['sections']
            : [];
        $sections = [];

        foreach ($knownSections as $section) {
            $row = is_array($sectionsInput[$section] ?? null)
                ? $sectionsInput[$section]
                : [];

            $sections[$section] = [
                'enabled' => !empty($row['enabled']),
                'eyebrow' => $this->text(
                    $row['eyebrow'] ?? '',
                    80,
                    ucfirst($section) . ' eyebrow'
                ),
                'title' => $this->text(
                    $row['title'] ?? '',
                    140,
                    ucfirst($section) . ' title'
                ),
                'link_label' => $this->text(
                    $row['link_label'] ?? '',
                    80,
                    ucfirst($section) . ' link label'
                ),
            ];

            if (
                $sections[$section]['enabled']
                && (
                    $sections[$section]['title'] === ''
                    || $sections[$section]['link_label'] === ''
                )
            ) {
                throw new InvalidArgumentException(
                    ucfirst($section)
                    . ' title and link label are required when the section is enabled.'
                );
            }
        }

        $labels = is_array($input['nav_label'] ?? null)
            ? array_values($input['nav_label'])
            : [];
        $paths = is_array($input['nav_path'] ?? null)
            ? array_values($input['nav_path'])
            : [];
        $enabled = is_array($input['nav_enabled'] ?? null)
            ? $input['nav_enabled']
            : [];

        $navigation = [];

        for ($index = 0; $index < 6; $index++) {
            $label = $this->text(
                $labels[$index] ?? '',
                60,
                'Navigation label'
            );
            $path = trim((string) ($paths[$index] ?? ''));
            $isEnabled = isset($enabled[$index])
                && in_array((string) $enabled[$index], ['1', 'true', 'on'], true);

            if ($label === '' && $path === '' && !$isEnabled) {
                continue;
            }

            if ($label === '' || $path === '') {
                throw new InvalidArgumentException(
                    'Every configured navigation item requires both a label and an internal path.'
                );
            }

            $path = $this->internalPath($path);

            $navigation[] = [
                'label' => $label,
                'path' => $path,
                'active_area' => $this->activeArea($path),
                'enabled' => $isEnabled,
            ];
        }

        if (
            count(array_filter(
                $navigation,
                static fn (array $item): bool => !empty($item['enabled'])
            )) < 1
        ) {
            throw new InvalidArgumentException(
                'Keep at least one primary navigation item enabled.'
            );
        }

        return [
            'homepage' => [
                'hero_eyebrow' => $eyebrow,
                'hero_title' => $title,
                'hero_description' => $description,
                'section_order' => array_keys($orderMap),
                'sections' => $sections,
            ],
            'navigation' => $navigation,
        ];
    }

    private function text(mixed $value, int $max, string $label): string
    {
        $value = trim((string) $value);

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(
                $label . ' cannot exceed ' . $max . ' characters.'
            );
        }

        return $value;
    }

    private function internalPath(string $path): string
    {
        if (
            $path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new InvalidArgumentException(
                'Navigation links must use safe same-site paths beginning with /.'
            );
        }

        $parts = parse_url($path);

        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'Navigation links must stay inside this Barama installation.'
            );
        }

        return mb_substr($path, 0, 255);
    }

    private function activeArea(string $path): string
    {
        $route = (string) parse_url($path, PHP_URL_PATH);

        return match (true) {
            $route === '/' => 'home',
            str_starts_with($route, '/properties') => 'properties',
            str_starts_with($route, '/projects') => 'projects',
            str_starts_with($route, '/company') => 'company',
            default => '',
        };
    }
}
