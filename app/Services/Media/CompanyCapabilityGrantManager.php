<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\User;
use App\Models\UserCapabilityGrant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanyCapabilityGrantManager
{
    public function grant(User $actor, string $email, string $capability): void
    {
        abort_unless($actor->isSystemAdministrator() && $actor->hasVerifiedEmail(), 403);
        abort_unless(in_array($capability, CompanyMediaCapabilities::ALL, true), 422);

        DB::transaction(function () use ($actor, $email, $capability): void {
            $recipient = User::query()->where('email', $email)
                ->where('system_role', 'super_admin')
                ->whereNotNull('email_verified_at')->first();

            if ($recipient === null) {
                throw ValidationException::withMessages([
                    'email' => 'Only existing, verified system administrators can hold these capabilities.',
                ]);
            }

            $grant = UserCapabilityGrant::query()->firstOrCreate([
                'user_id' => $recipient->getKey(),
                'capability' => $capability,
            ], [
                'granted_by' => $actor->getKey(),
            ]);

            if ($grant->wasRecentlyCreated) {
                $this->audit($actor, $grant, 'granted');
            }
        });
    }

    public function revoke(User $actor, UserCapabilityGrant $grant): void
    {
        abort_unless($actor->isSystemAdministrator() && $actor->hasVerifiedEmail(), 403);

        DB::transaction(function () use ($actor, $grant): void {
            $locked = UserCapabilityGrant::query()->lockForUpdate()->findOrFail($grant->getKey());
            $this->audit($actor, $locked, 'revoked');
            $locked->delete();
        });
    }

    private function audit(User $actor, UserCapabilityGrant $grant, string $action): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'admin.company.capability.'.$action,
            'subject_type' => 'user',
            'subject_id' => $grant->user_id,
            'context' => json_encode(['capability' => $grant->capability], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
