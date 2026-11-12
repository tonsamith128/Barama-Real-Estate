<x-layouts.admin title="Geographic Governance — Barama">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Admin / Geography</p>
            <h1>Global geographic governance</h1>
            <p>Manage country availability and canonical locations. External source packs are shown read-only until an independently verified import is available.</p>
        </div>
        <a href="{{ route('admin.catalog.index') }}">Catalog</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())
        <div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <section class="admin-card">
        <h2>Register a country</h2>
        <form class="admin-form" method="post" action="{{ route('admin.geography.countries.store') }}">
            @csrf
            <label>ISO-2 country code <input name="country_code" minlength="2" maxlength="2" pattern="[A-Za-z]{2}" required value="{{ old('country_code') }}"></label>
            <label>ISO-3 country code <input name="iso3" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" required value="{{ old('iso3') }}"></label>
            <label>English country name <input name="name" maxlength="160" required value="{{ old('name') }}"></label>
            <label>Native name <input name="native_name" maxlength="160" value="{{ old('native_name') }}"></label>
            <button type="submit">Register disabled country</button>
        </form>
        <p>Codes must be verified before registration. New countries are disabled by default; data packs are not automatically downloaded.</p>
    </section>

    <section class="admin-card">
        <h2>Registered countries</h2>
        <div class="admin-table-wrap">
            <table>
                <thead><tr><th>Country</th><th>Code</th><th>Mode</th><th>Source status</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($countries as $country)
                        <tr>
                            <td>{{ $country->name }} @if($country->native_name)<small>{{ $country->native_name }}</small>@endif</td>
                            <td>{{ $country->country_code }} / {{ $country->iso3 }}</td>
                            <td>{{ $country->enabled ? 'Enabled' : 'Disabled' }} @if($country->is_default)· Default @endif</td>
                            <td>{{ str_replace('_', ' ', $country->data_status) }}</td>
                            <td>
                                @if(!$country->enabled)
                                    <form method="post" action="{{ route('admin.geography.countries.enable', $country) }}">
                                        @csrf<button type="submit">Enable</button>
                                    </form>
                                @elseif(!$country->is_default)
                                    <form method="post" action="{{ route('admin.geography.countries.disable', $country) }}">
                                        @csrf<button type="submit">Disable</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.geography.countries.default', $country) }}">
                                        @csrf<button type="submit">Make default</button>
                                    </form>
                                @else<span>Current default</span>@endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="5">No countries registered. Existing location records remain intact.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-card">
        <h2>Add a canonical location</h2>
        <form method="post" class="admin-form" action="{{ route('admin.geography.locations.store') }}">
            @csrf
            <label>Name <input name="name" maxlength="190" required></label>
            <label>Slug <input name="slug" maxlength="190" required></label>
            <label>Country <select name="country_code" required>
                @foreach($countries->where('enabled', true) as $country)
                    <option value="{{ $country->country_code }}">{{ $country->name }}</option>
                @endforeach
            </select></label>
            <label>Level <select name="type">
                @foreach(\App\Models\Location::TYPES as $type)
                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                @endforeach
            </select></label>
            <label>Parent location <select name="parent_id">
                <option value="">Country/root</option>
                @foreach($parentChoices as $parent)
                    <option value="{{ $parent->id }}">{{ $parent->country_code }} / {{ $parent->name }}</option>
                @endforeach
            </select></label>
            <label>Sort order <input name="sort_order" type="number" value="0"></label>
            <input type="hidden" name="status" value="active">
            <button type="submit">Create location</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Existing hierarchy</h2>
        <p>Location IDs are permanent. Updates block cycles, unreviewed country transfers and disabling locations referenced by active inventory.</p>
        @forelse($locations as $location)
            <details>
                <summary>{{ $location->country_code }} / {{ $location->name }} · {{ $location->type }} · {{ $location->status }}</summary>
                <form method="post" class="admin-form" action="{{ route('admin.geography.locations.update', $location) }}">
                    @csrf @method('PUT')
                    <label>Name <input name="name" required maxlength="190" value="{{ $location->name }}"></label>
                    <label>Slug <input name="slug" required maxlength="190" value="{{ $location->slug }}"></label>
                    <label>Country <select name="country_code">
                        @foreach($countries->where('enabled', true) as $country)
                            <option value="{{ $country->country_code }}" @selected($location->country_code===$country->country_code)>{{ $country->name }}</option>
                        @endforeach
                    </select></label>
                    <label>Level <select name="type">
                        @foreach(\App\Models\Location::TYPES as $type)
                            <option value="{{ $type }}" @selected($location->type===$type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select></label>
                    <label>Parent <select name="parent_id">
                        <option value="">Country/root</option>
                        @foreach($parentChoices as $parent)
                            <option value="{{ $parent->id }}" @selected($location->parent_id===$parent->id)>{{ $parent->country_code }} / {{ $parent->name }}</option>
                        @endforeach
                    </select></label>
                    <label>Status <select name="status">
                        <option value="active" @selected($location->status==='active')>Active</option>
                        <option value="inactive" @selected($location->status==='inactive')>Inactive</option>
                    </select></label>
                    <label>Sort order <input name="sort_order" type="number" value="{{ $location->sort_order }}"></label>
                    <button type="submit">Save location</button>
                </form>
            </details>
        @empty<p>No locations in the canonical hierarchy.</p>@endforelse
        {{ $locations->links() }}
    </section>

    <section class="admin-card">
        <h2>Data provenance and conversion records</h2>
        <p>{{ number_format($registryCount) }} registered external-to-canonical location mappings. Data packs are metadata-only until verified by an independent importer.</p>
        @forelse($packs as $pack)
            <p>{{ $pack->country_code }} · {{ $pack->source }} / {{ $pack->source_version }} · {{ number_format($pack->record_count) }} records · {{ $pack->status }}</p>
        @empty<p>No geographic data pack installed.</p>@endforelse
    </section>
</x-layouts.admin>
