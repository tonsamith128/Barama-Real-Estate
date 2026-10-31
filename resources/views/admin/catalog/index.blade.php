<x-layouts.admin title="Catalog Moderation — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Administration / Marketplace</p>
            <h1>Catalog Moderation</h1>
            <p>Review projects and property listings, onboard verified companies and preserve review history.</p></div>
        <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="admin-card">
        <h2>Create company and assign verified owner</h2>
        <form class="admin-form" method="post" action="{{ route('admin.catalog.companies.create') }}">@csrf
            <label>Company name <input name="name" required maxlength="190" value="{{ old('name') }}"></label>
            <label>Company slug <input name="slug" required maxlength="190" pattern="[A-Za-z0-9_-]+" value="{{ old('slug') }}"></label>
            <label>Verified owner's registered email <input name="owner_email" type="email" required value="{{ old('owner_email') }}"></label>
            <button type="submit">Create and approve company</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Pending development projects</h2>
        <div class="catalog-review-list">
            @forelse($pendingProjects as $project)
                <article class="catalog-review-card">
                    <div><strong>{{ $project->name }}</strong>
                        <p>{{ $project->company->name }} · {{ ucfirst(str_replace('_',' ',$project->project_type)) }}</p>
                        <p>{{ $project->description }}</p></div>
                    <div class="catalog-review-actions">
                        <form method="post" action="{{ route('admin.catalog.projects.decision', $project) }}">@csrf
                            <input type="hidden" name="decision" value="approved">
                            <button type="submit">Approve project</button>
                        </form>
                        <form method="post" action="{{ route('admin.catalog.projects.decision', $project) }}">@csrf
                            <input type="hidden" name="decision" value="rejected">
                            <label>Rejection reason <textarea name="reason" minlength="10" maxlength="2000" rows="2" required placeholder="Explain the changes needed"></textarea></label>
                            <button type="submit">Reject project</button>
                        </form>
                    </div>
                </article>
            @empty<p>No pending projects.</p>@endforelse
        </div>
        {{ $pendingProjects->links() }}
    </section>

    <section class="admin-card">
        <h2>Pending listing reviews</h2>
        <div class="catalog-review-list">
            @forelse($pending as $offer)
                <article class="catalog-review-card">
                    <div><strong>{{ $offer->property->title }}</strong>
                        <p>{{ $offer->property->company->name }} · {{ $offer->currency }} {{ number_format((float)$offer->price,2) }}</p></div>
                    <div class="catalog-review-actions">
                        <form method="post" action="{{ route('admin.catalog.approve', $offer) }}">@csrf
                            <button type="submit">Approve and publish</button>
                        </form>
                        <form method="post" action="{{ route('admin.catalog.reject', $offer) }}">@csrf
                            <label>Rejection reason <textarea name="reason" minlength="10" maxlength="2000" rows="2" required placeholder="Describe what must be revised"></textarea></label>
                            <button type="submit">Reject listing</button>
                        </form>
                    </div>
                </article>
            @empty<p>No pending submissions.</p>@endforelse
        </div>
        {{ $pending->links() }}
    </section>

    <section class="admin-card">
        <h2>Published listings</h2>
        <p>Unpublishing removes a listing from the public catalog immediately and returns it to private draft.</p>
        <div class="catalog-review-list">
            @forelse($publishedListings as $offer)
                <article class="catalog-review-card">
                    <div><strong>{{ $offer->property->title }}</strong><p>{{ $offer->property->company->name }}</p>
                        <p>{{ $offer->currency }} {{ number_format((float)$offer->price,2) }}</p></div>
                    <form class="catalog-review-actions" method="post" action="{{ route('admin.catalog.unpublish', $offer) }}">@csrf
                        <label>Unpublish reason <textarea name="reason" minlength="10" maxlength="2000" rows="2" required placeholder="Reason for withdrawal"></textarea></label>
                        <button type="submit">Unpublish listing</button>
                    </form>
                </article>
            @empty<p>No published listings.</p>@endforelse
        </div>
        {{ $publishedListings->links() }}
    </section>

    <section class="admin-card">
        <h2>Recent moderation history</h2>
        <div class="admin-table-wrap"><table><thead><tr><th>When</th><th>Content</th><th>Decision</th><th>Reason</th></tr></thead><tbody>
            @forelse($recentDecisions as $decision)
                <tr><td>{{ $decision->created_at }}</td>
                    <td>{{ ucfirst($decision->subject_type) }} #{{ $decision->subject_id }}</td>
                    <td>{{ ucfirst($decision->decision) }}</td>
                    <td>{{ $decision->reason ?: '—' }}</td>
                </tr>
            @empty<tr><td colspan="4">No moderation decisions yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="admin-card"><h2>Companies</h2>
        <ul class="admin-company-list">@forelse($companies as $company)
            <li><strong>{{ $company->name }}</strong> · {{ ucfirst($company->status) }}</li>
        @empty<li>No companies yet.</li>@endforelse</ul>
        {{ $companies->links() }}
    </section>
</x-layouts.admin>
