<x-layouts.admin title="Appearance Manager — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Administration / Appearance</p>
        <h1>Website Appearance</h1>
        <p>Edit a private draft, review changes and publish when ready. Changes are not live until you publish.</p></div>
        <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="admin-card">
        <h2>Homepage Layout &amp; Navigation</h2>
        <p>Manage custom content sections and configure the order of the desktop and mobile navigation links.</p>
        <p><a href="{{ route('admin.homepage.index') }}">Open Homepage Builder</a></p>
    </section>
    <div class="appearance-grid">
        <section class="admin-card">
            <h2>Brand, home and search identity</h2>
            <form class="admin-form" method="post" action="{{ route('admin.appearance.save') }}">
                @csrf
                <label>Full site name <input name="brand_name" required maxlength="190" value="{{ old('brand_name',$draft['brand_name']) }}"></label>
                <label>Header brand <input name="brand_short" required maxlength="60" value="{{ old('brand_short',$draft['brand_short']) }}"></label>
                <label>Header subtitle <input name="brand_subtitle" required maxlength="60" value="{{ old('brand_subtitle',$draft['brand_subtitle']) }}"></label>
                <label>Theme accent color <input type="color" name="primary_color" required value="{{ old('primary_color',$draft['primary_color']) }}"></label>
                <label>Homepage eyebrow <input name="hero_eyebrow" maxlength="120" required value="{{ old('hero_eyebrow',$draft['hero_eyebrow']) }}"></label>
                <label>Homepage headline <input name="hero_title" maxlength="190" required value="{{ old('hero_title',$draft['hero_title']) }}"></label>
                <label>Homepage description <textarea name="hero_description" maxlength="500" required rows="3">{{ old('hero_description',$draft['hero_description']) }}</textarea></label>
                <label>Footer description <textarea name="footer_text" maxlength="300" required rows="3">{{ old('footer_text',$draft['footer_text']) }}</textarea></label>
                <label>Search engine description <textarea name="meta_description" maxlength="300" required rows="3">{{ old('meta_description',$draft['meta_description']) }}</textarea></label>
                <button type="submit">Save appearance draft</button>
            </form>
        </section>
        <div>
            <section class="admin-card">
                <h2>Current published website</h2>
                <div class="appearance-preview" style="--appearance-accent: {{ $live['primary_color'] }}">
                    <strong class="appearance-preview__brand">{{ $live['brand_short'] }}</strong>
                    <small>{{ $live['brand_subtitle'] }}</small>
                    <p class="appearance-preview__eyebrow">{{ $live['hero_eyebrow'] }}</p>
                    <h3>{{ $live['hero_title'] }}</h3>
                    <p>{{ $live['hero_description'] }}</p>
                    <span class="appearance-preview__button">Search properties</span>
                </div>
                <p>Live theme color: <code>{{ $live['primary_color'] }}</code></p>
                <p><a href="{{ route('home') }}" target="_blank" rel="noopener">View public homepage</a></p>
            </section>
            <section class="admin-card">
                <h2>Publish your draft</h2>
                <p>Saving a draft does not change public pages. After saving, publish to activate your brand, hero, footer and primary color.</p>
                <form method="post" action="{{ route('admin.appearance.publish') }}">@csrf
                    <button type="submit">Publish appearance</button>
                </form>
                <form method="post" action="{{ route('admin.appearance.reset') }}">@csrf @method('POST')
                    <button type="submit">Discard draft changes</button>
                </form>
            </section>
            <section class="admin-card">
                <h2>Not included in this module</h2>
                <p>This is a safe, publishable appearance settings manager. Full page-block editing, visual drag-and-drop layout, media placement, custom menu builder and per-page themes still require migration.</p>
            </section>
        </div>
    </div>
</x-layouts.admin>
