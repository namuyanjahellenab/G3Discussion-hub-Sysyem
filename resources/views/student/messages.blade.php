@extends('layouts.app') {{-- adjust to your actual layout name --}}

{{-- This page renders its own inline #chat-error-banner for validation
     errors — skip the layout's generic global banner to avoid showing the
     same error twice. --}}
@php($hideGlobalErrors = true)

@section('content')
<style>
    /* Kills the outer browser scrollbar entirely on this page - matches the
       desktop client, which has no such thing (only chat-window scrolls). */
    html, body { overflow: hidden; height: 100%; }
</style>
<div class="chat-page">

    <div class="chat-layout">

        {{-- LEFT: thread list (main group thread + any restricted threads) --}}
        <aside class="thread-list card">
            @if($userGroups->count() > 1)
                <div class="thread-list__subheader">Your Groups</div>
                @foreach($userGroups as $g)
                    <a href="{{ route('student.messages', ['groupId' => $g->GroupID]) }}"
                       class="thread-item {{ (string) $g->GroupID === (string) $groupId ? 'thread-item--active' : '' }}">
                        <i class="fa-solid fa-layer-group"></i>
                        <div><strong>{{ $g->GroupName }}</strong></div>
                    </a>
                @endforeach
            @endif

            <a href="{{ route('student.messages', ['groupId' => $groupId]) }}"
               class="thread-item {{ $activeConversation->ConversationID === $mainConversation->ConversationID ? 'thread-item--active' : '' }}">
                <i class="fa-solid fa-people-group"></i>
                <div>
                    <strong>Group Chat</strong>
                    <span>Everyone in the group</span>
                </div>
                @if($activeConversation->ConversationID !== $mainConversation->ConversationID && ($threadUnreadCounts[$mainConversation->ConversationID] ?? 0) > 0)
                    <span class="unread-badge">{{ $threadUnreadCounts[$mainConversation->ConversationID] > 99 ? '99+' : $threadUnreadCounts[$mainConversation->ConversationID] }}</span>
                @endif
            </a>

            @if($restrictedThreads->count())
                <div class="thread-list__subheader">Restricted Threads</div>
                @foreach($restrictedThreads as $thread)
                    <a href="{{ route('student.messages', ['groupId' => $groupId, 'conversationId' => $thread->ConversationID]) }}"
                       class="thread-item {{ $activeConversation->ConversationID === $thread->ConversationID ? 'thread-item--active' : '' }}">
                        <i class="fa-solid fa-user-slash"></i>
                        <div>
                            <strong>Restricted Thread</strong>
                            <span>Some members excluded</span>
                        </div>
                        @if($activeConversation->ConversationID !== $thread->ConversationID && ($threadUnreadCounts[$thread->ConversationID] ?? 0) > 0)
                            <span class="unread-badge">{{ $threadUnreadCounts[$thread->ConversationID] > 99 ? '99+' : $threadUnreadCounts[$thread->ConversationID] }}</span>
                        @endif
                    </a>
                @endforeach
            @endif
        </aside>

        {{-- RIGHT: active conversation --}}
        <div class="chat-main">

            <div class="chat-header">
                <h2>
                    @if($activeConversation->Type === 'restricted')
                        <i class="fa-solid fa-user-slash"></i> Restricted Thread
                    @else
                        <i class="fa-solid fa-people-group"></i> {{ $groupMembers->first()?->group?->GroupName ?? 'Group' }} Chat
                    @endif
                </h2>
                <p class="text-muted">
                    @if($activeConversation->Type === 'restricted')
                        Only selected members can see this thread.
                    @else
                        Everyone in this group sees your messages, unless you choose to exclude someone below.
                    @endif
                </p>
            </div>

            <div class="alert alert-danger" id="chat-error-banner" style="{{ $errors->any() ? '' : 'display:none;' }}">{{ $errors->first() }}</div>

            <div class="chat-window card" id="chat-window">
                @php($unreadDividerShown = false)
                @forelse($messages as $msg)
                    {{-- Receiver-only, WhatsApp-style: never shown for the viewer's own messages. --}}
                    @if(!$unreadDividerShown && (string) $msg->user_id !== (string) auth()->id() && $lastReadMessageId > 0 && $msg->MessageID > $lastReadMessageId)
                        @php($unreadDividerShown = true)
                        <div class="unread-divider"><span>NEW MESSAGES</span></div>
                    @endif
                    @include('student.partials.chat-bubble', ['msg' => $msg, 'canExclude' => $activeConversation->Type !== 'restricted'])
                @empty
                    <div class="chat-empty" id="chat-empty-state">
                        <i class="fa-solid fa-comment-dots"></i>
                        <p>No messages yet. Start the conversation.</p>
                    </div>
                @endforelse
            </div>

            @if($activeConversation->Type !== 'restricted')
                {{-- only the main group thread can spawn new restricted threads --}}
                <form action="{{ route('student.messages.store', ['groupId' => $groupId]) }}" method="POST" class="chat-composer" enctype="multipart/form-data" data-chat-composer>
                    @csrf
                    <div class="exclude-panel" id="exclude-panel">
                        @foreach($groupMembers as $member)
                            @if($member->UserID !== auth()->id())
                                <label class="exclude-option">
                                    <input type="checkbox" name="exclude[]" value="{{ $member->UserID }}">
                                    {{ $member->user->UserName ?? 'Member' }}
                                </label>
                            @endif
                        @endforeach
                        <p class="exclude-hint">Checking someone here moves this message (and future ones with the same exclusion) into a separate restricted thread they can't see.</p>
                    </div>

                    <div class="reply-context" data-reply-context style="display:none;">
                        <i class="fa-solid fa-reply"></i>
                        <span data-reply-context-text></span>
                        <button type="button" data-reply-context-clear title="Cancel reply">&times;</button>
                    </div>
                    <input type="hidden" name="parent_message_id" data-parent-message-input>

                    <div class="attachment-preview" data-attachment-preview style="display:none;">
                        <i class="fa-solid fa-paperclip"></i>
                        <span data-attachment-name></span>
                        <button type="button" data-attachment-clear title="Remove attachment">&times;</button>
                    </div>

                    <div class="chat-pill">
                        <button type="button" class="pill-icon-btn" onclick="toggleExcludePanel()" title="Exclude members">
                            <i class="fa-solid fa-user-slash"></i>
                        </button>
                        <input type="file" name="attachment" data-attachment-input hidden accept=".pdf,.doc,.docx,.ppt,.pptx,.png,.jpg,.jpeg,.zip">
                        <button type="button" class="pill-icon-btn" data-attachment-trigger title="Attach a file">
                            <i class="fa-solid fa-paperclip"></i>
                        </button>
                        <textarea name="body" placeholder="Type a message..." rows="1">{{ old('body') }}</textarea>
                        <button type="submit" class="pill-send-btn" title="Send">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            @else
                {{-- replying inside a restricted thread reuses the same exclusion set automatically --}}
                <form action="{{ route('student.messages.store', ['groupId' => $groupId]) }}" method="POST" class="chat-composer" enctype="multipart/form-data" data-chat-composer>
                    @csrf
                    <input type="hidden" name="conversation_id" value="{{ $activeConversation->ConversationID }}">

                    <div class="reply-context" data-reply-context style="display:none;">
                        <i class="fa-solid fa-reply"></i>
                        <span data-reply-context-text></span>
                        <button type="button" data-reply-context-clear title="Cancel reply">&times;</button>
                    </div>
                    <input type="hidden" name="parent_message_id" data-parent-message-input>

                    <div class="attachment-preview" data-attachment-preview style="display:none;">
                        <i class="fa-solid fa-paperclip"></i>
                        <span data-attachment-name></span>
                        <button type="button" data-attachment-clear title="Remove attachment">&times;</button>
                    </div>

                    <div class="chat-pill">
                        <input type="file" name="attachment" data-attachment-input hidden accept=".pdf,.doc,.docx,.ppt,.pptx,.png,.jpg,.jpeg,.zip">
                        <button type="button" class="pill-icon-btn" data-attachment-trigger title="Attach a file">
                            <i class="fa-solid fa-paperclip"></i>
                        </button>
                        <textarea name="body" placeholder="Reply in this restricted thread..." rows="1">{{ old('body') }}</textarea>
                        <button type="submit" class="pill-send-btn" title="Send">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            @endif

        </div>
    </div>
</div>

<script>
const currentUserId = {{ auth()->id() }};
const activeConversationId = {{ $activeConversation->ConversationID }};

function toggleExcludePanel() {
    document.getElementById('exclude-panel').classList.toggle('open');
    sizeChatWindow();
}

// Attach-file: clicking the paperclip opens the hidden file input; picking
// a file shows a removable filename chip above the pill (same slot pattern
// the exclude panel uses), so the user knows what's about to be sent.
document.querySelectorAll('[data-chat-composer]').forEach((form) => {
    const trigger = form.querySelector('[data-attachment-trigger]');
    const input = form.querySelector('[data-attachment-input]');
    const preview = form.querySelector('[data-attachment-preview]');
    if (!trigger || !input || !preview) return;

    const nameEl = preview.querySelector('[data-attachment-name]');
    const clearBtn = preview.querySelector('[data-attachment-clear]');

    trigger.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        if (input.files.length) {
            nameEl.textContent = input.files[0].name;
            preview.style.display = 'flex';
        } else {
            preview.style.display = 'none';
        }
        sizeChatWindow();
    });

    clearBtn.addEventListener('click', () => {
        input.value = '';
        preview.style.display = 'none';
        sizeChatWindow();
    });
});

