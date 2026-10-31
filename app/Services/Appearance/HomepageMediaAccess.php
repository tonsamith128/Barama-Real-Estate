<?php

declare(strict_types=1);

namespace App\Services\Appearance;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class HomepageMediaAccess
{
    public const APPROVED_RIGHTS = ['owned', 'licensed'];

    public function selectable(): Builder
    {
        return MediaAsset::query()
            ->whereIn('usage_rights', self::APPROVED_RIGHTS)
            ->whereNotNull('rights_holder')
            ->where('rights_holder', '!=', '');
    }

    public function isReady(): bool
    {
        return Schema::hasTable('media_assets')
            && Schema::hasColumn('media_assets', 'usage_rights')
            && Schema::hasTable('site_appearances');
    }

    public function validateSelection(?int $id): ?int
    {
        if ($id === null) {
            return null;
        }
        if (!$this->isReady() || !$this->selectable()->whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                'image_asset_id' => 'Select a Media Library image with an owned or licensed rights declaration and a rights holder.',
            ]);
        }

        return $id;
    }

    public function publiclySelected(MediaAsset $asset): bool
    {
        if (!$this->isReady() || !$this->selectable()->whereKey($asset->getKey())->exists()) {
            return false;
        }

        $appearance = app(SiteAppearanceService::class)->published();
        if (($appearance['hero_image_asset_id'] ?? null) === $asset->getKey()) {
            return true;
        }

        foreach ($appearance['home_sections'] ?? [] as $section) {
            if (!empty($section['enabled']) && ($section['image_asset_id'] ?? null) === $asset->getKey()
                && in_array($section['type'] ?? '', array_keys(HomePageEditorService::TYPES), true)) {
                return true;
            }
        }

        return false;
    }
}
