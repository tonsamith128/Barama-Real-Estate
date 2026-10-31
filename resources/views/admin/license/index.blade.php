<x-layouts.admin title="License & Activation — Barama Real Estate">
    <header class="admin-heading">
        <div>
            <p class="admin-eyebrow">SYSTEM / COMMERCIAL LICENSING</p>
            <h1>License &amp; Activation</h1>
            <p>Manage domain-bound activation safely through the official Barama publisher service.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
    </header>
    @if(session('notice'))<p class="admin-notice" role="status">{{ session('notice') }}</p>@endif
    @if($errors->any())
        <div class="admin-errors" role="alert">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <section class="license-hero">
        <div>
            <p class="license-hero__eyebrow">BARAMA REAL ESTATE · LICENSE CENTER</p>
            <h2>Your license. Your domain. Protected.</h2>
            <p>Activation is tied to the registered installation and domain. Purchase codes are not stored in the local activation receipt.</p>
        </div>
        <span class="license-hero__symbol" aria-hidden="true">◇</span>
    </section>

    <div class="license-grid">
        <section class="admin-card">
            <div class="license-section-heading"><h2>Installation activation</h2>
                @if($licenseStatus['activated'] && $licenseStatus['domain_matches'])
                    <span class="license-status license-status--recorded">Last recorded active</span>
                @elseif($licenseStatus['activated'])
                    <span class="license-status license-status--warning">Domain mismatch</span>
                @else
                    <span class="license-status">Not activated</span>
                @endif
            </div>

            <dl class="license-details">
                <div><dt>Current application domain</dt><dd>{{ $domain }}</dd></div>
                <div><dt>Recorded licensed domain</dt><dd>{{ $licenseStatus['licensed_domain'] ?? 'Not available' }}</dd></div>
                <div><dt>Last successful publisher verification</dt>
                    <dd>{{ $licenseStatus['validated_at'] ?? 'Never' }}</dd></div>
                <div><dt>Source</dt><dd>{{ $licenseStatus['source'] ?: 'Not recorded' }}</dd></div>
            </dl>

            @if($licenseStatus['activated'])
                <h3>Check current entitlement</h3>
                <p class="license-detail-note">The status above is a locally saved receipt, not a real-time license check. Use the button below to contact the publisher and refresh the verified status.</p>
                @if($licenseStatus['domain_matches'])
                    <form method="post" action="{{ route('admin.license.revalidate') }}" class="license-action-form">
                        @csrf
                        <button type="submit">Verify license with publisher</button>
                    </form>
                @else
                    <p class="license-domain-warning" role="alert">The current application domain does not match the stored receipt. Revalidation is disabled; correct the domain configuration or contact support.</p>
                @endif
            @else
                <h3>Activate your installation</h3>
                <p class="license-detail-note">Enter a genuine CodeCanyon purchase code or a directly issued Barama license key. This does not reinstall the application or change your existing database.</p>
                <form method="post" action="{{ route('admin.license.activate') }}" class="license-action-form">
                    @csrf
                    <label for="purchase_code">Purchase Code / License Key</label>
                    <input id="purchase_code" type="text" name="purchase_code"
                        minlength="8" maxlength="120" autocomplete="off"
                        pattern="[A-Za-z0-9-]+" required
                        placeholder="Enter your valid purchase code">
                    <button type="submit">Verify and activate license</button>
                </form>
            @endif
        </section>

        <aside class="admin-card license-guidance">
            <p class="admin-eyebrow">SECURE COMMERCIAL LICENSING</p>
            <h2>How licensing works</h2>
            <div class="license-guidance__step"><span>01</span><div><strong>Identify the domain</strong>
                <p>The application identifies the configured domain for license validation.</p></div></div>
            <div class="license-guidance__step"><span>02</span><div><strong>Verify with publisher</strong>
                <p>The official license service confirms product, domain and installation identity.</p></div></div>
            <div class="license-guidance__step"><span>03</span><div><strong>Keep credentials private</strong>
                <p>Only the activation token is stored in private server storage. The purchase code is not retained.</p></div></div>
            <p class="license-guidance__note">This screen does not perform payments, grant unverified entitlements, override declined licenses, or provide a release updater. Those remain separate commercial features.</p>
            <a href="https://tonsamith.com/contact" target="_blank" rel="noopener noreferrer">Contact official support</a>
        </aside>
    </div>
</x-layouts.admin>
