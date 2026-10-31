<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MediaMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function media(string $name, string $seed = 'a'): MediaAsset
    {
        $sha = str_repeat($seed, 64);
        return MediaAsset::query()->create([
            'path' => 'private/media/'.$sha.'.png',
            'original_name' => $name,
            'mime_type' => 'image/png',
            'size_bytes' => 9204,
            'width_px' => 800,
            'height_px' => 600,
            'checksum_sha256' => $sha,
            'alt_text' => 'Original description',
        ]);
    }

    private function updateData(string $status = 'owned'): array
    {
        return [
            'alt_text' => 'Front exterior view of the property',
            'usage_rights' => $status,
            'rights_holder' => 'Verified Developer Company',
            'rights_reference' => 'License reference ABC-123',
        ];
    }

    public function test_only_super_admin_may_edit_rights_metadata(): void
    {
        $asset = $this->media('private-house.png');
        $this->patch(route('admin.media.metadata.update', $asset), $this->updateData())
            ->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch(
            route('admin.media.metadata.update', $asset), $this->updateData()
        )->assertForbidden();
        $this->assertSame('unreviewed', $asset->fresh()->usage_rights);
        $this->actingAs(User::factory()->create(['system_role' => 'super_admin']))
            ->patch(route('admin.media.metadata.update', $asset), $this->updateData())
            ->assertRedirect();
        $this->assertSame('owned', $asset->fresh()->usage_rights);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'admin.media.metadata_updated',
            'subject_type' => 'media_asset',
            'subject_id' => $asset->getKey(),
        ]);
    }

    public function test_updating_metadata_never_overwrites_private_image_or_listing_references(): void
    {
        $asset = $this->media('original.png');
        $linked = MediaAttachment::query()->create([
            'media_asset_id' => $asset->id,
            'entity_type' => 'listing',
            'entity_id' => 10001,
            'collection' => 'gallery',
            'position' => 0,
            'is_primary' => true,
        ]);
        $before = $asset->only(['path', 'checksum_sha256', 'size_bytes', 'mime_type']);
        $this->actingAs(User::factory()->create(['system_role' => 'super_admin']))
            ->patch(route('admin.media.metadata.update', $asset), $this->updateData('licensed'))
            ->assertRedirect();
        $this->assertSame($before, $asset->fresh()->only(array_keys($before)));
        $this->assertDatabaseHas('media_attachments', ['id' => $linked->id, 'media_asset_id' => $asset->id]);
        $this->assertSame('Front exterior view of the property', $asset->fresh()->alt_text);
        $this->assertSame('License reference ABC-123', $asset->fresh()->rights_reference);
    }

    public function test_rights_holder_and_license_reference_are_required_for_claimed_usage(): void
    {
        $asset = $this->media('metadata.png');
        $this->actingAs(User::factory()->create(['system_role' => 'super_admin']))
            ->patch(route('admin.media.metadata.update', $asset), [
                'usage_rights' => 'owned', 'alt_text' => 'Example image',
            ])->assertSessionHasErrors('rights_holder');
        $this->patch(route('admin.media.metadata.update', $asset), [
            'usage_rights' => 'licensed',
            'rights_holder' => 'Brand Name',
        ])->assertSessionHasErrors('rights_reference');
        $this->patch(route('admin.media.metadata.update', $asset), [
            'usage_rights' => 'public-domain',
            'rights_holder' => 'Unknown',
        ])->assertSessionHasErrors('usage_rights');
        $this->assertSame('unreviewed', $asset->fresh()->usage_rights);
    }

    public function test_media_search_and_rights_filter_is_administrator_only(): void
    {
        $this->media('Sunrise-house.png', 'a');
        $other = $this->media('Riverside-office.png', 'b');
        $other->update(['usage_rights' => 'licensed', 'rights_holder' => 'Licensed Agency']);
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.media.index'))
            ->assertOk()->assertSee('Sunrise-house.png')->assertSee('Riverside-office.png');
        $this->get(route('admin.media.index', ['q' => 'Sunrise']))
            ->assertSee('Sunrise-house.png')->assertDontSee('Riverside-office.png');
        $this->get(route('admin.media.index', ['rights' => 'licensed']))
            ->assertSee('Riverside-office.png')->assertDontSee('Sunrise-house.png');
        $this->get(route('admin.media.index', ['rights' => 'bogus']))
            ->assertSessionHasErrors('rights');
        $this->actingAs(User::factory()->create())
            ->get(route('admin.media.index'))->assertForbidden();
    }

    public function test_description_cannot_render_injected_markup(): void
    {
        $asset = $this->media('safe.png');
        $this->actingAs(User::factory()->create(['system_role' => 'super_admin']))
            ->patch(route('admin.media.metadata.update', $asset), [
                'usage_rights' => 'unreviewed',
                'alt_text' => '<script>alert(1)</script> Entrance',
            ])->assertRedirect();
        $this->get(route('admin.media.index'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }
}
