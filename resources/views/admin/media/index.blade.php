<x-layouts.admin title="Media Library — Barama Real Estate">
    <header class="admin-heading"><div><p class="admin-eyebrow">Administration / Media</p>
        <h1>Media Library</h1>
        <p>Search private image inventory and manage descriptive and usage-rights metadata.</p></div>
        <a href="{{ route('admin.dashboard') }}">Dashboard</a></header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @unless($metadataReady)
        <section class="admin-card" role="alert"><h2>Database update required</h2>
            <p>After a verified database backup, run <code>php artisan migrate --force</code> before editing image metadata.</p></section>
    @endunless
    <section class="admin-card">
        <div class="media-library-upload-head">
            <div><h2>Upload image to the Media Library</h2>
                <p>Accepted: JPEG, PNG, WebP · up to 12 MB · 24 megapixels. Original is stored privately. Select rights before adding it to the homepage.</p></div>
            <a href="{{ route('admin.homepage.index') }}">Open Homepage Builder</a>
        </div>
        <form method="post" action="{{ route('admin.media.upload') }}" enctype="multipart/form-data" class="admin-form">
            @csrf
            <label>Image file <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
            <label>Image description <input name="alt_text" maxlength="255" placeholder="Describe what is visible"></label>
            <button type="submit">Upload to Media Library</button>
        </form>
    </section>
    <section class="admin-card">
        <form class="media-search-form" method="get" action="{{ route('admin.media.index') }}">
            <label>Search images <input name="q" type="search" maxlength="120" value="{{ $term }}" placeholder="Filename or alt text"></label>
            <label>Usage rights <select name="rights" @disabled(!$metadataReady)>
                <option value="">All statuses</option>
                @foreach($rightsOptions as $key=>$value)<option value="{{ $key }}" @selected($rightsStatus===$key)>{{ $value }}</option>@endforeach
            </select></label>
            <button type="submit">Filter images</button>
            <a href="{{ route('admin.media.index') }}">Clear filters</a>
        </form>
        <p>{{ $assets->total() }} matching images. Original files and attachments remain unchanged.</p>
    </section>
    <div class="media-manager-grid">
        @forelse($assets as $asset)
            <article class="media-manager-card">
                <img loading="lazy" src="{{ route('admin.media.preview', $asset) }}" alt="{{ $asset->alt_text ?: $asset->original_name }}" width="360" height="220">
                <div class="media-manager-card__body">
                    <h2>{{ $asset->original_name }}</h2>
                    <p>{{ $asset->mime_type }} · {{ number_format($asset->size_bytes / 1048576,2) }} MB · {{ $asset->attachments_count }} references</p>
                    <p>{{ $asset->width_px ?? '—' }} × {{ $asset->height_px ?? '—' }}</p>
                    @if($metadataReady)
                        <p>Rights: {{ $rightsOptions[$asset->usage_rights] ?? 'Not reviewed' }}</p>
                        <details><summary>Edit metadata</summary>
                            <form class="admin-form" method="post" action="{{ route('admin.media.metadata.update',$asset) }}">
                                @csrf @method('PATCH')
                                <label>Alt text <textarea name="alt_text" maxlength="255" rows="2">{{ $asset->alt_text }}</textarea></label>
                                <label>Usage rights <select name="usage_rights" required>
                                    @foreach($rightsOptions as $key=>$value)<option value="{{ $key }}" @selected($asset->usage_rights===$key)>{{ $value }}</option>@endforeach
                                </select></label>
                                <label>Rights holder <input name="rights_holder" maxlength="190" value="{{ $asset->rights_holder }}"></label>
                                <label>License/evidence reference <input name="rights_reference" maxlength="500" value="{{ $asset->rights_reference }}"></label>
                                <button type="submit">Save metadata</button>
                            </form>
                        </details>
                    @endif
                </div>
            </article>
        @empty
            <section class="admin-card"><h2>No images found</h2><p>Adjust the filters, or upload images from an approved company's property draft.</p></section>
        @endforelse
    </div>
    {{ $assets->links() }}
    <section class="admin-card">
        <h2>Rights review is not license verification</h2>
        <p>These fields record declarations; they do not verify copyright ownership or change public visibility. Listings still control image publication.</p>
    </section>
</x-layouts.admin>
