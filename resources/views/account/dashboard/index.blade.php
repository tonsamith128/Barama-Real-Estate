<x-layouts.account title="Account Dashboard — Barama Real Estate">
    <header class="customer-heading">
        <p class="customer-eyebrow">Your account</p>
        <h1>Welcome back, {{ auth()->user()->name }}.</h1>
        <p>Track the homes you love and the requests you've sent.</p>
    </header>

    <div class="customer-stat-grid">
        @foreach($metrics as $label => $amount)
            <article class="customer-stat"><strong>{{ number_format($amount) }}</strong><span>{{ $label }}</span></article>
        @endforeach
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
