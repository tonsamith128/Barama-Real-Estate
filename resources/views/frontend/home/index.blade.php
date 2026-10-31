@php($appearanceSettings = app(\App\Services\Appearance\SiteAppearanceService::class)->published())
<x-layouts.frontend title="Barama Real Estate — Find Properties and Developments">
    <main id="main-content">
        <section class="home-hero">
            <div class="home-hero__inner">
                <p class="home-eyebrow">{{ $appearanceSettings['hero_eyebrow'] }}</p>
                <h1>{{ $appearanceSettings['hero_title'] }}</h1>
                <p>{{ $appearanceSettings['hero_description'] }}</p>
                <form method="get" action="{{ route('properties.index') }}" role="search" class="home-search">
                    <label for="home-keywords">Search properties</label>
                    <input id="home-keywords" name="q" type="search" placeholder="Search by name or reference" maxlength="120">
                    <button class="button" type="submit">Search properties</button>
                </form>
            </div>
        </section>
        <section class="home-content" aria-label="Marketplace overview">
            <div class="home-metrics">
                <div><strong>{{ number_format($propertyCount) }}</strong><span>Published listings</span></div>
                <div><strong>{{ number_format($projectCount) }}</strong><span>Developments</span></div>
                <div><strong>{{ number_format($companyCount) }}</strong><span>Approved companies</span></div>
            </div>
            <div class="home-section-heading">
                <div><p class="home-eyebrow">DISCOVER</p><h2>Recently listed properties</h2></div>
                <a href="{{ route('properties.index') }}">View all properties</a>
            </div>
            @if($listings->isNotEmpty())
                <div class="marketplace__grid">
                    @foreach($listings as $listing)
                        <x-marketplace.listing-card :listing="$listing" />
                    @endforeach
                </div>
            @else
                <div class="home-empty">
                    <h3>We're preparing the property catalog.</h3>
                    <p>There are no published listings yet. Once approved properties are added, they will appear here automatically.</p>
                    <div class="actions">
                        <a class="button button--secondary" href="{{ route('projects.index') }}">Explore projects</a>
                        <a class="button button--secondary" href="{{ route('companies.index') }}">Explore companies</a>
                        @auth
                            @if(auth()->user()->isSystemAdministrator())
                                <a class="button" href="{{ route('admin.catalog.index') }}">Manage listings</a>
                                <a class="button button--secondary" href="{{ route('admin.demo.index') }}">Demo importer</a>
                            @endif
                        @endauth
                    </div>
                </div>
            @endif
        </section>
        @foreach($appearanceSettings['home_sections'] ?? [] as $section)
            @if(!empty($section['enabled']) && in_array($section['type'] ?? '', ['text', 'feature', 'cta'], true))
                <section class="home-builder-live home-builder-live--{{ $section['type'] }}" aria-label="{{ $section['title'] }}">
                    <div class="home-builder-live__inner">
                        <h2>{{ $section['title'] }}</h2>
                        @if(!empty($section['body']))<p>{{ $section['body'] }}</p>@endif
                        @if(!empty($section['button_label']) && isset(\App\Services\Appearance\HomePageEditorService::MENU[$section['button_route'] ?? '']))
                            <a class="button" href="{{ route(\App\Services\Appearance\HomePageEditorService::MENU[$section['button_route']]['route']) }}">{{ $section['button_label'] }}</a>
                        @endif
                    </div>
                </section>
            @endif
        @endforeach
    </main>
</x-layouts.frontend>
