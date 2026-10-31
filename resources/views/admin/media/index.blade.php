<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Media Library — Barama</title>@vite(['resources/css/admin/app.css','resources/js/admin/app.js'])</head>
<body class="admin-body"><main class="admin-shell">
<header><p class="admin-eyebrow">Barama Real Estate / Media</p>
<h1>Media Library</h1>
<p>Private original images remain outside the public document root. They can be displayed only on an approved listing or through a verified company workspace.</p>
<nav><a href="{{ route('admin.dashboard') }}">Dashboard</a> · <a href="{{ route('admin.catalog.index') }}">Manage listings</a></nav></header>
<section class="admin-stat"><h2>Stored media</h2>
<table><thead><tr><th>Preview</th><th>Original name</th><th>File type</th><th>Size</th><th>Dimensions</th><th>References</th></tr></thead>
<tbody>@forelse($assets as $asset)<tr>
<td><img width="120" height="80" style="object-fit:cover;border-radius:8px" loading="lazy" src="{{ route('admin.media.preview', $asset) }}" alt="{{ $asset->alt_text ?: $asset->original_name }}"></td><td>{{ $asset->original_name }}</td><td>{{ $asset->mime_type }}</td>
<td>{{ number_format($asset->size_bytes / 1048576, 2) }} MB</td>
<td>{{ $asset->width_px }} × {{ $asset->height_px }}</td>
<td>{{ $asset->attachments_count }}</td>
</tr>@empty<tr><td colspan="6">No images uploaded yet. Verified company owners can add images to draft listings.</td></tr>@endforelse
</tbody></table>
{{ $assets->links() }}</section></main></body></html>
