<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMediaRevision;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanyMediaRevisionManager
{
    public const COLLECTIONS = ['logo', 'cover'];

    public function __construct(private readonly CompanyMediaCapabilities $capabilities)
    {
    }

    /**
     * A request is a DB-only pending proposal: no publicly visible company
     * attachments are changed and no media files are deleted or overwritten.
     */
    public function request(
        User $actor, Company $company, MediaAsset $asset,
        string $collection, string $reason,
    ): CompanyMediaRevision {
        $this->capabilities->assertRequest($actor);
        abort_unless(in_array($collection, self::COLLECTIONS, true), 422);
        $reason = trim($reason);
        abort_unless(mb_strlen($reason) >= 10, 422);

        return DB::transaction(function () use ($actor, $company, $asset, $collection, $reason): CompanyMediaRevision {
            $locked = Company::query()->lockForUpdate()->findOrFail($company->getKey());
            abort_unless($locked->status === 'approved' && $locked->published_at?->lte(now()), 409);

            $media = MediaAsset::query()->findOrFail($asset->getKey());
            abort_unless(
                in_array($media->usage_rights, ['owned', 'licensed'], true)
                && in_array($media->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true)
                && str_starts_with($media->path, 'private/media/'),
                422
            );

            $pending = CompanyMediaRevision::query()->where('company_id', $locked->getKey())
                ->where('collection', $collection)->where('status', 'pending')->exists();
            abort_if($pending, 409, 'A correction is already under review.');

            $linkedElsewhere = MediaAttachment::query()
                ->where('entity_type', 'company')
                ->where('entity_id', $locked->getKey())
                ->where('media_asset_id', $media->getKey())->exists();
            abort_if($linkedElsewhere, 409, 'This image is already linked to this company.');

            $old = MediaAttachment::query()
                ->where('entity_type', 'company')
                ->where('entity_id', $locked->getKey())
                ->where('collection', $collection)->where('is_primary', true)->first();

            $revision = CompanyMediaRevision::query()->create([
                'company_id' => $locked->getKey(),
                'media_asset_id' => $media->getKey(),
                'previous_attachment_id' => $old?->getKey(),
                'requested_by' => $actor->getKey(),
                'collection' => $collection,
                'status' => 'pending',
                'reason' => $reason,
            ]);

            $this->audit($actor, $locked, $revision, 'requested');

            return $revision;
        });
    }

    public function decide(
        User $actor, CompanyMediaRevision $revision,
        string $decision, ?string $reason = null,
    ): void {
        $this->capabilities->assertReview($actor);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        $reason = trim($reason ?? '');
        abort_if($decision === 'rejected' && mb_strlen($reason) < 10, 422);

        DB::transaction(function () use ($actor, $revision, $decision, $reason): void {
            $locked = CompanyMediaRevision::query()
                ->lockForUpdate()->findOrFail($revision->getKey());
            abort_unless($locked->status === 'pending', 409);
            abort_if($locked->requested_by === $actor->getKey(), 403, 'A second administrator must review this request.');

            $company = Company::query()->lockForUpdate()->findOrFail($locked->company_id);
            abort_unless($company->status === 'approved' && $company->published_at?->lte(now()), 409);

            $newAttachmentId = null;

            if ($decision === 'approved') {
                $current = MediaAttachment::query()
                    ->where('entity_type', 'company')
                    ->where('entity_id', $company->getKey())
                    ->where('collection', $locked->collection)
                    ->where('is_primary', true)
                    ->lockForUpdate()->first();

                abort_unless($current?->getKey() === $locked->previous_attachment_id, 409,
                    'Company media changed while this correction was pending.');

                $asset = MediaAsset::query()->findOrFail($locked->media_asset_id);
                abort_unless(in_array($asset->usage_rights, ['owned', 'licensed'], true)
                    && in_array($asset->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
                    && str_starts_with($asset->path, 'private/media/'), 422);

                abort_if(MediaAttachment::query()
                    ->where('entity_type', 'company')
                    ->where('entity_id', $company->getKey())
                    ->where('media_asset_id', $asset->getKey())->exists(), 409);

                if ($current !== null) {
                    // History retains the original attachment and original file.
                    $current->update([
                        'collection' => 'history_'.$locked->collection,
                        'is_primary' => false,
                    ]);
                }

                $new = MediaAttachment::query()->create([
                    'entity_type' => 'company',
                    'entity_id' => $company->getKey(),
                    'media_asset_id' => $asset->getKey(),
                    'collection' => $locked->collection,
                    'is_primary' => true,
                    'position' => 0,
                ]);
                $newAttachmentId = $new->getKey();
            }

            $locked->update([
                'status' => $decision,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_reason' => $reason === '' ? null : $reason,
                'approved_attachment_id' => $newAttachmentId,
            ]);

            $this->audit($actor, $company, $locked, $decision);
            AccountNotification::query()->firstOrCreate([
                'user_id' => $locked->requested_by,
                'dedupe_key' => 'company-media-review:'.$locked->getKey(),
            ], [
                'title' => $decision === 'approved'
                    ? 'Company media correction approved'
                    : 'Company media correction needs changes',
                'message' => $company->name.' · '.ucfirst($locked->collection).' correction '.$decision.'.',
                'type' => 'company_media_'.$decision,
            ]);
        });
    }

    private function audit(
        User $actor, Company $company, CompanyMediaRevision $revision, string $action,
    ): void {
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'admin.company.media.'.$action,
            'subject_type' => 'company',
            'subject_id' => $company->getKey(),
            'context' => json_encode([
                'revision_id' => $revision->getKey(),
                'collection' => $revision->collection,
                'media_asset_id' => $revision->media_asset_id,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
