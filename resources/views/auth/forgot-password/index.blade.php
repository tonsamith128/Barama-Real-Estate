<x-layouts.frontend title="Forgot password — Barama Real Estate">
    <main class="auth-page">
        <section class="auth-panel" aria-labelledby="page-title">
            <h1 id="page-title">Reset your password</h1>
            <p>Enter your email address to request a reset link.</p>
            @if (session('status'))
                <p class="notice" role="status">{{ session('status') }}</p>
            @endif
            <form method="post" action="{{ route('password.email') }}">
                @csrf
                <label class="field" for="email">
                    <span>Email address</span>
                    <input class="field__control" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>
                @error('email') <p class="field__error" role="alert">{{ $message }}</p> @enderror
                <button class="button" type="submit">Request reset link</button>
            </form>
            <p class="auth-panel__footer"><a href="{{ route('login') }}">Back to login</a></p>
        </section>
    </main>
</x-layouts.frontend>
