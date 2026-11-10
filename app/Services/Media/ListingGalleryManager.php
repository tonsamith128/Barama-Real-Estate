<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Company;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Services\Developer\CompanyCatalogWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class ListingGalleryManager
{
    public const MAX_IMAGES = 20;
    public const MAX_BYTES = 12 * 1024 * 1024;

    public function __construct(
        private readonly CompanyCatalogWriter $access,
        private readonly PrivateGalleryImageInspector $inspector,
        private readonly GalleryOrderService $order,
        private readonly \App\Services\Developer\CatalogRevisionRecorder $revisions,
    ) {}

    public function authorizeDraft(User $actor, Company $company, Listing $listing): void
    {
        $this->access->assertCanManage($actor, $company);
        abort_unless(
            $listing->property?->company_id === $company->getKey(),
            404
        );
        abort_unless($listing->status === 'draft'
            && $listing->property->status === 'draft', 409);
    }

    public function upload(
        User $actor,
        Company $company,
        Listing $listing,
        UploadedFile $file,
        ?string $alt = null,
    ): MediaAttachment {
        $this->authorizeDraft($actor, $company, $listing);
        $validated = $this->inspector->inspect($file);
        $sha = $validated['sha'];
        $mime = $validated['mime'];
        $name = $validated['name'];
        $info = $validated['info'];
        $disk = Storage::disk('local');
        $savedByThisRequest = false;

        try {
            return DB::transaction(function () use (
                $actor, $company, $listing, $file, $alt,
                $sha, $name, $mime, $info, $disk, &$savedByThisRequest,
            ): MediaAttachment {
                $locked = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
                $this->authorizeDraft($actor, $company, $locked);
                $gallery = MediaAttachment::query()
                    ->where('entity_type', 'listing')
                    ->where('entity_id', $locked->getKey());
                $existing = MediaAsset::query()->where('checksum_sha256', $sha)->first();

                if ($existing !== null) {
                    $link = (clone $gallery)->where('media_asset_id', $existing->getKey())->first();
                    if ($link !== null) {
                        return $link; // safe idempotent retry
                    }
                }

                $count = (clone $gallery)->count();
                if ($count >= self::MAX_IMAGES) {
                    throw ValidationException::withMessages([
                        'image' => 'A listing may contain at most 20 images.',
                    ]);
                }

                if ($existing === null) {
                    $relative = 'media/'.$name;
                    if (!$disk->exists($relative)) {
                        if ($disk->putFileAs('media', $file, $name) !== $relative) {
                            throw new RuntimeException('Could not save the private image.');
                        }
                        $savedByThisRequest = true;
                    }
                    $existing = MediaAsset::query()->create([
                        'path' => 'private/'.$relative,
                        'original_name' => substr(basename($file->getClientOriginalName()), 0, 255),
                        'mime_type' => $mime,
                        'size_bytes' => $file->getSize(),
                        'width_px' => (int) $info[0],
                        'height_px' => (int) $info[1],
                        'alt_text' => $alt,
                        'checksum_sha256' => $sha,
                    ]);
                }

                $attachment = MediaAttachment::query()->create([
                    'media_asset_id' => $existing->getKey(),
                    'entity_type' => 'listing',
                    'entity_id' => $locked->getKey(),
                    'collection' => 'gallery',
                    'position' => (int) ((clone $gallery)->max('position') ?? -1) + 1,
                    'is_primary' => $count === 0,
                ]);
                DB::table('audit_events')->insert([
                    'actor_id' => $actor->getKey(),
                    'action' => 'developer.listing.media_added',
                    'subject_type' => 'listing',
                    'subject_id' => $locked->getKey(),
                    'context' => json_encode([
                        'company_id' => $company->getKey(),
                        'media_asset_id' => $existing->getKey(),
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                return $attachment;
            });
        } catch (Throwable $error) {
            if ($savedByThisRequest) {
                $disk->delete('media/'.$name);
            }
            throw $error;
        }
    }

    public function detach(
        User $actor, Company $company, Listing $listing, MediaAttachment $attachment
    ): void {
        $this->authorizeDraft($actor, $company, $listing);
        DB::transaction(function () use ($actor, $company, $listing, $attachment): void {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $this->authorizeDraft($actor, $company, $locked);
            $link = MediaAttachment::query()
                ->where('entity_type', 'listing')
                ->where('entity_id', $locked->getKey())
                ->whereKey($attachment->getKey())->firstOrFail();
            $link->delete(); // retain shared media and physical assets
            $remaining = MediaAttachment::query()
                ->where('entity_type', 'listing')->where('entity_id', $locked->getKey())
                ->orderBy('position')->orderBy('id')->get();
            foreach ($remaining as $i => $item) {
                $item->update(['position' => $i, 'is_primary' => $i === 0]);
            }
            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(),
                'action' => 'developer.listing.media_detached',
                'subject_type' => 'listing',
                'subject_id' => $locked->getKey(),
                'context' => json_encode([
                    'company_id' => $company->getKey(),
                    'media_attachment_id' => $attachment->getKey(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function changeOrder(
        User $actor, Company $company, Listing $listing,
        MediaAttachment $attachment, string $action,
    ): void {
        $this->authorizeDraft($actor, $company, $listing);

        DB::transaction(function () use ($actor, $company, $listing, $attachment, $action): void {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $this->authorizeDraft($actor, $company, $locked);
            $positions = $this->order->change('listing', (int) $locked->getKey(),
                (int) $attachment->getKey(), $action);

            if ($positions['before'] !== $positions['after']) {
                $this->revisions->record($actor, 'listing', $locked, 'gallery_changed',
                    ['media_attachment_ids' => $positions['before']],
                    ['media_attachment_ids' => $positions['after']]);
                DB::table('audit_events')->insert([
                    'actor_id' => $actor->getKey(),
                    'action' => 'developer.listing.gallery_reordered',
                    'subject_type' => 'listing',
                    'subject_id' => $locked->getKey(),
                    'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

}
