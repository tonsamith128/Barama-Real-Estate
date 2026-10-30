<x-layouts.frontend title="Verify email — Barama Real Estate">
    <main class="auth-page">
        <section class="auth-panel" aria-labelledby="page-title">
            <h1 id="page-title">Confirm your email address</h1>
            <p>We sent you a verification link. Confirm your email before entering your account.</p>
            @if (session('status'))
                <p class="notice" role="status">{{ session('status') }}</p>
            @endif
            <form method="post" action="{{ route('verification.send') }}">
                @csrf
                <button class="button" type="submit">Resend verification email</button>
            </form>
            <form method="post" action="{{ route('logout') }}" class="auth-panel__footer">
                @csrf
                <button class="button button--secondary" type="submit">Sign out</button>
            </form>
        </section>
    </main>
</x-layouts.frontend>
