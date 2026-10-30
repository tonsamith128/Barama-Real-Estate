<x-layouts.frontend title="Real Estate Projects — Barama Real Estate">
    <main class="marketplace" id="main-content">
        <header class="marketplace__header">
            <div>
                <p class="marketplace__eyebrow">Barama Real Estate</p>
                <h1>Real Estate Projects</h1>
                <p>Explore published developments from approved companies.</p>
            </div>
            <a href="{{ route('home') }}">Home</a>
        </header>
        <div class="marketplace__grid">
            @forelse ($projects as $project)
                <article class="property-card">
                    <p class="property-card__eyebrow">{{ ucfirst(str_replace('_', ' ', $project->project_type)) }}</p>
                    <h2 class="property-card__title"><a href="{{ route('projects.show', $project->slug) }}">{{ $project->name }}</a></h2>
                    <p class="property-card__meta">{{ $project->company->name }}</p>
                    @if ($project->completion_status)
                        <p>{{ ucfirst(str_replace('_', ' ', $project->completion_status)) }}</p>
                    @endif
                </article>
            @empty
                <p class="notice">No public projects are available yet.</p>
            @endforelse
        </div>
        {{ $projects->links() }}
    </main>
</x-layouts.frontend>
