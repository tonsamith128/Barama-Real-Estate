<x-layouts.frontend title="Account — Barama Real Estate">
    <main class="account-page">
        <div class="account-page__header">
            <div>
                <h1>Account dashboard</h1>
                <p>Welcome, {{ auth()->user()->name }}.</p>
                <p>Manage your profile and explore available real estate listings.</p>
                <nav class="actions" aria-label="Account shortcuts">
                    @if(auth()->user()->isSystemAdministrator())
                        <a class="button" href="{{ route('admin.dashboard') }}">Open Admin Dashboard</a>
                        <a class="button button--secondary" href="{{ route('admin.demo.index') }}">Demo importer</a>
                    @endif
                    <a class="button button--secondary" href="{{ route('developer.dashboard') }}">Developer workspace</a>
                    <a class="button button--secondary" href="{{ route('properties.index') }}">Explore properties</a>
                    <a class="button button--secondary" href="{{ route('home') }}">Homepage</a>
                </nav>
            </div>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="button button--secondary" type="submit">Sign out</button>
            </form>
        </div>
    </main>
</x-layouts.frontend>
