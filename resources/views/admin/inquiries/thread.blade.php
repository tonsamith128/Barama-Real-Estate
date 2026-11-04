<x-layouts.admin title="Inquiry Conversation — Barama Real Estate">
    <header class="admin-heading"><div><p class="admin-eyebrow">ADMIN / CUSTOMER MESSAGES</p><h1>Inquiry conversation</h1><p>Messages remain visible only to the linked customer and authorized property managers.</p></div><a href="{{ route('admin.inquiries.index') }}">Back to inquiries</a></header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    <x-inquiries.thread :inquiry="$inquiry" :messages="$messages" :reply-action="$replyAction" />
</x-layouts.admin>
