<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Services\Marketplace\PublicMediaAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyPublicMediaVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function asset(string $letter): MediaAsset
    {
        return MediaAsset::query()->create([
            'path' => 'private/media/'.str_repeat($letter, 64).'.png',
            'original_name' => 'company-'.$letter.'.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'checksum_sha256' => str_repeat($letter, 64),
            'usage_rights' => 'owned',
        ]);
    }

    private function company(string $status = 'approved'): Company
    {
        return Company::query()->create([
            'name' => 'Company Visibility QA',
            'slug' => 'company-visibility-qa',
            'status' => $status,
            'published_at' => now()->subDay(),
        ]);
    }

    private function link(Company $company, MediaAsset $asset, string $collection, bool $primary): void
    {
        MediaAttachment::query()->create([
            'entity_type' => 'company',
            'entity_id' => $company->getKey(),
            'media_asset_id' => $asset->getKey(),
            'collection' => $collection,
            'is_primary' => $primary,
            'position' => 0,
        ]);
    }

    private function assertPrivate(MediaAsset $asset): void
    {
        try {
            app(PublicMediaAccess::class)->findByDigest($asset->checksum_sha256);
            $this->fail('The asset should not be exposed by a public media lookup.');
        } catch (ModelNotFoundException $expected) {
            $this->assertTrue(true);
        }
    }

    public function test_only_active_primary_logo_or_cover_is_public(): void
    {
        $company = $this->company();
        $activeLogo = $this->asset('a');
        $activeCover = $this->asset('b');
        $archived = $this->asset('c');
        $unapprovedPending = $this->asset('d');

        $this->link($company, $activeLogo, 'logo', true);
        $this->link($company, $activeCover, 'cover', true);
        $this->link($company, $archived, 'history_logo', false);
        // A pending revision points only to a media asset, not to an active
        // attachment. No publication should occur before separate review.

        $this->assertSame($activeLogo->id,
            app(PublicMediaAccess::class)->findByDigest($activeLogo->checksum_sha256)->id);
        $this->assertSame($activeCover->id,
            app(PublicMediaAccess::class)->findByDigest($activeCover->checksum_sha256)->id);
        $this->assertPrivate($archived);
        $this->assertPrivate($unapprovedPending);
    }

    public function test_draft_company_media_is_never_public_even_with_primary_logo(): void
    {
        $company = $this->company('pending');
        $asset = $this->asset('e');
        $this->link($company, $asset, 'logo', true);
        $this->assertPrivate($asset);
    }
}
