<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\ForgotPassword;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

final class ForgotPasswordController
{
    public function create(): View
    {
        return view('auth.forgot-password.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Do not disclose whether an email belongs to an account.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If an account exists, a password reset link has been sent.');
    }
}
