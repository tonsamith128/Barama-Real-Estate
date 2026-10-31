@props(['assets', 'selectedId' => null, 'label' => 'Section image', 'inputName' => 'image_asset_id'])

<fieldset class="builder-media-picker">
    <legend>{{ $label }}</legend>
    <p class="builder-media-picker__hint">Select an image with an owned/licensed rights declaration. The original stays private until you publish a section using it.</p>
    <div class="builder-media-picker__grid" role="group" aria-label="{{ $label }}">
        <label class="builder-media-choice">
            <input type="radio" name="{{ $inputName }}" value="" @checked(!$selectedId)>
            <span class="builder-media-choice__empty" aria-hidden="true">✕</span>
            <span class="builder-media-choice__name">No image</span>
        </label>
        @foreach($assets as $asset)
            <label class="builder-media-choice">
                <input type="radio" name="{{ $inputName }}" value="{{ $asset->getKey() }}"
                    @checked((string)$selectedId === (string)$asset->getKey())>
                <img src="{{ route('admin.media.preview', $asset) }}" loading="lazy"
                    alt="{{ $asset->alt_text ?: $asset->original_name }}" width="140" height="94">
                <span class="builder-media-choice__name">{{ $asset->original_name }}</span>
            </label>
        @endforeach
    </div>
    @if($selectedId && !$assets->contains('id', $selectedId))
        <p class="builder-media-picker__warning" role="status">Previously selected asset #{{ $selectedId }} is not in the available selection. Choose another image or remove it.</p>
    @endif
    <a href="{{ route('admin.media.index') }}">Manage images and rights in Media Library</a>
</fieldset>
