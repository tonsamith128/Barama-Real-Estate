<x-layouts.account title="Account Dashboard — Barama Real Estate">
    <header class="customer-heading">
        <p class="customer-eyebrow">Your account</p>
        <h1>Welcome back, {{ auth()->user()->name }}.</h1>
        <p>Track the homes you love and the requests you've sent.</p>
    </header>

    <section class="customer-welcome">
        <div>
            <span class="customer-welcome__eyebrow">YOUR PERSONAL PROPERTY SPACE</span>
            <h2>Make room for what's next.</h2>
            <p>Your saved homes, property searches, and conversations are all here in one place.</p>
            <div class="customer-welcome__actions">
                <a class="button" href="{{ route('properties.index') }}">Discover properties</a>
                <a class="button button--secondary" href="{{ route('account.searches.index') }}">Your saved searches</a>
            </div>
        </div>
        <span class="customer-welcome__decoration" aria-hidden="true">⌂</span>
    </section>
    <div class="customer-stat-grid">
        @foreach($metrics as $label => $amount)
            <article class="customer-stat"><strong>{{ number_format($amount) }}</strong><span>{{ $label }}</span></article>
        @endforeach
    </div>


    <div class="customer-grid customer-priority-grid">
        <section class="customer-card" aria-labelledby="upcoming-viewings-heading">
            <div class="customer-card__heading">
                <div><p class="customer-eyebrow">Appointments</p><h2 id="upcoming-viewings-heading">Upcoming viewings</h2></div>
                <a href="{{ route('account.viewings.index') }}">View requests</a>
            </div>
            @forelse($upcomingViewings as $viewing)
                <div class="customer-record customer-record--full">
                    <div>
                        <strong>{{ $viewing->listing?->property?->title ?? 'Property unavailable' }}</strong>
                        <p><time datetime="{{ $viewing->requested_at->toIso8601String() }}">{{ $viewing->requested_at->format('M j, Y · g:i A') }}</time></p>
                    </div>
                    <span class="customer-status">{{ ucfirst($viewing->status) }}</span>
                </div>
            @empty
                <p class="customer-empty">No upcoming viewings. Open a published property to request an appointment.</p>
            @endforelse
        </section>
        <section class="customer-card" aria-labelledby="account-unread-heading">
            <div class="customer-card__heading">
                <div><p class="customer-eyebrow">Personal inbox</p><h2 id="account-unread-heading">Unread updates</h2></div>
                <a href="{{ route('account.notifications.index') }}">All notifications</a>
            </div>
            @forelse($unreadNotifications as $notification)
                <div class="customer-record customer-record--full customer-record--unread">
                    <div><strong>{{ $notification->title }}</strong>
                        <p>{{ $notification->message }}</p>
                        <small>{{ $notification->created_at->diffForHumans() }}</small></div>
                </div>
            @empty
                <p class="customer-empty">You're all caught up. New property updates will appear here.</p>
            @endforelse
            @if($metrics['Unread notifications'] > 0)
                <form method="post" action="{{ route('account.notifications.read-all') }}">
                    @csrf
                    <button class="button button--secondary customer-bulk-action" type="submit">Mark all as read</button>
                </form>
            @endif
        </section>
    </div>

    <div class="customer-grid">
        <section class="customer-card">
            <div class="customer-card__heading"><h2>Your saved homes</h2>
                <a href="{{ route('account.saved.index') }}">View all</a></div>
            @forelse($recentSaved as $item)
                <a class="customer-record" href="{{ route('properties.show', $item->listing->slug) }}">
                    <span>{{ $item->listing->property->title }}</span>
                    <small>{{ $item->listing->currency }} {{ number_format((float) $item->listing->price) }}</small>
                </a>
            @empty
                <p class="customer-empty">No saved properties yet. Explore listings to start your shortlist.</p>
            @endforelse
            <a class="button" href="{{ route('properties.index') }}">Explore properties</a>
        </section>
        <section class="customer-card">
            <div class="customer-card__heading"><h2>Recent inquiries</h2>
                <a href="{{ route('account.inquiries.index') }}">View history</a></div>
            @forelse($recentInquiries as $inquiry)
                <div class="customer-record">
                    <span>{{ $inquiry->listing?->property?->title ?? 'Property inquiry' }}</span>
                    <small>{{ ucfirst($inquiry->status) }} · {{ $inquiry->created_at->format('M j, Y') }}</small>
                </div>
            @empty
                <p class="customer-empty">Your inquiries will appear here once you contact a property owner.</p>
            @endforelse
        </section>
    </div>
    <section class="customer-card customer-searches-panel">
        <div class="customer-card__heading">
            <div><p class="customer-eyebrow">Quick access</p><h2>Your saved searches</h2></div>
            <a href="{{ route('account.searches.index') }}">Manage searches</a>
        </div>
        @forelse($recentSearches as $search)
            <a class="customer-record" href="{{ route('properties.index', $search->filters) }}">
                <span>{{ $search->name }}</span><small>Open search →</small>
            </a>
        @empty
            <p class="customer-empty">No searches saved yet. Filter properties by price, type or transaction to create your first shortcut.</p>
        @endforelse
    </section>
    <section class="customer-card customer-next">
        <h2>Next steps</h2>
        <p>Manage your profile, request a viewing, or check updates from your property inquiries.</p>
        <div class="actions">
            <a class="button button--secondary" href="{{ route('account.viewings.index') }}">Viewing requests</a>
            <a class="button button--secondary" href="{{ route('account.notifications.index') }}">Notifications</a>
            <a class="button button--secondary" href="{{ route('account.profile.edit') }}">Edit profile</a>
        </div>
    </section>
</x-layouts.account>
