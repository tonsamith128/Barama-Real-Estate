<?php

declare(strict_types=1);

namespace Tests\Feature\Geography;

use App\Models\Company;
use App\Models\Location;
use App\Models\Project;
use App\Models\Property;
use App\Services\Geography\LocationHierarchy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LocationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_geographic_tree_preserves_legacy_ids_and_links_company_project_property(): void
    {
        $country = $this->place('Cambodia', 'country');
        $province = $this->place('Battambang', 'province', $country);
        $district = $this->place('Sangkae', 'district', $province);

        $company = Company::query()->create([
            'name' => 'Battambang Homes', 'slug' => 'battambang-homes',
            'company_type' => 'agency', 'location_id' => $province->id,
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id, 'location_id' => $district->id,
            'name' => 'Sangkae Place', 'slug' => 'sangkae-place',
            'project_type' => 'residential',
        ]);

        $property = Property::query()->create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'location_id' => $district->id, 'reference_code' => 'BTB-001',
            'title' => 'Sangkae Villa', 'property_type' => 'villa',
        ]);

        $this->assertNotEmpty($country->uuid);
        $this->assertSame('Battambang', $company->location->name);
        $this->assertSame('Sangkae', $project->location->name);
        $this->assertSame('Sangkae', $property->location->name);
        $this->assertSame($province->id, $district->parent->id);

        $tree = app(LocationHierarchy::class);
        $this->assertSame(
            [$country->id, $province->id, $district->id],
            array_map(static fn (Location $location): int => $location->id, $tree->activePath($district->id))
        );
        $this->assertSame(
            [$country->id, $province->id, $district->id],
            $tree->activeDescendantIds($country->id)
        );
    }

    public function test_inactive_and_cross_country_descendants_do_not_appear(): void
    {
        $country = $this->place('Cambodia', 'country');
        $valid = $this->place('Siem Reap', 'province', $country);
        $inactive = $this->place('Hidden Province', 'province', $country, 'inactive');
        $hiddenChild = $this->place('Hidden District', 'district', $inactive);
        $wrongCountry = $this->place('Mismatched Country', 'province', $country, 'active', 'TH');

        $tree = app(LocationHierarchy::class);

        $this->assertSame([$country->id, $valid->id], $tree->activeDescendantIds($country->id));
        $this->assertSame([], $tree->activePath($hiddenChild->id));
        $this->assertSame([], $tree->activePath($wrongCountry->id));
        $this->assertSame(['Siem Reap'], $tree->activeChildren('KH', $country->id)->pluck('name')->all());
        $this->assertSame([], $tree->activeChildren('TH', $country->id)->all());
        $this->assertSame([], $tree->activeChildren('invalid-value')->all());
    }

    public function test_cyclic_hierarchy_is_rejected_without_infinite_recursion(): void
    {
        $country = $this->place('Cambodia', 'country');
        $province = $this->place('Battambang', 'province', $country);
        $country->update(['parent_id' => $province->id]);

        $this->assertSame([], app(LocationHierarchy::class)->activePath($province->id));
        $this->assertSame([], app(LocationHierarchy::class)->activeDescendantIds($country->id));
    }

    public function test_deleting_location_unlinks_existing_company_without_deleting_company(): void
    {
        $location = $this->place('Phnom Penh', 'city');
        $company = Company::query()->create([
            'name' => 'Central Realty', 'slug' => 'central-realty',
            'company_type' => 'agency', 'location_id' => $location->id,
        ]);

        $location->delete();

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'location_id' => null,
        ]);
    }

    private function place(
        string $name,
        string $type,
        ?Location $parent = null,
        string $status = 'active',
        string $countryCode = 'KH',
    ): Location {
        return Location::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'type' => $type,
            'country_code' => $countryCode,
            'parent_id' => $parent?->id,
            'status' => $status,
        ]);
    }
}
