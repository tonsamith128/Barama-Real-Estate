<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublishedCompanyMediaPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function company(string $slug, string $status = 'approved'): Company
    {
        return Company::query()->create([
            'name' => 'Property Group '.$slug,
            'slug' => $slug,
            'status' => $status,
            'company_type' => 'agency',
            'published_at' => now()->subDay(),
        ]);
    }

    private function asset(string $character, string $rights = 'owned'): MediaAsset
    {
        $digest = str_repeat($character, 64);
        Storage::disk('local')->put('media/'.$digest.'.png', 'isolated-fixture-image');
        return MediaAsset::query()->create([
            'path' => 'private/media/'.$digest.'.png',
            'original_name' => $character.'.png',
            'mime_type' => 'image/png',
            'checksum_sha256' => $digest,
            'size_bytes' => 22,
            'usage_rights' => $rights,
        ]);
    }

    private function attach(
        Company $company, MediaAsset $asset, string $collection, bool $primary = true
    ): MediaAttachment {
        return MediaAttachment::query()->create([
            'entity_type' => 'company',
            'entity_id' => $company->getKey(),
            'media_asset_id' => $asset->getKey(),
            'collection' => $collection,
            'is_primary' => $primary,
            'position' => 0,
        ]);
    }

    public function test_directory_detail_and_protected_binary_route_reuse_current_company_logo_and_cover(): void
    {
        $company = $this->company('eligible-company');
        $logo = $this->asset('a');
        $cover = $this->asset('b', 'licensed');
        $this->attach($company, $logo, 'logo');
        $this->attach($company, $cover, 'cover');

        $logoUrl = route('media.company.show', ['slug' => $company->slug, 'collection' => 'logo']);
        $coverUrl = route('media.company.show', ['slug' => $company->slug, 'collection' => 'cover']);

        $this->get(route('companies.index'))
            ->assertOk()->assertSee('Property Group eligible-company')
            ->assertSee($logoUrl)->assertDontSee($coverUrl);

        $this->get(route('companies.show', ['slug' => $company->slug]))
            ->assertOk()->assertSee($logoUrl)->assertSee($coverUrl)
            ->assertSee('Property Group eligible-company');

        $this->get($logoUrl)
            ->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'private, no-store');

        $this->get($coverUrl)
            ->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_draft_and_pending_company_images_are_not_public_even_with_primary_attachment(): void
    {
        $company = $this->company('private-company', 'pending');
        $logo = $this->asset('c');
        $this->attach($company, $logo, 'logo');

        $url = route('media.company.show', ['slug' => $company->slug, 'collection' => 'logo']);
        $this->get($url)->assertNotFound();
        $this->get(route('companies.index'))->assertOk()->assertDontSee($url);
        $this->get(route('companies.show', ['slug' => $company->slug]))->assertNotFound();

        $company->update(['status' => 'approved', 'published_at' => now()->addDay()]);
        $this->get($url)->assertNotFound();
    }

    public function test_archived_or_unreviewed_media_never_becomes_a_public_company_image(): void
    {
        $company = $this->company('history-company');
        $old = $this->asset('d');
        $unreviewed = $this->asset('e', 'unreviewed');
        $this->attach($company, $old, 'history_logo', false);
        $this->attach($company, $unreviewed, 'logo');

        $url = route('media.company.show', ['slug' => $company->slug, 'collection' => 'logo']);
        $this->get($url)->assertNotFound();
        $this->get(route('companies.show', ['slug' => $company->slug]))
            ->assertOk()->assertDontSee($url);
        $this->get(route('media.demo.show', ['digest' => $old->checksum_sha256]))
            ->assertNotFound();
        $this->get(route('media.company.show', ['slug' => $company->slug, 'collection' => 'history_logo']))
            ->assertNotFound();
    }

    public function test_approved_revision_uses_new_primary_and_preserves_original_without_caching_stale_slug_url(): void
    {
        $company = $this->company('revision-company');
        $original = $this->asset('f');
        $replacement = $this->asset('1');
        $attachment = $this->attach($company, $original, 'logo');
        $url = route('media.company.show', ['slug' => $company->slug, 'collection' => 'logo']);

        $this->get($url)->assertOk();

        $attachment->update(['collection' => 'history_logo', 'is_primary' => false]);
        $this->attach($company, $replacement, 'logo');

        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'private, no-store');
        $this->assertDatabaseHas('media_assets', ['id' => $original->getKey()]);
        $this->assertDatabaseHas('media_assets', ['id' => $replacement->getKey()]);
        $this->get(route('media.demo.show', ['digest' => $original->checksum_sha256]))
            ->assertNotFound();

        $company->update(['status' => 'rejected', 'published_at' => null]);
        $this->get($url)->assertNotFound();
    }

    public function test_unrelated_company_cannot_fetch_another_company_primary_image(): void
    {
        $first = $this->company('first-company');
        $second = $this->company('second-company');
        $logo = $this->asset('2');
        $this->attach($first, $logo, 'logo');

        $this->get(route('media.company.show', ['slug' => $first->slug, 'collection' => 'logo']))
            ->assertOk();
        $this->get(route('media.company.show', ['slug' => $second->slug, 'collection' => 'logo']))
            ->assertNotFound();
    }
}
