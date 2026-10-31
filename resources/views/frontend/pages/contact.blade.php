<x-layouts.frontend title="Contact Us — Barama Real Estate">
    <main class="marketplace info-page" id="main-content">
        <p class="marketplace__eyebrow">Get in touch</p><h1>Contact Barama Real Estate</h1>
        <p class="info-page__lead">Questions about the marketplace, your account, or using a listing? Send a message to the administration team.</p>
        <section class="contact-panel"><h2>Send a message</h2>
            @if(session('notice'))<p class="notice" role="status">{{ session('notice') }}</p>@endif
            @if($errors->any())<div class="notice notice--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="post" action="{{ route('contact.submit') }}" class="customer-form">
                @csrf
                <label>Full name <input name="full_name" required maxlength="120" value="{{ old('full_name', auth()->user()?->name) }}"></label>
                <label>Email address <input name="email" type="email" required maxlength="190" value="{{ old('email', auth()->user()?->email) }}"></label>
                <label>Subject <input name="subject" required maxlength="190" value="{{ old('subject') }}"></label>
                <label>Message <textarea name="message" required minlength="12" maxlength="4000" rows="6">{{ old('message') }}</textarea></label>
                <input class="inquiry-honeypot" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <label class="contact-consent"><input type="checkbox" required name="consent" value="1"> I consent to being contacted about this message.</label>
                <button class="button" type="submit">Send message</button>
            </form>
        </section>
        <p>For an inquiry about a specific property, use that property's inquiry form instead.</p>
    </main>
</x-layouts.frontend>
