<x-layouts.admin title="Business Pricing Plans — Barama Real Estate">
    <header class="admin-heading">
        <div><p class="admin-eyebrow">Administration / Finance</p>
            <h1>Business Pricing Plans</h1>
            <p>Configure public plan descriptions and review company interest. Payment processing and subscription activation are not enabled.</p>
        </div>
        <a href="{{ route('pricing.index') }}" target="_blank" rel="noopener">View pricing page</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())<div class="admin-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="admin-card">
        <h2>Add a new plan</h2>
        <form method="post" action="{{ route('admin.finance.plans.store') }}" class="admin-form commerce-plan-form">
            @csrf
            <label>Unique slug <input name="slug" required maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug') }}" placeholder="business-pro"></label>
            <label>Plan name <input name="name" required maxlength="120" value="{{ old('name') }}"></label>
            <label>Price in USD <input name="price" type="number" min="0" max="999999.99" step="0.01" required value="{{ old('price', '0.00') }}"></label>
            <label>Billing period
                <select name="billing_period" required>
                    <option value="month">Monthly</option><option value="year">Yearly</option><option value="once">One-time</option>
                </select>
            </label>
            <label>Proposed listing limit <input type="number" min="1" max="100000" name="listing_limit" required value="{{ old('listing_limit', 10) }}"></label>
            <label>Display order <input type="number" min="0" max="999" name="sort_order" required value="{{ old('sort_order', 0) }}"></label>
            <label>Description <textarea name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea></label>
            <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active'))> Publish this pricing option</label>
            <button type="submit">Create plan</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Manage plans</h2>
        @forelse($plans as $plan)
            <form method="post" action="{{ route('admin.finance.plans.update', $plan) }}" class="admin-form commerce-plan-form">
                @csrf @method('PATCH')
                <h3>{{ $plan->name }} <small>{{ $plan->is_active ? 'Published' : 'Hidden' }}</small></h3>
                <label>Slug <input name="slug" required maxlength="80" value="{{ $plan->slug }}"></label>
                <label>Name <input name="name" required maxlength="120" value="{{ $plan->name }}"></label>
                <label>Price in USD <input name="price" type="number" min="0" max="999999.99" step="0.01" required value="{{ number_format($plan->price_cents / 100, 2, '.', '') }}"></label>
                <label>Billing period
                    <select name="billing_period" required>
                        @foreach(['month'=>'Monthly', 'year'=>'Yearly', 'once'=>'One-time'] as $value=>$label)
                            <option value="{{ $value }}" @selected($plan->billing_period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Proposed listing limit <input type="number" min="1" max="100000" name="listing_limit" required value="{{ $plan->listing_limit }}"></label>
                <label>Display order <input type="number" min="0" max="999" name="sort_order" required value="{{ $plan->sort_order }}"></label>
                <label>Description <textarea name="description" rows="3" maxlength="2000">{{ $plan->description }}</textarea></label>
                <label><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Published</label>
                <button type="submit">Save plan</button>
            </form>
        @empty
            <p>No pricing plans yet.</p>
        @endforelse
    </section>

    <section class="admin-card">
        <h2>Company plan inquiries</h2>
        <p>These are requests for follow-up, not paid orders. Verify financial and contractual information outside this module.</p>
        <ul class="commerce-inquiry-list">
            @forelse($inquiries as $inquiry)
                <li><strong>{{ $inquiry->company?->name ?? 'Unknown company' }}</strong>
                    requested {{ $inquiry->plan?->name ?? 'Unavailable plan' }}
                    · {{ $inquiry->requestedBy?->email ?? 'Unknown user' }}
                    · {{ $inquiry->created_at?->format('Y-m-d') }}
                    <span>{{ ucfirst($inquiry->status) }}</span>
                </li>
            @empty
                <li>There are no company plan inquiries yet.</li>
            @endforelse
        </ul>
        {{ $inquiries->links() }}
    </section>
</x-layouts.admin>
