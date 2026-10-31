@props(['title' => 'Barama Real Estate'])
@php($appearanceSettings = app(\App\Services\Appearance\SiteAppearanceService::class)->published())
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="--barama-primary: {{ $appearanceSettings['primary_color'] }};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $appearanceSettings['meta_description'] }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/frontend/app.css', 'resources/js/frontend/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="site-logo" href="{{ route('home') }}"><span class="site-logo__mark">B</span><span>{{ $appearanceSettings['brand_short'] }} <small>{{ $appearanceSettings['brand_subtitle'] }}</small></span></a>
            <nav class="site-nav" aria-label="Main navigation">
                @foreach($appearanceSettings['navigation_links'] as $key)
                    @if(isset(\App\Services\Appearance\HomePageEditorService::MENU[$key]))
                        @php($item = \App\Services\Appearance\HomePageEditorService::MENU[$key])
                        <a @class(['is-active' => request()->routeIs($item['route'])]) href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                    @endif
                @endforeach
            </nav>
            <div class="site-header__actions">
                @auth
                    @if(auth()->user()->isSystemAdministrator())
                        <a class="site-header__action" href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
                    @else
                        <a class="site-header__action" href="{{ route('account.dashboard') }}">My Account</a>
                    @endif
                @else
                    <a class="site-header__action" href="{{ route('login') }}">Sign in</a>
                    <a class="site-header__register" href="{{ route('register') }}">Create account</a>
                @endauth
            </div>
            <details class="site-mobile">
                <summary aria-label="Open navigation menu">Menu</summary>
                <nav aria-label="Mobile navigation">
                    @foreach($appearanceSettings['navigation_links'] as $key)
                        @if(isset(\App\Services\Appearance\HomePageEditorService::MENU[$key]))
                            @php($item = \App\Services\Appearance\HomePageEditorService::MENU[$key])
                            <a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                        @endif
                    @endforeach
                    @auth<a href="{{ route('account.dashboard') }}">My account</a>
                        @if(auth()->user()->isSystemAdministrator())<a href="{{ route('admin.dashboard') }}">Administration</a>@endif
                    @else<a href="{{ route('login') }}">Sign in</a>
                        <a href="{{ route('register') }}">Create account</a>@endauth
                </nav>
            </details>
        </div>
    </header>
    {{ $slot }}
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div><a class="site-logo" href="{{ route('home') }}">{{ $appearanceSettings['brand_name'] }}</a>
                <p>{{ $appearanceSettings['footer_text'] }}</p></div>
            <nav aria-label="Footer navigation">
                <a href="{{ route('about') }}">About</a>
                <a href="{{ route('faq') }}">Help &amp; FAQ</a>
                <a href="{{ route('contact') }}">Contact</a>
                <a href="{{ route('legal.index') }}">Legal policies</a>
            </nav>
            <small>© {{ date('Y') }} Barama Real Estate</small>
        </div>
    </footer>
</body>
</html>
