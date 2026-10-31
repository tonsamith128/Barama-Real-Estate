<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard — Barama Real Estate</title>
    @vite(['resources/css/admin/app.css', 'resources/js/admin/app.js'])
</head>
<body class="admin-body">
<div class="dashboard-layout">
    <aside class="dashboard-sidebar">
        <a class="dashboard-brand" href="{{ route('admin.dashboard') }}">Barama <small>Real Estate / Admin</small></a>
        <nav aria-label="Admin navigation" class="dashboard-nav">
            <a class="is-active" href="{{ route('admin.dashboard') }}">Overview</a>
            <a href="{{ route('admin.catalog.index') }}">Companies &amp; listings</a>
            <a href="{{ route('admin.media.index') }}">Media Library</a>
            <a href="{{ route('admin.inquiries.index') }}">Customer inquiries</a>
            <a href="{{ route('admin.demo.index') }}">Demo content importer</a>
            <a href="{{ route('admin.license.index') }}">License &amp; Activation</a>
            <a href="{{ route('home') }}">View website</a>
            <a href="{{ route('account.dashboard') }}">My account</a>
        </nav>
        <form method="post" action="{{ route('logout') }}">@csrf
            <button type="submit" class="dashboard-signout">Sign out</button>
        </form>
    </aside>
    <main class="dashboard-main">
        <header class="dashboard-top">
            <div><p class="admin-eyebrow">Administration / Overview</p>
                <h1>Dashboard</h1><p>Manage your companies, listings, inquiries and demo content.</p></div>
            <a class="dashboard-link" href="{{ route('home') }}">Open website</a>
        </header>
        <section class="dashboard-stat-grid" aria-label="Marketplace statistics">
            @foreach($counts as $label => $count)
                <article class="dashboard-stat"><span>{{ $label }}</span><strong>{{ number_format($count) }}</strong></article>
            @endforeach
        </section>
        <div class="dashboard-content-grid">
            <section class="dashboard-section">
                <h2>Quick actions</h2>
                <div class="dashboard-actions">
                    <a href="{{ route('admin.catalog.index') }}"><strong>Review listings</strong><span>Onboard companies and approve new properties.</span></a>
                    <a href="{{ route('admin.media.index') }}"><strong>Media Library</strong><span>Review uploaded assets and listing references.</span></a>
                    <a href="{{ route('admin.inquiries.index') }}"><strong>Customer inquiries</strong><span>View contact requests from published listings.</span></a>
                    <a href="{{ route('admin.demo.index') }}"><strong>Import demo content</strong><span>Preview the original Barama demo archive; signed import is staging-only.</span></a>
                </div>
            </section>
            <section class="dashboard-section">
                <h2>Installation status</h2>
                <p>The Laravel application is installed and the administration dashboard is available.</p>
                @if($import)
                    <p class="dashboard-note"><strong>Demo imported</strong> — version {{ $import->archive_version }} with {{ number_format($import->marketplace_records) }} records and {{ $import->image_count }} images.</p>
                @else
                    <p class="dashboard-note">There is no imported demo content yet. The public marketplace will only show approved published listings.</p>
                @endif
                <p class="dashboard-note">This is an integrated development build. Billing, full media management, licenses and update modules remain under migration.</p>
            </section>
        </div>
    </main>
</div>
</body></html>
