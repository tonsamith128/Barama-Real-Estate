<x-layouts.admin title="Company Media Reviews — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Administration / Company Media</p>
            <h1>Company Media Reviews</h1>
            <p>Maintain approved profiles without taking them offline. Corrections require explicit permissions and an independent reviewer.</p>
        </div>
        <a href="{{ route('admin.media.index') }}">Media Library</a>
    </header>

    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="admin-card">
        <h2>Grant administrative capabilities</h2>
        <p>Privileges are deny-by-default. A correction requester must have <strong>company.manage</strong>, <strong>media.manage</strong> and <strong>company.media.correct</strong>. The independent reviewer needs the first two plus <strong>company.media.review</strong>.</p>
        <form class="admin-form" method="post" action="{{ route('admin.company-media.capabilities.grant') }}">@csrf
            <label>Verified administrator email <input type="email" name="email" value="{{ old('email') }}" maxlength="190" required></label>
            <label>Capability
                <select name="capability" required>
                    @foreach($capabilityOptions as $capability)
                        <option value="{{ $capability }}" @selected(old('capability') === $capability)>{{ $capability }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit">Grant capability</button>
        </form>
        <div class="admin-table-wrap"><table>
            <thead><tr><th>Administrator</th><th>Capability</th><th>Granted</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($grants as $grant)
                    <tr>
                        <td>{{ $grant->user?->email ?? 'Account unavailable' }}</td>
                        <td>{{ $grant->capability }}</td>
                        <td>{{ $grant->created_at?->format('Y-m-d') }}</td>
                        <td><form method="post" action="{{ route('admin.company-media.capabilities.revoke', $grant) }}">
                            @csrf @method('DELETE')
                            <button type="submit">Revoke</button>
                        </form></td>
                    </tr>
                @empty
                    <tr><td colspan="4">No special capabilities have been granted.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $grants->links() }}
    </section>

    <section class="admin-card">
        <h2>Request a correction</h2>
        <p>Use an existing, rights-reviewed image from the Media Library. The current published logo or cover remains in place until a different authorized administrator approves the request.</p>
        @if(!$canRequest)<p class="admin-notice" role="status">You do not have all three required correction capabilities.</p>@endif
        <form class="admin-form" method="post" action="{{ route('admin.company-media.revisions.request') }}">@csrf
            <label>Approved company
                <select name="company_id" required>
                    <option value="">Select company</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) old('company_id') === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Rights-reviewed image
                <select name="media_asset_id" required>
                    <option value="">Select image asset</option>
                    @foreach($assets as $asset)
                        <option value="{{ $asset->id }}" @selected((string) old('media_asset_id') === (string) $asset->id)">#{{ $asset->id }} — {{ $asset->original_name }} ({{ $asset->usage_rights }})</option>
                    @endforeach
                </select>
            </label>
            <label>Placement
                <select name="collection" required>
                    <option value="logo" @selected(old('collection') === 'logo')>Company logo</option>
                    <option value="cover" @selected(old('collection') === 'cover')>Company cover</option>
                </select>
            </label>
            <label>Reason for correction
                <textarea name="reason" required minlength="10" maxlength="2000" rows="3">{{ old('reason') }}</textarea>
            </label>
            <button type="submit" @disabled(!$canRequest)>Request media review</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Revision history and pending approvals</h2>
        <div class="admin-table-wrap"><table>
            <thead><tr><th>Company</th><th>Media</th><th>Requester</th><th>Status</th><th>Reason</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($revisions as $revision)
                    <tr>
                        <td>{{ $revision->company?->name ?? 'Company unavailable' }}</td>
                        <td>{{ ucfirst($revision->collection) }} · asset #{{ $revision->media_asset_id }}</td>
                        <td>{{ $revision->requester?->email ?? 'Account unavailable' }}</td>
                        <td>{{ ucfirst($revision->status) }}</td>
                        <td>{{ $revision->reason }}</td>
                        <td>
                            @if($revision->status === 'pending' && $canReview && $revision->requested_by !== auth()->id())
                                <form method="post" action="{{ route('admin.company-media.revisions.decide', $revision) }}">@csrf
                                    <input type="hidden" name="decision" value="approved">
                                    <button type="submit">Approve</button>
                                </form>
                                <form method="post" action="{{ route('admin.company-media.revisions.decide', $revision) }}">@csrf
                                    <input type="hidden" name="decision" value="rejected">
                                    <label>Rejection reason <textarea name="reason" minlength="10" maxlength="2000" required></textarea></label>
                                    <button type="submit">Reject</button>
                                </form>
                            @elseif($revision->status === 'pending')
                                <span>Independent reviewer required</span>
                            @else
                                <span>Reviewed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No company media revisions have been requested.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $revisions->links() }}
    </section>
</x-layouts.admin>
