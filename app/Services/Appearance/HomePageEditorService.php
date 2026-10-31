<?php

declare(strict_types=1);

namespace App\Services\Appearance;

use App\Models\SiteAppearance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class HomePageEditorService
{
    public function __construct(private readonly HomepageMediaAccess $media) {}

    /** Only verified internal destinations are allowed; never store arbitrary URLs. */
    public const MENU = [
        'properties' => ['route' => 'properties.index', 'label' => 'Properties'],
        'projects' => ['route' => 'projects.index', 'label' => 'Projects'],
        'companies' => ['route' => 'companies.index', 'label' => 'Companies'],
        'about' => ['route' => 'about', 'label' => 'About'],
        'contact' => ['route' => 'contact', 'label' => 'Contact'],
        'faq' => ['route' => 'faq', 'label' => 'FAQ'],
        'pricing' => ['route' => 'pricing.index', 'label' => 'Pricing'],
    ];

    public const TYPES = [
        'text' => 'Content section',
        'feature' => 'Feature card',
        'cta' => 'Call to action',
    ];

    public function add(User $actor, array $attributes): void
    {
        $this->mutate($actor, 'appearance.section.created', function (array $draft) use ($attributes): array {
            $sections = $draft['home_sections'] ?? [];
            if (count($sections) >= 12) {
                throw ValidationException::withMessages(['type' => 'A homepage supports at most 12 sections.']);
            }
            $sections[] = $this->section(Str::random(12), $attributes);
            $draft['home_sections'] = $sections;
            return $draft;
        });
    }

    public function edit(User $actor, string $id, array $attributes): void
    {
        $this->mutate($actor, 'appearance.section.updated', function (array $draft) use ($id, $attributes): array {
            $position = $this->locate($draft['home_sections'] ?? [], $id);
            $draft['home_sections'][$position] = $this->section($id, $attributes);
            return $draft;
        });
    }

    public function remove(User $actor, string $id): void
    {
        $this->mutate($actor, 'appearance.section.removed', function (array $draft) use ($id): array {
            $sections = $draft['home_sections'] ?? [];
            $position = $this->locate($sections, $id);
            array_splice($sections, $position, 1);
            $draft['home_sections'] = $sections;
            return $draft;
        });
    }

    public function moveSection(User $actor, string $id, string $direction): void
    {
        $this->mutate($actor, 'appearance.section.reordered', function (array $draft) use ($id, $direction): array {
            $sections = $draft['home_sections'] ?? [];
            $position = $this->locate($sections, $id);
            $target = $position + ($direction === 'up' ? -1 : 1);
            if ($target >= 0 && $target < count($sections)) {
                [$sections[$position], $sections[$target]] = [$sections[$target], $sections[$position]];
            }
            $draft['home_sections'] = $sections;
            return $draft;
        });
    }

    public function changeHeroImage(User $actor, ?int $assetId): void
    {
        $this->media->validateSelection($assetId);

        $this->mutate($actor, 'appearance.hero.image_changed', function (array $draft) use ($assetId): array {
            $draft['hero_image_asset_id'] = $assetId;
            return $draft;
        });
    }

    public function addLink(User $actor, string $key): void
    {
        abort_unless(isset(self::MENU[$key]), 422);
        $this->mutate($actor, 'appearance.navigation.added', function (array $draft) use ($key): array {
            $links = $draft['navigation_links'] ?? [];
            if (!in_array($key, $links, true)) {
                $links[] = $key;
            }
            $draft['navigation_links'] = $links;
            return $draft;
        });
    }

    public function removeLink(User $actor, string $key): void
    {
        $this->mutate($actor, 'appearance.navigation.removed', function (array $draft) use ($key): array {
            $links = $draft['navigation_links'] ?? [];
            $position = array_search($key, $links, true);
            abort_if($position === false, 404);
            if (count($links) < 2) {
                throw ValidationException::withMessages(['link' => 'Keep at least one navigation link.']);
            }
            array_splice($links, $position, 1);
            $draft['navigation_links'] = $links;
            return $draft;
        });
    }

    public function moveLink(User $actor, string $key, string $direction): void
    {
        $this->mutate($actor, 'appearance.navigation.reordered', function (array $draft) use ($key, $direction): array {
            $links = $draft['navigation_links'] ?? [];
            $position = array_search($key, $links, true);
            abort_if($position === false, 404);
            $target = $position + ($direction === 'up' ? -1 : 1);
            if ($target >= 0 && $target < count($links)) {
                [$links[$position], $links[$target]] = [$links[$target], $links[$position]];
            }
            $draft['navigation_links'] = $links;
            return $draft;
        });
    }

    private function mutate(User $actor, string $action, callable $transform): void
    {
        DB::transaction(function () use ($actor, $action, $transform): void {
            $row = SiteAppearance::query()->lockForUpdate()->first();
            if ($row === null) {
                $row = SiteAppearance::query()->create([
                    'draft_data' => SiteAppearanceService::defaults(),
                    'published_data' => SiteAppearanceService::defaults(),
                ]);
            }
            $draft = array_replace(SiteAppearanceService::defaults(), $row->draft_data ?: []);
            $draft = $transform($draft);
            $row->update(['draft_data' => $draft]);

            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(),
                'action' => $action,
                'subject_type' => 'site_appearance',
                'subject_id' => $row->getKey(),
                'context' => json_encode(['draft_only' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function locate(array $sections, string $id): int
    {
        foreach ($sections as $index => $section) {
            if (($section['id'] ?? null) === $id) {
                return $index;
            }
        }
        abort(404);
    }

    private function section(string $id, array $attributes): array
    {
        $assetId = $attributes['image_asset_id'] ?? null;
        $this->media->validateSelection($assetId);
        return [
            'id' => $id,
            'type' => $attributes['type'],
            'image_asset_id' => $assetId,
            'image_position' => $attributes['image_position'] ?? 'right',
            'title' => $attributes['title'],
            'body' => $attributes['body'] ?? '',
            'button_label' => $attributes['button_label'] ?? '',
            'button_route' => $attributes['button_route'] ?? 'properties',
            'enabled' => (bool) ($attributes['enabled'] ?? false),
        ];
    }
}
