<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\Listing;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CatalogReviewService
{
    public function decideProject(User $actor, Project $project, string $decision, ?string $reason = null): void
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        DB::transaction(function () use ($actor, $project, $decision, $reason): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            abort_unless($locked->status === 'pending', 409);
            $company = $locked->company()->firstOrFail();
            abort_unless($company->status === 'approved' && $company->published_at?->lte(now()), 422);
            $locked->update([
                'status' => $decision === 'approved' ? 'published' : 'rejected',
                'published_at' => $decision === 'approved' ? now() : null,
            ]);
            $this->record($actor, 'project', $locked->getKey(), $decision, $reason);
        });
    }

    public function rejectListing(User $actor, Listing $listing, string $reason): void
    {
        DB::transaction(function () use ($actor, $listing, $reason): void {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $property = $locked->property()->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending' && $property->status === 'pending', 409);
            $locked->update(['status' => 'rejected', 'published_at' => null]);
            $property->update(['status' => 'rejected']);
            $this->record($actor, 'listing', $locked->getKey(), 'rejected', $reason);
        });
    }

    public function unpublishListing(User $actor, Listing $listing, string $reason): void
    {
        DB::transaction(function () use ($actor, $listing, $reason): void {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $property = $locked->property()->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'published' && $property->status === 'approved', 409);
            $locked->update(['status' => 'draft', 'published_at' => null]);
            $property->update(['status' => 'draft']);
            $this->record($actor, 'listing', $locked->getKey(), 'unpublished', $reason);
        });
    }

    private function record(User $actor, string $type, int $id, string $decision, ?string $reason): void
    {
        DB::table('moderation_decisions')->insert([
            'actor_id' => $actor->getKey(),
            'subject_type' => $type,
            'subject_id' => $id,
            'decision' => $decision,
            'reason' => $reason,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'admin.'.$type.'.'.$decision,
            'subject_type' => $type,
            'subject_id' => $id,
            'context' => json_encode(['reason' => $reason], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
