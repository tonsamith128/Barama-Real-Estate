<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laravel-native company review notifications. Intended to be called inside
 * the same DB transaction as the authoritative moderation state transition.
 */
final class CompanyWorkflowNotifier
{
    public function notifyOwners(Company $company, string $decision, int $decisionId): void
    {
        if ($decisionId < 1 || !in_array($decision, ['approved', 'rejected'], true)) {
            return;
        }

        $name = mb_strimwidth(trim($company->name), 0, 100, '');
        $name = $name !== '' ? $name : 'Company #'.$company->getKey();

        $title = $decision === 'approved'
            ? 'Your company is approved'
            : 'Company changes requested';

        $message = $decision === 'approved'
            ? $name.' has been approved. You can manage its developer workspace.'
            : $name.' was not approved. Review your company details and contact support if needed.';

        $owners = CompanyMembership::query()
            ->where('company_id', $company->getKey())
            ->where('role', 'owner')
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $user): Builder => $user
                ->whereNotNull('email_verified_at'))
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($owners as $ownerId) {
            AccountNotification::query()->firstOrCreate(
                [
                    'user_id' => (int) $ownerId,
                    'dedupe_key' => 'company-review:'.$decisionId,
                ],
                [
                    'title' => $title,
                    'message' => $message,
                    'type' => 'company_'.$decision,
                ],
            );
        }
    }
}
