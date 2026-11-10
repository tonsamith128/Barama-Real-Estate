<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Amenity;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyAmenitiesMilestonesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function firm(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Legacy Firm '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function manager(Company $company): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'role' => 'owner', 'status' => 'active',
        ]);
        return $user;
    }

    private function project(Company $company): Project
    {
        return Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Green Valley Development',
            'slug' => 'green-valley-'.$company->id,
            'project_type' => 'residential',
            'status' => 'draft',
        ]);
    }

    private function fields(string $reference): array
    {
        return [
            'reference_code' => $reference, 'title' => 'Split-level loft',
            'property_type' => 'house',
            'bedrooms' => '2.5', 'bathrooms' => '1.5',
            'transaction_type' => 'sale', 'price' => 240000,
            'currency' => 'USD',
        ];
    }

    public function test_fractional_rooms_persist_without_truncation_and_rollback_protects_customer_data(): void
    {
        $company = $this->firm('fractional');
        $owner = $this->manager($company);
        $this->actingAs($owner)->post(route('developer.properties.store', $company),
            $this->fields('FRACTION-1'))->assertRedirect();

        $property = Property::query()->firstOrFail();
        $this->assertSame('2.5', $property->bedrooms);
        $this->assertSame('1.5', $property->bathrooms);

        $this->get(route('developer.properties.edit', [$company, $property]))
            ->assertOk()->assertSee('step="0.5"', false);

        // Executing the additive migration's reversal must refuse a
        // destructive decimal-to-integer truncation.
        $migration = require database_path(
            'migrations/2026_11_10_000006_add_amenities_fractional_rooms_and_project_milestones.php'
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fractional room values');
        $migration->down();
    }

    public function test_project_and_property_amenities_are_reusable_and_private_to_company(): void
    {
        $company = $this->firm('amenities');
        $other = $this->firm('other-amenities');
        $owner = $this->manager($company);
        $outsider = $this->manager($other);
        $project = $this->project($company);
        $active = Amenity::query()->create([
            'name' => 'Swimming pool', 'slug' => 'qa-swimming-pool', 'status' => 'active',
        ]);
        $disabled = Amenity::query()->create([
            'name' => 'Hidden facility', 'slug' => 'qa-hidden-facility', 'status' => 'inactive',
        ]);

        $this->actingAs($owner)->put(route('developer.projects.amenities.update', [$company, $project]), [
            'amenity_ids' => [$active->id],
        ])->assertRedirect();
        $this->assertSame([$active->id], $project->amenities()->pluck('amenities.id')->all());

        $this->put(route('developer.projects.amenities.update', [$company, $project]), [
            'amenity_ids' => [$active->id, $disabled->id],
        ])->assertSessionHasErrors('amenity_ids');
        $this->assertSame([$active->id], $project->amenities()->pluck('amenities.id')->all());

        $this->actingAs($outsider)->put(route('developer.projects.amenities.update', [$company, $project]), [
            'amenity_ids' => [],
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('developer.properties.store', $company),
            $this->fields('AMENITY-1'))->assertRedirect();
        $property = Property::query()->firstOrFail();
        $this->put(route('developer.properties.amenities.update', [$company, $property]), [
            'amenity_ids' => [$active->id],
        ])->assertRedirect();
        $this->assertSame([$active->id], $property->amenities()->pluck('amenities.id')->all());
        $this->put(route('developer.properties.amenities.update', [$company, $property]), [
            'amenity_ids' => [],
        ])->assertRedirect();
        $this->assertSame([], $property->amenities()->pluck('amenities.id')->all());

        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'project', 'subject_id' => $project->id,
            'event' => 'amenities_changed',
        ]);
        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'property', 'subject_id' => $property->id,
            'event' => 'amenities_changed',
        ]);

        $project->update(['status' => 'pending']);
        $this->put(route('developer.projects.amenities.update', [$company, $project]), [
            'amenity_ids' => [],
        ])->assertStatus(409);
    }

    public function test_milestones_support_revisioned_ordering_and_cannot_mutate_published_projects(): void
    {
        $company = $this->firm('milestones');
        $owner = $this->manager($company);
        $project = $this->project($company);
        $this->actingAs($owner);
        $url = route('developer.projects.milestones.store', [$company, $project]);

        $this->post($url, [
            'title' => 'Construction completion', 'status' => 'completed',
        ])->assertSessionHasErrors('completed_date');

        foreach (['Design approval', 'Start construction'] as $title) {
            $this->post($url, [
                'title' => $title, 'status' => 'planned', 'target_date' => '2027-06-01',
            ])->assertRedirect();
        }

        $items = ProjectMilestone::query()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->post(route('developer.projects.milestones.move', [$company, $project, $items[1]]), [
            'direction' => 'up',
        ])->assertRedirect();
        $this->assertSame(0, $items[1]->fresh()->position);

        $this->put(route('developer.projects.milestones.update', [$company, $project, $items[1]]), [
            'title' => 'Start construction',
            'status' => 'completed',
            'target_date' => '2027-06-01',
            'completed_date' => '2027-06-03',
        ])->assertRedirect();
        $this->assertSame('2027-06-03', $items[1]->fresh()->completed_date->format('Y-m-d'));

        $this->get(route('developer.projects.history', [$company, $project]))
            ->assertOk()->assertSeeText('Milestone changed');

        $project->update(['status' => 'published', 'published_at' => now()->subHour()]);
        $this->delete(route('developer.projects.milestones.destroy', [$company, $project, $items[1]]))
            ->assertStatus(409);
        $this->assertDatabaseHas('project_milestones', ['id' => $items[1]->id]);
    }
}
