<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\Location;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyCatalogParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function company(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Company '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function owner(Company $company): User
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'role' => 'owner', 'status' => 'active',
        ]);
        return $owner;
    }

    private function geography(): Location
    {
        $province = Location::query()->create([
            'name' => 'QA Province', 'slug' => 'qa-province',
            'type' => 'province', 'country_code' => 'KH', 'status' => 'active',
        ]);
        return Location::query()->create([
            'name' => 'QA City', 'slug' => 'qa-city',
            'type' => 'city', 'country_code' => 'KH',
            'parent_id' => $province->id, 'status' => 'active',
        ]);
    }

    private function projectFields(?Location $location = null): array
    {
        return [
            'name' => 'Riverside Development', 'project_type' => 'residential',
            'completion_status' => 'planning',
            'description' => 'A private residential development.',
            'location_id' => $location?->id,
            'address_line' => 'QA Street 12',
            'latitude' => 13.0955555, 'longitude' => 103.2011222,
            'launch_date' => '2026-01-01',
            'expected_completion_date' => '2027-12-31',
        ];
    }

    private function propertyFields(?Project $project = null, ?Location $location = null): array
    {
        return [
            'reference_code' => 'QA-PR-100',
            'title' => 'Riverside Three Bedroom Home',
            'property_type' => 'house',
            'project_id' => $project?->id,
            'location_id' => $location?->id,
            'address_line' => 'QA Street 12',
            'description' => 'A complete listing for review.',
            'bedrooms' => 3, 'bathrooms' => 2,
            'parking_spaces' => 2, 'floor_number' => 1, 'total_floors' => 3,
            'area_value' => 120.25, 'area_unit' => 'm2',
            'land_area_value' => 250.75, 'land_area_unit' => 'm2',
            'furnishing_status' => 'furnished', 'year_built' => 2022,
            'latitude' => 13.0955555, 'longitude' => 103.2011222,
            'transaction_type' => 'rent', 'price' => 1200, 'currency' => 'USD',
            'deposit_amount' => 2400, 'available_from' => '2026-12-01',
            'pricing_period' => 'month', 'negotiable' => 1,
        ];
    }

    public function test_project_and_property_legacy_fields_persist_and_have_review_history(): void
    {
        $company = $this->company('legacy-parity');
        $owner = $this->owner($company);
        $place = $this->geography();

        $this->actingAs($owner)->post(route('developer.projects.store', $company),
            $this->projectFields($place))->assertRedirect();
        $project = Project::query()->firstOrFail();

        $this->assertSame($place->id, $project->location_id);
        $this->assertSame('2027-12-31', $project->expected_completion_date?->format('Y-m-d'));
        $this->assertSame('13.0955555', $project->latitude);

        $this->post(route('developer.properties.store', $company),
            $this->propertyFields($project, $place))->assertRedirect();
        $property = Property::query()->firstOrFail();
        $listing = Listing::query()->firstOrFail();

        $this->assertSame($project->id, $property->project_id);
        $this->assertSame($place->id, $property->location_id);
        $this->assertSame(2, $property->parking_spaces);
        $this->assertSame('250.75', $property->land_area_value);
        $this->assertSame('2400.00', $listing->deposit_amount);
        $this->assertSame('2026-12-01', $listing->available_from?->format('Y-m-d'));
        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'project', 'subject_id' => $project->id,
            'revision_number' => 1, 'event' => 'created',
        ]);
        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'property', 'subject_id' => $property->id,
            'revision_number' => 1, 'event' => 'created',
        ]);

        $this->post(route('developer.projects.submit', [$company, $project]))->assertRedirect();
        $this->post(route('developer.properties.submit', [$company, $property]))->assertRedirect();

        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.catalog.projects.decision', $project),
            ['decision' => 'approved'])->assertRedirect();
        $this->post(route('admin.catalog.approve', $listing))->assertRedirect();

        $this->assertSame('published', $project->fresh()->status);
        $this->assertSame('approved', $property->fresh()->status);
        $this->assertSame('published', $listing->fresh()->status);

        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'project', 'subject_id' => $project->id,
            'revision_number' => 3, 'event' => 'approved',
        ]);
        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'property', 'subject_id' => $property->id,
            'revision_number' => 3, 'event' => 'approved',
        ]);
        $this->assertDatabaseHas('moderation_decisions', [
            'subject_type' => 'listing', 'subject_id' => $listing->id,
            'decision' => 'approved',
        ]);

        $this->actingAs($owner)->get(route('developer.projects.history', [$company, $project]))
            ->assertOk()->assertSeeText('Revision timeline')->assertSeeText('Approved');
        $this->get(route('developer.properties.history', [$company, $property]))
            ->assertOk()->assertSeeText('Riverside Three Bedroom Home');
    }

    public function test_inactive_ancestors_and_wrong_company_project_fail_closed(): void
    {
        $company = $this->company('geo-tenant-1');
        $other = $this->company('geo-tenant-2');
        $owner = $this->owner($company);
        $active = $this->geography();
        $parent = $active->parent;
        $parent->update(['status' => 'inactive']);

        $this->actingAs($owner)->post(route('developer.projects.store', $company),
            $this->projectFields($active))->assertSessionHasErrors('location_id');
        $this->assertDatabaseCount('projects', 0);

        $parent->update(['status' => 'active']);
        $foreignProject = Project::query()->create([
            'company_id' => $other->id,
            'name' => 'Other Company Project',
            'slug' => 'other-company-project',
            'status' => 'draft',
        ]);
        $this->post(route('developer.properties.store', $company),
            $this->propertyFields($foreignProject, $active))
            ->assertSessionHasErrors('project_id');

        $this->assertDatabaseCount('properties', 0);

        $active->update(['parent_id' => $active->getKey()]);
        $this->post(route('developer.properties.store', $company),
            $this->propertyFields(null, $active))->assertSessionHasErrors('location_id');
        $this->assertDatabaseCount('properties', 0);
    }

    public function test_edits_and_rejection_preserve_revision_history_but_not_publication(): void
    {
        $company = $this->company('revisions');
        $owner = $this->owner($company);
        $this->actingAs($owner)->post(route('developer.projects.store', $company),
            $this->projectFields())->assertRedirect();
        $project = Project::query()->firstOrFail();
        $this->post(route('developer.projects.submit', [$company, $project]))->assertRedirect();

        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.catalog.projects.decision', $project), [
            'decision' => 'rejected', 'reason' => 'Please update the estimated completion date.',
        ])->assertRedirect();

        $this->actingAs($owner)->put(route('developer.projects.update', [$company, $project]),
            array_replace($this->projectFields(), [
                'name' => 'Revised Riverside Development',
                'expected_completion_date' => '2028-01-01',
            ]))->assertRedirect();
        $this->assertSame('draft', $project->fresh()->status);
        $this->assertNull($project->fresh()->published_at);

        $history = DB::table('catalog_revisions')
            ->where('subject_type', 'project')
            ->where('subject_id', $project->getKey())
            ->orderBy('revision_number')->get();
        $this->assertSame([1,2,3,4], $history->pluck('revision_number')->all());
        $this->assertSame(['created','submitted','rejected','revised'],
            $history->pluck('event')->all());
        $this->assertSame('2028-01-01',
            json_decode($history->last()->after_snapshot, true)['expected_completion_date']);
        $this->get(route('developer.projects.history', [$company, $project]))
            ->assertOk()->assertSeeText('Revised Riverside Development');
    }

    public function test_company_revision_history_is_private_to_assigned_managers(): void
    {
        $company = $this->company('history-own');
        $other = $this->company('history-other');
        $owner = $this->owner($company);
        $attacker = $this->owner($other);

        $this->actingAs($owner)->post(route('developer.projects.store', $company),
            $this->projectFields())->assertRedirect();
        $project = Project::query()->firstOrFail();

        $this->actingAs($attacker)->get(route('developer.projects.history', [$company, $project]))
            ->assertForbidden();
        $this->get(route('developer.projects.history', [$other, $project]))
            ->assertNotFound();
    }
}
