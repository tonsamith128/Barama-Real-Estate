<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $policy->exists ? 'Edit' : 'Create' }} Legal Page — Barama</title>
@vite(['resources/css/admin/app.css','resources/js/admin/app.js'])</head>
<body class="admin-body"><main class="admin-shell"><header>
<p class="admin-eyebrow">Barama Real Estate / Legal</p><h1>{{ $policy->exists ? 'Edit legal page' : 'Create legal page' }}</h1>
<nav><a href="{{ route('admin.policies.index') }}">Policy Center</a> · <a href="{{ route('admin.dashboard') }}">Dashboard</a></nav>
</header>
@if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
@if($errors->any())<div class="admin-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="admin-stat">
<form method="post" action="{{ $policy->exists ? route('admin.policies.update', $policy) : route('admin.policies.store') }}" class="policy-form">
@csrf
@if($policy->exists) @method('PUT') @endif
<label for="policy_title">Page title</label>
<input id="policy_title" name="title" maxlength="190" required value="{{ old('title', $policy->title) }}">
<label for="policy_slug">URL slug</label>
<input id="policy_slug" name="slug" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="190" required value="{{ old('slug', $policy->slug) }}">
<label for="policy_summary">Summary (optional)</label>
<input id="policy_summary" name="summary" maxlength="500" value="{{ old('summary', $policy->summary) }}">
<label for="policy_content">Legal content (safe HTML)</label>
<textarea id="policy_content" name="content_html" rows="20" maxlength="100000" required>{{ old('content_html', $policy->content_html) }}</textarea>
<p class="policy-note">Supported HTML: headings, paragraphs, lists, bold/italic, tables and HTTPS links. Scripts, event handlers, embeds and unsafe styles are stripped. Saving changes to an already published policy returns it to Draft for review.</p>
<button type="submit">{{ $policy->exists ? 'Save as draft' : 'Create draft' }}</button>
</form>
</section>
@if($policy->exists)
<section class="admin-stat"><h2>Publication</h2><p>Status: <strong>{{ ucfirst($policy->status) }}</strong></p>
@if($policy->status !== 'published')
<form method="post" action="{{ route('admin.policies.publish', $policy) }}">@csrf
<button type="submit">Publish this legal page</button></form>
@else
<p><a href="{{ route('legal.show', $policy->slug) }}">View published page</a></p>
<form method="post" action="{{ route('admin.policies.unpublish', $policy) }}">@csrf
<button type="submit">Unpublish</button></form>
@endif
</section>
@endif
</main></body></html>
