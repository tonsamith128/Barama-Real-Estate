<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProjectGalleryParityTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function image(string $suffix): UploadedFile
    {
        $data = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=',
            true
        );
        $path = tempnam(sys_get_temp_dir(), 'barama-project-photo-');
        file_put_contents($path, $data.$suffix);
        $this->temporaryPaths[] = $path;
        return new UploadedFile($path, 'project.png', 'image/png', null, true);
    }

    private function company(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Agency '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function manager(Company $company): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'active',
            'role' => 'manager',
        ]);
        return $user;
    }

    private function project(Company $company, string $slug): Project
    {
        return Project::query()->create([
            'company_id' => $company->id,
            'name' => 'QA Project '.$slug,
            'slug' => $slug,
            'project_type' => 'residential',
            'status' => 'draft',
        ]);
    }

    public function test_project_gallery_upload_order_remove_and_revision_history(): void
    {
        $company = $this->company('images');
        $project = $this->project($company, 'project-images');
        $user = $this->manager($company);
        $this->actingAs($user);

        $this->post(route('developer.projects.gallery.store', [$company, $project]), [
            'image' => $this->image('-one'), 'alt_text' => 'Housing exterior',
        ])->assertRedirect();
        $this->post(route('developer.projects.gallery.store', [$company, $project]), [
            'image' => $this->image('-two'), 'alt_text' => 'Housing interior',
        ])->assertRedirect();

        $links = MediaAttachment::query()->where('entity_type', 'project')
            ->orderBy('position')->get();
        $this->assertCount(2, $links);
        $this->assertTrue((bool) $links[0]->is_primary);
        $this->assertFalse((bool) $links[1]->is_primary);
        $asset = $links[0]->asset;
        Storage::disk('local')->assertExists(substr($asset->path, 8));

        $this->get(route('developer.projects.gallery.preview', [$company, $project, $asset]))
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('media.demo.show', $asset->checksum_sha256))->assertNotFound();

        $this->post(route('developer.projects.gallery.order', [$company, $project, $links[1]]), [
            'action' => 'primary',
        ])->assertRedirect();
        $this->assertTrue((bool) $links[1]->fresh()->is_primary);
        $this->assertSame(0, $links[1]->fresh()->position);

        $this->post(route('developer.projects.gallery.order', [$company, $project, $links[0]]), [
            'action' => 'up',
        ])->assertRedirect();
        $this->assertTrue((bool) $links[0]->fresh()->is_primary);

        $this->delete(route('developer.projects.gallery.destroy', [$company, $project, $links[1]]))
            ->assertRedirect();
        $this->assertDatabaseHas('media_assets', ['id' => $links[1]->media_asset_id]);
        $this->assertDatabaseMissing('media_attachments', ['id' => $links[1]->id]);
        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'project', 'subject_id' => $project->id,
            'event' => 'gallery_changed',
        ]);

        $this->get(route('developer.projects.history', [$company, $project]))
            ->assertOk()->assertSeeText('Gallery changed');
    }

    public function test_project_media_is_private_until_publication_and_cross_company_access_fails(): void
    {
        $company = $this->company('gallery-owner');
        $outsiderCompany = $this->company('gallery-other');
        $project = $this->project($company, 'private-project');
        $owner = $this->manager($company);
        $outsider = $this->manager($outsiderCompany);

        $this->actingAs($owner)->post(route('developer.projects.gallery.store', [$company, $project]), [
            'image' => $this->image('-secure'),
        ])->assertRedirect();
        $asset = MediaAsset::query()->firstOrFail();
        $link = MediaAttachment::query()->firstOrFail();

        $this->actingAs($outsider)->get(route('developer.projects.gallery.preview', [
            $company, $project, $asset,
        ]))->assertForbidden();
        $this->post(route('developer.projects.gallery.order', [$company, $project, $link]), [
            'action' => 'primary',
        ])->assertForbidden();
        $this->delete(route('developer.projects.gallery.destroy', [$company, $project, $link]))
            ->assertForbidden();

        $project->update(['status' => 'pending']);
        $this->actingAs($owner)->post(route('developer.projects.gallery.store', [$company, $project]), [
            'image' => $this->image('-late'),
        ])->assertStatus(409);
        $this->get(route('developer.projects.gallery.preview', [$company, $project, $asset]))
            ->assertStatus(409);
        $this->get(route('media.demo.show', $asset->checksum_sha256))->assertNotFound();

        $project->update(['status' => 'published', 'published_at' => now()->subHour()]);
        $this->get(route('media.demo.show', $asset->checksum_sha256))->assertOk();
        $this->get(route('projects.show', $project->slug))
            ->assertOk()->assertSee($asset->checksum_sha256);
    }

    public function test_listing_photos_can_be_reordered_without_cross_listing_mutation(): void
    {
        $company = $this->company('listing-gallery-order');
        $owner = $this->manager($company);
        $property = Property::query()->create([
            'company_id' => $company->id, 'reference_code' => 'ORDER-L-1',
            'title' => 'Listing Order Home', 'property_type' => 'house',
            'status' => 'draft',
        ]);
        $listing = Listing::query()->create([
            'property_id' => $property->id, 'slug' => 'order-l-1',
            'transaction_type' => 'sale', 'status' => 'draft',
            'price' => 999, 'currency' => 'USD',
        ]);

        $this->actingAs($owner)->post(route('developer.gallery.store', [$company, $listing]), [
            'image' => $this->image('-listing-1'),
        ])->assertRedirect();
        $this->post(route('developer.gallery.store', [$company, $listing]), [
            'image' => $this->image('-listing-2'),
        ])->assertRedirect();

        $links = MediaAttachment::query()->where('entity_type', 'listing')
            ->orderBy('position')->get();
        $this->post(route('developer.gallery.order', [$company, $listing, $links[1]]), [
            'action' => 'primary',
        ])->assertRedirect();
        $this->assertSame(0, $links[1]->fresh()->position);
        $this->assertTrue((bool) $links[1]->fresh()->is_primary);

        $foreign = $this->project($company, 'different-gallery-subject');
        $foreignPhoto = MediaAttachment::query()->create([
            'entity_type' => 'project', 'entity_id' => $foreign->id,
            'media_asset_id' => $links[0]->media_asset_id,
            'collection' => 'gallery', 'position' => 0, 'is_primary' => true,
        ]);
        $this->post(route('developer.gallery.order', [$company, $listing, $foreignPhoto]), [
            'action' => 'primary',
        ])->assertNotFound();
        $this->assertTrue((bool) $foreignPhoto->fresh()->is_primary);
        $this->assertDatabaseHas('catalog_revisions', [
            'subject_type' => 'listing', 'subject_id' => $listing->id,
            'event' => 'gallery_changed',
        ]);
    }
}
