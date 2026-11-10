<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Project;
use App\Models\User;
use App\Services\Developer\CatalogRevisionRecorder;
use App\Services\Developer\ProjectManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** Reuses secure private-media storage; never mutates published projects. */
final class ProjectGalleryManager
{
    public const MAX_IMAGES = 20;

    public function __construct(
        private readonly ProjectManager $access,
        private readonly PrivateGalleryImageInspector $inspector,
        private readonly GalleryOrderService $order,
        private readonly CatalogRevisionRecorder $revisions,
    ) {
    }

    public function authorizeDraft(User $actor, Company $company, Project $project): void
    {
        $this->access->assertOwner($actor, $company, $project);
        abort_unless($project->status === 'draft', 409);
    }

    public function upload(
        User $actor, Company $company, Project $project,
        UploadedFile $file, ?string $alt = null,
    ): MediaAttachment {
        $this->authorizeDraft($actor, $company, $project);
        $verified = $this->inspector->inspect($file);
        $sha = $verified['sha'];
        $name = $verified['name'];
        $info = $verified['info'];
        $disk = Storage::disk('local');
        $savedByThisRequest = false;

        try {
            return DB::transaction(function () use (
                $actor, $company, $project, $file, $alt,
                $sha, $name, $info, $disk, &$savedByThisRequest,
            ): MediaAttachment {
                $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
                $this->authorizeDraft($actor, $company, $locked);

                $gallery = MediaAttachment::query()
                    ->where('entity_type', 'project')
                    ->where('entity_id', $locked->getKey())
                    ->where('collection', 'gallery');
                $before = (clone $gallery)->orderBy('position')->orderBy('id')
                    ->pluck('id')->map(fn ($id) => (int) $id)->all();
                $asset = MediaAsset::query()->where('checksum_sha256', $sha)->first();

                if ($asset !== null) {
                    $already = (clone $gallery)->where('media_asset_id', $asset->getKey())->first();
                    if ($already !== null) {
                        return $already;
                    }
                }

                if (count($before) >= self::MAX_IMAGES) {
                    throw ValidationException::withMessages([
                        'image' => 'Project galleries may contain at most 20 images.',
                    ]);
                }

                if ($asset === null) {
                    $relative = 'media/'.$name;
                    if (!$disk->exists($relative)) {
                        if ($disk->putFileAs('media', $file, $name) !== $relative) {
                            throw new RuntimeException('Could not save the private project image.');
                        }
                        $savedByThisRequest = true;
                    }
                    $asset = MediaAsset::query()->create([
                        'path' => 'private/'.$relative,
                        'original_name' => substr(basename($file->getClientOriginalName()), 0, 255),
                        'mime_type' => $info['mime'],
                        'size_bytes' => $file->getSize(),
                        'width_px' => (int) $info[0],
                        'height_px' => (int) $info[1],
                        'alt_text' => $alt,
                        'checksum_sha256' => $sha,
                    ]);
                }

                $attachment = MediaAttachment::query()->create([
                    'entity_type' => 'project',
                    'entity_id' => $locked->getKey(),
                    'media_asset_id' => $asset->getKey(),
                    'collection' => 'gallery',
                    'position' => count($before),
                    'is_primary' => $before === [],
                ]);

                $this->recordChange($actor, $company, $locked, $before,
                    [...$before, (int) $attachment->getKey()], 'developer.project.media_added');
                return $attachment;
            });
        } catch (Throwable $error) {
            // Do not remove shared or previously stored source media.
            if ($savedByThisRequest
                && !MediaAsset::query()->where('checksum_sha256', $sha)->exists()) {
                $disk->delete('media/'.$name);
            }
            throw $error;
        }
    }

    public function detach(
        User $actor, Company $company, Project $project, MediaAttachment $attachment,
    ): void {
        $this->authorizeDraft($actor, $company, $project);
        DB::transaction(function () use ($actor, $company, $project, $attachment): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $this->authorizeDraft($actor, $company, $locked);

            $gallery = MediaAttachment::query()
                ->where('entity_type', 'project')
                ->where('entity_id', $locked->getKey())
                ->where('collection', 'gallery');
            $before = (clone $gallery)->orderBy('position')->orderBy('id')
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $link = (clone $gallery)->whereKey($attachment->getKey())->firstOrFail();
            $link->delete();

            $remaining = (clone $gallery)->orderBy('position')->orderBy('id')->get();
            $after = [];
            foreach ($remaining as $index => $item) {
                $item->update(['position' => $index, 'is_primary' => $index === 0]);
                $after[] = (int) $item->getKey();
            }

            $this->recordChange($actor, $company, $locked, $before, $after,
                'developer.project.media_detached');
        });
    }

    public function changeOrder(
        User $actor, Company $company, Project $project,
        MediaAttachment $attachment, string $action,
    ): void {
        $this->authorizeDraft($actor, $company, $project);
        DB::transaction(function () use ($actor, $company, $project, $attachment, $action): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $this->authorizeDraft($actor, $company, $locked);
            $changed = $this->order->change(
                'project', (int) $locked->getKey(),
                (int) $attachment->getKey(), $action,
            );
            if ($changed['before'] !== $changed['after']) {
                $this->recordChange($actor, $company, $locked,
                    $changed['before'], $changed['after'],
                    'developer.project.gallery_reordered');
            }
        });
    }

    /**
     * @param array<int, int> $before
     * @param array<int, int> $after
     */
    private function recordChange(
        User $actor, Company $company, Project $project,
        array $before, array $after, string $event,
    ): void {
        $this->revisions->record($actor, 'project', $project, 'gallery_changed',
            ['media_attachment_ids' => $before],
            ['media_attachment_ids' => $after]);
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => $event,
            'subject_type' => 'project',
            'subject_id' => $project->getKey(),
            'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