// WhatsApp-style scroll containment: chat-window gets an explicit pixel
// height filling the viewport down to the (sticky, bottom-pinned) composer,
// so scrolling only moves the messages inside it - the header, thread
// list, and composer never move. Recomputed whenever the composer's own
// height could change (exclude panel opened, textarea resized, window
// resized) so the two never overlap or leave a gap.
function sizeChatWindow() {
    const chatWindow = document.getElementById('chat-window');
    const composer = document.querySelector('[data-chat-composer]');
    if (!chatWindow) return;
    const top = chatWindow.getBoundingClientRect().top;
    const composerHeight = composer ? composer.getBoundingClientRect().height : 0;
    const bottomGap = 24;
    const available = window.innerHeight - top - composerHeight - bottomGap;
    chatWindow.style.height = Math.max(240, available) + 'px';
}
window.addEventListener('resize', sizeChatWindow);
sizeChatWindow();

// WhatsApp-style tagging: sets which message is being replied to (shown as
// a quote strip above the composer) and stamps its id into the hidden
// parent_message_id field every composer form already carries, so it rides
// along with whichever form actually gets submitted.
function setReplyContext(messageId, authorName, body) {
    const snippet = body.length > 40 ? body.slice(0, 40) + '…' : body;
    document.querySelectorAll('[data-reply-context]').forEach((el) => {
        el.style.display = 'flex';
        el.querySelector('[data-reply-context-text]').textContent = `Replying to ${authorName}: ${snippet}`;
    });
    document.querySelectorAll('[data-parent-message-input]').forEach((input) => {
        input.value = messageId;
    });
    document.querySelector('[data-chat-composer] textarea[name="body"]')?.focus();
    sizeChatWindow();
}

