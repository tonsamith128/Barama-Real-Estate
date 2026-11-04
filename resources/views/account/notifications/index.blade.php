<x-layouts.account title="Notifications — Barama Real Estate">
    <header class="customer-heading"><p class="customer-eyebrow">Your inbox</p><h1>Notifications</h1>
        <p>Important updates about your account and property requests.</p></header>
    <section class="customer-card">
        <div class="customer-notification-actions">
            <p>{{ number_format($unreadCount) }} unread {{ $unreadCount === 1 ? 'notification' : 'notifications' }}</p>
            @if($unreadCount > 0)
                <form method="post" action="{{ route('account.notifications.read-all') }}">
                    @csrf
                    <button class="button button--secondary" type="submit">Mark all as read</button>
                </form>
            @endif
        </div>
        @forelse($notifications as $notification)
            <div @class(['customer-record', 'customer-record--full', 'customer-record--unread' => !$notification->read_at])>
                <div><strong>{{ $notification->title }}</strong><p>{{ $notification->message }}</p>
                    <small>{{ $notification->created_at->diffForHumans() }}</small></div>
                @if(!$notification->read_at)
                    <form method="post" action="{{ route('account.notifications.read', $notification) }}">@csrf @method('PATCH')
                        <button class="button button--secondary" type="submit">Mark as read</button>
                    </form>
                @else<small>Read</small>@endif
            </div>
        @empty<p class="customer-empty">No notifications yet.</p>@endforelse
        {{ $notifications->links() }}
    </section>
</x-layouts.account>
