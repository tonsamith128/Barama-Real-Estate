<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\AccountNotification;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Application lifecycle is separate from approved-company catalog writes.
 * A rejected company must be revised back into draft before resubmission.
 */
final class CompanyApplicationService
{
    public const COMPANY_TYPES = ['developer', 'agency', 'property_company', 'owner'];

    public function createDraft(User $owner, array $input): Company
    {
        abort_unless($owner->hasVerifiedEmail(), 403);

        return DB::transaction(function () use ($owner, $input): Company {
            $ownedCount = CompanyMembership::query()
                ->where('user_id', $owner->getKey())
                ->where('role', 'owner')
                ->count();

            if ($ownedCount >= 10) {
                throw ValidationException::withMessages([
                    'name' => 'You have reached the maximum number of company applications.',
                ]);
            }

            $company = Company::query()->create([
                'name' => $input['name'],
                'slug' => Str::slug($input['name']).'-'.Str::lower(Str::random(12)),
                'company_type' => $input['company_type'],
                'description' => $input['description'] ?? null,
                'status' => 'draft',
                'published_at' => null,
            ]);

            CompanyMembership::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $owner->getKey(),
                'role' => 'owner',
                'status' => 'active',
            ]);

            $this->audit($owner, $company, 'company.application.created');
            return $company;
        });
    }

    public function revise(User $owner, Company $company, array $input): void
    {
        DB::transaction(function () use ($owner, $company, $input): void {
            $locked = Company::query()->lockForUpdate()->findOrFail($company->getKey());
            $this->assertOwner($owner, $locked);
            abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 409);

            $locked->update([
                'name' => $input['name'],
                'company_type' => $input['company_type'],
                'description' => $input['description'] ?? null,
                'status' => 'draft',
                'published_at' => null,
            ]);
            $this->audit($owner, $locked, 'company.application.revised');
        });
    }

    public function submit(User $owner, Company $company): void
    {
        DB::transaction(function () use ($owner, $company): void {
            $locked = Company::query()->lockForUpdate()->findOrFail($company->getKey());
            $this->assertOwner($owner, $locked);
            abort_unless($locked->status === 'draft', 409);

            $locked->update(['status' => 'pending', 'published_at' => null]);
            $eventId = $this->audit($owner, $locked, 'company.application.submitted');

            // Durable in-app review notification within the same transaction.
            // A replayed submit returns 409 and does not fan out duplicate alerts.
            $reviewers = User::query()
                ->where('system_role', 'super_admin')
                ->whereNotNull('email_verified_at')
                ->pluck('id');

            foreach ($reviewers as $reviewerId) {
                AccountNotification::query()->firstOrCreate([
                    'user_id' => (int) $reviewerId,
                    'dedupe_key' => 'company-submission:'.$eventId,
                ], [
                    'title' => 'Company awaiting approval',
                    'message' => $locked->name.' has been submitted for company review.',
                    'type' => 'company_pending',
                ]);
            }
        });
    }

    private function assertOwner(User $actor, Company $company): void
    {
        abort_unless($actor->hasVerifiedEmail(), 403);
        abort_unless(CompanyMembership::query()
            ->where('company_id', $company->getKey())
            ->where('user_id', $actor->getKey())
            ->where('role', 'owner')
            ->where('status', 'active')
            ->exists(), 403);
    }

    private function audit(User $actor, Company $company, string $action): int
    {
        return DB::table('audit_events')->insertGetId([
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => 'company',
            'subject_id' => $company->getKey(),
            'context' => json_encode(['status' => $company->status], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
