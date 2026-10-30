<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>License & Activation — Barama Real Estate</title>@vite(['resources/css/admin/app.css','resources/js/admin/app.js'])</head>
<body class="admin-body"><main class="admin-shell">
<header><p class="admin-eyebrow">System / License</p><h1>License &amp; Activation</h1>
<nav><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a> · <a href="{{ route('home') }}">Website</a></nav></header>
@if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
@if($errors->any())<div class="admin-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="admin-stat">
<h2>Current installation</h2><p><strong>Domain:</strong> {{ $domain }}</p>
@if($receipt)
<p><strong>Status:</strong> {{ ucfirst($receipt['status']) }}</p>
<p><strong>Last activated:</strong> {{ $receipt['validated_at'] ?? 'Recorded' }}</p>
<p>The purchase code is not saved. The activation token is kept in private server storage.</p>
@else
<p>No publisher activation is recorded for this Laravel installation. If you already completed the five-step setup, you can activate here without reinstalling or resetting your database.</p>
<form method="post" action="{{ route('admin.license.activate') }}">@csrf
<label for="purchase_code">Purchase Code / License Key</label>
<input id="purchase_code" type="text" name="purchase_code" minlength="8" maxlength="120" autocomplete="off" required placeholder="Enter your CodeCanyon purchase code or issued key">
<button type="submit">Verify and activate license</button></form>
@endif
</section>
<p>This interface uses the original Barama publisher license API. It requires an authentic accepted code; no code or entitlement is invented for development testing.</p>
</main></body></html>