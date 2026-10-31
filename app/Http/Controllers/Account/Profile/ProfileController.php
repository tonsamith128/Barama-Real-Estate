<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\Profile;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ProfileController
{
    public function edit(Request $request): View
    {
        return view('account.profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->getKey())],
            'current_password' => ['nullable', 'required_if_email_changed', 'string'],
        ]);
        $newEmail = strcasecmp($user->email, $data['email']) !== 0;
        if ($newEmail) {
            if (!filled($data['current_password'] ?? null)
                || !Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Confirm your password to change your email address.'])->withInput();
            }
            $user->email = $data['email'];
            $user->email_verified_at = null;
        }
        $user->name = $data['name'];
        $user->save();
        if ($newEmail) {
            $user->sendEmailVerificationNotification();
            return redirect()->route('verification.notice')
                ->with('status', 'Verify your new email address to continue.');
        }
        return back()->with('notice', 'Profile updated successfully.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed',
                \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $request->user()->forceFill(['password' => $data['password']])->save();
        $request->session()->regenerate();
        return back()->with('notice', 'Your password was updated.');
    }
}
