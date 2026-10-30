<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Marketplace Moderation | Barama</title>@vite(['resources/css/admin/app.css','resources/js/admin/app.js'])</head>
<body><main class="admin-shell"><header><h1>Marketplace Moderation</h1>
<nav><a href="{{ route('admin.dashboard') }}">Dashboard</a> · <a href="{{ route('admin.demo.index') }}">Demo Importer</a></nav></header>
@if(session('notice'))<p class="admin-notice">{{ session('notice') }}</p>@endif
@if($errors->any())<div class="admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="admin-stat"><h2>Create company and assign verified owner</h2>
<form method="post" action="{{ route('admin.catalog.companies.create') }}">@csrf
<label>Company name <input name="name" required maxlength="190"></label>
<label>Company slug <input name="slug" required maxlength="190" pattern="[A-Za-z0-9_-]+"></label>
<label>Verified owner's registered email <input type="email" name="owner_email" required></label>
<button type="submit">Create and approve company</button></form>
</section>
<section class="admin-stat"><h2>Pending listing reviews</h2>
<ul>@forelse($pending as $offer)<li>
<strong>{{ $offer->property->title }}</strong> — {{ $offer->property->company->name }} · {{ $offer->currency }} {{ number_format((float)$offer->price,2) }}
<form method="post" action="{{ route('admin.catalog.approve', $offer) }}">@csrf<button type="submit">Approve and publish</button></form>
</li>@empty<li>No pending submissions.</li>@endforelse</ul>
{{ $pending->links() }}</section>
<section class="admin-stat"><h2>Companies</h2><ul>
@foreach($companies as $company)<li>{{ $company->name }} — {{ $company->status }}</li>@endforeach
</ul>{{ $companies->links() }}</section>
</main></body></html>
