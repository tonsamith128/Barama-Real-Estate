<x-layouts.admin title="Developer Workspace — Barama Real Estate">
    <header class="workspace-dashboard-heading">
        <div>
            <p class="admin-eyebrow">COMPANY MANAGEMENT / OVERVIEW</p>
            <h1>Developer Workspace</h1>
            <p>Manage your companies, listing submissions, inquiries and customer viewings.</p>
        </div>
        <a class="workspace-dashboard-heading__cta" href="{{ route('home') }}">View website</a>
    </header>

    <section class="workspace-dashboard-hero workspace-developer-hero">
        <div>
            <span class="workspace-dashboard-hero__eyebrow">BARAMA REAL ESTATE · DEVELOPER</span>
            <h2>Your portfolio at a glance.</h2>
            <p>Follow your properties from private draft to publication, monitor leads, and coordinate viewing requests from one secure workspace.</p>
            <div class="workspace-dashboard-hero__actions">
                <a href="#company-directory">Manage your companies</a>
                <a href="{{ route('developer.inquiries.index') }}">Company inquiries</a>
                <a href="{{ route('developer.viewings.index') }}">Viewing requests</a>
            </div>
        </div>
        <span aria-hidden="true" class="workspace-dashboard-hero__art">⌂</span>
    </section>

    <section aria-label="Your company portfolio statistics" class="workspace-dashboard-stats dashboard-stat-grid">
        @foreach($metrics as $label => $value)
            <article class="dashboard-stat">
                <span>{{ $label }}</span><strong>{{ number_format($value) }}</strong>
                <small>Within active memberships</small>
            </article>
        @endforeach
    </section>

    <div class="workspace-developer-queue" aria-label="Your customer operations">
        <a href="{{ route('developer.inquiries.index') }}" class="workspace-queue-tile">
            <span>New company inquiries</span><strong>{{ number_format($newInquiries) }}</strong>
            <small>Open customer inquiry inbox</small>
        </a>
        <a href="{{ route('developer.viewings.index') }}" class="workspace-queue-tile">
            <span>Pending viewing requests</span><strong>{{ number_format($pendingViewings) }}</strong>
            <small>Review customer appointments</small>
        </a>
    </div>

    <section class="admin-card" aria-labelledby="new-company-heading">
        <h2 id="new-company-heading">Register a company</h2>
        <p>Create a private draft first. Your company will not appear publicly until an administrator approves it.</p>
        @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
        @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form class="admin-form" method="post" action="{{ route('developer.companies.apply') }}">@csrf
            <label>Company name <input name="name" value="{{ old('name') }}" required maxlength="190" minlength="3"></label>
            <label>Company type
                <select name="company_type" required>
                    @foreach(\App\Services\Developer\CompanyApplicationService::COMPANY_TYPES as $type)
                        <option value="{{ $type }}" @selected(old('company_type', 'developer') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Company description
                <textarea name="description" maxlength="12000" rows="3">{{ old('description') }}</textarea>
            </label>
            <button type="submit">Create private draft</button>
        </form>
    </section>

    <section class="dashboard-section workspace-company-directory" id="company-directory" aria-labelledby="company-directory-heading">
        <div class="workspace-dashboard-section-heading">
            <div><p class="admin-eyebrow">YOUR PORTFOLIO</p><h2 id="company-directory-heading">Company directory</h2></div>
            <span>{{ number_format($companies->total()) }} active {{ $companies->total() === 1 ? 'membership' : 'memberships' }}</span>
        </div>
        <div class="workspace-company-grid">
            @forelse($companies as $company)
                @php($role = $roles->get($company->getKey(), 'member'))
                @php($canManage = $company->status === 'approved' && in_array($role, ['owner', 'manager'], true))
                <article class="workspace-company-card">
                    <div class="workspace-company-card__heading">
                        <span class="workspace-company-card__mark" aria-hidden="true">{{ strtoupper(mb_substr($company->name, 0, 1)) }}</span>
                        <div>
                            <h3>{{ $company->name }}</h3>
                            <small>{{ ucfirst($role) }} membership</small>
                        </div>
                        <span class="workspace-status-pill">{{ ucfirst($company->status) }}</span>
                    </div>
                    <div class="workspace-company-card__stats">
                        <div><strong>{{ number_format($company->properties_count) }}</strong><span>Properties</span></div>
                        <div><strong>{{ number_format($company->projects_count) }}</strong><span>Projects</span></div>
                        <div><strong>{{ number_format($company->active_members_count) }}</strong><span>Team members</span></div>
                    </div>
                    @if($role === 'owner' && $company->status === 'rejected' && $rejectionReasons->get($company->getKey()))
                        <p class="workspace-company-card__note" role="status">Changes requested: {{ $rejectionReasons->get($company->getKey()) }}</p>
                    @endif
                    @if($role === 'owner' && in_array($company->status, ['draft', 'rejected'], true))
                        <div class="workspace-company-card__actions">
                            <details>
                                <summary>Edit company application</summary>
                                <form class="admin-form" method="post" action="{{ route('developer.companies.application.update', $company) }}">
                                    @csrf @method('PATCH')
                                    <label>Company name <input name="name" value="{{ $company->name }}" minlength="3" maxlength="190" required></label>
                                    <label>Company type
                                        <select name="company_type" required>
                                            @foreach(\App\Services\Developer\CompanyApplicationService::COMPANY_TYPES as $type)
                                                <option value="{{ $type }}" @selected($company->company_type === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Description <textarea name="description" maxlength="12000" rows="3">{{ $company->description }}</textarea></label>
                                    <button type="submit">Save changes</button>
                                </form>
                            </details>
                            @if($company->status === 'draft')
                                <form method="post" action="{{ route('developer.companies.application.submit', $company) }}">
                                    @csrf
                                    <button type="submit">Submit for review</button>
                                </form>
                            @endif
                        </div>
                    @elseif($role === 'owner' && $company->status === 'pending')
                        <p class="workspace-company-card__note">Your application is under administrator review.</p>
                    @endif
                    @if($canManage)
                        <div class="workspace-company-card__actions">
                            <a href="{{ route('developer.properties.index', $company) }}">Manage properties</a>
                            <a href="{{ route('developer.projects.index', $company) }}">Manage projects</a>
                            @if($role === 'owner')
                                <a href="{{ route('developer.team.index', $company) }}">Manage team</a>
                            @endif
                        </div>
                    @else
                        <p class="workspace-company-card__note">
                            {{ $company->status !== 'approved'
                                ? 'Company approval is required before editing catalog content.'
                                : 'This membership has read-only access. Contact the company owner to request management permissions.' }}
                        </p>
                    @endif
                </article>
            @empty
                <div class="workspace-company-empty">
                    <h3>No company memberships yet</h3>
                    <p>You can register a company draft above, or an administrator can assign you to an approved company.</p>
                    <a href="{{ route('account.dashboard') }}">Return to your account</a>
                </div>
            @endforelse
        </div>
        {{ $companies->links() }}
    </section>

    <section class="dashboard-section workspace-company-leads" aria-labelledby="latest-leads-heading">
        <div class="workspace-dashboard-section-heading">
            <div><p class="admin-eyebrow">CUSTOMER RELATIONSHIPS</p><h2 id="latest-leads-heading">Recent company inquiries</h2></div>
            <a href="{{ route('developer.inquiries.index') }}">View all inquiries</a>
        </div>
        @forelse($recentInquiries as $inquiry)
            <div class="workspace-compact-record">
                <div>
                    <strong>{{ $inquiry->listing?->property?->title ?? 'Property unavailable' }}</strong>
                    <small>{{ $inquiry->listing?->property?->company?->name ?? 'Company unavailable' }} · {{ $inquiry->created_at->diffForHumans() }}</small>
                </div>
                <span class="workspace-status-pill">{{ ucfirst($inquiry->status) }}</span>
            </div>
        @empty
            <p class="dashboard-note">No company inquiries are available to your management role yet.</p>
        @endforelse
    </section>
</x-layouts.admin>
