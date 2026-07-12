@extends('layouts.app') {{-- adjust to your actual layout name --}}

@section('content')
<div class="chat-page">

    <div class="chat-layout">

        {{-- LEFT: thread list (main group thread + any restricted threads) --}}
        <aside class="thread-list card">
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

            <div class="chat-window card" id="chat-window">
                @forelse($messages as $msg)
                    <div class="chat-bubble {{ $msg->user_id === auth()->id() ? 'chat-bubble--own' : '' }}">
                        <div class="chat-bubble__avatar">{{ strtoupper(substr($msg->user->UserName ?? '?', 0, 1)) }}</div>
                        <div class="chat-bubble__content">
                            <div class="chat-bubble__meta">
                                <strong>{{ $msg->user->UserName ?? 'Unknown' }}</strong>
                                <span>{{ \Carbon\Carbon::parse($msg->CreatedAt)->diffForHumans() }}</span>
                            </div>
                            <p>{{ $msg->body }}</p>
                        </div>
                    </div>
                @empty
                    <div class="chat-empty">
                        <i class="fa-solid fa-comment-dots"></i>
                        <p>No messages yet. Start the conversation.</p>
                    </div>
                @endforelse
            </div>

            @if($activeConversation->Type !== 'restricted')
                {{-- only the main group thread can spawn new restricted threads --}}
                <form action="{{ route('student.messages.store', ['groupId' => $groupId]) }}" method="POST" class="chat-composer card">
                    @csrf
                    <textarea name="body" placeholder="Type a message..." required></textarea>

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
                <form action="{{ route('student.messages.store', ['groupId' => $groupId]) }}" method="POST" class="chat-composer card">
                    @csrf
                    <input type="hidden" name="conversation_id" value="{{ $activeConversation->ConversationID }}">
                    <textarea name="body" placeholder="Reply in this restricted thread..." required></textarea>
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
</script>
@endsection