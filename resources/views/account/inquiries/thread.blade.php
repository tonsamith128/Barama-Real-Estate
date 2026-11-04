<x-layouts.account title="Inquiry Conversation — Barama Real Estate">
    <header class="customer-heading"><p class="customer-eyebrow">PROPERTY CONVERSATION</p><h1>Inquiry messages</h1><p>Private messages between you and the property team.</p></header>
    @if(session('notice'))<p class="notice" role="status">{{ session('notice') }}</p>@endif
    <x-inquiries.thread :inquiry="$inquiry" :messages="$messages" :reply-action="$replyAction" />
</x-layouts.account>
