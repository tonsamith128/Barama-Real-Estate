<x-layouts.frontend title="Register — Barama Real Estate">
    <main class="auth-page">
        <section class="auth-panel" aria-labelledby="page-title">
            <h1 id="page-title">Create an account</h1>
            <form method="post" action="{{ route('register.store') }}">
                @csrf
                <label class="field" for="name">
                    <span>Full name</span>
                    <input class="field__control" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                </label>
                @error('name') <p class="field__error" role="alert">{{ $message }}</p> @enderror

                <label class="field" for="email">
                    <span>Email address</span>
                    <input class="field__control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>
                @error('email') <p class="field__error" role="alert">{{ $message }}</p> @enderror

                <label class="field" for="password">
                    <span>Password (12+ characters, mixed case, number and symbol)</span>
                    <input class="field__control" id="password" name="password" type="password" autocomplete="new-password" required>
                </label>
                @error('password') <p class="field__error" role="alert">{{ $message }}</p> @enderror

                <label class="field" for="password_confirmation">
                    <span>Confirm password</span>
                    <input class="field__control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </label>

                <button class="button" type="submit">Register</button>
            </form>
            <p class="auth-panel__footer">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
        </section>
    </main>
</x-layouts.frontend>
