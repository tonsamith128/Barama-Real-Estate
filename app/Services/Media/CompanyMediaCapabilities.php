<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\User;
use App\Models\UserCapabilityGrant;

final class CompanyMediaCapabilities
{
    public const COMPANY_MANAGE = 'company.manage';
    public const MEDIA_MANAGE = 'media.manage';
    public const MEDIA_CORRECT = 'company.media.correct';
    public const MEDIA_REVIEW = 'company.media.review';

    public const ALL = [
        self::COMPANY_MANAGE,
        self::MEDIA_MANAGE,
        self::MEDIA_CORRECT,
        self::MEDIA_REVIEW,
    ];

    /**
     * No implicit super-administrator bypass: corrective published media
     * requires explicit, individually revocable capability grants.
     */
    public function canRequest(User $actor): bool
    {
        return $this->hasAll($actor, [
            self::COMPANY_MANAGE, self::MEDIA_MANAGE, self::MEDIA_CORRECT,
        ]);
    }

    public function canReview(User $actor): bool
    {
        return $this->hasAll($actor, [
            self::COMPANY_MANAGE, self::MEDIA_MANAGE, self::MEDIA_REVIEW,
        ]);
    }

    /** @param array<int, string> $required */
    private function hasAll(User $actor, array $required): bool
    {
        if (!$actor->isSystemAdministrator() || !$actor->hasVerifiedEmail()) {
            return false;
        }

        return UserCapabilityGrant::query()
            ->where('user_id', $actor->getKey())
            ->whereIn('capability', $required)
            ->distinct()->count('capability') === count($required);
    }

    public function assertRequest(User $actor): void
    {
        abort_unless($this->canRequest($actor), 403);
    }

    public function assertReview(User $actor): void
    {
        abort_unless($this->canReview($actor), 403);
    }
}
