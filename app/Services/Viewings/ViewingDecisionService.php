<?php

declare(strict_types=1);

namespace App\Services\Viewings;

use App\Models\AccountNotification;
use App\Models\Listing;
use App\Models\User;
use App\Models\ViewingEvent;
use App\Models\ViewingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ViewingDecisionService
{
    /** All transitions are serialized by locking the original viewing row. */

    public function submit(
        User $actor, Listing $listing, CarbonImmutable $requested, ?string $message,
    ): ViewingRequest {
        abort_unless(Listing::query()->visibleToPublic()
            ->whereKey($listing->getKey())->exists(), 404);
        $this->requireFuture($requested);

        return DB::transaction(function () use ($actor, $listing, $requested, $message): ViewingRequest {
            $viewing = ViewingRequest::query()->create([
                'user_id' => $actor->getKey(),
                'listing_id' => $listing->getKey(),
                'requested_at' => $requested,
                'message' => $message,
                'status' => 'pending',
            ]);
            $this->record($viewing, $actor, 'customer', 'submitted',
                null, 'pending', null, $requested);
            $this->notify($viewing, 'Viewing request submitted',
                'Your request to view '.$listing->property->title
                .' was sent. The appointment is not confirmed.');
            $this->notifyCompany($viewing, 'New customer viewing request',
                'A customer requested a property viewing. Open your company workspace to review it.');
            $this->audit($viewing, $actor, 'viewing.submitted');

            return $viewing;
        });
    }

    public function decide(User $actor, ViewingRequest $viewing, string $decision): void
    {
        if (!in_array($decision, ['confirmed', 'declined'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose Confirm or Decline.']);
        }
        DB::transaction(function () use ($actor, $viewing, $decision): void {
            $locked = $this->locked($viewing);
            $kind = $this->requireCompanyOperator($actor, $locked);
            $this->requireNotHeld($locked);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'This request has already been processed or cancelled.',
                ]);
            }
            if ($decision === 'confirmed') {
                $this->requirePublicOffer($locked);
            }
            if ($decision === 'confirmed' && $locked->requested_at->isPast()) {
                throw ValidationException::withMessages([
                    'decision' => 'A past viewing time cannot be confirmed.',
                ]);
            }
            $locked->forceFill([
                'status' => $decision,
                'confirmed_at' => $decision === 'confirmed' ? now() : null,
            ])->save();
            $this->record($locked, $actor, $kind, $decision,
                'pending', $decision, null, $locked->requested_at);
            $this->notify($locked, $decision === 'confirmed'
                ? 'Viewing request confirmed' : 'Viewing request declined',
                $decision === 'confirmed'
                    ? 'Your property viewing is confirmed for '.$locked->requested_at->format('M j, Y g:i A').'.'
                    : 'Your property viewing request was declined. You may submit a new request.');
            $this->audit($locked, $actor, 'viewing.'.$decision);
        });
    }

    public function propose(
        User $actor, ViewingRequest $viewing, CarbonImmutable $proposed,
        ?string $reason = null,
    ): void {
        DB::transaction(function () use ($actor, $viewing, $proposed, $reason): void {
            $locked = $this->locked($viewing);
            $kind = $this->requireCompanyOperator($actor, $locked);
            $this->requireNotHeld($locked);
            abort_unless(in_array($locked->status, ['pending', 'confirmed'], true), 409);
            $this->requirePublicOffer($locked);
            $this->requireFuture($proposed);
            $old = $locked->status;
            $locked->forceFill([
                'status' => 'reschedule_proposed',
                'proposed_at' => $proposed,
                'proposed_expires_at' => now()->addHours(72),
                'proposed_by' => $actor->getKey(),
                'reschedule_note' => $reason,
            ])->save();
            $this->record($locked, $actor, $kind, 'reschedule_proposed', $old,
                'reschedule_proposed', $locked->requested_at, $proposed, $reason);
            $this->notify($locked, 'New viewing time proposed',
                'The property company proposed '.$proposed->format('M j, Y g:i A')
                .'. Open your Viewing requests to accept or decline within 72 hours.'
                .' Your original appointment is not automatically rescheduled.');
            $this->audit($locked, $actor, 'viewing.reschedule_proposed');
        });
    }

    public function respondToProposal(
        User $actor, ViewingRequest $viewing, string $decision,
    ): void {
        abort_unless(in_array($decision, ['accept', 'decline'], true), 422);
        DB::transaction(function () use ($actor, $viewing, $decision): void {
            $locked = $this->locked($viewing);
            $this->requireCustomer($actor, $locked);
            $this->requireNotHeld($locked);
            abort_unless($locked->status === 'reschedule_proposed'
                && $locked->proposed_at !== null, 409);
            if ($decision === 'accept') {
                abort_unless($locked->proposed_expires_at?->isFuture(), 409);
                $this->requireFuture(CarbonImmutable::instance($locked->proposed_at));
            }
            $oldTime = $locked->requested_at;
            $proposed = $locked->proposed_at;
            $status = $decision === 'accept' ? 'confirmed' : 'cancelled';
            $locked->forceFill([
                'status' => $status,
                'requested_at' => $decision === 'accept' ? $proposed : $oldTime,
                'confirmed_at' => $decision === 'accept' ? now() : $locked->confirmed_at,
                'cancelled_at' => $decision === 'decline' ? now() : null,
                'proposed_at' => null,
                'proposed_expires_at' => null,
                'proposed_by' => null,
                'reschedule_note' => null,
            ])->save();
            $this->record($locked, $actor, 'customer',
                'reschedule_'.$decision, 'reschedule_proposed', $status,
                $oldTime, $proposed);
            $this->notifyCompany($locked, $decision === 'accept'
                ? 'Customer accepted a viewing time' : 'Customer declined a viewing time',
                $decision === 'accept'
                    ? 'The customer accepted the proposed viewing for '.$proposed->format('M j, Y g:i A').'.'
                    : 'The customer declined the proposed time. The viewing request is cancelled.');
            $this->audit($locked, $actor, 'viewing.reschedule_'.$decision);
        });
    }

    public function cancel(User $actor, ViewingRequest $viewing, bool $asCompany = false): void
    {
        DB::transaction(function () use ($actor, $viewing, $asCompany): void {
            $locked = $this->locked($viewing);
            $kind = $asCompany
                ? $this->requireCompanyOperator($actor, $locked) : 'customer';
            if (!$asCompany) {
                $this->requireCustomer($actor, $locked);
            }
            abort_unless(in_array($locked->status,
                ['pending', 'confirmed', 'reschedule_proposed'], true), 409);
            $old = $locked->status;
            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'proposed_at' => null,
                'proposed_expires_at' => null,
                'proposed_by' => null,
                'reschedule_note' => null,
            ])->save();
            $this->record($locked, $actor, $kind, 'cancelled', $old, 'cancelled',
                $locked->requested_at, null);
            if ($asCompany) {
                $this->notify($locked, 'Viewing cancelled by company',
                    'The company cancelled your viewing. You may submit a new request.');
            } else {
                $this->notifyCompany($locked, 'Customer cancelled a viewing',
                    'The customer cancelled a property viewing request.');
            }
            $this->audit($locked, $actor, 'viewing.cancelled');
        });
    }

    public function review(
        User $actor, ViewingRequest $viewing, string $decision, string $reason,
    ): void {
        abort_unless($actor->isSystemAdministrator(), 403);
        abort_unless(in_array($decision, ['hold', 'release'], true), 422);
        DB::transaction(function () use ($actor, $viewing, $decision, $reason): void {
            $locked = $this->locked($viewing, true);
            $from = $locked->review_status ?? 'clear';
            $to = $decision === 'hold' ? 'held' : 'clear';
            abort_unless($from !== $to, 409);
            $locked->forceFill([
                'review_status' => $to,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();
            $this->record($locked, $actor, 'admin', 'admin_review_'.$decision,
                $locked->status, $locked->status,
                $locked->requested_at, $locked->requested_at, $reason);
            $this->audit($locked, $actor, 'viewing.review_'.$decision);
            // Moderation holds do not notify customers or activate archived
            // appointments. Admins must review private evidence separately.
        });
    }

    private function locked(ViewingRequest $viewing, bool $allowHistoricalReview = false): ViewingRequest
    {
        $locked = ViewingRequest::query()->lockForUpdate()
            ->findOrFail($viewing->getKey());
        // Never turn an imported historical appointment into a new real
        // appointment merely because an admin or company clicks a button.
        abort_unless($allowHistoricalReview || ($locked->legacy_uuid === null
            && $locked->status !== 'legacy_review'), 409);
        return $locked;
    }

    private function requireCompanyOperator(User $actor, ViewingRequest $viewing): string
    {
        if ($actor->isSystemAdministrator()) {
            return 'admin';
        }
        $company = $viewing->listing?->property?->company;
        abort_unless($company !== null && $company->status === 'approved', 404);
        abort_unless($company->memberships()
            ->where('user_id', $actor->getKey())
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'manager'])->exists(), 404);
        return 'company';
    }

    private function requireCustomer(User $actor, ViewingRequest $viewing): void
    {
        abort_unless((int)$viewing->user_id === (int)$actor->getKey(), 404);
    }

    private function requireNotHeld(ViewingRequest $viewing): void
    {
        abort_unless($viewing->review_status !== 'held', 409);
    }

    private function requirePublicOffer(ViewingRequest $viewing): void
    {
        if (!Listing::query()->visibleToPublic()
            ->whereKey($viewing->listing_id)->exists()) {
            throw ValidationException::withMessages([
                'decision' => 'This listing is no longer approved for new viewing confirmations.',
            ]);
        }
    }

    private function requireFuture(CarbonImmutable $date): void
    {
        if (!$date->isAfter(now()->addMinutes(30))
            || !$date->isBefore(now()->addMonths(6))) {
            throw ValidationException::withMessages([
                'proposed_at' => 'Choose a time at least 30 minutes from now and within six months.',
            ]);
        }
    }

    private function record(
        ViewingRequest $viewing, User $actor, string $kind, string $event,
        ?string $from, ?string $to, mixed $previous,
        mixed $next, ?string $reason = null,
    ): void {
        ViewingEvent::query()->create([
            'viewing_request_id' => $viewing->getKey(),
            'actor_id' => $actor->getKey(),
            'actor_kind' => $kind,
            'event_type' => $event,
            'from_status' => $from, 'to_status' => $to,
            'previous_time' => $previous,
            'new_time' => $next,
            'reason' => $reason,
        ]);
    }

    private function notify(ViewingRequest $viewing, string $title, string $message): void
    {
        AccountNotification::query()->create([
            'user_id' => $viewing->user_id,
            'title' => $title, 'message' => $message,
            'type' => 'viewing',
        ]);
    }

    private function notifyCompany(
        ViewingRequest $viewing, string $title, string $message,
    ): void {
        $company = $viewing->listing?->property?->company;
        if ($company === null) {
            return;
        }
        $ids = $company->memberships()->where('status', 'active')
            ->whereIn('role', ['owner', 'manager'])->pluck('user_id')->unique();
        foreach ($ids as $id) {
            AccountNotification::query()->create([
                'user_id' => $id, 'title' => $title,
                'message' => $message, 'type' => 'viewing',
            ]);
        }
    }

    private function audit(ViewingRequest $viewing, User $actor, string $action): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => 'viewing_request',
            'subject_id' => $viewing->getKey(),
            'context' => json_encode([
                'listing_id' => $viewing->listing_id,
                'status' => $viewing->status,
                'review_status' => $viewing->review_status,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
