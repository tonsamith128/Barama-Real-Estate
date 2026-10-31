@props(['title' => 'Barama Real Estate — Workspace'])
@php($developerArea = request()->routeIs('developer.*'))
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14243f">
    <title>{{ $title }}</title>
    @vite(['resources/css/admin/app.css', 'resources/js/admin/app.js'])
</head>
<body class="admin-body">
    <a href="#main-content" class="workspace-skip">Skip to main content</a>
    <div class="workspace-frame">
        <aside class="workspace-sidebar" aria-label="Workspace navigation">
            <a class="workspace-brand" href="{{ route('home') }}" aria-label="Barama Real Estate homepage">
                <span class="workspace-brand__mark" aria-hidden="true">B</span>
                <span class="workspace-brand__name">Barama <small>REAL ESTATE</small></span>
            </a>

            <div class="workspace-person">
                <span class="workspace-person__avatar" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()?->name ?? 'B',0,1)) }}</span>
                <div>
                    <strong>{{ auth()->user()?->name ?? 'Workspace' }}</strong>
                    <small>{{ $developerArea ? 'Company workspace' : 'Platform administrator' }}</small>
                </div>
            </div>

            <nav class="workspace-nav" aria-label="{{ $developerArea ? 'Developer navigation' : 'Admin navigation' }}">
                @if($developerArea)
                    <span class="workspace-nav__label">YOUR BUSINESS</span>
                    <a @class(['is-current' => request()->routeIs('developer.dashboard')]) href="{{ route('developer.dashboard') }}">Overview</a>
                    <a @class(['is-current' => request()->routeIs('developer.inquiries.*')]) href="{{ route('developer.inquiries.index') }}">Company inquiries</a>
                    <a @class(['is-current' => request()->routeIs('developer.viewings.*')]) href="{{ route('developer.viewings.index') }}">Viewing requests</a>
                @else
                    <span class="workspace-nav__label">OVERVIEW</span>
                    <a @class(['is-current' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">Dashboard</a>
                    <a @class(['is-current' => request()->routeIs('admin.catalog.*')]) href="{{ route('admin.catalog.index') }}">Companies &amp; listings</a>
                    <a @class(['is-current' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">User directory</a>
                    <span class="workspace-nav__label">CONTENT &amp; EXPERIENCE</span>
                    <a @class(['is-current' => request()->routeIs('admin.appearance.*')]) href="{{ route('admin.appearance.index') }}">Appearance Manager</a>
                    <a @class(['is-current' => request()->routeIs('admin.homepage.*')]) href="{{ route('admin.homepage.index') }}">Homepage Builder</a>
                    <a @class(['is-current' => request()->routeIs('admin.media.*')]) href="{{ route('admin.media.index') }}">Media Library</a>
                    <a @class(['is-current' => request()->routeIs('admin.policies.*')]) href="{{ route('admin.policies.index') }}">Policy Center</a>
                    <span class="workspace-nav__label">CUSTOMERS &amp; SUPPORT</span>
                    <a @class(['is-current' => request()->routeIs('admin.inquiries.*')]) href="{{ route('admin.inquiries.index') }}">Customer inquiries</a>
                    <a @class(['is-current' => request()->routeIs('admin.viewings.*')]) href="{{ route('admin.viewings.index') }}">Viewing requests</a>
                    <a @class(['is-current' => request()->routeIs('admin.contacts.*')]) href="{{ route('admin.contacts.index') }}">Contact inbox</a>
                    <span class="workspace-nav__label">FINANCE</span>
                    <a @class(['is-current' => request()->routeIs('admin.finance.plans.*')]) href="{{ route('admin.finance.plans.index') }}">Pricing plans &amp; inquiries</a>
                    <a @class(['is-current' => request()->routeIs('admin.finance.billing.*')]) href="{{ route('admin.finance.billing.index') }}">Billing ledger</a>
                    <span class="workspace-nav__label">SYSTEM</span>
                    <a @class(['is-current' => request()->routeIs('admin.demo.*')]) href="{{ route('admin.demo.index') }}">Demo content importer</a>
                    <a @class(['is-current' => request()->routeIs('admin.license.*')]) href="{{ route('admin.license.index') }}">License &amp; Activation</a>
                @endif
                <span class="workspace-nav__label">QUICK LINKS</span>
                <a href="{{ route('home') }}">View website</a>
                <a href="{{ route('account.dashboard') }}">My account</a>
                @if(!$developerArea && auth()->user()?->companyMemberships()->where('status','active')->exists())
                    <a href="{{ route('developer.dashboard') }}">Developer workspace</a>
                @endif
            </nav>
            <form method="post" action="{{ route('logout') }}" class="workspace-sidebar__signout">
                @csrf<button type="submit">Sign out</button>
            </form>
        </aside>
        <div class="workspace-page">
            <header class="workspace-topbar">
                <details class="workspace-mobile-menu">
                    <summary>Menu</summary>
                    <nav aria-label="Mobile workspace shortcuts">
                        <a href="{{ $developerArea ? route('developer.dashboard') : route('admin.dashboard') }}">Overview</a>
                        @if(!$developerArea)
                            <a href="{{ route('admin.catalog.index') }}">Marketplace</a>
                            <a href="{{ route('admin.appearance.index') }}">Appearance</a>
                            <a href="{{ route('admin.media.index') }}">Media Library</a>
                            <a href="{{ route('admin.policies.index') }}">Policies</a>
                            <a href="{{ route('admin.finance.plans.index') }}">Pricing plans</a>
                        @else
                            <a href="{{ route('developer.inquiries.index') }}">Inquiries</a>
                            <a href="{{ route('developer.viewings.index') }}">Viewings</a>
                        @endif
                        <a href="{{ route('account.dashboard') }}">My account</a>
                    </nav>
                </details>
                <div class="workspace-topbar__crumb"><span class="workspace-topbar__dot"></span>{{ $developerArea ? 'Developer Management' : 'Platform Administration' }}</div>
                <div class="workspace-topbar__actions">
                    <a href="{{ route('home') }}">View website</a>
                    <a class="workspace-topbar__avatar" href="{{ route('account.dashboard') }}" aria-label="Open my account">{{ strtoupper(mb_substr(auth()->user()?->name ?? 'B',0,1)) }}</a>
                </div>
            </header>
            <main class="admin-main workspace-main" id="main-content">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
