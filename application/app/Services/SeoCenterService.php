<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\WorkspaceReadRepository;

final class SeoCenterService
{
    private const PER_PAGE = 10;
    private const EXPORT_LIMIT = 500;

    /**
     * @param array{status:string,type:string} $filters
     * @return array<string,mixed>
     */
    public function dashboard(
        string $search,
        int $page,
        array $filters,
        string $sort
    ): array {
        $repository = new WorkspaceReadRepository();

        $summary = $repository->adminMarketplaceSummary('seo');
        $records = $repository->adminSection(
            'seo',
            mb_substr(trim($search), 0, 80),
            max(1, $page),
            self::PER_PAGE,
            [
                'status' => $this->allowed(
                    $filters['status'] ?? '',
                    ['', 'attention', 'ready']
                ),
                'type' => $this->allowed(
                    $filters['type'] ?? '',
                    ['', 'listing', 'project', 'company', 'page']
                ),
                'location' => '',
            ],
            $this->allowed(
                $sort,
                [
                    'updated_desc',
                    'updated_asc',
                    'name_asc',
                    'name_desc',
                ],
                'updated_desc'
            )
        );

        $records['rows'] = array_map(
            fn (array $row): array => $this->decorate($row),
            (array) ($records['rows'] ?? [])
        );

        return [
            'summary' => $summary,
            'health' => $this->health($summary),
            'records' => $records,
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
                'type' => (string) ($filters['type'] ?? ''),
                'sort' => $sort,
                'q' => $search,
            ],
            'per_page' => self::PER_PAGE,
            'discovery' => [
                [
                    'title' => 'Robots policy',
                    'detail' => 'Inspect crawler access rules served by Barama.',
                    'path' => '/robots.txt',
                    'kind' => 'robots',
                ],
                [
                    'title' => 'XML Sitemap',
                    'detail' => 'Inspect the live discovery feed for public URLs.',
                    'path' => '/sitemap.xml',
                    'kind' => 'sitemap',
                ],
            ],
        ];
    }

    /**
     * @param array{status:string,type:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(
        string $search,
        array $filters,
        string $sort
    ): array {
        $repository = new WorkspaceReadRepository();
        $rows = [];
        $page = 1;

        do {
            $chunk = $repository->adminSection(
                'seo',
                mb_substr(trim($search), 0, 80),
                $page,
                50,
                [
                    'status' => $this->allowed(
                        $filters['status'] ?? '',
                        ['', 'attention', 'ready']
                    ),
                    'type' => $this->allowed(
                        $filters['type'] ?? '',
                        ['', 'listing', 'project', 'company', 'page']
                    ),
                    'location' => '',
                ],
                $this->allowed(
                    $sort,
                    [
                        'updated_desc',
                        'updated_asc',
                        'name_asc',
                        'name_desc',
                    ],
                    'updated_desc'
                )
            );

            foreach ((array) ($chunk['rows'] ?? []) as $row) {
                $rows[] = $this->decorate($row);

                if (count($rows) >= self::EXPORT_LIMIT) {
                    break 2;
                }
            }

            $page++;
            $pages = max(1, (int) ($chunk['pages'] ?? 1));
        } while ($page <= $pages);

        return $rows;
    }

    /** @return array<string,mixed> */
    private function decorate(array $row): array
    {
        $index = strtolower((string) ($row['index_state'] ?? ''));
        $description = strtolower(
            (string) ($row['description_state'] ?? '')
        );
        $image = strtolower((string) ($row['image_state'] ?? ''));
        $alt = strtolower((string) ($row['alt_state'] ?? ''));

        $score = 0;

        if ($index === 'indexable') {
            $score += 30;
        }

        if ($description === 'ready') {
            $score += 25;
        }

        if (in_array($image, ['ready', 'n/a'], true)) {
            $score += 25;
        }

        if (in_array($alt, ['ready', 'n/a'], true)) {
            $score += 20;
        }

        $issues = [];

        if ($index !== 'indexable') {
            $issues[] = [
                'key' => 'index',
                'label' => 'Not indexable',
                'detail' => 'Review robots/indexing policy for this public record.',
                'severity' => 'high',
            ];
        }

        if ($description !== 'ready') {
            $issues[] = [
                'key' => 'description',
                'label' => 'Description missing',
                'detail' => 'Add a useful source description for search snippets.',
                'severity' => 'high',
            ];
        }

        if ($image === 'missing') {
            $issues[] = [
                'key' => 'image',
                'label' => 'Social image missing',
                'detail' => 'Attach an appropriate public image to improve sharing coverage.',
                'severity' => 'medium',
            ];
        }

        if ($alt === 'missing') {
            $issues[] = [
                'key' => 'alt',
                'label' => 'Image alt text missing',
                'detail' => 'Complete alt text on attached public images.',
                'severity' => 'medium',
            ];
        }

        $type = strtolower((string) ($row['entity_type'] ?? ''));
        $id = max(0, (int) ($row['id'] ?? 0));

        $editUrl = match ($type) {
            'listing' => '/dashboard/manage/listing/' . $id,
            'project' => '/dashboard/manage/project/' . $id . '?edit=1',
            'company' => '/dashboard/manage/company/' . $id . '?edit=1',
            'page' => '/dashboard/pages/' . $id . '/edit',
            default => '/dashboard/seo',
        };

        $label = match (true) {
            $score >= 95 => 'Excellent',
            $score >= 80 => 'Strong',
            $score >= 60 => 'Needs work',
            default => 'Priority',
        };

        return array_merge($row, [
            'seo_score' => $score,
            'seo_label' => $label,
            'issues' => $issues,
            'issue_count' => count($issues),
            'edit_url' => $editUrl,
            'public_path' => (string) ($row['path'] ?? ''),
        ]);
    }

    /** @param array<string,int|numeric-string> $summary */
    private function health(array $summary): array
    {
        $total = max(0, (int) ($summary['total'] ?? 0));
        $indexable = max(0, (int) ($summary['indexable'] ?? 0));
        $missingDescription = max(
            0,
            (int) ($summary['missing_description'] ?? 0)
        );
        $missingImage = max(
            0,
            (int) ($summary['missing_social_image'] ?? 0)
        );
        $missingAlt = max(
            0,
            (int) ($summary['missing_alt_text'] ?? 0)
        );

        if ($total === 0) {
            return [
                'score' => 100,
                'indexability' => 100,
                'description' => 100,
                'social_image' => 100,
                'alt_text' => 100,
                'label' => 'No published records',
            ];
        }

        $indexPct = $this->percentage($indexable, $total);
        $descriptionPct = $this->percentage(
            max(0, $total - $missingDescription),
            $total
        );
        $imagePct = $this->percentage(
            max(0, $total - $missingImage),
            $total
        );
        $altPct = $this->percentage(
            max(0, $total - $missingAlt),
            $total
        );

        $score = (int) round(
            ($indexPct * 0.30)
            + ($descriptionPct * 0.25)
            + ($imagePct * 0.25)
            + ($altPct * 0.20)
        );

        return [
            'score' => max(0, min(100, $score)),
            'indexability' => $indexPct,
            'description' => $descriptionPct,
            'social_image' => $imagePct,
            'alt_text' => $altPct,
            'label' => match (true) {
                $score >= 95 => 'Excellent',
                $score >= 80 => 'Strong',
                $score >= 60 => 'Needs work',
                default => 'Priority',
            },
        ];
    }

    private function percentage(int $value, int $total): int
    {
        if ($total <= 0) {
            return 100;
        }

        return max(
            0,
            min(
                100,
                (int) round(($value / $total) * 100)
            )
        );
    }

    /**
     * @param array<int,string> $allowed
     */
    private function allowed(
        mixed $value,
        array $allowed,
        string $fallback = ''
    ): string {
        $value = mb_substr(trim((string) $value), 0, 80);

        return in_array($value, $allowed, true)
            ? $value
            : $fallback;
    }
}
