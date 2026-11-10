<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMediaRevision;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Models\UserCapabilityGrant;
use App\Services\Media\CompanyMediaCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class CompanyMediaRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'system_role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
    }

    private function company(): Company
    {
        return Company::query()->create([
            'name' => 'Public Realty', 'slug' => 'public-realty',
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function asset(string $hash, string $rights = 'owned'): MediaAsset
    {
        return MediaAsset::query()->create([
            'path' => 'private/media/'.$hash.'.png',
            'original_name' => $hash.'.png',
            'mime_type' => 'image/png',
            'size_bytes' => 2048,
            'checksum_sha256' => str_repeat($hash, 64),
            'usage_rights' => $rights,
        ]);
    }

    /** @param array<int, string> $capabilities */
    private function allow(User $admin, array $capabilities): void
    {
        foreach ($capabilities as $capability) {
            UserCapabilityGrant::query()->create([
                'user_id' => $admin->id,
                'capability' => $capability,
                'granted_by' => $admin->id,
            ]);
        }
    }

    private function requestBody(Company $company, MediaAsset $asset): array
    {
        return [
            'company_id' => $company->id,
            'media_asset_id' => $asset->id,
            'collection' => 'logo',
            'reason' => 'Update outdated company branding with the approved logo.',
        ];
    }

    public function test_two_admins_require_explicit_grants_and_preserve_published_company_media_history(): void
    {
        $requester = $this->admin();
        $reviewer = $this->admin();
        $company = $this->company();
        $oldAsset = $this->asset('a');
        $newAsset = $this->asset('b');
        $oldAttachment = MediaAttachment::query()->create([
            'entity_type' => 'company', 'entity_id' => $company->id,
            'media_asset_id' => $oldAsset->id,
            'collection' => 'logo', 'is_primary' => true,
            'position' => 0,
        ]);

        $this->actingAs($requester)->post(route('admin.company-media.revisions.request'),
            $this->requestBody($company, $newAsset))->assertForbidden();
        $this->assertDatabaseCount('company_media_revisions', 0);

        $this->allow($requester, [
            CompanyMediaCapabilities::COMPANY_MANAGE,
            CompanyMediaCapabilities::MEDIA_MANAGE,
            CompanyMediaCapabilities::MEDIA_CORRECT,
        ]);
        $this->allow($reviewer, [
            CompanyMediaCapabilities::COMPANY_MANAGE,
            CompanyMediaCapabilities::MEDIA_MANAGE,
            CompanyMediaCapabilities::MEDIA_REVIEW,
        ]);

        $this->post(route('admin.company-media.revisions.request'),
            $this->requestBody($company, $newAsset))->assertRedirect();

        $revision = CompanyMediaRevision::query()->firstOrFail();
        $this->assertSame('pending', $revision->status);
        $this->assertSame('approved', $company->fresh()->status);
        $this->assertSame($oldAttachment->id, $revision->previous_attachment_id);
        $this->assertSame('logo', $oldAttachment->fresh()->collection);
        $this->assertDatabaseMissing('media_attachments', [
            'entity_type' => 'company', 'entity_id' => $company->id,
            'media_asset_id' => $newAsset->id,
        ]);

        $this->post(route('admin.company-media.revisions.request'),
            $this->requestBody($company, $newAsset))->assertStatus(409);

        $this->post(route('admin.company-media.revisions.decide', $revision),
            ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($reviewer)->post(route('admin.company-media.revisions.decide', $revision),
            ['decision' => 'approved'])->assertRedirect();

        $this->assertSame('approved', $revision->fresh()->status);
        $this->assertNotNull($revision->fresh()->approved_attachment_id);
        $this->assertSame('approved', $company->fresh()->status);
        $this->assertNotNull($company->fresh()->published_at);
        $this->assertSame('history_logo', $oldAttachment->fresh()->collection);
        $this->assertFalse((bool) $oldAttachment->fresh()->is_primary);
        $this->assertDatabaseHas('media_attachments', [
            'entity_type' => 'company', 'entity_id' => $company->id,
            'media_asset_id' => $newAsset->id,
            'collection' => 'logo', 'is_primary' => 1,
        ]);
        $this->assertDatabaseHas('media_assets', ['id' => $oldAsset->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $newAsset->id]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'admin.company.media.approved',
            'actor_id' => $reviewer->id,
        ]);
        $this->assertDatabaseHas('account_notifications', [
            'user_id' => $requester->id,
            'type' => 'company_media_approved',
        ]);

        $this->post(route('admin.company-media.revisions.decide', $revision),
            ['decision' => 'approved'])->assertStatus(409);
        $this->assertSame(2, MediaAttachment::query()->count());
        $this->assertSame(1, AccountNotification::query()->count());
    }

    public function test_unreviewed_assets_cannot_be_requested_and_rejection_does_not_replace_media(): void
    {
        $requester = $this->admin();
        $reviewer = $this->admin();
        $company = $this->company();
        $unreviewed = $this->asset('c', 'unreviewed');
        $usable = $this->asset('d');

        $this->allow($requester, [
            CompanyMediaCapabilities::COMPANY_MANAGE,
            CompanyMediaCapabilities::MEDIA_MANAGE,
            CompanyMediaCapabilities::MEDIA_CORRECT,
        ]);
        $this->allow($reviewer, [
            CompanyMediaCapabilities::COMPANY_MANAGE,
            CompanyMediaCapabilities::MEDIA_MANAGE,
            CompanyMediaCapabilities::MEDIA_REVIEW,
        ]);

        $this->actingAs($requester)->post(route('admin.company-media.revisions.request'),
            $this->requestBody($company, $unreviewed))->assertStatus(422);

        $this->post(route('admin.company-media.revisions.request'),
            $this->requestBody($company, $usable))->assertRedirect();

        $revision = CompanyMediaRevision::query()->firstOrFail();
        $this->actingAs($reviewer)->post(route('admin.company-media.revisions.decide', $revision), [
            'decision' => 'rejected',
        ])->assertSessionHasErrors('reason');

        $this->post(route('admin.company-media.revisions.decide', $revision), [
            'decision' => 'rejected',
            'reason' => 'This logo requires a clearer rights license reference.',
        ])->assertRedirect();

        $this->assertSame('rejected', $revision->fresh()->status);
        $this->assertDatabaseCount('media_attachments', 0);
        $this->assertSame('approved', $company->fresh()->status);
    }

    public function test_only_verified_system_administrators_can_receive_capabilities(): void
    {
        $actor = $this->admin();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($actor)->post(route('admin.company-media.capabilities.grant'), [
            'email' => $other->email,
            'capability' => CompanyMediaCapabilities::MEDIA_CORRECT,
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('user_capability_grants', 0);

        $adminTwo = $this->admin();
        $this->post(route('admin.company-media.capabilities.grant'), [
            'email' => $adminTwo->email,
            'capability' => CompanyMediaCapabilities::MEDIA_CORRECT,
        ])->assertRedirect();

        $grant = UserCapabilityGrant::query()->firstOrFail();
        $this->assertSame($adminTwo->id, $grant->user_id);

        $this->delete(route('admin.company-media.capabilities.revoke', $grant))->assertRedirect();
        $this->assertDatabaseCount('user_capability_grants', 0);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'admin.company.capability.revoked',
            'subject_id' => $adminTwo->id,
        ]);
    }
}
