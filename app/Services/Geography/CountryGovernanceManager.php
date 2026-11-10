<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Models\Location;
use App\Models\LocationCountry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CountryGovernanceManager
{
    public function create(User $actor, array $data): LocationCountry
    {
        $this->admin($actor);
        return DB::transaction(function () use ($actor, $data): LocationCountry {
            $country = LocationCountry::query()->create([
                'country_code' => strtoupper($data['country_code']),
                'iso3' => strtoupper($data['iso3']),
                'name' => $data['name'],
                'native_name' => $data['native_name'] ?? null,
                'enabled' => false,
                'is_default' => false,
                'data_status' => 'catalog_only',
            ]);
            $this->audit($actor, 'country_created', $country->country_code);
            return $country;
        });
    }

    public function enable(User $actor, LocationCountry $country): void
    {
        $this->setEnabled($actor, $country, true);
    }

    public function disable(User $actor, LocationCountry $country): void
    {
        $this->setEnabled($actor, $country, false);
    }

    public function chooseDefault(User $actor, LocationCountry $country): void
    {
        $this->admin($actor);
        DB::transaction(function () use ($actor, $country): void {
            // Lock every country in one order before changing the default.
            $countries = LocationCountry::query()->orderBy('country_code')
                ->lockForUpdate()->get();
            $selected = $countries->firstWhere('country_code', $country->country_code);
            abort_if($selected === null || !$selected->enabled, 422,
                'Enable the selected country before making it the default.');

            foreach ($countries as $item) {
                if ((bool) $item->is_default !== ($item->country_code === $country->country_code)) {
                    $item->update(['is_default' => $item->country_code === $country->country_code]);
                }
            }
            $this->audit($actor, 'country_default_changed', $country->country_code);
        });
    }

    private function setEnabled(User $actor, LocationCountry $country, bool $enabled): void
    {
        $this->admin($actor);
        DB::transaction(function () use ($actor, $country, $enabled): void {
            $locked = LocationCountry::query()->lockForUpdate()->findOrFail($country->getKey());
            if (!$enabled) {
                abort_if($locked->is_default, 422, 'Assign another default country first.');
                abort_if(Location::query()->where('country_code', $locked->country_code)
                    ->where('status', 'active')->exists(), 422,
                    'Cannot disable a country referenced by active geographic locations.');
            }
            if ((bool) $locked->enabled === $enabled) {
                return;
            }
            $locked->update(['enabled' => $enabled]);
            $this->audit($actor, $enabled ? 'country_enabled' : 'country_disabled',
                $locked->country_code);
        });
    }

    private function admin(User $actor): void
    {
        abort_unless($actor->isSystemAdministrator(), 403);
    }

    private function audit(User $actor, string $event, string $code): void
    {
        // country codes are not numeric catalog entity IDs; keep them in context.
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'admin.geography.'.$event,
            'subject_type' => 'country',
            'subject_id' => 0,
            'context' => json_encode(['country_code' => $code], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
