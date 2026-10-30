<x-layouts.frontend title="Barama Real Estate — Laravel Development">
    <main class="foundation" id="main-content">
        <p class="foundation__eyebrow">Barama Real Estate v2</p>
        <h1>Laravel 13 Development Preview</h1>
        <p>Explore the newly implemented read-only marketplace foundation. This is not the full migrated commercial application.</p>
        <nav class="actions" aria-label="Marketplace sections">
            <a class="button" href="{{ route('properties.index') }}">Properties</a>
            <a class="button button--secondary" href="{{ route('projects.index') }}">Projects</a>
            <a class="button button--secondary" href="{{ route('companies.index') }}">Companies</a>
        </nav>
        <p class="auth-panel__footer"><a href="{{ route('login') }}">Sign in</a> or <a href="{{ route('register') }}">register</a> to use the new account foundation.</p>
    </main>
</x-layouts.frontend>
