<div class="chat-bubble {{ $msg->user_id === auth()->id() ? 'chat-bubble--own' : '' }}"
     data-message-id="{{ $msg->MessageID }}"
     data-user-id="{{ $msg->user_id }}"
     data-username="{{ $msg->user->UserName ?? 'Unknown' }}"
     data-body="{{ $msg->body }}">
    <div class="chat-bubble__avatar">{{ Str::initials($msg->user->UserName ?? '?') }}</div>
    <div class="chat-bubble__content">
        <div class="chat-bubble__meta">
            <strong>{{ $msg->user->UserName ?? 'Unknown' }}</strong>
            <span>{{ \Carbon\Carbon::parse($msg->CreatedAt)->diffForHumans() }}</span>
        </div>
        @if($msg->parentMessage)
            <div class="quote-strip">
                <i class="fa-solid fa-reply"></i> Replying to <strong>{{ $msg->parentMessage->user->UserName ?? 'Unknown' }}</strong>: {{ Str::limit($msg->parentMessage->body, 40) }}
            </div>
        @endif
        @if($msg->is_spam)
            {{-- Only ever rendered for the sender's own message - other
                 members never receive a spam-flagged bubble at all (see
                 GroupChatController::index / GroupChatService::send). --}}
            <div class="chat-bubble__pending">
                <i class="fa-solid fa-triangle-exclamation"></i> Under review — suspected spam. Only you can see this until an admin approves it.
            </div>
        @endif
        @if($msg->body)
            <p data-message-body>{{ $msg->body }}</p>
        @endif
        @if($msg->Attachment)
            @php($msgAttachmentName = \App\Services\AttachmentUploader::displayName($msg->Attachment))
            @if($msg->AttachmentType === 'image')
                <div class="attach-img">
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($msg->Attachment) }}" target="_blank" rel="noopener" title="View full size">
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($msg->Attachment) }}" alt="Attachment">
                    </a>
                    <a class="attach-img__download" href="{{ route('student.messages.attachment', $msg->MessageID) }}" title="Download">
                        <i class="fa-solid fa-download"></i>
                    </a>
                </div>
            @else
                <div class="attach-file">
                    <a class="attach-file__open" href="{{ \Illuminate\Support\Facades\Storage::url($msg->Attachment) }}" target="_blank" rel="noopener" title="Open {{ $msgAttachmentName }}">
                        <div class="icon"><i class="fa-solid fa-file"></i></div>
                        <div class="fname">{{ $msgAttachmentName }}</div>
                    </a>
                    <a class="attach-download-icon" href="{{ route('student.messages.attachment', $msg->MessageID) }}" title="Download">
                        <i class="fa-solid fa-download"></i>
                    </a>
                </div>
            @endif
        @endif
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
            @if($msg->user_id !== auth()->id())
                <button type="button" class="chat-bubble__menu-item" data-action="flag">
                    <i class="fa-solid fa-flag"></i> Report
                </button>
            @endif
            @if($msg->user_id === auth()->id())
                {{-- Always available, even on an attachment-only message
                     with no text yet - editing it there just adds a caption. --}}
                <button type="button" class="chat-bubble__menu-item" data-action="edit">
                    <i class="fa-solid fa-pen"></i> Edit
                </button>
                <button type="button" class="chat-bubble__menu-item" data-action="delete">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            @endif
        </div>
    </div>
</div>
