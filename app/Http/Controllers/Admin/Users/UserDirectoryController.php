<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UserDirectoryController
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        $query = User::query()->withCount('companyMemberships');
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            abort_if(mb_strlen($term) > 120, 422);
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            });
        }
        return view('admin.users.index', ['users' => $query->latest()->paginate(20)->withQueryString(), 'term' => $term]);
    }
}
