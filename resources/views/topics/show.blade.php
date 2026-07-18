@extends('layouts.app')

@section('content')
@php
    $displayName = auth()->user()->UserName ?? auth()->user()->name ?? 'Student User';
    $initials = Str::initials($displayName);

    $statusMap = [
        'answered' => ['label' => 'Answered', 'badge' => 'bg-success-subtle'],
        'discussion' => ['label' => 'Discussion', 'badge' => 'bg-warning'],
        'open' => ['label' => 'Open', 'badge' => 'bg-secondary-subtle'],
    ];
    $statusMeta = $statusMap[$topic->Status] ?? $statusMap['open'];
@endphp

<style>
    .content-workspace { padding: 28px; max-width: 1200px; }
    .breadcrumb-row { margin-bottom: 16px; }
    .back-link { display: inline-flex; align-items: center; gap: 7px; color: var(--text-muted); text-decoration: none; font-size: 0.82rem; font-weight: 600; padding: 7px 14px; border-radius: 999px; border: 1px solid var(--surface-border); background: var(--surface-card); box-shadow: var(--shadow-soft); transition: color 0.15s ease, border-color 0.15s ease, background-color 0.15s ease; }
    .back-link i { font-size: 0.72rem; transition: transform 0.15s ease; }
    /* Scoped to real pointer devices only - a :hover rule that changes an
       element's appearance makes touch browsers (notably iOS Safari) consume
       the first tap just to simulate hover, requiring a second tap to
       actually navigate. (hover: hover) excludes touchscreens from this rule
       entirely, so a single tap always navigates immediately. */
    @media (hover: hover) {
        .back-link:hover { color: var(--luna-mid); border-color: var(--luna-mid); background: var(--luna-lightest); }
        .back-link:hover i { transform: translateX(-2px); }
    }

    .forum-layout { display: flex; gap: 20px; align-items: flex-start; }
    .forum-main { flex: 1; min-width: 0; max-width: 760px; }

    .thread-title { color: var(--text-heading); font-size: 1.5rem; font-weight: 800; margin: 0; }
    .thread-meta { color: var(--text-muted); font-size: 0.85rem; margin: 10px 0 20px 0; }

    .post-card { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 16px; display: flex; gap: 14px; box-shadow: var(--shadow-soft); }
    .post-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--luna-mid); color: #fff; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .post-avatar.lecturer { background: var(--accent-success); }
    .post-author { font-weight: 700; color: var(--text-heading); font-size: 0.9rem; }
    .post-time { color: var(--text-muted); font-size: 0.78rem; margin-bottom: 6px; }
    .post-content { color: var(--text-body); font-size: 0.92rem; line-height: 1.5; white-space: pre-wrap; }

    .accepted-card { border: 2px solid var(--accent-success); background: var(--accent-success-bg); }
    .accepted-tag { color: var(--accent-success); font-weight: 700; font-size: 0.78rem; text-transform: uppercase; display: flex; align-items: center; gap: 6px; margin-bottom: 10px; }
    .accept-btn { background: none; border: 1px solid var(--surface-border); color: var(--text-muted); border-radius: var(--radius-md); padding: 5px 10px; font-size: 0.75rem; margin-top: 8px; cursor: pointer; }
    .accept-btn:hover { border-color: var(--luna-mid); color: var(--luna-mid); }

    .replies-heading { font-weight: 700; color: var(--text-heading); margin: 24px 0 12px 0; font-size: 15px; }
    .empty-state { color: var(--text-muted); font-size: 0.88rem; }

    .tag-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 600; background: #fff; border: 1px solid var(--surface-border); padding: 2px 9px; border-radius: 20px; color: var(--text-muted); }
    .tag-chip.flagged { color: var(--accent-danger); border-color: #EBAFAF; }

    /* Wallpaper panel behind the replies — dots/small-squares only, no
       curved-line pattern (tried earlier and rejected). */
    .chat-panel {
        background-color: var(--surface-bg);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160' viewBox='0 0 160 160'%3E%3Cg fill='%2326658C' fill-opacity='0.07'%3E%3Ccircle cx='18' cy='22' r='3'/%3E%3Ccircle cx='96' cy='14' r='2'/%3E%3Ccircle cx='134' cy='70' r='2.6'/%3E%3Ccircle cx='30' cy='120' r='2.2'/%3E%3Crect x='60' y='96' width='9' height='9' rx='2.5'/%3E%3Crect x='118' y='128' width='7' height='7' rx='2'/%3E%3Ccircle cx='108' cy='48' r='2'/%3E%3Ccircle cx='6' cy='70' r='2'/%3E%3Crect x='140' y='96' width='6' height='6' rx='1.5'/%3E%3Ccircle cx='70' cy='150' r='2'/%3E%3C/g%3E%3C/svg%3E");
        background-size: 160px 160px;
        border: 1px solid var(--surface-border); border-radius: var(--radius-lg);
        padding: 14px 12px;
    }

    .reply-row { display: flex; gap: 12px; padding: 10px 8px; border-radius: var(--radius-md); position: relative; }
    .reply-row:hover { background: rgba(255, 255, 255, 0.55); }
    .reply-row:hover .hover-actions { opacity: 1; transform: translateY(0); }
    .reply-row + .reply-row { margin-top: 4px; }

    .reply-top { display: flex; align-items: baseline; gap: 8px; margin-bottom: 3px; }
    .reply-name { font-weight: 700; font-size: 13.5px; color: var(--text-heading); }
    .reply-time { font-size: 11.5px; color: var(--text-muted); }

    .reply-bubble { background: #fff; border: 1px solid var(--surface-border); border-radius: 4px 14px 14px 14px; padding: 10px 14px; font-size: 13.5px; line-height: 1.5; display: inline-block; max-width: 600px; color: var(--text-body); }
    .reply-answer-flag { display: flex; align-items: center; gap: 5px; color: var(--accent-success); font-weight: 700; font-size: 11.5px; margin-bottom: 5px; }

    .quote-strip { border-left: 3px solid var(--luna-mid); background: var(--luna-lightest); border-radius: 6px; padding: 5px 10px; font-size: 11.5px; color: var(--luna-dark); margin-bottom: 6px; display: inline-block; }

    /* WhatsApp/Slack-style "unread since your last visit" divider - shown
       once above the first reply newer than the frozen read marker for
       this visit (see the poll() script below and lastReadReplyId). */
    .unread-divider { display: flex; align-items: center; gap: 10px; padding: 10px 4px; }
    .unread-divider::before, .unread-divider::after { content: ''; flex: 1; height: 1px; background: var(--accent-success); }
    .unread-divider span { color: var(--accent-success); font-size: 10.5px; font-weight: 700; letter-spacing: 0.5px; white-space: nowrap; }

    /* WhatsApp/Telegram-style split: your own replies float right in the
       theme accent color, everyone else's stay left in a plain card. Kept
       as a distinct block (not folded into .reply-bubble above) so the
       .answer/.flagged status colors below can still override it - status
       always wins over own/other, since those rules are declared after
       this block and match at the same specificity (3 classes each). */
    .reply-content { flex: 1; min-width: 0; }
    .reply-row.own { flex-direction: row-reverse; }
    .reply-row.own .reply-content { text-align: right; }
    .reply-row.own .reply-top { flex-direction: row-reverse; }
    .reply-row.own .quote-strip { text-align: left; }
    .reply-row.own .reply-bubble { background: var(--luna-mid); color: #fff; border: none; border-radius: 14px 4px 14px 14px; text-align: left; }
    .reply-row.own .reply-bubble .attach-file { background: rgba(255,255,255,0.16); }
    .reply-row.own .reply-bubble .attach-file .icon { background: rgba(255,255,255,0.3); }
    .reply-row.own .reply-bubble .attach-file .fname { color: #fff; }
    .reply-row.own .hover-actions { right: auto; left: 4px; }

    .reply-row.answer .reply-bubble { border-color: var(--accent-success); background: var(--accent-success-bg); color: var(--text-body); }
    .reply-row.flagged .reply-bubble { background: var(--accent-danger-bg); border: 1px dashed #EBAFAF; color: var(--text-body); }

    .hover-actions { position: absolute; top: 4px; right: 4px; display: flex; gap: 3px; background: #fff; border: 1px solid var(--surface-border); border-radius: 9px; padding: 3px; box-shadow: var(--shadow-medium); opacity: 0; transform: translateY(-4px); transition: 0.15s; }
    .hover-actions button { width: 26px; height: 26px; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 12.5px; color: var(--text-muted); }
    .hover-actions button:hover { background: var(--surface-bg); color: var(--text-heading); }
    .hover-actions button.danger:hover { background: var(--accent-danger-bg); color: var(--accent-danger); }
    .hover-actions button.mark:hover { background: var(--accent-success-bg); color: var(--accent-success); }
    .hover-actions .sep { width: 1px; background: var(--surface-border); margin: 3px 1px; }

    /* Touch devices have no :hover, so opacity:0-until-hover would make
       these actions permanently invisible/undiscoverable on mobile — show
       them at reduced opacity by default instead of relying on hover. */
    @media (hover: none) {
        .hover-actions { opacity: 0.85; transform: none; position: static; margin-top: 6px; box-shadow: none; border: none; background: transparent; justify-content: flex-end; }
        .hover-actions button { background: var(--surface-bg); }
    }

    .attach-file { display: flex; align-items: center; gap: 10px; background: var(--surface-bg); border: 1px solid var(--surface-border); border-radius: 10px; padding: 8px 12px; margin-top: 8px; max-width: 280px; text-decoration: none; }
    .attach-file .icon { width: 32px; height: 32px; border-radius: 8px; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
    .attach-file .fname { font-size: 12.5px; font-weight: 600; color: var(--text-heading); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .attach-img { margin-top: 8px; border-radius: 12px; overflow: hidden; border: 1px solid var(--surface-border); max-width: 260px; }
    .attach-img img { width: 100%; display: block; }

    .reply-context-bar { display: none; align-items: center; justify-content: space-between; background: var(--luna-lightest); border: 1px solid var(--luna-light); border-radius: var(--radius-md); padding: 8px 12px; font-size: 12.5px; color: var(--luna-dark); margin-bottom: 10px; }
    .reply-context-bar.open { display: flex; }
    .reply-context-bar button { background: none; border: none; color: var(--luna-dark); cursor: pointer; font-size: 14px; }

    .composer-attach-row { display: flex; gap: 8px; margin: 8px 0; }
    .composer-attach-row .btn-outline-secondary { font-size: 12px; padding: 0.4rem 0.75rem; }
    .composer-attach-filename { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 8px; }

    .reply-form textarea { width: 100%; border: 1px solid var(--surface-border); border-radius: var(--radius-md); padding: 14px; font-size: 0.9rem; resize: vertical; margin-bottom: 10px; font-family: var(--font-body); }

    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 0.55rem 1rem; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid transparent; box-shadow: var(--shadow-soft); cursor: pointer; }
    .btn-primary { background: var(--luna-mid); color: #fff; }
    .btn-primary:hover { background: var(--luna-dark); color: #fff; }

    .right-info-panel { display: flex; flex-direction: column; gap: 20px; max-width: 300px; }
    .panel { background: var(--surface-card); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft); }
    .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--surface-border); }
    .panel-header h2 { font-size: 15px; font-weight: 700; margin: 0; color: var(--text-heading); }

    .profile-mini { display: flex; align-items: center; gap: 10px; padding: 16px 20px; }
    .profile-mini .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--luna-mid); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
    .profile-mini .name { font-weight: 700; color: var(--text-heading); font-size: 13.5px; }
    .profile-mini .role { color: var(--text-muted); font-size: 11.5px; }

    .topic-info-body { padding: 4px 20px 16px; }
    .topic-info-body .row { display: flex; justify-content: space-between; font-size: 13px; color: var(--text-body); padding: 6px 0; }

    .participant-avatars { display: flex; padding: 4px 20px 16px; }
    .participant-avatars span { width: 28px; height: 28px; border-radius: 50%; background: var(--luna-mid); color: #fff; font-size: 0.68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; margin-left: -8px; border: 2px solid var(--surface-card); }
    .participant-avatars span:first-child { margin-left: 0; }

    .rec-item { padding: 10px 20px; border-bottom: 1px solid var(--surface-border); font-size: 0.85rem; }
    .rec-item:last-child { border-bottom: none; }
    .rec-item a { color: var(--text-heading); font-weight: 600; text-decoration: none; }
    .rec-item a:hover { color: var(--luna-mid); }
    .rec-item .rec-meta { color: var(--text-muted); font-size: 0.75rem; margin-top: 2px; }

    .announcement-banner { background: var(--luna-mid); color: #fff; border-radius: var(--radius-lg); padding: 18px 20px; }
    .announcement-banner .tag { display: flex; align-items: center; gap: 8px; text-transform: uppercase; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9; margin-bottom: 8px; }
    .announcement-banner .body { font-size: 0.85rem; font-weight: 500; line-height: 1.4; }

    @media (max-width: 1024px) {
        .forum-layout { flex-direction: column; }
        .forum-main { max-width: 100%; }
        .right-info-panel { max-width: 100%; }
    }
</style>

<div class="content-workspace">
    <div class="breadcrumb-row">
        <a href="{{ route('groups.topics', $topic->group) }}" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="forum-layout">
        <div class="forum-main">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h1 class="thread-title">{{ $topic->Title }}</h1>
                    <span class="badge {{ $statusMeta['badge'] }}">{{ $statusMeta['label'] }}</span>
                    @if($topic->exclusions->isNotEmpty())
                        <span class="tag-chip"><i class="fa-solid fa-eye-slash"></i> Restricted audience</span>
                    @endif
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a href="{{ route('topics.export', $topic) }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-file-pdf"></i> Export to PDF
                    </a>
                    @if($shareLinks)
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-share-nodes"></i> Share
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ $shareLinks['Links']['whatsapp'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a></li>
                                <li><a class="dropdown-item" href="{{ $shareLinks['Links']['twitter'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i> X / Twitter</a></li>
                                <li><a class="dropdown-item" href="{{ $shareLinks['Links']['facebook'] }}" target="_blank" rel="noopener"><i class="fa-brands fa-facebook"></i> Facebook</a></li>
                                <li><a class="dropdown-item" href="{{ $shareLinks['Links']['email'] }}"><i class="fa-solid fa-envelope"></i> Email</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><button type="button" class="dropdown-item" onclick="copyTopicShareLink()"><i class="fa-solid fa-link"></i> Copy Link</button></li>
                            </ul>
                        </div>
                    @else
                        {{-- Both the ML gateway and the PHP fallback failed to build share
                             links (see DiscussionHubPageController::showTopic()) — show why
                             instead of the button just vanishing. --}}
                        <button type="button" class="btn btn-outline-secondary" disabled
                                title="Sharing is temporarily unavailable" data-bs-toggle="tooltip">
                            <i class="fa-solid fa-share-nodes"></i> Share
                        </button>
                    @endif
                </div>
            </div>

            @if($mainPost)
                <div class="thread-meta">
                    Posted by {{ $mainPost->author?->UserName ?? $mainPost->author?->name ?? 'a member' }}
                    &bull; {{ $mainPost->CreatedAt->diffForHumans() }}
                </div>

                <div class="post-card">
                    <div class="post-avatar">{{ Str::initials($mainPost->author?->UserName ?? $mainPost->author?->name ?? '?') }}</div>
                    <div style="flex:1;">
                        <div class="post-author">{{ $mainPost->author?->UserName ?? $mainPost->author?->name ?? 'a member' }}</div>
                        <div class="post-content">{{ $mainPost->Content }}</div>
                    </div>
                </div>

                <h6 class="replies-heading">Replies</h6>

                <div class="chat-panel">
                    @php($unreadDividerShown = false)
                    @forelse($mainPost->replies as $reply)
                        @php($isOwnReply = $reply->UserID === auth()->id())
                        {{-- Receiver-only, WhatsApp-style: never shown for the viewer's own replies. --}}
                        @if(!$unreadDividerShown && !$isOwnReply && $lastReadReplyId > 0 && $reply->ReplyID > $lastReadReplyId)
                            @php($unreadDividerShown = true)
                            <div class="unread-divider"><span>NEW MESSAGES</span></div>
                        @endif
                        @php($canFlag = !$isOwnReply)
                        @php($canDelete = $isOwnReply || in_array(auth()->user()->Role, ['Lecturer', 'Administrator'], true))
                        @php($canAccept = !$reply->IsAccepted && (auth()->id() === $mainPost->UserID || auth()->user()->Role === 'Lecturer'))
                        @php($replySignature = $reply->ReplyContent . '|' . ($reply->IsAccepted ? 'true' : 'false') . '|' . ($reply->IsFlagged ? 'true' : 'false') . '|' . ($canFlag ? 'true' : 'false') . '|' . ($canAccept ? 'true' : 'false') . '|' . ($canDelete ? 'true' : 'false'))
                        <div class="reply-row {{ $isOwnReply ? 'own' : 'other' }} {{ $reply->IsAccepted ? 'answer' : '' }} {{ $reply->IsFlagged ? 'flagged' : '' }}" data-reply-id="{{ $reply->ReplyID }}" data-reply-author="{{ $reply->author?->UserName ?? $reply->author?->name ?? 'a member' }}" data-signature="{{ $replySignature }}">
                            <div class="post-avatar {{ $reply->author?->Role === 'Lecturer' ? 'lecturer' : '' }}">
                                {{ Str::initials($reply->author?->UserName ?? $reply->author?->name ?? '?') }}
                            </div>
                            <div class="reply-content">
                                <div class="reply-top">
                                    <span class="reply-name">{{ $isOwnReply ? 'You' : ($reply->author?->UserName ?? $reply->author?->name ?? 'a member') }}</span>
                                    <span class="reply-time">{{ $reply->CreatedAt->diffForHumans() }}</span>
                                </div>

                                @if($reply->parentReply)
                                    <div class="quote-strip">
                                        <i class="fa-solid fa-reply"></i> Replying to <strong>{{ $reply->parentReply->author?->UserName ?? $reply->parentReply->author?->name ?? 'a member' }}</strong>: {{ Str::limit($reply->parentReply->ReplyContent, 40) }}
                                    </div>
                                @endif

                                <div class="reply-bubble">
                                    @if($reply->IsAccepted)
                                        <div class="reply-answer-flag"><i class="fa-solid fa-circle-check"></i> Marked as answer</div>
                                    @endif
                                    @if($reply->IsFlagged)
                                        <span class="tag-chip flagged" style="margin-bottom: 6px;"><i class="fa-solid fa-flag"></i> Flagged</span>
                                    @endif
                                    {{ $reply->ReplyContent }}

                                    @if($reply->Attachment)
                                        @if($reply->AttachmentType === 'image')
                                            <div class="attach-img"><img src="{{ \Illuminate\Support\Facades\Storage::url($reply->Attachment) }}" alt="Attachment"></div>
                                        @else
                                            <a class="attach-file" href="{{ \Illuminate\Support\Facades\Storage::url($reply->Attachment) }}" target="_blank" rel="noopener">
                                                <div class="icon"><i class="fa-solid fa-file"></i></div>
                                                <div class="fname">{{ basename($reply->Attachment) }}</div>
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            <div class="hover-actions">
                                <button type="button" title="Reply" onclick="startThreadedReply({{ $reply->ReplyID }}, '{{ addslashes($reply->author?->UserName ?? $reply->author?->name ?? 'a member') }}')">
                                    <i class="fa-solid fa-reply"></i>
                                </button>
                                @if($canFlag)
                                    <button type="button" title="Report" onclick="ajaxReplyAction({{ $reply->ReplyID }}, 'flag')"><i class="fa-solid fa-flag"></i></button>
                                @endif
                                @if($canDelete)
                                    <div class="sep"></div>
                                    <button type="button" class="danger" title="Delete" onclick="ajaxReplyAction({{ $reply->ReplyID }}, 'delete')"><i class="fa-solid fa-trash"></i></button>
                                @endif
                                @if($canAccept)
                                    <div class="sep"></div>
                                    <button type="button" class="mark" title="Mark as answer" onclick="ajaxReplyAction({{ $reply->ReplyID }}, 'accept')"><i class="fa-solid fa-check"></i></button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="empty-state" style="padding: 8px;">No replies yet. Be the first to respond.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('posts.reply', $mainPost->PostID) }}" class="reply-form" style="margin-top: 20px;" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="parent_reply_id" id="parentReplyId" value="">

                    <div class="reply-context-bar" id="replyContextBar">
                        <span>Replying to <strong id="replyContextName"></strong></span>
                        <button type="button" onclick="cancelThreadedReply()">&times;</button>
                    </div>

                    <textarea name="ReplyContent" rows="3" placeholder="Write your reply here..." required></textarea>

                    <input type="file" name="attachment" id="replyAttachment" style="display:none;">
                    <div class="composer-attach-row">
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('replyAttachment').removeAttribute('accept'); document.getElementById('replyAttachment').click()">
                            <i class="fa-solid fa-paperclip"></i> Attach file
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('replyAttachment').setAttribute('accept','image/*'); document.getElementById('replyAttachment').click()">
                            <i class="fa-solid fa-image"></i> Attach image
                        </button>
                    </div>
                    <div class="composer-attach-filename" id="replyAttachFilename"></div>

                    <button type="submit" class="btn btn-primary">Post Reply <i class="fa-solid fa-paper-plane"></i></button>
                </form>
            @else
                <p class="empty-state" style="margin-top: 16px;">No discussion started in this topic yet.</p>
            @endif
        </div>

        <div class="right-info-panel">
            <section class="panel">
                <div class="profile-mini">
                    <div class="avatar">{{ strtoupper($initials ?: 'S') }}</div>
                    <div>
                        <div class="name">{{ $displayName }}</div>
                        <div class="role">{{ auth()->user()->Role ?? 'Student' }} Account</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2>Topic Info</h2></div>
                <div class="topic-info-body">
                    <div class="row"><span>Group</span><strong>{{ $topic->group?->GroupName ?? '—' }}</strong></div>
                    <div class="row"><span>Status</span><strong>{{ $statusMeta['label'] }}</strong></div>
                    <div class="row"><span>Last activity</span><strong>{{ $lastActivity?->diffForHumans() ?? '—' }}</strong></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2>Participants ({{ $participants->count() }})</h2></div>
                <div class="participant-avatars">
                    @foreach($participants->take(8) as $participant)
                        <span title="{{ $participant->UserName ?? $participant->name }}">{{ Str::initials($participant->UserName ?? $participant->name ?? '?') }}</span>
                    @endforeach
                    @if($participants->isEmpty())
                        <span class="empty-state" style="padding: 0;">No participants yet.</span>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><h2>Related Topics</h2></div>
                <div>
                    @forelse($recommended as $rec)
                        <div class="rec-item">
                            <a href="{{ route('topics.show', $rec) }}">{{ $rec->Title }}</a>
                            <div class="rec-meta">{{ $rec->posts_count }} {{ Str::plural('reply', $rec->posts_count) }} &bull; {{ $rec->CreatedAt->diffForHumans() }}</div>
                        </div>
                    @empty
                        <div class="empty-state" style="padding: 16px 20px;">No other topics in this group yet.</div>
                    @endforelse
                </div>
            </section>

            <div class="announcement-banner">
                <div class="tag"><i class="fa-solid fa-bullhorn"></i> Tip</div>
                <div class="body">Lecturers can mark the most helpful reply as the accepted answer to close out a discussion.</div>
            </div>
        </div>
    </div>
</div>

@if($shareLinks)
<script>
function copyTopicShareLink() {
    navigator.clipboard.writeText(@json($shareLinks['ShareUrl'])).then(() => {
        alert('Link copied to clipboard!');
    });
}
</script>
@endif

<script>
function startThreadedReply(replyId, authorName) {
    document.getElementById('parentReplyId').value = replyId;
    document.getElementById('replyContextName').textContent = authorName;
    document.getElementById('replyContextBar').classList.add('open');
    document.querySelector('.reply-form textarea[name="ReplyContent"]')?.focus();
}

function cancelThreadedReply() {
    document.getElementById('parentReplyId').value = '';
    document.getElementById('replyContextBar').classList.remove('open');
}

document.getElementById('replyAttachment')?.addEventListener('change', function () {
    document.getElementById('replyAttachFilename').textContent = this.files[0] ? this.files[0].name : '';
});
</script>

@if($mainPost)
<script>
// Polls the same JSON endpoint the desktop app uses (/api/topics/{id}) every
// 2 seconds and diffs the result against what's already in the DOM instead of
// reloading the page - an untouched reply is never removed or rebuilt, so
// there's nothing to visibly blink whether you're typing or just reading
// (mirrors the desktop client's TopicController).
//
// Sending/flagging/accepting/deleting your OWN reply used to be a plain
// <form> POST, which still did a full page reload even after the polling
// above stopped OTHER people's replies from causing one - that leftover
// full-page round trip was the remaining "it still blinks" cause. All four
// actions now go through fetch() + this same diff-render path instead,
// matching how the desktop client's onPostReply/flagReply/acceptAnswer/
// deleteReply already never trigger a full screen rebuild either.
(function () {
    const topicId = {{ $topic->TopicID }};
    const chatPanel = document.querySelector('.chat-panel');
    // Frozen server-side at page load (see DiscussionHubPageController::
    // showTopic()) - mirrors the desktop client's unreadThresholdReplyId.
    // If Blade already rendered the divider on initial paint, this starts
    // true so a poll cycle never inserts a duplicate one.
    const unreadThresholdReplyId = {{ (int) $lastReadReplyId }};
    let unreadBannerInserted = document.querySelector('.unread-divider') !== null;

    function buildUnreadBanner() {
        const div = document.createElement('div');
        div.className = 'unread-divider';
        div.innerHTML = '<span>NEW MESSAGES</span>';
        return div;
    }
    const replyTextarea = document.querySelector('.reply-form textarea[name="ReplyContent"]');
    const replyForm = document.querySelector('.reply-form');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!chatPanel) return;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function initials(name) {
        const parts = (name || '').trim().split(/\s+/).filter(Boolean);
        if (parts.length === 0) return '?';
        let s = parts[0].charAt(0).toUpperCase();
        if (parts.length > 1) s += parts[parts.length - 1].charAt(0).toUpperCase();
        return s;
    }

    // "created_at" is deliberately excluded - it changes every cycle just
    // from time passing, which would defeat the point of diffing (every row
    // would look "changed" every 2 seconds). It gets its own always-updated
    // path in applyUpdate() below instead, so the relative time still ticks
    // without touching anything else about an otherwise untouched row.
    function replySignature(r) {
        return [r.content, r.is_accepted, r.is_flagged, r.can_flag, r.can_accept, r.can_delete].join('|');
    }

    function actionsHtmlFor(r) {
        let html = `<button type="button" title="Reply" onclick="startThreadedReply(${r.id}, '${escapeHtml(r.author_name).replace(/'/g, "\\'")}')"><i class="fa-solid fa-reply"></i></button>`;
        if (r.can_flag) {
            html += `<button type="button" title="Report" onclick="ajaxReplyAction(${r.id}, 'flag')"><i class="fa-solid fa-flag"></i></button>`;
        }
        if (r.can_delete) {
            html += `<div class="sep"></div><button type="button" class="danger" title="Delete" onclick="ajaxReplyAction(${r.id}, 'delete')"><i class="fa-solid fa-trash"></i></button>`;
        }
        if (r.can_accept) {
            html += `<div class="sep"></div><button type="button" class="mark" title="Mark as answer" onclick="ajaxReplyAction(${r.id}, 'accept')"><i class="fa-solid fa-check"></i></button>`;
        }
        return html;
    }

    function buildReplyRow(r) {
        const classes = ['reply-row', r.is_own ? 'own' : 'other'];
        if (r.is_accepted) classes.push('answer');
        if (r.is_flagged) classes.push('flagged');

        const quoteHtml = r.parent_reply_author
            ? `<div class="quote-strip"><i class="fa-solid fa-reply"></i> Replying to <strong>${escapeHtml(r.parent_reply_author)}</strong>: ${escapeHtml(r.parent_reply_snippet || '')}</div>`
            : '';
        const acceptedHtml = r.is_accepted
            ? `<div class="reply-answer-flag"><i class="fa-solid fa-circle-check"></i> Marked as answer</div>` : '';
        const flaggedHtml = r.is_flagged
            ? `<span class="tag-chip flagged" style="margin-bottom: 6px;"><i class="fa-solid fa-flag"></i> Flagged</span>` : '';

        let attachHtml = '';
        if (r.attachment_url) {
            attachHtml = r.attachment_type === 'image'
                ? `<div class="attach-img"><img src="${escapeHtml(r.attachment_url)}" alt="Attachment"></div>`
                : `<a class="attach-file" href="${escapeHtml(r.attachment_url)}" target="_blank" rel="noopener"><div class="icon"><i class="fa-solid fa-file"></i></div><div class="fname">${escapeHtml(r.attachment_name || '')}</div></a>`;
        }

        const row = document.createElement('div');
        row.className = classes.join(' ');
        row.dataset.replyId = r.id;
        row.dataset.replyAuthor = r.author_name;
        row.dataset.signature = replySignature(r);
        row.innerHTML = `
            <div class="post-avatar ${r.is_lecturer ? 'lecturer' : ''}">${escapeHtml(initials(r.author_name))}</div>
            <div class="reply-content">
                <div class="reply-top">
                    <span class="reply-name">${r.is_own ? 'You' : escapeHtml(r.author_name)}</span>
                    <span class="reply-time">${escapeHtml(r.created_at)}</span>
                </div>
                ${quoteHtml}
                <div class="reply-bubble">
                    ${acceptedHtml}
                    ${flaggedHtml}
                    ${escapeHtml(r.content)}
                    ${attachHtml}
                </div>
            </div>
            <div class="hover-actions">${actionsHtmlFor(r)}</div>
        `;
        return row;
    }

    function applyUpdate(json) {
        const replies = json.replies || [];
        const incomingIds = new Set(replies.map(r => String(r.id)));

        chatPanel.querySelectorAll('.reply-row[data-reply-id]').forEach(row => {
            if (!incomingIds.has(row.dataset.replyId)) row.remove();
        });

        const placeholder = chatPanel.querySelector(':scope > .empty-state');
        if (replies.length && placeholder) placeholder.remove();

        replies.forEach(r => {
            const existing = chatPanel.querySelector(`.reply-row[data-reply-id="${r.id}"]`);

            const timeEl = existing?.querySelector('.reply-time');
            if (timeEl) timeEl.textContent = r.created_at;

            const signature = replySignature(r);
            if (existing && existing.dataset.signature === signature) {
                return;
            }

            // Receiver-only, WhatsApp-style: never for the viewer's own replies.
            if (!existing && !unreadBannerInserted && !r.is_own && unreadThresholdReplyId > 0 && r.id > unreadThresholdReplyId) {
                chatPanel.appendChild(buildUnreadBanner());
                unreadBannerInserted = true;
            }

            const newRow = buildReplyRow(r);
            if (existing) {
                existing.replaceWith(newRow);
            } else {
                chatPanel.appendChild(newRow);
            }
        });

        if (!replies.length && !chatPanel.querySelector('.reply-row') && !chatPanel.querySelector(':scope > .empty-state')) {
            const p = document.createElement('p');
            p.className = 'empty-state';
            p.style.padding = '8px';
            p.textContent = 'No replies yet. Be the first to respond.';
            chatPanel.appendChild(p);
        }
    }

    function poll() {
        if (replyTextarea && document.activeElement === replyTextarea && replyTextarea.value.trim() !== '') {
            return;
        }
        fetch(`/api/topics/${topicId}`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(res => (res.ok ? res.json() : null))
            .then(json => { if (json) applyUpdate(json); })
            .catch(() => {});
    }

    window.ajaxReplyAction = function (replyId, action) {
        if (action === 'delete' && !confirm('Delete this reply?')) return;
        const url = action === 'flag' ? `/replies/${replyId}/flag`
            : action === 'accept' ? `/replies/${replyId}/accept`
            : `/replies/${replyId}`;
        const method = action === 'delete' ? 'DELETE' : 'POST';
        fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(poll).catch(() => {});
    };

    if (replyForm) {
        replyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(replyForm);
            fetch(replyForm.action, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            }).then(res => {
                if (!res.ok) {
                    return res.json().then(body => {
                        throw new Error(body.message || Object.values(body.errors || {}).flat()[0] || 'Could not post your reply.');
                    });
                }
                replyTextarea.value = '';
                document.getElementById('parentReplyId').value = '';
                document.getElementById('replyContextBar').classList.remove('open');
                document.getElementById('replyAttachment').value = '';
                document.getElementById('replyAttachFilename').textContent = '';
                return poll();
            }).catch(err => alert(err.message));
        });
    }

    setInterval(poll, 2000);
})();
</script>
@endif
@endsection
