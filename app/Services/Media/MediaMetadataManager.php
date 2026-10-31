<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MediaMetadataManager
{
    public const RIGHTS = [
        'unreviewed' => 'Not reviewed',
        'owned' => 'Owned by rights holder',
        'licensed' => 'Licensed with supporting reference',
        'restricted' => 'Restricted / needs review',
    ];

    /**
     * Metadata and recorded rights are administrative declarations, not
     * verification of copyright ownership or external licensing contracts.
     * Images, checksum, storage location and attachments remain unchanged.
     */
    public function update(User $actor, MediaAsset $asset, array $data): void
    {
        DB::transaction(function () use ($actor, $asset, $data): void {
            $locked = MediaAsset::query()->lockForUpdate()->findOrFail($asset->getKey());
            $locked->update([
                'alt_text' => $data['alt_text'] ?? null,
                'usage_rights' => $data['usage_rights'],
                'rights_holder' => $data['rights_holder'] ?? null,
                'rights_reference' => $data['rights_reference'] ?? null,
            ]);

            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(),
                'action' => 'admin.media.metadata_updated',
                'subject_type' => 'media_asset',
                'subject_id' => $locked->getKey(),
                'context' => json_encode([
                    'usage_rights' => $locked->usage_rights,
                    'checksum_sha256' => $locked->checksum_sha256,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
