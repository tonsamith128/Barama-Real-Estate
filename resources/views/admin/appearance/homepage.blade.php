<x-layouts.admin title="Homepage Builder — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Administration / Appearance / Homepage</p>
            <h1>Homepage &amp; Navigation Builder</h1>
            <p>Create content sections, change their order and configure the main menu. Edits remain private until you publish.</p>
        </div>
        <a href="{{ route('admin.appearance.index') }}">Brand settings</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <div class="home-builder-grid">
        <div>
            <section class="admin-card builder-media-intro">
                <div class="home-builder-heading"><div><p class="admin-eyebrow">VISUAL CONTENT</p><h2>Homepage image library</h2>
                    <p>Use owned or licensed assets without copying files. The public homepage never exposes drafts.</p></div>
                    <a href="{{ route('admin.media.index') }}">Upload / manage images</a>
                </div>
                @unless($mediaReady)
                    <p role="alert">Media Library metadata migrations must be applied before selecting homepage images.</p>
                @endunless
                <form method="get" action="{{ route('admin.homepage.index') }}" class="builder-media-search">
                    <label>Find approved images <input name="media_q" type="search" maxlength="80" value="{{ $mediaTerm }}" placeholder="Search by filename"></label>
                    <button type="submit">Search images</button>
                    @if($mediaTerm !== '')<a href="{{ route('admin.homepage.index') }}">Clear</a>@endif
                </form>
                <p class="builder-media-intro__hint">{{ $mediaAssets->count() }} selectable images shown. Rights status must be owned or licensed, with a named rights holder.</p>
            </section>
            <section class="admin-card">
                <div class="home-builder-heading">
                    <div><h2>Homepage sections</h2><p>{{ count($draft['home_sections']) }} of 12 sections. Sections are displayed below the property catalog.</p></div>
                    <span class="home-builder-draft-label">Unpublished draft</span>
                </div>
                <div class="home-builder-sections">
                    @forelse($draft['home_sections'] as $index => $section)
                        <article class="home-builder-section" aria-label="Section {{ $index + 1 }}">
                            <div class="home-builder-section__heading">
                                <div><small>Section {{ $index + 1 }} · {{ $types[$section['type']] ?? 'Content' }}</small>
                                    <h3>{{ $section['title'] }}</h3>
                                    <span>{{ $section['enabled'] ? 'Visible when published' : 'Hidden' }}</span></div>
                                <div class="home-builder-actions">
                                    @if($index > 0)
                                        <form method="post" action="{{ route('admin.homepage.sections.move', $section['id']) }}">
                                            @csrf<input type="hidden" name="direction" value="up">
                                            <button type="submit" title="Move section up">↑</button>
                                        </form>
                                    @endif
                                    @if($index < count($draft['home_sections']) - 1)
                                        <form method="post" action="{{ route('admin.homepage.sections.move', $section['id']) }}">
                                            @csrf<input type="hidden" name="direction" value="down">
                                            <button type="submit" title="Move section down">↓</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('admin.homepage.sections.remove', $section['id']) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Remove section from draft">Remove</button>
                                    </form>
                                </div>
                            </div>
                            @if($section['body'])<p>{{ $section['body'] }}</p>@endif
                            <details>
                                <summary>Edit section</summary>
                                <form class="admin-form" method="post" action="{{ route('admin.homepage.sections.update', $section['id']) }}">
                                    @csrf @method('PATCH')
                                    <label>Section type
                                        <select name="type" required>
                                            @foreach($types as $key => $label)
                                                <option value="{{ $key }}" @selected($section['type'] === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Heading <input name="title" required minlength="3" maxlength="140" value="{{ $section['title'] }}"></label>
                                    <label>Content <textarea name="body" maxlength="1200" rows="3">{{ $section['body'] }}</textarea></label>
                                    <label>Button label <input name="button_label" maxlength="60" value="{{ $section['button_label'] }}"></label>
                                    <label>Button destination
                                        <select name="button_route" required>
                                            @foreach($menu as $key => $item)
                                                <option value="{{ $key }}" @selected($section['button_route'] === $key)>{{ $item['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Image placement
                                        <select name="image_position">
                                            <option value="right" @selected(($section['image_position'] ?? 'right') === 'right')>Right of content</option>
                                            <option value="left" @selected(($section['image_position'] ?? 'right') === 'left')>Left of content</option>
                                        </select>
                                    </label>
                                    <x-appearance.media-picker :assets="$mediaAssets" :selected-id="$section['image_asset_id'] ?? null" label="Choose image for this section" />
                                    <label class="home-builder-check"><input type="checkbox" name="enabled" value="1" @checked($section['enabled'])> Show section on homepage</label>
                                    <button type="submit">Save section</button>
                                </form>
                            </details>
                        </article>
                    @empty
                        <p>No custom homepage sections yet. The property catalog remains visible by default.</p>
                    @endforelse
                </div>
            </section>
            <section class="admin-card">
                <h2>Add homepage section</h2>
                <form class="admin-form" method="post" action="{{ route('admin.homepage.sections.store') }}">
                    @csrf
                    <label>Section type
                        <select name="type" required>
                            @foreach($types as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label>Heading <input name="title" required minlength="3" maxlength="140" value="{{ old('title') }}" placeholder="Why choose Barama?"></label>
                    <label>Content <textarea name="body" maxlength="1200" rows="4" placeholder="Introduce this feature or section...">{{ old('body') }}</textarea></label>
                    <label>Button label <input name="button_label" maxlength="60" value="{{ old('button_label') }}" placeholder="Explore properties"></label>
                    <label>Button destination
                        <select name="button_route" required>
                            @foreach($menu as $key=>$item)<option value="{{ $key }}">{{ $item['label'] }}</option>@endforeach
                        </select>
                    </label>
                    <label>Image placement <select name="image_position">
                        <option value="right">Right of content</option><option value="left">Left of content</option>
                    </select></label>
                    <x-appearance.media-picker :assets="$mediaAssets" label="Choose image for new section" />
                    <label class="home-builder-check"><input type="checkbox" name="enabled" value="1" checked> Show section after publishing</label>
                    <button type="submit" @disabled(count($draft['home_sections']) >= 12)>Add to draft</button>
                </form>
            </section>
        </div>
        <div>
            <section class="admin-card">
                <p class="admin-eyebrow">FEATURED VISUAL</p>
                <h2>Homepage hero image</h2>
                <p>Select a Media Library image to appear beside the main homepage headline. The published image remains unchanged until you click Publish.</p>
                <form method="post" action="{{ route('admin.homepage.hero-image.update') }}" class="admin-form">
                    @csrf
                    <x-appearance.media-picker :assets="$mediaAssets" :selected-id="$draft['hero_image_asset_id'] ?? null" label="Choose hero image" />
                    <button type="submit">Save hero image to draft</button>
                </form>
            </section>
            <section class="admin-card">
                <h2>Main navigation</h2>
                <p>Use safe built-in destinations. The same order applies to the desktop and mobile menu.</p>
                <div class="home-builder-navigation">
                    @foreach($draft['navigation_links'] as $position => $key)
                        @if(isset($menu[$key]))
                            <div class="home-builder-navigation__item">
                                <strong>{{ $menu[$key]['label'] }}</strong>
                                <div class="home-builder-actions">
                                    @if($position > 0)
                                        <form method="post" action="{{ route('admin.homepage.links.move', $key) }}">
                                            @csrf<input type="hidden" name="direction" value="up">
                                            <button type="submit" title="Move link up">↑</button>
                                        </form>
                                    @endif
                                    @if($position < count($draft['navigation_links']) - 1)
                                        <form method="post" action="{{ route('admin.homepage.links.move', $key) }}">
                                            @csrf<input type="hidden" name="direction" value="down">
                                            <button type="submit" title="Move link down">↓</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('admin.homepage.links.remove', $key) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit">Remove</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <form method="post" action="{{ route('admin.homepage.links.store') }}" class="admin-form">
                    @csrf
                    <label>Add navigation link
                        <select name="link" required>
                            @foreach($menu as $key=>$item)
                                @unless(in_array($key,$draft['navigation_links'],true))
                                    <option value="{{ $key }}">{{ $item['label'] }}</option>
                                @endunless
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" @disabled(count($draft['navigation_links']) >= count($menu))>Add navigation link</button>
                </form>
            </section>
            <section class="admin-card">
                <h2>Publish when ready</h2>
                <p>All section, navigation and brand changes are saved together in the unpublished appearance draft. Publishing updates the public site in one action.</p>
                <form method="post" action="{{ route('admin.appearance.publish') }}">
                    @csrf<button type="submit">Publish website appearance</button>
                </form>
                <p><a href="{{ route('home') }}" target="_blank" rel="noopener">Open live website</a></p>
                <p><a href="{{ route('admin.appearance.index') }}">Edit brand settings</a></p>
            </section>
        </div>
    </div>
</x-layouts.admin>
