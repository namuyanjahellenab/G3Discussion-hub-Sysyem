<div class="chat-bubble {{ $msg->user_id === auth()->id() ? 'chat-bubble--own' : '' }}"
     data-message-id="{{ $msg->MessageID }}"
     data-user-id="{{ $msg->user_id }}"
     data-username="{{ $msg->user->UserName ?? 'Unknown' }}">
    <div class="chat-bubble__avatar">{{ Str::initials($msg->user->UserName ?? '?') }}</div>
    <div class="chat-bubble__content">
        <div class="chat-bubble__meta">
            <strong>{{ $msg->user->UserName ?? 'Unknown' }}</strong>
            <span>{{ \Carbon\Carbon::parse($msg->CreatedAt)->diffForHumans() }}</span>
        </div>
        <p>{{ $msg->body }}</p>
    </div>
    <div class="chat-bubble__actions">
        <button type="button" class="chat-bubble__menu-toggle" aria-label="Message actions">
            <i class="fa-solid fa-ellipsis-vertical"></i>
        </button>
        <div class="chat-bubble__menu">
            <button type="button" class="chat-bubble__menu-item" data-action="reply">
                <i class="fa-solid fa-reply"></i> Reply
            </button>
            @if(($canExclude ?? true) && $msg->user_id !== auth()->id())
                <button type="button" class="chat-bubble__menu-item" data-action="exclude">
                    <i class="fa-solid fa-user-slash"></i> Exclude sender
                </button>
            @endif
        </div>
    </div>
</div>
