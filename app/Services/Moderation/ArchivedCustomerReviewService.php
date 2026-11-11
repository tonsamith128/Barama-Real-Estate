<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\LegacyCustomerInquiry;
use App\Models\LegacyCustomerLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArchivedCustomerReviewService
{
    public function reviewInquiry(
        User $actor, LegacyCustomerInquiry $record, string $decision, string $note,
    ): void {
        $this->review($actor, LegacyCustomerInquiry::class,
            $record->getKey(), $decision, $note, 'legacy_customer_inquiry');
    }

    public function reviewLead(
        User $actor, LegacyCustomerLead $record, string $decision, string $note,
    ): void {
        $this->review($actor, LegacyCustomerLead::class,
            $record->getKey(), $decision, $note, 'legacy_customer_lead');
    }

    /**
     * A review decision does not create public inquiries, assign sales access,
     * email customers, or mark a full historical conversion as complete.
     */
    private function review(
        User $actor, string $model, int $id, string $decision,
        string $note, string $subject,
    ): void {
        abort_unless($actor->isSystemAdministrator(), 403);
        if (!in_array($decision, ['reviewed', 'needs_followup'], true)) {
            throw ValidationException::withMessages(['decision'=>'Choose a valid review decision.']);
        }
        DB::transaction(function () use ($actor,$model,$id,$decision,$note,$subject): void {
            /** @var Model $record */
            $record = $model::query()->lockForUpdate()->findOrFail($id);
            if ($record->review_status === $decision) {
                throw ValidationException::withMessages([
                    'decision'=>'The historical record already has this review status.',
                ]);
            }
            $record->forceFill([
                'review_status'=>$decision,
                'reviewed_by'=>$actor->getKey(),
                'reviewed_at'=>now(),
                'reviewer_note'=>$note,
            ])->save();
            DB::table('audit_events')->insert([
                'actor_id'=>$actor->getKey(),
                'action'=>'legacy.customer.review_'.$decision,
                'subject_type'=>$subject,
                'subject_id'=>$record->getKey(),
                // No private notes or customer contact information are
                // inserted into the shared audit log.
                'context'=>json_encode(['review_status'=>$decision],JSON_THROW_ON_ERROR),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
    }
}
