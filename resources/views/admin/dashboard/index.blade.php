<x-layouts.admin title="Admin Dashboard — Barama Real Estate">
    <header class="workspace-dashboard-heading">
        <div>
            <p class="admin-eyebrow">WORKSPACE / OVERVIEW</p>
            <h1>Platform overview</h1>
            <p>Manage your property marketplace, customers and content from one workspace.</p>
        </div>
        <a class="workspace-dashboard-heading__cta" href="{{ route('home') }}">Visit website</a>
    </header>

    @if(count($missingTables) > 0)
        <section class="dashboard-section workspace-alert" role="alert">
            <div>
                <strong>Database update required</strong>
                <p>Some new modules are not yet migrated: <code>{{ implode(', ', $missingTables) }}</code>.</p>
                <p>Back up your database, then run <code>php artisan migrate --force</code> and <code>php artisan optimize:clear</code> in the Laravel root. Never reset a customer database or reinstall the application.</p>
            </div>
        </section>
    @endif

    <section class="workspace-dashboard-hero" aria-label="Administration introduction">
        <div>
            <span class="workspace-dashboard-hero__eyebrow">BARAMA REAL ESTATE · ADMINISTRATION</span>
            <h2>Everything in its place.</h2>
            <p>A clear view of your growing marketplace, listing pipeline, customer activity, and essential operations.</p>
            <div class="workspace-dashboard-hero__actions">
                <a href="{{ route('admin.catalog.index') }}">Review listings</a>
                <a href="{{ route('admin.appearance.index') }}">Manage appearance</a>
            </div>
        </div>
        <span aria-hidden="true" class="workspace-dashboard-hero__art">⌂</span>
    </section>

    <section aria-label="Marketplace statistics" class="dashboard-stat-grid workspace-dashboard-stats">
        @foreach($counts as $label => $count)
            <article class="dashboard-stat">
                <span>{{ $label }}</span>
                <strong>{{ number_format($count) }}</strong>
                <small>Current marketplace total</small>
            </article>
        @endforeach
    </section>

    <div class="dashboard-content-grid workspace-dashboard-columns">
        <section class="dashboard-section">
            <div class="workspace-dashboard-section-heading">
                <div><p class="admin-eyebrow">POPULAR TASKS</p><h2>Quick actions</h2></div>
                <span>Manage your marketplace</span>
            </div>
            <div class="dashboard-actions workspace-dashboard-actions">
                <a href="{{ route('admin.catalog.index') }}"><strong>Review listings</strong><span>Onboard companies and approve projects and properties.</span></a>
                <a href="{{ route('admin.homepage.index') }}"><strong>Homepage Builder</strong><span>Publish content sections and main navigation updates.</span></a>
                <a href="{{ route('admin.appearance.index') }}"><strong>Appearance Manager</strong><span>Manage brand, colors, hero messaging and SEO descriptions.</span></a>
                <a href="{{ route('admin.media.index') }}"><strong>Media Library</strong><span>Browse images, descriptions and usage-rights information.</span></a>
                <a href="{{ route('admin.policies.index') }}"><strong>Policy Center</strong><span>Review and publish legal pages without percentage barriers.</span></a>
                <a href="{{ route('admin.users.index') }}"><strong>User Directory</strong><span>Review registered users and company memberships.</span></a>
                <a href="{{ route('admin.viewings.index') }}"><strong>Viewing Requests</strong><span>Review requests for customer property tours.</span></a>
                <a href="{{ route('admin.contacts.index') }}"><strong>Contact Inbox</strong><span>Read customer and support messages.</span></a>
                <a href="{{ route('admin.demo.index') }}"><strong>Demo content importer</strong><span>Inspect the original Barama archive; customer import still requires acceptance.</span></a>
            </div>
        </section>
        <section class="dashboard-section workspace-dashboard-status">
            <div class="workspace-dashboard-section-heading"><div><p class="admin-eyebrow">SYSTEM HEALTH</p><h2>Installation status</h2></div></div>
            <p>The Laravel application is installed and the administration dashboard is available.</p>
            @if($import)
                <div class="workspace-status-item">
                    <span class="workspace-status-item__dot"></span>
                    <div><strong>Demo archive imported</strong>
                        <p>Version {{ $import->archive_version }} · {{ number_format($import->marketplace_records) }} records · {{ $import->image_count }} images.</p></div>
                </div>
            @else
                <div class="workspace-status-item">
                    <span class="workspace-status-item__dot workspace-status-item__dot--amber"></span>
                    <div><strong>Demo content not imported</strong>
                        <p>Public property pages only display approved, published listings.</p></div>
                </div>
            @endif
            <p class="dashboard-note">This is a Laravel development build. Billing, complete licensing, automatic updating and full visual-page editing remain under migration.</p>
            <a href="{{ route('admin.license.index') }}">License &amp; Activation</a>
        </section>
    </div>

    <div class="dashboard-content-grid workspace-dashboard-columns">
        <section class="dashboard-section">
            <div class="workspace-dashboard-section-heading">
                <div><p class="admin-eyebrow">RECENT ACTIVITY</p><h2>Property inquiries</h2></div>
                <a href="{{ route('admin.inquiries.index') }}">Open inbox</a>
            </div>
            @forelse($recentInquiries as $inquiry)
                <div class="dashboard-feed"><strong>{{ $inquiry->listing?->property?->title ?? 'Property' }}</strong>
                    <span>{{ $inquiry->full_name }} · {{ $inquiry->created_at->diffForHumans() }}</span></div>
            @empty <p class="dashboard-note">No inquiries received yet.</p> @endforelse
        </section>
        <section class="dashboard-section">
            <div class="workspace-dashboard-section-heading">
                <div><p class="admin-eyebrow">CUSTOMER MESSAGES</p><h2>Contact inbox</h2></div>
                <a href="{{ route('admin.contacts.index') }}">View messages</a>
            </div>
            @forelse($recentContacts as $message)
                <div class="dashboard-feed"><strong>{{ $message->subject }}</strong>
                    <span>{{ $message->full_name }} · {{ $message->created_at->diffForHumans() }}</span></div>
            @empty <p class="dashboard-note">No contact messages yet.</p> @endforelse
        </section>
    </div>
</x-layouts.admin>
