<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SavedSearchManager
{
    public const MAX_PER_USER = 20;

    public function create(User $user, string $name, array $filters): SavedSearch
    {
        $name = trim($name);
        return DB::transaction(function () use ($user, $name, $filters): SavedSearch {
            // Serialize each user's writes, preventing a concurrent 20-search cap bypass.
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (SavedSearch::query()->where('user_id', $user->getKey())->count() >= self::MAX_PER_USER) {
                throw ValidationException::withMessages([
                    'name' => 'You can save up to 20 searches. Remove an old one to save a new search.',
                ]);
            }
            if (SavedSearch::query()->where('user_id', $user->getKey())->where('name', $name)->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'You already have a saved search with this name.',
                ]);
            }
            return SavedSearch::query()->create([
                'user_id' => $user->getKey(),
                'name' => $name,
                'filters' => $filters,
            ]);
        });
    }

    public function rename(User $user, SavedSearch $savedSearch, string $name): void
    {
        $this->assertOwner($user, $savedSearch);
        DB::transaction(function () use ($user, $savedSearch, $name): void {
            $row = SavedSearch::query()->where('user_id', $user->getKey())
                ->whereKey($savedSearch->getKey())->lockForUpdate()->firstOrFail();
            $name = trim($name);
            if (SavedSearch::query()->where('user_id', $user->getKey())
                ->where('name', $name)->whereKeyNot($row->getKey())->exists()) {
                throw ValidationException::withMessages(['name' => 'You already use this search name.']);
            }
            $row->update(['name' => $name]);
        });
    }

    public function remove(User $user, SavedSearch $savedSearch): void
    {
        $this->assertOwner($user, $savedSearch);
        SavedSearch::query()->where('user_id', $user->getKey())
            ->whereKey($savedSearch->getKey())->delete();
    }

    public function assertOwner(User $user, SavedSearch $savedSearch): void
    {
        abort_unless($savedSearch->user_id === $user->getKey(), 404);
    }
}
