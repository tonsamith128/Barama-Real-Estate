<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AccessibleCompanies
{
    /**
     * Membership is required in addition to authentication. Company records
     * are not exposed to unrelated users, even when public browsing is open.
     */
    public function forUser(User $user): LengthAwarePaginator
    {
        return Company::query()
            ->whereHas('memberships', function (Builder $membership) use ($user): void {
                $membership->where('user_id', $user->getKey())
                    ->where('status', 'active');
            })
            ->orderBy('name')
            ->paginate(15);
    }

    public function canAccess(User $user, Company $company): bool
    {
        return $company->memberships()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->exists();
    }
}
