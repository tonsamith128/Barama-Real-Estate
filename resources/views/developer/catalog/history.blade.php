<x-layouts.admin :title="$subjectType . ' Revision History — Barama Real Estate'">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Developer / {{ $subjectType }} / Revision History</p>
            <h1>{{ $subjectName }}</h1>
            <p>Read-only record of edits, submissions, moderator decisions and gallery changes for {{ $company->name }}.</p>
        </div>
        <a href="{{ $backUrl }}">Back to {{ strtolower($subjectType) }} manager</a>
    </header>
    <section class="admin-card">
        <h2>Revision timeline</h2>
        @forelse($revisions as $revision)
            <article class="portfolio-record">
                <div>
                    <strong>{{ ucfirst(str_replace('_', ' ', $revision->event)) }} · #{{ $revision->revision_number }}</strong>
                    <p>{{ ucfirst($revision->subject_type) }} · {{ $revision->created_at }} · Actor ID: {{ $revision->actor_id ?? 'system' }}</p>
                </div>
                @if($revision->changes !== [])
                    <div class="admin-table-wrap">
                        <table>
                            <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                            <tbody>
                                @foreach($revision->changes as $change)
                                    <tr>
                                        <th scope="row">{{ ucfirst(str_replace('_', ' ', $change['field'])) }}</th>
                                        <td>{{ $change['before'] }}</td>
                                        <td>{{ $change['after'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p>No field values changed in this recorded transition.</p>
                @endif
            </article>
        @empty
            <p>No revision history recorded yet. Historical Pure PHP data requires a separate authorized migration.</p>
        @endforelse
        {{ $revisions->links() }}
    </section>
</x-layouts.admin>
