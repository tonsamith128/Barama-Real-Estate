<x-layouts.admin :title="$company->name . ' — Projects'">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Developer / Projects</p>
            <h1>{{ $company->name }} — Development Projects</h1>
            <p>Create and revise private drafts; administrators must approve publication.</p></div>
        <a href="{{ route('developer.properties.index', $company) }}">Properties</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="admin-card">
        <h2>Create a project</h2>
        <form class="admin-form" method="post" action="{{ route('developer.projects.store', $company) }}">
            @csrf
            <label>Project name <input name="name" minlength="4" maxlength="190" required value="{{ old('name') }}"></label>
            <label>Project category <select name="project_type">
                @foreach(['residential'=>'Residential','commercial'=>'Commercial','mixed_use'=>'Mixed use','industrial'=>'Industrial','other'=>'Other'] as $key=>$label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select></label>
            <label>Completion status <select name="completion_status">
                <option value="">Not specified</option>
                @foreach(['planning'=>'Planning','under_construction'=>'Under construction','completed'=>'Completed'] as $key=>$label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select></label>
            <label>Description <textarea name="description" maxlength="12000" rows="4">{{ old('description') }}</textarea></label>
            <button type="submit">Create private draft</button>
        </form>
    </section>
    <section class="admin-card">
        <h2>Project portfolio</h2>
        @forelse($projects as $project)
            <article class="portfolio-record">
                <div><strong>{{ $project->name }}</strong><p>Status: {{ ucfirst($project->status) }}</p>
                    <p>Type: {{ str_replace('_',' ',ucfirst($project->project_type)) }}</p></div>
                @if(in_array($project->status, ['draft','rejected'], true))
                    <details><summary>Edit project</summary>
                        <form class="admin-form" method="post" action="{{ route('developer.projects.update', [$company, $project]) }}">
                            @csrf @method('PUT')
                            <label>Name <input name="name" required maxlength="190" value="{{ $project->name }}"></label>
                            <label>Type <select name="project_type">
                                @foreach(['residential','commercial','mixed_use','industrial','other'] as $type)
                                    <option value="{{ $type }}" @selected($project->project_type===$type)>{{ ucfirst(str_replace('_',' ',$type)) }}</option>
                                @endforeach
                            </select></label>
                            <label>Completion <select name="completion_status">
                                <option value="">Not specified</option>
                                @foreach(['planning','under_construction','completed'] as $value)
                                    <option value="{{ $value }}" @selected($project->completion_status===$value)>{{ ucfirst(str_replace('_',' ',$value)) }}</option>
                                @endforeach
                            </select></label>
                            <label>Description <textarea name="description" rows="3">{{ $project->description }}</textarea></label>
                            <button type="submit">Save changes</button>
                        </form>
                    </details>
                    @if($project->status === 'draft')
                        <form method="post" action="{{ route('developer.projects.submit', [$company,$project]) }}">@csrf
                            <button type="submit">Submit for review</button></form>
                    @else<p>Edit your rejected project to return it to draft before resubmitting.</p>@endif
                @endif
            </article>
        @empty<p>No projects yet. Create a draft to begin.</p>@endforelse
        {{ $projects->links() }}
    </section>
</x-layouts.admin>
