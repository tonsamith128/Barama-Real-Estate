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


    <section class="workspace-operations" aria-labelledby="review-queue-heading">
        <div class="workspace-dashboard-section-heading">
            <div><p class="admin-eyebrow">OPERATIONS CENTER</p><h2 id="review-queue-heading">Your review queue</h2></div>
            <span>Live counts · follow links to review safely</span>
        </div>
        <div class="workspace-queue-grid">
            @foreach([
                ['label' => 'Pending projects', 'count' => $reviewCounts['projects'], 'href' => route('admin.catalog.index')],
                ['label' => 'Pending listings', 'count' => $reviewCounts['listings'], 'href' => route('admin.catalog.index')],
                ['label' => 'New inquiries', 'count' => $reviewCounts['inquiries'], 'href' => route('admin.inquiries.index')],
                ['label' => 'Pending viewings', 'count' => $reviewCounts['viewings'], 'href' => route('admin.viewings.index')],
                ['label' => 'Unread contact requests', 'count' => $reviewCounts['contacts'], 'href' => route('admin.contacts.index')],
            ] as $task)
                <a class="workspace-queue-tile" href="{{ $task['href'] }}">
                    <span>{{ $task['label'] }}</span><strong>{{ number_format($task['count']) }}</strong>
                    <small>{{ $task['count'] > 0 ? 'Open workspace to review' : 'Nothing waiting' }}</small>
                </a>
            @endforeach
        </div>
    </section>

    <div class="workspace-dashboard-columns dashboard-content-grid">
        <section class="dashboard-section workspace-activity-panel" aria-labelledby="activity-heading">
            <div class="workspace-dashboard-section-heading">
                <div><p class="admin-eyebrow">REAL-TIME RECORDS</p><h2 id="activity-heading">Marketplace activity</h2></div>
                <nav class="workspace-period-switch" aria-label="Activity period">
                    <a @class(['is-current' => $activityDays === 7]) href="{{ route('admin.dashboard', ['days' => 7]) }}" @if($activityDays === 7) aria-current="page" @endif>7 days</a>
                    <a @class(['is-current' => $activityDays === 30]) href="{{ route('admin.dashboard', ['days' => 30]) }}" @if($activityDays === 30) aria-current="page" @endif>30 days</a>
                </nav>
            </div>
            <div class="workspace-chart-legend"><span>Inquiries</span><span>Viewing requests</span></div>
            <div class="workspace-activity-chart" role="img" aria-label="Daily inquiries and viewing requests over the past {{ $activityDays }} days">
                @foreach($activity as $point)
                    @php($total = $point['inquiries'] + $point['viewings'])
                    <div class="workspace-activity-day" title="{{ $point['label'] }}: {{ $point['inquiries'] }} inquiries, {{ $point['viewings'] }} viewings">
                        <div class="workspace-activity-track">
                            @if($total > 0)
                                <div class="workspace-activity-bar" style="height: {{ max(6, (int) round($total / $activityMax * 100)) }}%">
                                    @if($point['viewings'] > 0)
                                        <span class="workspace-activity-viewings" style="height: {{ (int) round($point['viewings'] / $total * 100) }}%"></span>
                                    @endif
                                </div>
                            @else
                                <span class="workspace-activity-zero" aria-hidden="true"></span>
                            @endif
                        </div>
                        <small>{{ $activityDays === 7 || $loop->iteration % 5 === 1 ? $point['label'] : '·' }}</small>
                    </div>
                @endforeach
            </div>
            <p class="dashboard-note">Counts come from stored inquiries and viewing requests. No external analytics service or customer tracking is used.</p>
        </section>
        <section class="dashboard-section" aria-labelledby="waiting-review-heading">
            <div class="workspace-dashboard-section-heading">
                <div><p class="admin-eyebrow">MODERATION</p><h2 id="waiting-review-heading">Waiting for review</h2></div>
                <a href="{{ route('admin.catalog.index') }}">Open catalog</a>
            </div>
            @forelse($pendingProjects as $project)
                <div class="workspace-compact-record">
                    <div><strong>{{ $project->name }}</strong><small>Project · {{ $project->company?->name ?? 'Company unavailable' }}</small></div>
                    <span class="workspace-status-pill">Pending</span>
                </div>
            @empty
                <p class="dashboard-note">No projects waiting for moderation.</p>
            @endforelse
            @foreach($pendingListings as $offer)
                <div class="workspace-compact-record">
                    <div><strong>{{ $offer->property?->title ?? 'Property unavailable' }}</strong><small>Listing · {{ $offer->property?->company?->name ?? 'Company unavailable' }}</small></div>
                    <span class="workspace-status-pill">Pending</span>
                </div>
            @endforeach
            <a class="workspace-panel-footer" href="{{ route('admin.catalog.index') }}">Review all submissions</a>
        </section>
    </div>

    <section class="dashboard-section workspace-viewing-queue" aria-labelledby="dashboard-viewings-heading">
        <div class="workspace-dashboard-section-heading">
            <div><p class="admin-eyebrow">CUSTOMER APPOINTMENTS</p><h2 id="dashboard-viewings-heading">Pending viewing requests</h2></div>
            <a href="{{ route('admin.viewings.index') }}">Manage viewings</a>
        </div>
        <div class="workspace-viewing-grid">
            @forelse($pendingViewings as $viewing)
                <article class="workspace-viewing-card">
                    <strong>{{ $viewing->listing?->property?->title ?? 'Property unavailable' }}</strong>
                    <span>{{ $viewing->user?->name ?? 'Customer' }}</span>
                    <time datetime="{{ $viewing->requested_at->toIso8601String() }}">{{ $viewing->requested_at->format('M j, Y · g:i A') }}</time>
                </article>
            @empty
                <p class="dashboard-note">No pending viewing requests.</p>
            @endforelse
        </div>
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
