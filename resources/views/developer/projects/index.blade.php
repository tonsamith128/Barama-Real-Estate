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
            @include('developer.catalog.project-legacy-fields', ['project' => null])
            <button type="submit">Create private draft</button>
        </form>
    </section>
    <section class="admin-card">
        <h2>Project portfolio</h2>
        @forelse($projects as $project)
            <article class="portfolio-record">
                <div><strong>{{ $project->name }}</strong><p>Status: {{ ucfirst($project->status) }}</p>
                    <p>Type: {{ str_replace('_',' ',ucfirst($project->project_type)) }}</p></div>
                <section class="listing-gallery" aria-label="Project photos for {{ $project->name }}">
                    <p><strong>Project gallery</strong> · {{ $project->mediaAttachments->count() }} / 20 photos</p>
                    @if($project->mediaAttachments->isNotEmpty())
                        <ul class="listing-gallery__images">
                            @foreach($project->mediaAttachments as $photo)
                                <li>
                                    @if($project->status === 'draft')
                                        <img loading="lazy" src="{{ route('developer.projects.gallery.preview', [$company, $project, $photo->asset]) }}"
                                            alt="{{ $photo->asset->alt_text ?: $project->name }}">
                                        @if($photo->is_primary)<strong>Primary photo</strong>@endif
                                        <form method="post" action="{{ route('developer.projects.gallery.order', [$company, $project, $photo]) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="primary">
                                            <button type="submit">Make primary</button>
                                        </form>
                                        <form method="post" action="{{ route('developer.projects.gallery.order', [$company, $project, $photo]) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="up">
                                            <button type="submit">Move up</button>
                                        </form>
                                        <form method="post" action="{{ route('developer.projects.gallery.order', [$company, $project, $photo]) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="down">
                                            <button type="submit">Move down</button>
                                        </form>
                                        <form method="post" action="{{ route('developer.projects.gallery.destroy', [$company, $project, $photo]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit">Remove</button>
                                        </form>
                                    @elseif($project->status === 'published')
                                        <img loading="lazy" src="{{ route('media.demo.show', $photo->asset->checksum_sha256) }}"
                                            alt="{{ $photo->asset->alt_text ?: $project->name }}">
                                    @else
                                        <span>{{ $photo->asset->original_name }} (private during review)</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if($project->status === 'draft')
                        <form method="post" enctype="multipart/form-data"
                            action="{{ route('developer.projects.gallery.store', [$company, $project]) }}">
                            @csrf
                            <label>Upload photo <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
                            <label>Image description <input name="alt_text" maxlength="255"></label>
                            <button type="submit">Add private project photo</button>
                        </form>
                    @endif
                </section>
                <p><a href="{{ route('developer.projects.history', [$company, $project]) }}">Revision history</a></p>
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
                            @include('developer.catalog.project-legacy-fields', ['project' => $project])
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
