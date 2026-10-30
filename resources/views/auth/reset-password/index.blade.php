<x-layouts.frontend title="Set new password — Barama Real Estate">
    <main class="auth-page">
        <section class="auth-panel" aria-labelledby="page-title">
            <h1 id="page-title">Choose a new password</h1>
            <form method="post" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="field" for="email">
                    <span>Email address</span>
                    <input class="field__control" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
                </label>
                @error('email') <p class="field__error" role="alert">{{ $message }}</p> @enderror
                <label class="field" for="password">
                    <span>New password (12+ characters, mixed case, number and symbol)</span>
                    <input class="field__control" id="password" name="password" type="password" autocomplete="new-password" required>
                </label>
                @error('password') <p class="field__error" role="alert">{{ $message }}</p> @enderror
                <label class="field" for="password_confirmation">
                    <span>Confirm new password</span>
                    <input class="field__control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </label>
                <button class="button" type="submit">Reset password</button>
            </form>
        </section>
    </main>
</x-layouts.frontend>