function clearReplyContext() {
    document.querySelectorAll('[data-reply-context]').forEach((el) => { el.style.display = 'none'; });
    document.querySelectorAll('[data-parent-message-input]').forEach((input) => { input.value = ''; });
    sizeChatWindow();
}

document.querySelectorAll('[data-reply-context-clear]').forEach((btn) => {
    btn.addEventListener('click', clearReplyContext);
});

function showChatError(message) {
    const banner = document.getElementById('chat-error-banner');
    banner.textContent = message;
    banner.style.display = 'block';
    setTimeout(() => { banner.style.display = 'none'; }, 6000);
}

document.getElementById('chat-window').addEventListener('click', (event) => {
    const menuToggle = event.target.closest('.chat-bubble__menu-toggle');
    const menuItem = event.target.closest('.chat-bubble__menu-item');
    const bubble = event.target.closest('.chat-bubble');

    if (menuToggle) {
        const actions = menuToggle.closest('.chat-bubble__actions');
        const wasOpen = actions.classList.contains('open');
        document.querySelectorAll('.chat-bubble__actions.open').forEach((el) => el.classList.remove('open'));
        if (!wasOpen) actions.classList.add('open');
        return;
    }

    if (menuItem && bubble) {
        const action = menuItem.dataset.action;

        if (action === 'reply') {
            setReplyContext(bubble.dataset.messageId, bubble.dataset.username, bubble.dataset.body || '');
        }

        if (action === 'exclude') {
            const panel = document.getElementById('exclude-panel');
            const checkbox = panel?.querySelector(`input[value="${bubble.dataset.userId}"]`);
            if (panel && checkbox) {
                panel.classList.add('open');
                checkbox.checked = true;
                checkbox.closest('.exclude-option')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                // Opening the panel this way (vs the pill's own toggle
                // button, which already calls this) grows the composer too -
                // without resizing, chat-window doesn't shrink to make room
                // and the panel can overlap or spill past the composer.
                sizeChatWindow();
            }
        }

        if (action === 'edit') {
            startEditingBubble(bubble);
        }

        if (action === 'delete') {
            deleteBubble(bubble);
        }

        if (action === 'flag') {
            flagBubble(bubble);
        }

        bubble.querySelector('.chat-bubble__actions')?.classList.remove('open');
    }
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Swaps the message text for an inline textarea + Save/Cancel, matching
// the sender-only Edit action in chat-bubble.blade.php. Only the text can
// change here - an already-sent attachment can't be swapped out.
function startEditingBubble(bubble) {
    if (bubble.querySelector('[data-edit-form]')) return;
    const bodyEl = bubble.querySelector('[data-message-body]');

    const originalText = bubble.dataset.body || '';
    const editForm = document.createElement('div');
    editForm.setAttribute('data-edit-form', '');
    editForm.innerHTML = `
        <textarea class="chat-edit-textarea" rows="2"></textarea>
        <div class="chat-edit-actions">
            <button type="button" data-edit-cancel>Cancel</button>
            <button type="button" data-edit-save>Save</button>
        </div>
    `;
    editForm.querySelector('textarea').value = originalText;

    if (bodyEl) {
        bodyEl.replaceWith(editForm);
    } else {
        // Attachment-only message with no text yet - nothing to swap out,
        // so insert the form instead, right before the attachment link (or
        // at the end of the bubble if there isn't one).
        const content = bubble.querySelector('.chat-bubble__content');
        const attachment = content.querySelector('.chat-bubble__attachment');
        if (attachment) {
            attachment.before(editForm);
        } else {
            content.appendChild(editForm);
        }
    }
    editForm.querySelector('textarea').focus();

    editForm.querySelector('[data-edit-cancel]').addEventListener('click', () => {
        if (bodyEl) {
            editForm.replaceWith(bodyEl);
        } else {
            editForm.remove();
        }
    });

    editForm.querySelector('[data-edit-save]').addEventListener('click', async () => {
        const newBody = editForm.querySelector('textarea').value.trim();
        if (!newBody) return;

        try {
            const res = await fetch(`/group-messages/${bubble.dataset.messageId}`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ body: newBody }),
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data?.success) {
                showChatError(data?.message || 'Could not save your edit.');
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.innerHTML = data.html;
            bubble.replaceWith(wrapper.firstElementChild);
        } catch (err) {
            console.error('Edit error', err);
            showChatError('Something went wrong saving your edit.');
        }
    });
}

async function deleteBubble(bubble) {
    if (!confirm('Delete this message?')) return;

    try {
        const res = await fetch(`/group-messages/${bubble.dataset.messageId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.success) {
            showChatError(data?.message || 'Could not delete your message.');
            return;
        }
        bubble.remove();
    } catch (err) {
        console.error('Delete error', err);
        showChatError('Something went wrong deleting your message.');
    }
}

// Reason is optional server-side (Message::flagBy accepts null), same as
// ajaxReplyAction's flag path in topics/show.blade.php - still worth asking
// so the admin moderation queue has some context beyond "someone reported
// this".
async function flagBubble(bubble) {
    const reason = prompt('Why are you reporting this message? (optional)') || null;

    try {
        const res = await fetch(`/group-messages/${bubble.dataset.messageId}/flag`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ Reason: reason }),
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.success) {
            showChatError(data?.message || 'Could not report this message.');
            return;
        }
        alert('Message reported. A moderator will review it.');
    } catch (err) {
        console.error('Flag error', err);
        showChatError('Something went wrong reporting this message.');
    }
}

document.addEventListener('click', (event) => {
    if (!event.target.closest('.chat-bubble__actions')) {
        document.querySelectorAll('.chat-bubble__actions.open').forEach((el) => el.classList.remove('open'));
    }
});

document.querySelectorAll('[data-chat-composer]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const textarea = form.querySelector('textarea[name="body"]');
        const submitBtn = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);
        const chatWindow = document.getElementById('chat-window');

        if (submitBtn) submitBtn.disabled = true;

        const emptyState = document.getElementById('chat-empty-state');
        if (emptyState) emptyState.remove();

        const skeleton = document.createElement('div');
        skeleton.className = 'chat-bubble chat-bubble--own chat-bubble--skeleton';
        skeleton.innerHTML = `
            <div class="chat-bubble__avatar skeleton skeleton-circle"></div>
            <div class="chat-bubble__content">
                <div class="skeleton skeleton-line skeleton-line--short"></div>
                <div class="skeleton skeleton-line"></div>
            </div>
        `;
        chatWindow.appendChild(skeleton);
        chatWindow.scrollTop = chatWindow.scrollHeight;

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            });

            const data = await res.json().catch(() => null);

            if (res.status === 422 || !res.ok) {
                showChatError(data?.message || 'Your message could not be sent.');
                return;
            }

            if (data?.success && data?.html) {
                // Spam or not, the sender always sees their own bubble -
                // chat-bubble.blade.php renders the "under review" badge on
                // it when data.pending is true (GroupChatController::store).
                // Other members simply never receive it (no broadcast, and
                // index() excludes it from anyone else's view).
                chatWindow.insertAdjacentHTML('beforeend', data.html);
                chatWindow.scrollTop = chatWindow.scrollHeight;
                textarea.value = '';

                if (data.pending) {
                    showChatError(data.message || 'Your message was flagged for review - only you can see it until an admin approves it.');
                }

                const attachmentInput = form.querySelector('[data-attachment-input]');
                const attachmentPreview = form.querySelector('[data-attachment-preview]');
                if (attachmentInput) attachmentInput.value = '';
                if (attachmentPreview) attachmentPreview.style.display = 'none';
                clearReplyContext();
            }
        } catch (err) {
            console.error('Fetch error', err);
            showChatError('Something went wrong sending your message. Please try again.');
        } finally {
            skeleton.remove();
            if (submitBtn) submitBtn.disabled = false;
        }
    });
});

// Real-time: messages sent by anyone else while this page is open appear
// immediately, no reload needed. Our own sends are already appended
// optimistically above, so skip re-appending those when the broadcast of
// our own message echoes back.
function buildRemoteBubble(e) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
        <div class="chat-bubble" data-message-id="${e.id}" data-user-id="${e.user_id}" data-username="${e.author_name}">
            <div class="chat-bubble__avatar">${(e.author_name || '?').charAt(0).toUpperCase()}</div>
            <div class="chat-bubble__content">
                <div class="chat-bubble__meta">
                    <strong></strong>
                    <span></span>
                </div>
                <div class="quote-strip-slot"></div>
                <p></p>
            </div>
            <div class="chat-bubble__actions">
                <button type="button" class="chat-bubble__menu-toggle" aria-label="Message actions">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
                <div class="chat-bubble__menu">
                    <button type="button" class="chat-bubble__menu-item" data-action="reply">
                        <i class="fa-solid fa-reply"></i> Reply
                    </button>
                    <button type="button" class="chat-bubble__menu-item" data-action="exclude">
                        <i class="fa-solid fa-user-slash"></i> Exclude sender
                    </button>
                </div>
            </div>
        </div>
    `;
    const bubble = wrapper.firstElementChild;
    const content = bubble.querySelector('.chat-bubble__content');
    bubble.querySelector('.chat-bubble__meta strong').textContent = e.author_name;
    bubble.querySelector('.chat-bubble__meta span').textContent = e.created_at;
    bubble.dataset.body = e.body || '';

    const quoteSlot = content.querySelector('.quote-strip-slot');
    if (e.parent_message_author) {
        const quoteEl = document.createElement('div');
        quoteEl.className = 'quote-strip';
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-reply';
        quoteEl.appendChild(icon);
        quoteEl.append(' Replying to ');
        const strong = document.createElement('strong');
        strong.textContent = e.parent_message_author;
        quoteEl.appendChild(strong);
        quoteEl.append(': ' + (e.parent_message_snippet || ''));
        quoteSlot.replaceWith(quoteEl);
    } else {
        quoteSlot.remove();
    }

    const bodyEl = content.querySelector('p');
    if (e.body) {
        bodyEl.textContent = e.body;
    } else {
        bodyEl.remove();
    }

    if (e.attachment_url) {
        const link = document.createElement('a');
        link.href = e.attachment_url;
        link.target = '_blank';
        link.className = 'chat-bubble__attachment';
        link.innerHTML = `<i class="fa-solid fa-paperclip"></i> ${e.attachment_name || 'Attachment'}`;
        content.appendChild(link);
    }

    return bubble;
}

// Echo/Pusher no longer auto-connects on every page load (see
// resources/js/echo.js) - only this page, which actually needs live
// updates, pays for opening the WebSocket connection.
if (window.initEcho) {
    window.initEcho();
}

if (window.Echo) {
    window.Echo.private(`conversation.${activeConversationId}`)
        .listen('.message.sent', (e) => {
            if (e.user_id === currentUserId) return;

            const chatWindow = document.getElementById('chat-window');
            if (chatWindow.querySelector(`[data-message-id="${e.id}"]`)) return;

            document.getElementById('chat-empty-state')?.remove();
            chatWindow.appendChild(buildRemoteBubble(e));
            chatWindow.scrollTop = chatWindow.scrollHeight;
        });
}
</script>
@endsection