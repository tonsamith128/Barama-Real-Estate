<x-layouts.frontend :title="$project->name . ' — Barama Real Estate'">
    <main class="marketplace" id="main-content">
        <nav aria-label="Breadcrumb" class="marketplace__breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('projects.index') }}">Projects</a>
        </nav>
        <article class="property-detail">
            <p class="marketplace__eyebrow">{{ ucfirst(str_replace('_', ' ', $project->project_type)) }}</p>
            <h1>{{ $project->name }}</h1>
            <p>{{ $project->company->name }}</p>
            @if ($project->completion_status)
                <p>Status: {{ ucfirst(str_replace('_', ' ', $project->completion_status)) }}</p>
            @endif
            @if ($project->description)
                <div class="property-detail__description">{{ $project->description }}</div>
            @endif
        </article>
    </main>
</x-layouts.frontend>
