<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Login;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class LoginController
{
    public function create(): View
    {
        return view('auth.login.index');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'));
    }
}
