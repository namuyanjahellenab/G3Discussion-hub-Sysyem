@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .thread-page-wrap { font-family: 'Inter', sans-serif; padding: 2.5rem; max-width: 800px; margin: 0 auto; }
    .back-link { color: #0d52cc; text-decoration: none; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; display: inline-block; }
    .thread-title { color: #101828; font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0; }
    .thread-meta { color: #667085; font-size: 0.85rem; margin-bottom: 1.5rem; }
    .post-card { background: #fff; border: 1px solid #e4e7ec; border-radius: 14px; padding: 18px 20px; margin-bottom: 16px; display: flex; gap: 14px; }
    .post-avatar { width: 36px; height: 36px; border-radius: 50%; background: #0d52cc; color: #fff; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .post-avatar.lecturer { background: #12b76a; }
    .post-author { font-weight: 700; color: #101828; font-size: 0.9rem; }
    .post-time { color: #98a2b3; font-size: 0.78rem; margin-bottom: 6px; }
    .post-content { color: #344054; font-size: 0.92rem; line-height: 1.5; }

    /* ---- Replies thread (bordered box + chat bubbles) ---- */
    .replies-heading { font-weight: 700; color: #101828; margin: 24px 0 12px 0; font-size: 0.95rem; }
    .thread-box { background: #f8fafc; border: 1px solid #e4e7ec; border-radius: 14px; padding: 18px; margin-bottom: 20px; }
    .msg-row { display: flex; gap: 10px; margin-bottom: 14px; }
    .msg-row:last-child { margin-bottom: 0; }
    .msg-row.own { flex-direction: row-reverse; }
    .bubble-wrap { max-width: 78%; display: flex; flex-direction: column; }
    .msg-row.own .bubble-wrap { align-items: flex-end; }
    .msg-row.other .bubble-wrap { align-items: flex-start; }
    .bubble { border-radius: 12px; padding: 12px 14px; font-size: 0.9rem; line-height: 1.5; }
    .bubble.other { background: #fff; border: 1px solid #e4e7ec; }
    .bubble.own { background: #eafaf0; border: 1px solid #b7ebc9; }
    .bubble.accepted { border: 2px solid #12b76a; background: #f6fefa; }
    .accepted-tag { color: #12b76a; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; display: flex; align-items: center; gap: 5px; margin-bottom: 6px; }
    .bubble-quote { border-left: 3px solid #0d52cc; background: rgba(13,82,204,0.06); padding: 5px 9px; border-radius: 6px; font-size: 0.78rem; color: #344054; margin-bottom: 7px; }
    .bubble-quote .qname { font-weight: 700; color: #0d52cc; margin-right: 3px; }
    .bubble-time { font-size: 0.72rem; color: #98a2b3; margin-top: 4px; padding: 0 3px; }
    .bubble-actions { display: flex; gap: 6px; margin-top: 5px; opacity: 0; transition: opacity .12s ease; }
    .msg-row:hover .bubble-actions { opacity: 1; }
    .bubble-actions button, .accept-btn { font-size: 0.72rem; font-weight: 600; border: 1px solid #e4e7ec; background: #fff; color: #475467; padding: 4px 10px; border-radius: 20px; cursor: pointer; }
    .bubble-actions .reply-btn { color: #0d52cc; border-color: #c6d4fb; }
    .bubble-actions .delete-btn { color: #d92d20; border-color: #f3c6c9; }
    .edit-box textarea { width: 100%; border: 1px solid #b7ebc9; border-radius: 10px; padding: 8px 10px; font-family: inherit; font-size: 0.88rem; resize: vertical; min-height: 50px; }
    .edit-actions { display: flex; gap: 6px; margin-top: 6px; justify-content: flex-end; }
    .edit-actions button { font-size: 0.72rem; font-weight: 700; border: none; border-radius: 20px; padding: 5px 12px; cursor: pointer; }
    .edit-actions .save { background: #0d52cc; color: #fff; }
    .edit-actions .cancel { background: #eef1f5; color: #333; }

    .quote-preview { display: flex; align-items: flex-start; justify-content: space-between; background: #eef4ff; border-left: 3px solid #0d52cc; border-radius: 8px; padding: 8px 10px; margin-bottom: 10px; font-size: 0.82rem; }
    .quote-preview .qname { font-weight: 700; color: #0d52cc; }
    .quote-preview button { background: none; border: none; color: #98a2b3; cursor: pointer; font-size: 15px; line-height: 1; }

    .reply-form textarea { width: 100%; border: 1px solid #e4e7ec; border-radius: 12px; padding: 14px; font-size: 0.9rem; resize: vertical; margin-bottom: 10px; }
    .reply-submit { background: #0d52cc; color: #fff; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 600; font-size: 0.88rem; cursor: pointer; }

    /* ---- Right rail (fills the previously empty 340px grid column) ---- */
    .right-info-panel { border-left: 1px solid #e4e7ec !important; background: #ffffff !important; padding: 2.5rem 1.75rem !important; box-sizing: border-box !important; }
    .rail-card { background: #f8fafc; border: 1px solid #e4e7ec; border-radius: 14px; padding: 16px; margin-bottom: 16px; }
    .rail-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px; color: #667085; text-transform: uppercase; margin-bottom: 10px; }
    .profile-row { display: flex; align-items: center; gap: 10px; }
    .profile-row .name { font-weight: 700; color: #101828; font-size: 0.9rem; }
    .profile-row .sub { font-size: 0.75rem; color: #667085; }
    .rail-btn { display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; padding: 10px; margin-bottom: 9px; border-radius: 10px; border: none; font-weight: 600; font-size: 0.83rem; cursor: pointer; }
    .rail-btn.primary { background: #0d52cc; color: #fff; }
    .rail-row { display: flex; gap: 8px; }
    .rail-row .rail-btn { background: #fff; color: #344054; border: 1px solid #e4e7ec; font-size: 0.78rem; padding: 8px 6px; margin-bottom: 0; }

    .dashboard-grid-container { display: grid !important; grid-template-columns: 260px 1fr 340px !important; min-height: 100vh !important; width: 100% !important; background-color: #fcfcfd !important; font-family: 'Inter', sans-serif !important; }
    .sidebar-panel { background: #ffffff !important; border-right: 1px solid #e4e7ec !important; padding-top: 24px !important; }
    .sidebar-brand { padding: 0 24px 24px 24px !important; display: flex !important; align-items: center !important; gap: 12px !important; border-bottom: 1px solid #f2f4f7 !important; color: #0d52cc !important; font-weight: 700 !important; font-size: 1.2rem !important; letter-spacing: -0.5px !important; }
    .sidebar-menu { list-style: none !important; padding: 20px 0 !important; margin: 0 !important; }
    .sidebar-menu li a { padding: 12px 24px !important; font-size: 0.95rem !important; display: flex !important; align-items: center !important; gap: 12px !important; color: #667085 !important; text-decoration: none !important; font-weight: 500 !important; }
    .sidebar-menu li.active a { color: #0d52cc !important; background: #eef4ff !important; border-radius: 0 24px 24px 0 !important; margin-right: 12px !important; font-weight: 600 !important; }
    .content-workspace { padding: 3rem 2.5rem !important; background: #fcfcfd !important; }
</style>

<div class="dashboard-grid-container" id="clean-dashboard-root">
    @include('layouts.sidebar')

    <div class="content-workspace">
        <div class="thread-page-wrap">
            <a href="{{ route('groups.topics', $topic->group) }}" class="back-link">&larr; Back to Forum</a>
            <h1 class="thread-title">{{ $topic->Title }}</h1>

            @if($mainPost)
                <div class="thread-meta">
                    Posted by {{ $mainPost->author?->UserName ?? $mainPost->author?->name ?? 'a member' }}
                    &bull; {{ $mainPost->CreatedAt->diffForHumans() }}
                </div>

                <div class="post-card">
                    <div class="post-avatar">{{ Str::substr($mainPost->author?->UserName ?? $mainPost->author?->name ?? '?', 0, 1) }}</div>
                    <div style="flex:1;">
                        <div class="post-author">{{ $mainPost->author?->UserName ?? $mainPost->author?->name ?? 'a member' }}</div>
                        <div class="post-content">{{ $mainPost->Content }}</div>
                    </div>
                </div>

                <div class="replies-heading">Replies</div>

                <div class="thread-box">
                    @forelse($mainPost->replies as $reply)
                        @php $isOwn = $reply->UserID === auth()->id(); @endphp

                        <div class="msg-row {{ $isOwn ? 'own' : 'other' }}">
                            <div class="bubble-wrap">

                                {{-- inline edit form, hidden by default --}}
                                @if ($isOwn)
                                    <form class="edit-box" id="edit-form-{{ $reply->ReplyID }}" style="display:none; width:100%;"
                                          action="{{ route('replies.update', $reply) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <textarea name="ReplyContent">{{ $reply->ReplyContent }}</textarea>
                                        <div class="edit-actions">
                                            <button type="submit" class="save">Save</button>
                                            <button type="button" class="cancel" onclick="toggleEdit({{ $reply->ReplyID }})">Cancel</button>
                                        </div>
                                    </form>
                                @endif

                                <div class="post-author" style="font-size:0.82rem; margin-bottom:3px;">
                                    {{ $reply->author?->UserName ?? $reply->author?->name ?? 'a member' }}
                                    @if($reply->author?->Role === 'Lecturer')
                                        <span style="color:#12b76a; font-size:0.7rem; font-weight:700;">· Lecturer</span>
                                    @endif
                                </div>

                                <div class="bubble {{ $isOwn ? 'own' : 'other' }} {{ $reply->IsAccepted ? 'accepted' : '' }}"
                                     id="bubble-{{ $reply->ReplyID }}">
                                    @if ($reply->IsAccepted)
                                        <div class="accepted-tag"><i class="fa-solid fa-circle-check"></i> Accepted Answer</div>
                                    @endif
                                    @if ($reply->quotedReply)
                                        <div class="bubble-quote">
                                            <span class="qname">{{ $reply->quotedReply->author?->UserName ?? $reply->quotedReply->author?->name }}:</span>
                                            {{ Str::limit($reply->quotedReply->ReplyContent, 90) }}
                                        </div>
                                    @endif
                                    {{ $reply->ReplyContent }}
                                </div>

                                <div class="bubble-time">{{ $reply->CreatedAt->diffForHumans() }}</div>

                                <div class="bubble-actions">
                                    <button type="button" class="reply-btn"
                                            onclick="quoteReply({{ $reply->ReplyID }}, '{{ addslashes($reply->author?->UserName ?? $reply->author?->name ?? 'a member') }}', '{{ addslashes(Str::limit($reply->ReplyContent, 80)) }}')">
                                        Reply
                                    </button>

                                    @if ($isOwn)
                                        <button type="button" onclick="toggleEdit({{ $reply->ReplyID }})">Edit</button>
                                        <form action="{{ route('replies.destroy', $reply) }}" method="POST"
                                              onsubmit="return confirm('Delete this message?');" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                    @endif

                                    @if(!$reply->IsAccepted && auth()->user()->Role === 'Lecturer')
                                        <form method="POST" action="{{ route('replies.accept', $reply) }}" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="accept-btn">Mark as Accepted</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p style="color:#667085; font-size:0.88rem;">No replies yet. Be the first to respond.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('posts.reply', $mainPost->PostID) }}" class="reply-form">
                    @csrf
                    <input type="hidden" name="QuotedReplyID" id="quotedReplyId" value="">
                    <div id="quotePreview"></div>
                    <textarea name="ReplyContent" id="composerInput" rows="3" placeholder="Write your reply here..." required></textarea>
                    <button type="submit" class="reply-submit">Post Reply <i class="fa-solid fa-paper-plane"></i></button>
                </form>
            @else
                <p style="color:#667085;">No discussion started in this topic yet.</p>
            @endif
        </div>
    </div>

    <div class="right-info-panel">
        <div class="rail-card">
            <div class="rail-label">Student Profile</div>
            <div class="profile-row">
                <div class="post-avatar" style="width:34px;height:34px;">
                    {{ Str::substr(auth()->user()->UserName ?? auth()->user()->name ?? '?', 0, 1) }}
                </div>
                <div>
                    <div class="name">{{ auth()->user()->UserName ?? auth()->user()->name }}</div>
                    <div class="sub">{{ auth()->user()->Role ?? 'Student' }} Account</div>
                </div>
            </div>
        </div>

        <div class="rail-card">
            <div class="rail-label">Workspace Actions</div>
            <a class="rail-btn primary" href="{{ route('topics.export', $topic) }}" target="_blank">
                <i class="fa-solid fa-file-pdf"></i> Export to PDF
            </a>
            <button class="rail-btn primary" id="shareBtn" data-url="{{ url()->current() }}"><i class="fa-solid fa-share-nodes"></i> Share via</button>
            <div class="rail-row">
                <button class="rail-btn" id="waBtn" data-topic="{{ $topic->Title }}" data-url="{{ url()->current() }}"><i class="fa-brands fa-whatsapp"></i> WhatsApp</button>
                <button class="rail-btn" id="emailBtn" data-topic="{{ $topic->Title }}" data-url="{{ url()->current() }}"><i class="fa-solid fa-envelope"></i> Email</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var rootGrid = document.getElementById('clean-dashboard-root');
        if (rootGrid) {
            var parentContainer = rootGrid.parentElement;
            Array.from(parentContainer.children).forEach(function (element) {
                if (element !== rootGrid && element.tagName !== 'STYLE' && element.tagName !== 'SCRIPT') {
                    element.style.setProperty('display', 'none', 'important');
                }
            });
            Array.from(document.body.children).forEach(function (element) {
                if (!element.contains(rootGrid) && element !== rootGrid && element.tagName !== 'STYLE' && element.tagName !== 'SCRIPT') {
                    element.style.setProperty('display', 'none', 'important');
                }
            });
        }
    });

    // Fills the hidden QuotedReplyID field and shows a preview above the composer
    function quoteReply(replyId, authorName, snippet) {
        document.getElementById('quotedReplyId').value = replyId;
        var preview = document.getElementById('quotePreview');
        preview.innerHTML = '';

        var box = document.createElement('div');
        box.className = 'quote-preview';

        var left = document.createElement('div');
        var name = document.createElement('span');
        name.className = 'qname';
        name.textContent = authorName + ': ';
        left.appendChild(name);
        left.appendChild(document.createTextNode(snippet));
        box.appendChild(left);

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.textContent = '✕';
        closeBtn.onclick = function () {
            document.getElementById('quotedReplyId').value = '';
            preview.innerHTML = '';
        };
        box.appendChild(closeBtn);

        preview.appendChild(box);
        document.getElementById('composerInput').focus();
    }

    // Toggles the inline edit form for a given reply
    function toggleEdit(replyId) {
        var bubble = document.getElementById('bubble-' + replyId);
        var form = document.getElementById('edit-form-' + replyId);
        var showingEdit = form.style.display !== 'none';
        form.style.display = showingEdit ? 'none' : 'block';
        bubble.style.display = showingEdit ? 'block' : 'none';
    }

    // Workspace actions
    document.addEventListener('DOMContentLoaded', function () {
        var shareBtn = document.getElementById('shareBtn');
        if (shareBtn) shareBtn.addEventListener('click', function () {
            var url = shareBtn.dataset.url;
            if (navigator.share) {
                navigator.share({ title: document.title, url: url });
            } else {
                navigator.clipboard?.writeText(url);
                alert('Link copied to clipboard');
            }
        });

        var waBtn = document.getElementById('waBtn');
        if (waBtn) waBtn.addEventListener('click', function () {
            var text = encodeURIComponent('Check out this discussion: "' + waBtn.dataset.topic + '"');
            window.open('https://wa.me/?text=' + text + '%20' + encodeURIComponent(waBtn.dataset.url), '_blank');
        });

        var emailBtn = document.getElementById('emailBtn');
        if (emailBtn) emailBtn.addEventListener('click', function () {
            var subject = encodeURIComponent('Discussion Hub — ' + emailBtn.dataset.topic);
            var body = encodeURIComponent('Take a look at this discussion thread:\n\n' + emailBtn.dataset.url);
            window.location.href = 'mailto:?subject=' + subject + '&body=' + body;
        });
    });
</script>
@endsection
