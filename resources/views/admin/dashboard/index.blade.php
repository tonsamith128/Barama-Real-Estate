<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard | Barama Real Estate</title>@vite(['resources/css/admin/app.css','resources/js/admin/app.js'])</head>
<body><main class="admin-shell"><header><p>Barama Real Estate</p><h1>Admin Dashboard</h1>
<nav><a href="{{ route('home') }}">Marketplace</a> · <a href="{{ route('admin.demo.index') }}">Demo Importer</a></nav></header>
<section class="admin-grid">@foreach($counts as $label => $count)
<div class="admin-stat"><h2>{{ $label }}</h2><strong>{{ number_format($count) }}</strong></div>@endforeach</section>
@if($import)<section class="admin-stat"><h2>Demo import</h2><p>Version {{ $import->archive_version }} · {{ $import->image_count }} images · {{ number_format($import->marketplace_records) }} marketplace records</p></section>@endif
<p>The full management workspace is still under migration. Demo data can be inspected here without exposing unfinished operations.</p>
</main></body></html>
