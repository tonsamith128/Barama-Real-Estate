<?php

declare(strict_types=1);

namespace App\Services\Appearance;

use App\Models\SiteAppearance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SiteAppearanceService
{
    private ?array $memo = null;

    public static function defaults(): array
    {
        return [
            'brand_name' => 'Barama Real Estate',
            'brand_short' => 'Barama',
            'brand_subtitle' => 'Real Estate',
            'primary_color' => '#165dff',
            'hero_eyebrow' => 'BARAMA REAL ESTATE',
            'hero_title' => 'Find your next place to call home.',
            'hero_description' => 'Explore properties for sale and rent, discover developments, and connect with real estate companies.',
            'footer_text' => 'Discover properties, developments and trusted connections.',
            'meta_description' => 'Find properties for sale and rent, explore real estate developments and connect with companies on Barama Real Estate.',
        ];
    }

    public function published(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }
        if (!Schema::hasTable('site_appearances')) {
            return $this->memo = self::defaults();
        }
        $row = SiteAppearance::query()->first();
        $values = $row?->published_data;
        return $this->memo = array_replace(
            self::defaults(),
            is_array($values) ? array_intersect_key($values, self::defaults()) : [],
        );
    }

    public function editing(): array
    {
        if (!Schema::hasTable('site_appearances')) {
            return self::defaults();
        }
        $values = SiteAppearance::query()->first()?->draft_data;
        return array_replace(self::defaults(), is_array($values) ? $values : []);
    }

    public function saveDraft(User $actor, array $data): void
    {
        DB::transaction(function () use ($actor, $data): void {
            $row = SiteAppearance::query()->lockForUpdate()->first();
            if ($row === null) {
                $row = SiteAppearance::query()->create([
                    'draft_data' => self::defaults(),
                    'published_data' => self::defaults(),
                ]);
            }
            $row->update(['draft_data' => array_replace(self::defaults(), $data)]);
            $this->audit($actor, 'appearance.draft.saved', $row);
        });
    }

    public function publish(User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            $row = SiteAppearance::query()->lockForUpdate()->first();
            if ($row === null) {
                $row = SiteAppearance::query()->create([
                    'draft_data' => self::defaults(),
                    'published_data' => self::defaults(),
                ]);
            }
            $row->update([
                'published_data' => array_replace(self::defaults(), $row->draft_data ?: []),
                'published_at' => now(),
            ]);
            $this->audit($actor, 'appearance.published', $row);
        });
        $this->memo = null;
    }

    public function resetDraft(User $actor): void
    {
        DB::transaction(function () use ($actor): void {
            $row = SiteAppearance::query()->lockForUpdate()->firstOrFail();
            $row->update(['draft_data' => array_replace(self::defaults(), $row->published_data ?: [])]);
            $this->audit($actor, 'appearance.draft.reset', $row);
        });
    }

    private function audit(User $actor, string $action, SiteAppearance $row): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(), 'action' => $action,
            'subject_type' => 'site_appearance', 'subject_id' => $row->getKey(),
            'context' => json_encode(['published_at' => $row->published_at?->toIso8601String()], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
