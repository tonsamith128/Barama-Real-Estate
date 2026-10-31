<?php

declare(strict_types=1);

namespace App\Services\Viewings;

use App\Models\AccountNotification;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ViewingDecisionService
{
    /**
     * A pending request may be confirmed or declined once.
     * Audit and customer notification commit in the same transaction.
     */
    public function decide(User $actor, ViewingRequest $viewing, string $decision): void
    {
        if (!in_array($decision, ['confirmed', 'declined'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose Confirm or Decline.']);
        }

        DB::transaction(function () use ($actor, $viewing, $decision): void {
            $locked = ViewingRequest::query()->lockForUpdate()->findOrFail($viewing->getKey());
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'This request has already been processed or cancelled.',
                ]);
            }
            if ($decision === 'confirmed' && $locked->requested_at->isPast()) {
                throw ValidationException::withMessages([
                    'decision' => 'A past viewing time cannot be confirmed.',
                ]);
            }
            $locked->update(['status' => $decision]);
            $property = $locked->listing?->property?->title ?? 'the property';
            AccountNotification::query()->create([
                'user_id' => $locked->user_id,
                'title' => $decision === 'confirmed' ? 'Viewing request confirmed' : 'Viewing request declined',
                'message' => $decision === 'confirmed'
                    ? 'Your viewing of '.$property.' is confirmed for '.$locked->requested_at->format('M j, Y g:i A').'. Please confirm access arrangements with the company.'
                    : 'Your viewing request for '.$property.' could not be confirmed. Please contact the property company to arrange a new time.',
                'type' => 'viewing',
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(),
                'action' => 'viewing.'.$decision,
                'subject_type' => 'viewing_request',
                'subject_id' => $locked->getKey(),
                'context' => json_encode(['listing_id' => $locked->listing_id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
