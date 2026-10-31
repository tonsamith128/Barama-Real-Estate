<x-layouts.frontend :title="$policy->title . ' — Barama Real Estate'">
<main class="marketplace legal-content" id="main-content">
<nav aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('legal.index') }}">Legal</a></nav>
<article><h1>{{ $policy->title }}</h1>
@if($policy->summary)<p class="legal-summary">{{ $policy->summary }}</p>@endif
<div class="legal-content__body">{!! $safeHtml !!}</div>
<p class="legal-updated">Updated {{ $policy->updated_at->format('F j, Y') }}</p>
</article></main></x-layouts.frontend>
