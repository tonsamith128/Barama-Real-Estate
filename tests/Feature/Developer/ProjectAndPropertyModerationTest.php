<?php

declare(strict_types=1);

namespace Tests\Feature\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProjectAndPropertyModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function firm(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Approved '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function manager(Company $company, string $role = 'owner'): User
    {
        $user = User::factory()->create();
        CompanyMembership::query()->create([
            'company_id' => $company->getKey(), 'user_id' => $user->getKey(),
            'role' => $role, 'status' => 'active',
        ]);
        return $user;
    }

    private function fields(): array
    {
        return ['name' => 'Riverside Residences', 'project_type' => 'residential',
            'completion_status' => 'planning', 'description' => 'A future housing development.'];
    }

    private function listing(Company $company, string $suffix): Listing
    {
        $property = Property::query()->create([
            'company_id' => $company->getKey(), 'reference_code' => 'PR-'.$suffix,
            'title' => 'Modern townhouse '.$suffix, 'property_type' => 'house',
            'status' => 'draft',
        ]);
        return Listing::query()->create([
            'property_id' => $property->getKey(), 'slug' => 'listing-'.$suffix,
            'transaction_type' => 'sale', 'status' => 'draft',
            'price' => '90000', 'currency' => 'USD',
        ]);
    }

    public function test_developer_can_create_and_admin_can_publish_project(): void
    {
        $company = $this->firm('project-good');
        $owner = $this->manager($company);
        $this->actingAs($owner)->post(route('developer.projects.store', $company), $this->fields())
            ->assertRedirect();
        $project = Project::query()->firstOrFail();
        $this->assertSame('draft', $project->status);
        $this->get(route('projects.index'))->assertDontSee('Riverside Residences');
        $this->post(route('developer.projects.submit', [$company, $project]))->assertRedirect();
        $this->assertSame('pending', $project->fresh()->status);
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)
            ->post(route('admin.catalog.projects.decision', $project), ['decision' => 'approved'])
            ->assertRedirect();
        $this->assertSame('published', $project->fresh()->status);
        $this->get(route('projects.index'))->assertSee('Riverside Residences');
        $this->assertDatabaseHas('moderation_decisions', [
            'subject_type' => 'project', 'subject_id' => $project->id, 'decision' => 'approved',
        ]);
    }

    public function test_rejection_requires_reason_and_project_revision_returns_draft(): void
    {
        $company = $this->firm('project-reject');
        $owner = $this->manager($company);
        $this->actingAs($owner)->post(route('developer.projects.store', $company), $this->fields());
        $project = Project::query()->firstOrFail();
        $this->post(route('developer.projects.submit', [$company, $project]))->assertRedirect();
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.catalog.projects.decision', $project), [
            'decision' => 'rejected',
        ])->assertSessionHasErrors('reason');
        $this->post(route('admin.catalog.projects.decision', $project), [
            'decision' => 'rejected', 'reason' => 'The details are not sufficient.',
        ])->assertRedirect();
        $this->assertSame('rejected', $project->fresh()->status);
        $this->actingAs($owner)->put(route('developer.projects.update', [$company, $project]), [
            'name' => 'Riverside revised', 'project_type' => 'mixed_use',
            'description' => 'Revised details provided.',
        ])->assertRedirect();
        $this->assertSame('draft', $project->fresh()->status);
        $this->post(route('developer.projects.submit', [$company, $project]))->assertRedirect();
        $this->assertSame('pending', $project->fresh()->status);
    }

    public function test_company_isolation_blocks_project_edits_and_reviews(): void
    {
        $company = $this->firm('project-own');
        $other = $this->firm('project-other');
        $owner = $this->manager($company);
        $project = Project::query()->create([
            'company_id' => $other->id, 'name' => 'Other private',
            'slug' => 'other-private', 'status' => 'draft',
        ]);
        $this->actingAs($owner)->get(route('developer.projects.index', $other))->assertForbidden();
        $this->put(route('developer.projects.update', [$company, $project]), $this->fields())->assertNotFound();
        $this->post(route('developer.projects.submit', [$company, $project]))->assertNotFound();
        $this->post(route('admin.catalog.projects.decision', $project), ['decision'=>'approved'])->assertForbidden();
    }

    public function test_private_property_edit_reject_and_resubmit_flow(): void
    {
        $company = $this->firm('property-good');
        $owner = $this->manager($company);
        $listing = $this->listing($company, 'GOOD');
        $property = $listing->property;
        $payload = [
            'reference_code' => 'PR-GOOD', 'title' => 'Improved townhouse',
            'property_type' => 'house', 'transaction_type' => 'rent',
            'price' => '850', 'currency' => 'USD', 'description' => 'Revised draft.',
        ];
        $this->actingAs($owner)->get(route('developer.properties.edit', [$company,$property]))->assertOk();
        $this->put(route('developer.properties.update', [$company,$property]), $payload)->assertRedirect();
        $this->assertSame('Improved townhouse', $property->fresh()->title);
        $this->assertSame('850.00', $listing->fresh()->price);
        $this->post(route('developer.properties.submit', [$company,$property]))->assertRedirect();
        $this->put(route('developer.properties.update', [$company,$property]), $payload)->assertStatus(409);

        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.catalog.reject', $listing), [
            'reason' => 'Location description must be expanded.',
        ])->assertRedirect();
        $this->assertSame('rejected', $property->fresh()->status);
        $this->assertSame('rejected', $listing->fresh()->status);
        $this->get(route('properties.index'))->assertDontSee('Improved townhouse');

        $this->actingAs($owner)->put(route('developer.properties.update', [$company,$property]),
            array_replace($payload, ['description'=>'Detailed corrected address and legal status.'])
        )->assertRedirect();
        $this->assertSame('draft', $property->fresh()->status);
        $this->post(route('developer.properties.submit', [$company,$property]))->assertRedirect();

        $this->actingAs($admin)->post(route('admin.catalog.approve', $listing))->assertRedirect();
        $this->assertSame('published', $listing->fresh()->status);
        $this->get(route('properties.index'))->assertSee('Improved townhouse');
        $this->put(route('developer.properties.update', [$company,$property]), $payload)->assertForbidden();
    }

    public function test_admin_unpublishes_without_deleting_customer_records(): void
    {
        $company = $this->firm('property-unsafe');
        $listing = $this->listing($company, 'UNPUB');
        $property = $listing->property;
        $property->update(['status'=>'approved']);
        $listing->update(['status'=>'published','published_at'=>now()->subDay()]);
        $admin = User::factory()->create(['system_role'=>'super_admin']);
        $this->actingAs($admin)->post(route('admin.catalog.unpublish', $listing), [
            'reason'=>'The property details are inaccurate.',
        ])->assertRedirect();
        $this->assertSame('draft', $listing->fresh()->status);
        $this->assertNull($listing->fresh()->published_at);
        $this->assertSame('draft', $property->fresh()->status);
        $this->get(route('properties.index'))->assertDontSee('Modern townhouse UNPUB');
        $this->assertDatabaseHas('moderation_decisions', [
            'subject_type'=>'listing', 'subject_id'=>$listing->id, 'decision'=>'unpublished',
        ]);
        $this->post(route('admin.catalog.unpublish', $listing), [
            'reason'=>'Repeat invalid unpublish request.',
        ])->assertStatus(409);
    }

    public function test_cross_company_property_edit_fails_closed(): void
    {
        $ownerCompany = $this->firm('editing-owner');
        $otherCompany = $this->firm('editing-target');
        $user = $this->manager($ownerCompany);
        $listing = $this->listing($otherCompany, 'OTHER');
        $this->actingAs($user)->put(
            route('developer.properties.update', [$ownerCompany, $listing->property]), [
                'reference_code'=>'PR-OTHER', 'title'=>'Stolen listing',
                'property_type'=>'house','transaction_type'=>'sale','price'=>1,'currency'=>'USD',
            ],
        )->assertNotFound();
        $this->assertSame('Modern townhouse OTHER', $listing->property->fresh()->title);
    }
}
