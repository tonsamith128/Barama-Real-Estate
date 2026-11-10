<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class GalleryOrderService
{
    /**
     * Caller must hold a lock on the owning draft listing or project, in a
     * transaction. Only its gallery attachments may be reordered.
     *
     * @return array{before: array<int, int>, after: array<int, int>}
     */
    public function change(
        string $type, int $subjectId, int $attachmentId, string $action,
    ): array {
        abort_unless(in_array($type, ['listing', 'project'], true), 422);
        abort_unless(in_array($action, ['primary', 'up', 'down'], true), 422);

        /** @var Collection<int, MediaAttachment> $attachments */
        $attachments = MediaAttachment::query()
            ->where('entity_type', $type)
            ->where('entity_id', $subjectId)
            ->where('collection', 'gallery')
            ->orderBy('position')->orderBy('id')->lockForUpdate()->get();

        $ids = $attachments->pluck('id')->map(fn ($id) => (int) $id)->all();
        $index = array_search($attachmentId, $ids, true);
        abort_if($index === false, 404);

        $updated = $ids;
        if ($action === 'primary') {
            array_splice($updated, $index, 1);
            array_unshift($updated, $attachmentId);
        } else {
            $next = $index + ($action === 'up' ? -1 : 1);
            if ($next >= 0 && $next < count($updated)) {
                [$updated[$index], $updated[$next]] = [$updated[$next], $updated[$index]];
            }
        }

        if ($ids !== $updated) {
            foreach ($updated as $i => $id) {
                $attachment = $attachments->firstWhere('id', $id);
                $attachment->update(['position' => $i, 'is_primary' => $i === 0]);
            }
        }

        return ['before' => $ids, 'after' => $updated];
    }
}
