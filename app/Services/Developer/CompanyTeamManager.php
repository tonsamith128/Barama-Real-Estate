<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanyTeamManager
{
    public function assertOwner(User $actor, Company $company): void
    {
        abort_unless($company->status === 'approved', 403);
        abort_unless($company->memberships()
            ->where('user_id', $actor->getKey())
            ->where('role', 'owner')
            ->where('status', 'active')
            ->exists(), 403);
    }

    /** Assign active roles only to already registered, verified accounts. */
    public function assign(User $actor, Company $company, string $email, string $role): void
    {
        $this->assertOwner($actor, $company);
        abort_unless(in_array($role, ['manager', 'member'], true), 422);
        DB::transaction(function () use ($actor, $company, $email, $role): void {
            $recipient = User::query()->where('email', $email)
                ->whereNotNull('email_verified_at')->first();
            if ($recipient === null) {
                throw ValidationException::withMessages([
                    'email' => 'Only existing, email-verified users can be added.',
                ]);
            }
            $membership = CompanyMembership::query()->where('company_id', $company->getKey())
                ->where('user_id', $recipient->getKey())->lockForUpdate()->first();
            if ($membership?->role === 'owner') {
                throw ValidationException::withMessages([
                    'email' => 'The original company owner role cannot be changed here.',
                ]);
            }
            CompanyMembership::query()->updateOrCreate(
                ['company_id' => $company->getKey(), 'user_id' => $recipient->getKey()],
                ['role' => $role, 'status' => 'active'],
            );
            $this->audit($actor, $company, $recipient->getKey(), 'member.assigned');
        });
    }

    public function deactivate(User $actor, Company $company, CompanyMembership $membership): void
    {
        $this->assertOwner($actor, $company);
        abort_unless($membership->company_id === $company->getKey(), 404);
        abort_unless($membership->role !== 'owner', 409);
        DB::transaction(function () use ($actor, $company, $membership): void {
            $locked = CompanyMembership::query()->lockForUpdate()->findOrFail($membership->getKey());
            abort_unless($locked->company_id === $company->getKey(), 404);
            abort_unless($locked->role !== 'owner', 409);
            abort_unless($locked->status === 'active', 409);
            $locked->update(['status' => 'inactive']);
            $this->audit($actor, $company, $locked->user_id, 'member.deactivated');
        });
    }

    private function audit(User $actor, Company $company, int $userId, string $action): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'developer.company.'.$action,
            'subject_type' => 'company',
            'subject_id' => $company->getKey(),
            'context' => json_encode(['user_id' => $userId], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
