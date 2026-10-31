<x-layouts.admin title="Contact Inbox — Barama Real Estate">
    <header class="admin-heading"><div><p class="admin-eyebrow">Administration / Support</p><h1>Contact Inbox</h1>
        <p>Messages sent through the public contact form.</p></div>
        <a href="{{ route('admin.dashboard') }}">Dashboard</a></header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    <section class="admin-card">
        @forelse($messages as $message)
            <article class="admin-message"><div><p><strong>{{ $message->subject }}</strong> · {{ ucfirst($message->status) }}</p>
                <p>{{ $message->full_name }} · {{ $message->email }} · {{ $message->created_at->format('M j, Y') }}</p>
                <p class="admin-message__body">{{ $message->message }}</p></div>
                @if($message->status === 'new')
                    <form method="post" action="{{ route('admin.contacts.read', $message) }}">@csrf @method('PATCH')
                        <button type="submit">Mark reviewed</button>
                    </form>
                @endif
            </article>
        @empty<p>No messages yet.</p>@endforelse
        {{ $messages->links() }}
    </section>
</x-layouts.admin>
