@props(['title' => 'My Account — Barama Real Estate'])
<x-layouts.frontend :title="$title">
    <main class="customer-shell" id="main-content">
        <aside class="customer-sidebar" aria-label="Account sections">
            <p class="customer-sidebar__eyebrow">My workspace</p>
            <h2>{{ auth()->user()->name }}</h2>
            <nav class="customer-nav">
                <a @class(['is-current' => request()->routeIs('account.dashboard')]) href="{{ route('account.dashboard') }}">Overview</a>
                <a @class(['is-current' => request()->routeIs('account.saved.*')]) href="{{ route('account.saved.index') }}">Saved properties</a>
                <a @class(['is-current' => request()->routeIs('account.inquiries.*')]) href="{{ route('account.inquiries.index') }}">My inquiries</a>
                <a @class(['is-current' => request()->routeIs('account.viewings.*')]) href="{{ route('account.viewings.index') }}">Viewing requests</a>
                <a @class(['is-current' => request()->routeIs('account.notifications.*')]) href="{{ route('account.notifications.index') }}">Notifications</a>
                <a @class(['is-current' => request()->routeIs('account.profile.*')]) href="{{ route('account.profile.edit') }}">Profile &amp; security</a>
                @if(auth()->user()->isSystemAdministrator())
                    <a href="{{ route('admin.dashboard') }}">Open Admin Dashboard</a>
                @elseif(auth()->user()->companyMemberships()->where('status','active')->exists())
                    <a href="{{ route('developer.dashboard') }}">Developer workspace</a>
                @endif
            </nav>
            <form method="post" action="{{ route('logout') }}">@csrf
                <button class="customer-signout" type="submit">Sign out</button>
            </form>
        </aside>
        <div class="customer-main">
            @if(session('notice'))<div class="notice" role="status">{{ session('notice') }}</div>@endif
            @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="notice notice--error" role="alert"><ul>
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>@endif
            {{ $slot }}
        </div>
    </main>
</x-layouts.frontend>
