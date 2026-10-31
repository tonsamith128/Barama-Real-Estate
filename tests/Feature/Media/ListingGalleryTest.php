<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ListingGalleryTest extends TestCase
{
    use RefreshDatabase;

    private array $temps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->temps as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function uploadImage(string $name = 'house.png', string $suffix = ''): UploadedFile
    {
        // Valid PNG fixture, independent of the optional GD PHP extension.
        $image = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=',
            true
        );
        $path = tempnam(sys_get_temp_dir(), 'barama-photo-');
        file_put_contents($path, $image.$suffix);
        $this->temps[] = $path;
        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function listing(string $slug = 'real-estate-gallery'): array
    {
        $company = Company::query()->create([
            'name' => 'Gallery Company '.$slug,
            'slug' => 'gallery-company-'.$slug,
            'status' => 'approved',
            'published_at' => now()->subDay(),
        ]);
        $property = Property::query()->create([
            'company_id' => $company->getKey(),
            'reference_code' => 'REF-'.$slug,
            'title' => 'Sample Home',
            'property_type' => 'villa',
            'status' => 'draft',
        ]);
        $listing = Listing::query()->create([
            'property_id' => $property->getKey(),
            'slug' => $slug,
            'transaction_type' => 'sale',
            'status' => 'draft',
            'price' => 140000,
            'currency' => 'USD',
        ]);
        return [$company, $property, $listing];
    }

    private function owner(Company $company, string $role = 'owner'): User
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $owner->getKey(),
            'role' => $role,
            'status' => 'active',
        ]);
        return $owner;
    }

    public function test_verified_company_owner_can_upload_preview_then_publish_photo(): void
    {
        [$company, $property, $listing] = $this->listing();
        $this->actingAs($this->owner($company))
            ->post(route('developer.gallery.store', [$company, $listing]), [
                'image' => $this->uploadImage(),
                'alt_text' => 'Front entrance',
            ])->assertRedirect(route('developer.properties.index', $company));

        $asset = MediaAsset::query()->firstOrFail();
        $attachment = MediaAttachment::query()->firstOrFail();
        $this->assertSame('private/media/', substr($asset->path, 0, 14));
        $this->assertSame('image/png', $asset->mime_type);
        $this->assertTrue($attachment->is_primary || $attachment->is_primary == 1);
        Storage::disk('local')->assertExists(substr($asset->path, 8));

        // Draft images must not be exposed through public URLs.
        $this->get(route('media.demo.show', $asset->checksum_sha256))->assertNotFound();
        $this->get(route('developer.gallery.preview', [$company, $listing, $asset]))
            ->assertOk()->assertHeader('Content-Type', 'image/png');

        // Once the same offer is approved and published, the image is public.
        $property->update(['status' => 'approved']);
        $listing->update(['status' => 'published', 'published_at' => now()->subHour()]);
        $this->get(route('media.demo.show', $asset->checksum_sha256))
            ->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('properties.show', $listing->slug))
            ->assertOk()->assertSee($asset->checksum_sha256);
        $this->get(route('developer.gallery.preview', [$company, $listing, $asset]))
            ->assertStatus(409);
    }

    public function test_duplicate_upload_is_idempotent_and_media_is_private_by_default(): void
    {
        [$company,, $listing] = $this->listing('duplicate-gallery');
        $this->actingAs($this->owner($company));
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('developer.gallery.store', [$company, $listing]), [
                'image' => $this->uploadImage(),
            ])->assertRedirect();
        }
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media_attachments', 1);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'developer.listing.media_added',
        ]);
    }

    public function test_tenant_and_read_only_members_cannot_upload_or_preview(): void
    {
        [$company,, $listing] = $this->listing('private-gallery');
        $other = Company::query()->create([
            'name' => 'Another Agency', 'slug' => 'unrelated-gallery-company',
            'status' => 'approved', 'published_at' => now()->subHour(),
        ]);
        $outsider = $this->owner($other);
        $this->actingAs($outsider)->post(route('developer.gallery.store', [$company, $listing]), [
            'image' => $this->uploadImage(),
        ])->assertForbidden();
        $this->actingAs($this->owner($company, 'member'))
            ->post(route('developer.gallery.store', [$company, $listing]), [
                'image' => $this->uploadImage(),
            ])->assertForbidden();
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_invalid_file_is_rejected_without_writing_data(): void
    {
        [$company,, $listing] = $this->listing('invalid-gallery');
        $this->actingAs($this->owner($company));
        $path = tempnam(sys_get_temp_dir(), 'barama-script-');
        file_put_contents($path, '<?php echo "not an image";');
        $this->temps[] = $path;
        $this->post(route('developer.gallery.store', [$company, $listing]), [
            'image' => new UploadedFile($path, 'bad.png', 'image/png', null, true),
        ])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media_attachments', 0);
    }

    public function test_detach_keeps_shared_original_and_requires_draft_access(): void
    {
        [$company,, $listing] = $this->listing('detach-gallery');
        $this->actingAs($this->owner($company))
            ->post(route('developer.gallery.store', [$company, $listing]), [
                'image' => $this->uploadImage(),
            ])->assertRedirect();
        $attachment = MediaAttachment::query()->firstOrFail();
        $asset = MediaAsset::query()->firstOrFail();
        $this->delete(route('developer.gallery.destroy', [$company, $listing, $attachment]))
            ->assertRedirect();
        $this->assertDatabaseCount('media_attachments', 0);
        $this->assertDatabaseCount('media_assets', 1);
        Storage::disk('local')->assertExists(substr($asset->path, 8));
        $this->assertDatabaseHas('audit_events', ['action' => 'developer.listing.media_detached']);
    }

    public function test_only_admin_can_read_media_inventory(): void
    {
        $this->get(route('admin.media.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get(route('admin.media.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['system_role' => 'super_admin']))
            ->get(route('admin.media.index'))->assertOk()->assertSee('Media Library');
    }
}
