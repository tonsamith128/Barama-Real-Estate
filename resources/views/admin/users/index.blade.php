<x-layouts.admin title="User Directory — Barama Real Estate">
    <header class="admin-heading"><div><p class="admin-eyebrow">Administration / Users</p><h1>User Directory</h1>
        <p>Review registered accounts without editing privileged roles or credentials.</p></div>
        <a href="{{ route('admin.dashboard') }}">Dashboard</a></header>
    <section class="admin-card">
        <form method="get" action="{{ route('admin.users.index') }}">
            <label for="users-q">Find user by name or email</label>
            <div class="actions"><input id="users-q" name="q" type="search" maxlength="120" value="{{ $term }}">
                <button type="submit">Search</button></div>
        </form>
        <div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Companies</th><th>Joined</th></tr></thead><tbody>
            @forelse($users as $member)
                <tr><td>{{ $member->name }}</td><td>{{ $member->email }}</td>
                    <td>{{ $member->isSystemAdministrator() ? 'Super administrator' : 'Customer / company member' }}</td>
                    <td>{{ $member->company_memberships_count }}</td><td>{{ $member->created_at->format('M j, Y') }}</td></tr>
            @empty<tr><td colspan="5">No matching accounts.</td></tr>@endforelse
        </tbody></table></div>{{ $users->links() }}
    </section>
</x-layouts.admin>
