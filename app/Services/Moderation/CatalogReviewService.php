<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\Listing;
use App\Models\Project;
use App\Models\User;
use App\Services\Developer\CatalogRevisionRecorder;
use Illuminate\Support\Facades\DB;

final class CatalogReviewService
{
    public function __construct(private readonly CatalogRevisionRecorder $revisions)
    {
    }

    public function decideProject(User $actor, Project $project, string $decision, ?string $reason = null): void
    {
        $this->assertAdmin($actor);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);

        DB::transaction(function () use ($actor, $project, $decision, $reason): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            abort_unless($locked->status === 'pending', 409);
            $company = $locked->company()->firstOrFail();
            abort_unless($company->status === 'approved' && $company->published_at?->lte(now()), 422);

            $before = $this->revisions->snapshot($locked, 'project');
            $locked->update([
                'status' => $decision === 'approved' ? 'published' : 'rejected',
                'published_at' => $decision === 'approved' ? now() : null,
            ]);
            $this->revisions->record($actor, 'project', $locked, $decision, $before,
                $this->revisions->snapshot($locked, 'project'));
            $this->record($actor, 'project', $locked->getKey(), $decision, $reason);
        });
    }

    public function approveListing(User $actor, Listing $listing): void
    {
        $this->assertAdmin($actor);
        DB::transaction(function () use ($actor, $listing): void {
            $property = \App\Models\Property::query()->lockForUpdate()
                ->findOrFail($listing->property_id);
            $offer = $property->listings()->lockForUpdate()->findOrFail($listing->getKey());
            $company = $property->company()->firstOrFail();
            abort_unless($company->status === 'approved'
                && $company->published_at?->lte(now()), 422);
            abort_unless(in_array($property->status, ['pending', 'approved'], true)
                && $offer->status === 'pending', 422);

            $beforeProperty = $this->revisions->snapshot($property, 'property');
            $beforeListing = $this->revisions->snapshot($offer, 'listing');
            if ($property->status === 'pending') {
                $property->update(['status' => 'approved']);
                $this->revisions->record($actor, 'property', $property, 'approved', $beforeProperty,
                    $this->revisions->snapshot($property, 'property'));
            }
            $offer->update(['status' => 'published', 'published_at' => now()]);
            $this->revisions->record($actor, 'listing', $offer, 'approved', $beforeListing,
                $this->revisions->snapshot($offer, 'listing'));
            $this->record($actor, 'listing', $offer->getKey(), 'approved', null);
        });
    }

    public function rejectListing(User $actor, Listing $listing, string $reason): void
    {
        $this->assertAdmin($actor);
        DB::transaction(function () use ($actor, $listing, $reason): void {
            $property = \App\Models\Property::query()->lockForUpdate()
                ->findOrFail($listing->property_id);
            $locked = $property->listings()->lockForUpdate()->findOrFail($listing->getKey());
            abort_unless($locked->status === 'pending'
                && in_array($property->status, ['pending', 'approved'], true), 409);

            $beforeProperty = $this->revisions->snapshot($property, 'property');
            $beforeListing = $this->revisions->snapshot($locked, 'listing');
            $locked->update(['status' => 'rejected', 'published_at' => null]);
            if ($property->status === 'pending') {
                $property->update(['status' => 'rejected']);
                $this->revisions->record($actor, 'property', $property, 'rejected', $beforeProperty,
                    $this->revisions->snapshot($property, 'property'));
            }
            $this->revisions->record($actor, 'listing', $locked, 'rejected', $beforeListing,
                $this->revisions->snapshot($locked, 'listing'));
            $this->record($actor, 'listing', $locked->getKey(), 'rejected', $reason);
        });
    }

    public function unpublishListing(User $actor, Listing $listing, string $reason): void
    {
        $this->assertAdmin($actor);
        DB::transaction(function () use ($actor, $listing, $reason): void {
            $property = \App\Models\Property::query()->lockForUpdate()
                ->findOrFail($listing->property_id);
            $locked = $property->listings()->lockForUpdate()->findOrFail($listing->getKey());
            abort_unless($locked->status === 'published' && $property->status === 'approved', 409);

            $beforeProperty = $this->revisions->snapshot($property, 'property');
            $beforeListing = $this->revisions->snapshot($locked, 'listing');
            $locked->update(['status' => 'draft', 'published_at' => null]);
            // A sibling sale/rental offer can remain publicly visible.
            $stillPublished = $property->listings()
                ->where('id', '!=', $locked->getKey())
                ->where('status', 'published')->exists();
            if (!$stillPublished) {
                $property->update(['status' => 'draft']);
                $this->revisions->record($actor, 'property', $property, 'unpublished', $beforeProperty,
                    $this->revisions->snapshot($property, 'property'));
            }
            $this->revisions->record($actor, 'listing', $locked, 'unpublished', $beforeListing,
                $this->revisions->snapshot($locked, 'listing'));
            $this->record($actor, 'listing', $locked->getKey(), 'unpublished', $reason);
        });
    }

    private function assertAdmin(User $actor): void
    {
        abort_unless($actor->isSystemAdministrator(), 403);
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
