<x-layouts.admin title="Developer Workspace — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Barama Real Estate</p>
            <h1>Developer Workspace</h1>
            <p>Your active company memberships are listed below.</p>
        </div>
        <a href="{{ route('home') }}">Public website</a>
    </header>

    <section aria-labelledby="companies-heading" class="admin-card">
        <h2 id="companies-heading">Your companies</h2>
        <ul class="admin-company-list">
            @forelse ($companies as $company)
                <li>
                    <strong>{{ $company->name }}</strong>
                    <span>{{ ucfirst($company->status) }}</span>
                </li>
            @empty
                <li>No active company memberships. Contact an administrator to obtain access.</li>
            @endforelse
        </ul>
        {{ $companies->links() }}
    </section>

    <p class="admin-notice">
        Developer editing tools are not yet migrated into this Laravel branch.
    </p>
</x-layouts.admin>
