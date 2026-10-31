<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Customer Inquiries | Barama</title>@vite(['resources/css/admin/app.css','resources/js/admin/app.js'])</head>
<body><main class="admin-shell"><header><h1>Customer Inquiries</h1>
<nav><a href="{{ route('admin.dashboard') }}">Dashboard</a> · <a href="{{ route('admin.catalog.index') }}">Marketplace</a></nav></header>
@if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
@if($errors->any())<div class="admin-notice" role="alert">{{ $errors->first() }}</div>@endif
<section class="admin-stat"><h2>Incoming inquiries</h2>
<ul class="admin-company-list">@forelse($inquiries as $inquiry)
<li><div><strong>{{ $inquiry->listing->property->title }}</strong> · {{ $inquiry->listing->property->company->name }}
<p>{{ $inquiry->full_name }} · {{ $inquiry->email }} @if($inquiry->phone) · {{ $inquiry->phone }} @endif</p>
<p>{{ $inquiry->message }}</p><small>{{ $inquiry->created_at->format('Y-m-d H:i') }} · {{ $inquiry->status }}</small>@if($inquiry->status === 'new')
<form method="post" action="{{ route('admin.inquiries.respond', $inquiry) }}">@csrf @method('PATCH')
<button type="submit">Mark handled</button></form>@endif
</div></li>
@empty<li>No inquiries received.</li>@endforelse</ul>
{{ $inquiries->links() }}</section>
</main></body></html>
