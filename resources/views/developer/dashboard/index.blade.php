<x-layouts.admin title="Developer Workspace — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Barama Real Estate</p>
            <h1>Developer Workspace</h1>
            <p>Your active company memberships are listed below.</p>
        </div>
        <a href="{{ route('developer.inquiries.index') }}">Company inquiries</a> · <a href="{{ route('developer.viewings.index') }}">Viewing requests</a> · <a href="{{ route('home') }}">Public website</a>
    </header>

    <section aria-labelledby="companies-heading" class="admin-card">
        <h2 id="companies-heading">Your companies</h2>
        <ul class="admin-company-list">
            @forelse ($companies as $company)
                <li>
                    <strong><a href="{{ route('developer.properties.index', $company) }}">{{ $company->name }} · Manage properties</a></strong>
                    <span>{{ ucfirst($company->status) }}</span>
                    @if($company->status === 'approved')
                        <a href="{{ route('developer.projects.index', $company) }}">Manage projects</a>
                    @endif
                </li>
            @empty
                <li>No active company memberships. Contact an administrator to obtain access.</li>
            @endforelse
        </ul>
        {{ $companies->links() }}
    </section>

    <p class="admin-notice">
        Property drafts and review submission are enabled for active company owners and managers. Other Developer tools remain under migration.
    </p>
</x-layouts.admin>
