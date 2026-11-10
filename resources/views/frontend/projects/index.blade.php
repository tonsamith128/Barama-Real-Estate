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
        <form class="marketplace-filters" method="get" action="{{ route('projects.index') }}" role="search">
            <label class="field" for="project-search">
                <span>Keywords</span>
                <input class="field__control" id="project-search" name="q" type="search"
                    maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Project or company">
            </label>
            <label class="field" for="project-type">
                <span>Project type</span>
                <select class="field__control" id="project-type" name="type">
                    <option value="">All types</option>
                    @foreach ($projectTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field" for="project-stage">
                <span>Construction stage</span>
                <select class="field__control" id="project-stage" name="stage">
                    <option value="">All stages</option>
                    @foreach ($completionStages as $stage)
                        <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ ucfirst(str_replace('_', ' ', $stage)) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field" for="project-sort">
                <span>Sort by</span>
                <select class="field__control" id="project-sort" name="sort">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Project name</option>
                </select>
            </label>
            <button class="button" type="submit">Find developments</button>
            <a class="button button--secondary" href="{{ route('projects.index') }}">Clear filters</a>
        </form>

        @if ($errors->any())
            <div class="notice notice--error" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <p class="marketplace__count"><strong>{{ number_format($projects->total()) }}</strong> matching developments</p>
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
