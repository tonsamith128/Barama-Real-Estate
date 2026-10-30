<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Demo Importer | Barama</title>@vite(['resources/css/admin/app.css','resources/js/admin/app.js'])</head>
<body><main class="admin-shell"><header><p>Barama Real Estate / System</p><h1>Demo Content Importer</h1>
<nav><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a></nav></header>
<p>Preview an official Barama demo archive in read-only mode. The production importer is disabled until licensing is migrated.</p>
@if(session('notice'))<p class="admin-notice">{{ session('notice') }}</p>@endif
@if($errors->any())<div class="admin-errors"><strong>Action not completed</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($import)<section class="admin-stat"><h2>Import completed</h2><p>Archive {{ $import->archive_version }} · {{ $import->image_count }} images. Duplicate import is blocked.</p></section>
@else
<section class="admin-stat"><h2>1. Inspect original ZIP</h2>
<form method="post" action="{{ route('admin.demo.preview') }}" enctype="multipart/form-data">
@csrf<label for="archive">Official demo archive (up to 64MB)</label>
<input id="archive" type="file" name="archive" accept=".zip,application/zip" required>
<button type="submit">Validate and preview archive</button></form></section>
@if($stage)<section class="admin-stat"><h2>2. Preview</h2>
<p><strong>Archive version:</strong> {{ $stage['version'] }} · <strong>Images:</strong> {{ $stage['images'] }} · <strong>Policy templates:</strong> {{ $stage['policies'] }}</p>
<table><thead><tr><th>Data collection</th><th>Records</th></tr></thead><tbody>
@foreach($stage['counts'] as $label => $count)<tr><td>{{ $label }}</td><td>{{ number_format($count) }}</td></tr>@endforeach
</tbody></table>
<p><strong>Digest:</strong> <code>{{ $stage['sha256'] }}</code></p>
<p>The ZIP has NOT been authenticated. Import also requires a publisher-signed Ed25519 attestation matching the independently pinned public key.</p>
@if($enabled)<form method="post" action="{{ route('admin.demo.install') }}">@csrf
<label for="signature">Publisher detached signature (base64)</label>
<input type="text" id="signature" name="signature" required maxlength="512" autocomplete="off">
<label for="confirmation">Type IMPORT VERIFIED DEMO</label>
<input type="text" id="confirmation" name="confirmation" required autocomplete="off">
<button type="submit">Import authenticated demo into empty staging database</button>
</form>@else<p class="admin-errors">Import disabled: staging authorization or independently pinned publisher public key is not configured.</p>@endif
</section>@endif
@endif
<p>Do not use legacy Barama production databases. Demo legal policies and appearance are imported as inactive templates.</p>
</main></body></html>
