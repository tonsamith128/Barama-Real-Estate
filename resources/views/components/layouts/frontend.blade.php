@props(['title' => 'Barama Real Estate'])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find properties for sale and rent, explore real estate developments and connect with companies on Barama Real Estate.">
    <title>{{ $title }}</title>
    @vite(['resources/css/frontend/app.css', 'resources/js/frontend/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="site-logo" href="{{ route('home') }}"><span class="site-logo__mark">B</span><span>Barama <small>Real Estate</small></span></a>
            <nav class="site-nav" aria-label="Main navigation">
                <a @class(['is-active' => request()->routeIs('properties.*')]) href="{{ route('properties.index') }}">Properties</a>
                <a @class(['is-active' => request()->routeIs('projects.*')]) href="{{ route('projects.index') }}">Projects</a>
                <a @class(['is-active' => request()->routeIs('companies.*')]) href="{{ route('companies.index') }}">Companies</a>
                <a @class(['is-active' => request()->routeIs('about')]) href="{{ route('about') }}">About</a>
                <a @class(['is-active' => request()->routeIs('contact')]) href="{{ route('contact') }}">Contact</a>
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
                    <a href="{{ route('properties.index') }}">Properties</a>
                    <a href="{{ route('projects.index') }}">Projects</a>
                    <a href="{{ route('companies.index') }}">Companies</a>
                    <a href="{{ route('about') }}">About</a>
                    <a href="{{ route('contact') }}">Contact</a>
                    <a href="{{ route('faq') }}">Help</a>
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
            <div><a class="site-logo" href="{{ route('home') }}">Barama Real Estate</a>
                <p>Discover properties, developments and trusted connections.</p></div>
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
