<x-layouts.admin :title="$company->name.' — Team Management'">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Developer / Company</p><h1>Team Management</h1>
            <p>{{ $company->name }} · Verified users only</p></div>
        <a href="{{ route('developer.dashboard') }}">Developer Dashboard</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="admin-card">
        <h2>Assign an existing verified account</h2>
        <p>Ask the team member to register and verify their email before adding them. This does not send invitation emails.</p>
        <form class="admin-form" method="post" action="{{ route('developer.team.assign', $company) }}">
            @csrf
            <label>Registered email <input type="email" name="email" required maxlength="190" value="{{ old('email') }}"></label>
            <label>Permissions <select name="role"><option value="member">Member (read-only)</option>
                <option value="manager">Manager (properties and inquiries)</option></select></label>
            <button type="submit">Add or update member</button>
        </form>
    </section>
    <section class="admin-card">
        <h2>Company members</h2>
        <div class="admin-table-wrap"><table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>@forelse($memberships as $membership)<tr>
                <td>{{ $membership->user?->name ?? 'Account unavailable' }}</td>
                <td>{{ $membership->user?->email ?? '—' }}</td>
                <td>{{ ucfirst($membership->role) }}</td>
                <td>{{ ucfirst($membership->status) }}</td>
                <td>
                    @if($membership->role !== 'owner' && $membership->status === 'active')
                        <form method="post" action="{{ route('developer.team.deactivate', [$company,$membership]) }}">@csrf @method('PATCH')
                            <button type="submit">Deactivate</button>
                        </form>
                    @else<span>—</span>@endif
                </td>
            </tr>@empty<tr><td colspan="5">No company memberships.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $memberships->links() }}
    </section>
</x-layouts.admin>
