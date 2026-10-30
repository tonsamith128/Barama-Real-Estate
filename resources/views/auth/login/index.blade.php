<x-layouts.frontend title="Sign in — Barama Real Estate">
    <main class="auth-page">
        <section class="auth-panel" aria-labelledby="page-title">
            <h1 id="page-title">Sign in</h1>
            <p>Access your Barama account.</p>

            @if (session('status'))
                <p class="notice" role="status">{{ session('status') }}</p>
            @endif

            <form method="post" action="{{ route('login.store') }}">
                @csrf
                <label class="field" for="email">
                    <span>Email address</span>
                    <input class="field__control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                </label>
                @error('email') <p class="field__error" role="alert">{{ $message }}</p> @enderror

                <label class="field" for="password">
                    <span>Password</span>
                    <input class="field__control" id="password" name="password" type="password" autocomplete="current-password" required>
                </label>
                @error('password') <p class="field__error" role="alert">{{ $message }}</p> @enderror

                <button class="button" type="submit">Sign in</button>
            </form>

            <div class="auth-panel__footer actions">
                <a href="{{ route('password.request') }}">Forgot password?</a>
                <a href="{{ route('register') }}">Create an account</a>
            </div>
        </section>
    </main>
</x-layouts.frontend>
