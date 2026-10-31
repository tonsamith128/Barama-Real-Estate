<x-layouts.account title="Profile & Security — Barama Real Estate">
    <header class="customer-heading"><p class="customer-eyebrow">Account settings</p><h1>Profile &amp; security</h1>
        <p>Keep your contact information and credentials up to date.</p></header>
    <div class="customer-grid">
        <section class="customer-card"><h2>Personal information</h2>
            <form class="customer-form" method="post" action="{{ route('account.profile.update') }}">@csrf @method('PUT')
                <label>Full name <input name="name" required maxlength="190" value="{{ old('name', $user->name) }}"></label>
                <label>Email <input name="email" type="email" required maxlength="190" value="{{ old('email', $user->email) }}"></label>
                <label>Current password (required to change email) <input name="current_password" type="password" autocomplete="current-password"></label>
                <button class="button" type="submit">Save profile</button>
            </form>
        </section>
        <section class="customer-card"><h2>Change password</h2>
            <form class="customer-form" method="post" action="{{ route('account.profile.password') }}">@csrf @method('PUT')
                <label>Current password <input name="current_password" type="password" required autocomplete="current-password"></label>
                <label>New password <input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
                <label>Confirm password <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
                <p>At least 12 characters with uppercase, lowercase, a number and symbol.</p>
                <button class="button" type="submit">Update password</button>
            </form>
        </section>
    </div>
</x-layouts.account>
