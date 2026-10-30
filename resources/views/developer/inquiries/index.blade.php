<x-layouts.admin title="Inquiries — Developer Workspace">
<header class="admin-heading"><div><p class="admin-eyebrow">Developer Workspace</p>
<h1>Your company inquiries</h1><p>Only inquiries associated with your active company memberships are shown.</p></div>
<a href="{{ route('developer.dashboard') }}">Your companies</a></header>
<section class="admin-card"><ul class="admin-company-list">@forelse($inquiries as $inquiry)
<li><div><strong>{{ $inquiry->listing->property->title }}</strong>
<p>{{ $inquiry->full_name }} · {{ $inquiry->email }}</p><p>{{ $inquiry->message }}</p>
<small>{{ $inquiry->created_at->format('Y-m-d H:i') }} · {{ $inquiry->status }}</small></div></li>
@empty<li>No inquiries yet.</li>@endforelse</ul>
{{ $inquiries->links() }}</section>
</x-layouts.admin>
