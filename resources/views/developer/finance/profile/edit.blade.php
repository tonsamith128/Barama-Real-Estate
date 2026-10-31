<x-layouts.admin title="International Billing Profile — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">Developer / Finance</p>
            <h1>{{ $company->name }} — Billing profile</h1>
            <p>Provide business billing details and display preferences for customers across regions.</p>
        </div>
        <a href="{{ route('developer.finance.plans.index', $company) }}">Back to plans</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="admin-card">
        <h2>Company billing contact</h2>
        <form method="post" action="{{ route('developer.finance.profile.update', $company) }}" class="admin-form commerce-plan-form">
            @csrf
            @method('PUT')
            <label>Business or billing name
                <input name="billing_name" maxlength="160" required value="{{ old('billing_name', $profile?->billing_name ?? $company->name) }}">
            </label>
            <label>Billing email
                <input name="billing_email" type="email" maxlength="190" required value="{{ old('billing_email', $profile?->billing_email ?? auth()->user()?->email) }}">
            </label>
            <label>Billing country or territory
                <select name="country_code" required>
                    <option value="">Choose a country or territory</option>
                    @foreach($countries as $code => $name)
                        <option value="{{ $code }}" @selected(old('country_code', $profile?->country_code) === $code)>{{ $name }} ({{ $code }})</option>
                    @endforeach
                </select>
            </label>
            <label>Street / building <input name="address_line" maxlength="190" value="{{ old('address_line', $profile?->address_line) }}"></label>
            <label>City <input name="city" maxlength="100" value="{{ old('city', $profile?->city) }}"></label>
            <label>State / province <input name="region" maxlength="100" value="{{ old('region', $profile?->region) }}"></label>
            <label>Postal code <input name="postal_code" maxlength="32" value="{{ old('postal_code', $profile?->postal_code) }}"></label>
            <label>Preferred language
                <select name="preferred_locale" required>
                    @foreach($locales as $code => $name)
                        <option value="{{ $code }}" @selected(old('preferred_locale', $profile?->preferred_locale ?? 'en') === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Display currency
                <select name="preferred_currency" required>
                    @foreach($currencies as $code)
                        <option value="{{ $code }}" @selected(old('preferred_currency', $profile?->preferred_currency ?? 'USD') === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </label>
            <label>Timezone <input name="timezone" maxlength="64" required value="{{ old('timezone', $profile?->timezone ?? 'UTC') }}" placeholder="Asia/Phnom_Penh"></label>
            <button type="submit">Save billing preferences</button>
        </form>
    </section>
    <p class="admin-notice">Preferences do not convert prices, change USD charges, enable a payment provider, or determine tax liability. The available interface is currently in English; other languages are saved preferences for future translation support.</p>
</x-layouts.admin>
