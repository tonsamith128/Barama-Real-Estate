<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Company approval/rejection is a company-scoped, audited transaction.
 * The caller is independently authenticated and authorized here, not just
 * at the controller or route boundary.
 */
final class CompanyReviewService
{
    public function __construct(private readonly CompanyWorkflowNotifier $notifier)
    {
    }

    public function decide(
        User $actor,
        Company $company,
        string $decision,
        ?string $reason = null,
    ): void {
        abort_unless($actor->isSystemAdministrator(), 403);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);

        $reason = $reason !== null ? trim($reason) : null;
        abort_if($decision === 'rejected' && mb_strlen($reason ?? '') < 10, 422);

        DB::transaction(function () use ($actor, $company, $decision, $reason): void {
            $locked = Company::query()->lockForUpdate()->findOrFail($company->getKey());
            abort_unless($locked->status === 'pending', 409);

            $locked->update([
                'status' => $decision,
                'published_at' => $decision === 'approved' ? now() : null,
            ]);

            $decisionId = DB::table('moderation_decisions')->insertGetId([
                'actor_id' => $actor->getKey(),
                'subject_type' => 'company',
                'subject_id' => $locked->getKey(),
                'decision' => $decision,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(),
                'action' => 'admin.company.'.$decision,
                'subject_type' => 'company',
                'subject_id' => $locked->getKey(),
                'context' => json_encode([
                    'moderation_decision_id' => $decisionId,
                    'reason' => $reason,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Database notifications and the review log commit or roll back together.
            $this->notifier->notifyOwners($locked, $decision, (int) $decisionId);
        });
    }
}
