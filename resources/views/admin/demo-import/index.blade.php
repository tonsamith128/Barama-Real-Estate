<x-layouts.admin title="Staging Data Import — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">SYSTEM / STAGING UTILITIES</p>
            <h1>Staging Data Import</h1>
            <p>Inspect and verify a signed publisher archive in an isolated, empty staging installation.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}">Back to dashboard</a>
    </header>

    @if(session('notice'))
        <p class="admin-notice" role="status">{{ session('notice') }}</p>
    @endif
    @if($errors->any())
        <section class="admin-errors" role="alert">
            <strong>Action not completed</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <section class="admin-card" aria-label="Import safety">
        <h2>Staging safeguards</h2>
        <p>This tool is available only when a staging administrator explicitly enables publisher verification.
            It does not import into production or into an existing marketplace database.</p>
        <p>Archive inspection alone does not establish authenticity. A valid detached Ed25519 signature
            and a separately configured publisher key are required before import.</p>
    </section>

    @if($import)
        <section class="admin-card">
            <h2>Import history</h2>
            <p>Archive {{ $import->archive_version }} · {{ number_format($import->marketplace_records) }} records ·
                {{ number_format($import->image_count) }} images.</p>
            <p>Duplicate imports are blocked. Legal and appearance templates remain unpublished for operator review.</p>
        </section>
    @else
        <section class="admin-card">
            <h2>1. Inspect signed archive contents</h2>
            <p>Choose a publisher archive, up to 64 MB, to inspect its manifest, record counts and image digests.</p>
            <form method="post" action="{{ route('admin.demo.preview') }}" enctype="multipart/form-data" class="admin-form">
                @csrf
                <label for="archive">Archive ZIP
                    <input id="archive" type="file" name="archive" accept=".zip,application/zip" required>
                </label>
                <button type="submit">Inspect archive</button>
            </form>
        </section>

        @if($stage)
            <section class="admin-card">
                <h2>2. Verify publisher signature</h2>
                <p>Archive version {{ $stage['version'] }} · {{ number_format($stage['images']) }} images ·
                    {{ number_format($stage['policies']) }} policy templates.</p>
                <div class="admin-table-wrap">
                    <table>
                        <thead><tr><th>Collection</th><th>Records</th></tr></thead>
                        <tbody>@foreach($stage['counts'] as $label => $count)
                            <tr><td>{{ ucfirst(str_replace('_', ' ', $label)) }}</td><td>{{ number_format($count) }}</td></tr>
                        @endforeach</tbody>
                    </table>
                </div>
                <p>Archive SHA-256: <code>{{ $stage['sha256'] }}</code></p>
                <p><strong>Preview is untrusted.</strong> Verify the detached signature using independently published credentials.</p>

                <form method="post" action="{{ route('admin.demo.install') }}" class="admin-form">
                    @csrf
                    <label for="signature">Detached publisher signature (Base64)
                        <input type="text" id="signature" name="signature" required maxlength="512" autocomplete="off">
                    </label>
                    <label for="confirmation">Type IMPORT VERIFIED DEMO
                        <input type="text" id="confirmation" name="confirmation" required
                            autocomplete="off" placeholder="IMPORT VERIFIED DEMO">
                    </label>
                    <button type="submit">Verify signature and import into empty staging database</button>
                </form>
            </section>
        @endif
    @endif
</x-layouts.admin>
