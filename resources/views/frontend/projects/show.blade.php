<x-layouts.frontend :title="$project->name . ' — Barama Real Estate'">
    <main class="marketplace" id="main-content">
        <nav aria-label="Breadcrumb" class="marketplace__breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('projects.index') }}">Projects</a>
        </nav>
        <article class="property-detail">
            @php($cover = $project->mediaAttachments->first()?->asset)
            @if($cover)
                <img class="property-detail__image" src="{{ route('media.demo.show', $cover->checksum_sha256) }}"
                    alt="{{ $cover->alt_text ?: $project->name }}" loading="lazy">
            @endif
            @if($project->mediaAttachments->count() > 1)
                <div class="property-photo-gallery" aria-label="Project photo gallery">
                    @foreach($project->mediaAttachments->skip(1) as $attachment)
                        <img src="{{ route('media.demo.show', $attachment->asset->checksum_sha256) }}"
                            alt="{{ $attachment->asset->alt_text ?: $project->name }}" loading="lazy">
                    @endforeach
                </div>
            @endif
            <p class="marketplace__eyebrow">{{ ucfirst(str_replace('_', ' ', $project->project_type)) }}</p>
            <h1>{{ $project->name }}</h1>
            <p>{{ $project->company->name }}</p>
            @if ($project->completion_status)
                <p>Status: {{ ucfirst(str_replace('_', ' ', $project->completion_status)) }}</p>
            @endif
            @if($project->amenities->isNotEmpty())
                <h2>Project amenities</h2>
                <p>{{ $project->amenities->pluck('name')->join(', ') }}</p>
            @endif
            @if($project->milestones->isNotEmpty())
                <h2>Development milestones</h2>
                <ul>
                    @foreach($project->milestones as $milestone)
                        <li>
                            <strong>{{ $milestone->title }}</strong>
                            · {{ ucfirst(str_replace('_', ' ', $milestone->status)) }}
                            @if($milestone->target_date) · {{ $milestone->target_date->format('Y-m-d') }} @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($project->description)
                <div class="property-detail__description">{{ $project->description }}</div>
            @endif
        </article>
    </main>
</x-layouts.frontend>
