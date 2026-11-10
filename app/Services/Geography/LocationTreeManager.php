<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Models\Location;
use App\Models\LocationCountry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LocationTreeManager
{
    public function __construct(private readonly LocationHierarchy $hierarchy)
    {
    }

    /** @param array<string, mixed> $fields */
    public function create(User $actor, array $fields): Location
    {
        $this->admin($actor);
        return DB::transaction(function () use ($actor, $fields): Location {
            $this->validateParent($fields, null);
            $location = Location::query()->create($fields + ['status' => 'active']);
            $this->audit($actor, $location, 'created', null);
            return $location;
        });
    }

    /** @param array<string, mixed> $fields */
    public function update(User $actor, Location $location, array $fields): void
    {
        $this->admin($actor);
        DB::transaction(function () use ($actor, $location, $fields): void {
            $locked = Location::query()->lockForUpdate()->findOrFail($location->getKey());
            $this->validateParent($fields, (int) $locked->getKey());
            $countryChanged = $locked->country_code !== $fields['country_code'];
            if ($countryChanged && (
                $locked->children()->exists()
                || $locked->companies()->exists()
                || $locked->projects()->exists()
                || $locked->properties()->exists()
            )) {
                throw ValidationException::withMessages([
                    'country_code' => 'Move referenced locations through a separately reviewed conversion.',
                ]);
            }

            if ($fields['status'] === 'inactive' && (
                $locked->children()->where('status', 'active')->exists()
                || $locked->companies()->exists()
                || $locked->projects()->exists()
                || $locked->properties()->exists()
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Disable child locations and resolve attached records first.',
                ]);
            }

            $before = $locked->only([
                'name','type','country_code','parent_id','status','sort_order',
            ]);
            $locked->update($fields);
            $this->audit($actor, $locked, 'updated', $before);
        });
    }

    /** @param array<string, mixed> $fields */
    private function validateParent(array $fields, ?int $editingId): void
    {
        $country = strtoupper((string) $fields['country_code']);
        if (!LocationCountry::query()->where('country_code', $country)
            ->where('enabled', true)->exists()) {
            throw ValidationException::withMessages([
                'country_code' => 'Register and enable this country first.',
            ]);
        }

        $parentId = $fields['parent_id'] ?? null;
        if ($parentId === null) {
            return;
        }
        if ($fields['type'] === 'country' || $editingId === (int) $parentId) {
            throw ValidationException::withMessages(['parent_id' => 'Invalid location parent.']);
        }

        $path = $this->hierarchy->activePath((int) $parentId);
        if ($path === [] || end($path)->country_code !== $country) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent must belong to the same active country hierarchy.',
            ]);
        }
        if ($editingId !== null) {
            foreach ($path as $ancestor) {
                if ((int) $ancestor->getKey() === $editingId) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Cannot make a location its own descendant.',
                    ]);
                }
            }
        }
    }

    private function admin(User $actor): void
    {
        abort_unless($actor->isSystemAdministrator(), 403);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, Location $location, string $event, ?array $before): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'admin.geography.location_'.$event,
            'subject_type' => 'location',
            'subject_id' => $location->getKey(),
            'context' => json_encode([
                'before' => $before,
                'after' => $location->only([
                    'name','type','country_code','parent_id','status','sort_order',
                ]),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
