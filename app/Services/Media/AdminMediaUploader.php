<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class AdminMediaUploader
{
    public const MAX_BYTES = 12 * 1024 * 1024;

    public function upload(User $actor, UploadedFile $file, ?string $altText): MediaAsset
    {
        abort_unless($actor->isSystemAdministrator(), 403);

        if (!$file->isValid() || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['image' => 'Please choose an image up to 12 MB.']);
        }

        $source = $file->getRealPath();
        $info = $source ? @getimagesize($source) : false;
        $formats = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

        if (!is_array($info) || !isset($formats[$info['mime'] ?? ''])
            || (int) $info[0] < 1 || (int) $info[1] < 1
            || (int) $info[0] > 8000 || (int) $info[1] > 8000
            || (int) $info[0] * (int) $info[1] > 24000000) {
            throw ValidationException::withMessages([
                'image' => 'Choose a valid PNG, JPEG or WebP image up to 24 megapixels.',
            ]);
        }

        $sha = hash_file('sha256', $source);
        if (!is_string($sha)) {
            throw new RuntimeException('Unable to calculate image checksum.');
        }
        $relative = 'media/'.$sha.'.'.$formats[$info['mime']];
        $disk = Storage::disk('local');
        $newFile = false;

        try {
            return DB::transaction(function () use (
                $actor, $file, $altText, $info, $sha, $relative, $disk, &$newFile,
            ): MediaAsset {
                $asset = MediaAsset::query()->where('checksum_sha256', $sha)->first();
                if ($asset === null) {
                    if (!$disk->exists($relative)) {
                        if ($disk->putFileAs('media', $file, basename($relative)) !== $relative) {
                            throw new RuntimeException('Unable to store private image.');
                        }
                        $newFile = true;
                    }

                    $asset = MediaAsset::query()->create([
                        'path' => 'private/'.$relative,
                        'original_name' => substr(basename($file->getClientOriginalName()), 0, 255),
                        'mime_type' => $info['mime'],
                        'size_bytes' => $file->getSize(),
                        'width_px' => (int) $info[0],
                        'height_px' => (int) $info[1],
                        'alt_text' => $altText,
                        'checksum_sha256' => $sha,
                        'usage_rights' => 'unreviewed',
                    ]);
                }

                DB::table('audit_events')->insert([
                    'actor_id' => $actor->getKey(), 'action' => 'admin.media.uploaded',
                    'subject_type' => 'media_asset', 'subject_id' => $asset->getKey(),
                    'context' => json_encode(['sha256' => $sha], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return $asset;
            });
        } catch (Throwable $e) {
            // Never remove an existing or shared original.
            if ($newFile && !MediaAsset::query()->where('checksum_sha256', $sha)->exists()) {
                $disk->delete($relative);
            }
            throw $e;
        }
    }
}
