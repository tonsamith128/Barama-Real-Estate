<x-layouts.frontend title="Legal policies — Barama Real Estate">
<main class="marketplace legal-content" id="main-content"><nav aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a></nav>
<h1>Legal information</h1><p>Read the published policies governing this website.</p>
<ul>@forelse($policies as $policy)<li><a href="{{ route('legal.show', $policy->slug) }}">{{ $policy->title }}</a>
@if($policy->summary)<p>{{ $policy->summary }}</p>@endif</li>
@empty<li>No legal pages have been published yet.</li>@endforelse</ul>
</main></x-layouts.frontend>
