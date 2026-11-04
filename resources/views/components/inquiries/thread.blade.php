@props(['inquiry', 'messages', 'replyAction'])
<section class="conversation-shell" aria-labelledby="conversation-heading">
    <div class="conversation-subject">
        <div>
            <p class="conversation-eyebrow">PROPERTY INQUIRY</p>
            <h2 id="conversation-heading">{{ $inquiry->listing?->property?->title ?? 'Property unavailable' }}</h2>
            <p>{{ $inquiry->listing?->property?->company?->name ?? 'Company unavailable' }}
                · Submitted {{ $inquiry->created_at->format('M j, Y') }}</p>
        </div>
        <span class="conversation-state">{{ ucfirst($inquiry->status) }}</span>
    </div>

    <div class="conversation-initial">
        <strong>Original inquiry from {{ $inquiry->full_name }}</strong>
        <p>{{ $inquiry->message }}</p>
        <time datetime="{{ $inquiry->created_at->toIso8601String() }}">{{ $inquiry->created_at->format('M j, Y · g:i A') }}</time>
    </div>

    <div class="conversation-timeline" aria-label="Inquiry message history">
        @forelse($messages as $message)
            <article @class(['conversation-item', 'conversation-item--mine' => $message->sender_id === auth()->id()])>
                <div class="conversation-item__top">
                    <strong>{{ $message->sender?->name ?? 'Former team member' }}</strong>
                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, Y · g:i A') }}</time>
                </div>
                <p>{{ $message->body }}</p>
            </article>
        @empty
            <div class="conversation-empty">
                <h3>No replies yet</h3>
                <p>No messages have been recorded for this inquiry yet.</p>
            </div>
        @endforelse
    </div>

    {{ $messages->links() }}

    @if($inquiry->requester_id !== null)
        <form class="conversation-compose" method="post" action="{{ $replyAction }}">
            @csrf
            <label for="inquiry-reply">Write a private reply</label>
            <textarea id="inquiry-reply" name="body" rows="4" minlength="2" maxlength="3000"
                required placeholder="Write a helpful update about this property or the next steps.">{{ old('body') }}</textarea>
            @error('body')<p class="conversation-error" role="alert">{{ $message }}</p>@enderror
            <div class="conversation-compose__footer">
                <p>Replies are stored privately in your account. Email or SMS delivery is not enabled.</p>
                <button type="submit">Send message</button>
            </div>
        </form>
    @else
        <p class="conversation-unlinked" role="note">
            This inquiry was submitted without a linked account. In-app replies are unavailable;
            use the existing authorized contact process for follow-up.
        </p>
    @endif
</section>
