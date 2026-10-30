<x-layouts.frontend title="Account — Barama Real Estate">
    <main class="account-page">
        <div class="account-page__header">
            <div>
                <h1>Account dashboard</h1>
                <p>Welcome, {{ auth()->user()->name }}.</p>
                <p>This is the Laravel authentication milestone. Marketplace account features are not migrated yet.</p>
            </div>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="button button--secondary" type="submit">Sign out</button>
            </form>
        </div>
    </main>
</x-layouts.frontend>
