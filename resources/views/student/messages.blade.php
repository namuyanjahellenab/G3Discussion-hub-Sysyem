@extends('layouts.app') {{-- adjust to your actual layout name --}}

{{-- This page renders its own inline #chat-error-banner for validation
     errors — skip the layout's generic global banner to avoid showing the
     same error twice. --}}
@php($hideGlobalErrors = true)

@section('content')
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

            <div class="thread-list__header">
                <i class="fa-solid fa-comments"></i> Threads
            </div>

            <a href="{{ route('student.messages', ['groupId' => $groupId]) }}"
               class="thread-item {{ $activeConversation->ConversationID === $mainConversation->ConversationID ? 'thread-item--active' : '' }}">
                <i class="fa-solid fa-people-group"></i>
                <div>
                    <strong>Group Chat</strong>
                    <span>Everyone in the group</span>
                </div>
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
                @forelse($messages as $msg)
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
                <form action="{{ route('student.messages.store', ['groupId' => $groupId]) }}" method="POST" class="chat-composer card" data-chat-composer>
                    @csrf
                    <textarea name="body" placeholder="Type a message..." required>{{ old('body') }}</textarea>

                    <div class="chat-composer__exclude">
                        <button type="button" class="exclude-toggle" onclick="toggleExcludePanel()">
                            <i class="fa-solid fa-user-slash"></i> Exclude members
                        </button>
                        <div class="exclude-panel" id="exclude-panel">
                            @foreach($groupMembers as $member)
                                @if($member->UserID !== auth()->id())
                                    <label class="exclude-option">
                                        <input type="checkbox" name="exclude[]" value="{{ $member->UserID }}">
                                        {{ $member->user->UserName ?? 'Member' }}
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        <p class="exclude-hint">Checking someone here moves this message (and future ones with the same exclusion) into a separate restricted thread they can't see.</p>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Send
                    </button>
                </form>
            @else
                {{-- replying inside a restricted thread reuses the same exclusion set automatically --}}
                <form action="{{ route('student.messages.store', ['groupId' => $groupId]) }}" method="POST" class="chat-composer card" data-chat-composer>
                    @csrf
                    <input type="hidden" name="conversation_id" value="{{ $activeConversation->ConversationID }}">
                    <textarea name="body" placeholder="Reply in this restricted thread..." required>{{ old('body') }}</textarea>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Send
                    </button>
                </form>
            @endif

        </div>
    </div>
</div>

<script>
function toggleExcludePanel() {
    document.getElementById('exclude-panel').classList.toggle('open');
}

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
            const textarea = document.querySelector('[data-chat-composer] textarea[name="body"]');
            if (textarea) {
                textarea.value = `@${bubble.dataset.username}: ${textarea.value}`;
                textarea.focus();
            }
        }

        if (action === 'exclude') {
            const panel = document.getElementById('exclude-panel');
            const checkbox = panel?.querySelector(`input[value="${bubble.dataset.userId}"]`);
            if (panel && checkbox) {
                panel.classList.add('open');
                checkbox.checked = true;
                checkbox.closest('.exclude-option')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        bubble.querySelector('.chat-bubble__actions')?.classList.remove('open');
    }
});

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
                chatWindow.insertAdjacentHTML('beforeend', data.html);
                chatWindow.scrollTop = chatWindow.scrollHeight;
                textarea.value = '';
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
</script>
@endsection