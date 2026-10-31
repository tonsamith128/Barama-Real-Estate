<?php

declare(strict_types=1);

namespace App\Services\Inquiries;

use App\Models\AccountNotification;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InquiryReviewService
{
    public function markResponded(User $actor, Inquiry $inquiry): void
    {
        DB::transaction(function () use ($actor, $inquiry): void {
            $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->getKey());
            if ($locked->status !== 'new') {
                throw ValidationException::withMessages([
                    'inquiry' => 'This inquiry has already been handled.',
                ]);
            }
            $locked->update(['status' => 'responded', 'responded_at' => now()]);
            if ($locked->requester_id !== null) {
                AccountNotification::query()->create([
                    'user_id' => $locked->requester_id,
                    'title' => 'Property inquiry reviewed',
                    'message' => 'Your inquiry about '.($locked->listing?->property?->title ?? 'a property').' has been marked as handled. Please check the contact details provided by the company for follow-up.',
                    'type' => 'inquiry',
                ]);
            }
            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(),
                'action' => 'inquiry.responded',
                'subject_type' => 'inquiry',
                'subject_id' => $locked->getKey(),
                'context' => json_encode(['listing_id' => $locked->listing_id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
