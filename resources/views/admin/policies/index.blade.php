<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Policy Center — Barama</title>@vite(['resources/css/admin/app.css','resources/js/admin/app.js'])</head>
<body class="admin-body"><main class="admin-shell">
<header><p class="admin-eyebrow">Barama Real Estate / Legal</p>
<h1>Policy Center</h1><p>Write, save, and publish legal pages. There is no compliance-percentage publishing barrier.</p>
<nav><a href="{{ route('admin.dashboard') }}">Dashboard</a> · <a href="{{ route('admin.policies.create') }}">Create legal page</a> · <a href="{{ route('legal.index') }}">Public legal pages</a></nav>
</header>
@if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
<section class="admin-stat">
<h2>Legal pages</h2>
<table><thead><tr><th>Page</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($policies as $policy)<tr>
<td><strong>{{ $policy->title }}</strong><small> /legal/{{ $policy->slug }}</small></td>
<td>{{ ucfirst($policy->status) }}</td>
<td><div class="policy-actions">
<a href="{{ route('admin.policies.edit', $policy) }}">Edit</a>
@if($policy->status !== 'published')
<form method="post" action="{{ route('admin.policies.publish', $policy) }}">@csrf
<button type="submit">Publish</button></form>
@else
<a href="{{ route('legal.show', $policy->slug) }}">View public page</a>
<form method="post" action="{{ route('admin.policies.unpublish', $policy) }}">@csrf
<button type="submit">Unpublish</button></form>
@endif
</div></td></tr>
@empty<tr><td colspan="3">No legal pages. Create your first page or import draft templates through the demo importer.</td></tr>@endforelse
</tbody></table>{{ $policies->links() }}
</section></main></body></html>
