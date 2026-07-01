@extends('layouts.app')

@section('content')
@php
    $nameParts = explode(' ', auth()->user()->name ?? auth()->user()->UserName ?? '');
    $initials = collect($nameParts)
        ->filter()
        ->map(fn($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<link href="https://fonts.googleapis.com/css2?family=Inter:wght=400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary-color: #0d52cc;
        --primary-hover: #0a44ab;
        --primary-light: #eef4ff;
        --text-main: #101828;
        --text-muted: #667085;
        --border-color: #e4e7ec;
        --bg-main: #fcfcfd;
        --bg-panel: #ffffff;
        --radius-lg: 16px;
        --radius-md: 10px;
        --radius-sm: 8px;
    }

    .dashboard-grid-container { 
        display: grid !important; 
        grid-template-columns: 260px 1fr 340px !important; 
        min-height: 100vh !important; 
        width: 100% !important; 
        background-color: var(--bg-main) !important; 
        font-family: 'Inter', sans-serif !important; 
    }
    
    .sidebar-panel { background: var(--bg-panel) !important; border-right: 1px solid var(--border-color) !important; padding-top: 24px !important; }
    .sidebar-brand { padding: 0 24px 24px 24px !important; display: flex !important; align-items: center !important; gap: 12px !important; border-bottom: 1px solid #f2f4f7 !important; color: var(--primary-color) !important; font-weight: 700 !important; font-size: 1.2rem !important; }
    .sidebar-menu { list-style: none !important; padding: 20px 0 !important; margin: 0 !important; }
    .sidebar-menu li a { padding: 12px 24px !important; font-size: 0.95rem !important; display: flex !important; align-items: center !important; gap: 12px !important; color: var(--text-muted) !important; text-decoration: none !important; font-weight: 500 !important; }
    .sidebar-menu li.active a { color: var(--primary-color) !important; background: var(--primary-light) !important; border-radius: 0 24px 24px 0 !important; margin-right: 12px !important; font-weight: 600 !important; }
    
    .content-workspace { padding: 2.5rem !important; background: var(--bg-main) !important; display: flex !important; flex-direction: column !important; }
    
    .filter-bar { background: var(--bg-panel) !important; border: 1px solid var(--border-color) !important; border-radius: var(--radius-md) !important; padding: 12px 16px !important; display: flex !important; gap: 12px !important; align-items: center !important; }
    .filter-select { border: 1px solid var(--border-color) !important; border-radius: var(--radius-sm) !important; color: var(--text-main) !important; font-weight: 500 !important; font-size: 0.9rem !important; padding: 8px 12px !important; cursor: pointer !important; }
    .search-wrapper { position: relative !important; flex-grow: 1 !important; }
    .search-wrapper i { position: absolute !important; left: 12px !important; top: 50% !important; transform: translateY(-50%) !important; color: var(--text-muted) !important; }
    .search-input { width: 100% !important; border: 1px solid var(--border-color) !important; border-radius: var(--radius-sm) !important; padding: 8px 12px 8px 36px !important; font-size: 0.9rem !important; }
    
    .conversation-panel { background: var(--bg-panel) !important; border: 1px solid var(--border-color) !important; border-radius: var(--radius-lg) !important; display: flex !important; flex-direction: column !important; flex-grow: 1 !important; overflow: hidden; box-shadow: 0px 4px 18px rgba(16, 24, 40, 0.03) !important; margin-top: 1.5rem !important; }
    
    .right-info-panel { border-left: 1px solid var(--border-color) !important; background: var(--bg-panel) !important; padding: 2.5rem 1.75rem !important; display: flex !important; flex-direction: column !important; gap: 2rem !important; box-sizing: border-box !important; }
    .panel-section-title { color: var(--text-muted) !important; font-weight: 700 !important; text-transform: uppercase; font-size: 0.75rem !important; letter-spacing: 0.75px !important; margin-bottom: 12px !important; }
    .student-profile-box { background: #f8fafc !important; border: 1px solid var(--border-color) !important; border-radius: var(--radius-md) !important; padding: 1rem 1.25rem !important; display: flex !important; align-items: center !important; gap: 14px !important; }
    
    /* Universal Avatar CSS Element */
    .avatar-circle-ui { width: 38px; height: 38px; border-radius: 50%; background: var(--primary-color); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; user-select: none; flex-shrink: 0; }
    .avatar-circle-ui.avatar-orange { background: #e67e22; }
    .avatar-circle-ui.avatar-purple { background: #9b59b6; }
    .avatar-circle-ui.avatar-green { background: #2ecc71; }

    .share-grid { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; margin-top: 12px !important; }
    .btn-share { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; font-size: 0.8rem !important; font-weight: 600 !important; padding: 8px !important; border-radius: var(--radius-sm) !important; border: 1px solid var(--border-color) !important; background: #fff !important; text-decoration: none !important; color: var(--text-main) !important; }

    /* Custom Message Thread / Reply Actions Layout Structure */
    .msg-bubble-wrapper { position: relative; max-width: 75%; display: flex; gap: 10px; align-items: flex-start; width: fit-content; }
    .msg-bubble-wrapper .reply-action-btn { visibility: hidden; opacity: 0; position: absolute; top: 50%; transform: translateY(-50%); color: var(--text-muted); cursor: pointer; padding: 6px 8px; background: #fff; border: 1px solid var(--border-color); border-radius: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); z-index: 10; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: opacity 0.15s ease, visibility 0.15s ease; }
    .msg-bubble-wrapper:hover .reply-action-btn { visibility: visible; opacity: 1; }
    
    .mine-wrapper { align-self: flex-end; flex-direction: row-reverse; }
    .mine-wrapper .reply-action-btn { left: -75px; }
    .theirs-wrapper { align-self: flex-start; }
    .theirs-wrapper .reply-action-btn { right: -75px; }

    /* Profile Modal Backplates */
    .profile-modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(16, 24, 40, 0.4); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .profile-modal-box { background: #fff; width: 100%; max-width: 400px; border-radius: var(--radius-lg); box-shadow: 0 10px 32px rgba(16,24,40,0.15); overflow: hidden; animation: slideUpModal 0.25s ease-out; }
    @keyframes slideUpModal { from { transform: translateY(20px); opacity:0; } to { transform: translateY(0); opacity:1; } }
</style>

<div class="dashboard-grid-container" id="clean-dashboard-root">
    <!-- Sidebar Panel Left -->
    <div class="sidebar-panel">
        <div class="sidebar-brand"><i class="fa-solid fa-comments"></i><span>DISCUSSION HUB</span></div>
        <ul class="sidebar-menu">
            <li><a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-columns"></i> Dashboard</a></li>
            <li><a href="{{ route('forum.index') }}"><i class="fa-regular fa-comments"></i> Forum</a></li>
            <li class="active"><a href="{{ route('messages.index') }}"><i class="fa-regular fa-envelope"></i> Messages</a></li>
            <li><a href="{{ route('marks.index') }}"><i class="fa-regular fa-star"></i> Marks</a></li>
            <li><a href="{{ route('quizzes.index') }}"><i class="fa-regular fa-file-lines"></i> Quizzes</a></li>
            <li><a href="{{ route('recommend.index') }}"><i class="fa-regular fa-thumbs-up"></i> Recommend</a></li>
            <li><a href="{{ route('settings.index') }}"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Center Workspace Area -->
    <div class="content-workspace">
        <div style="margin-bottom: 1.5rem;">
            <p style="text-transform: uppercase; color: var(--text-muted); font-size: 0.75rem; font-weight: 700; letter-spacing: 0.75px; margin: 0 0 4px 0;">Collaboration</p>
            <h1 style="letter-spacing: -0.75px; color: var(--text-main); font-size: 1.85rem; font-weight: 700; margin: 0;">Messages</h1>
        </div>

        <!-- Filter Utility Bar -->
        <form method="GET" class="filter-bar">
            <select name="group_id" class="form-select filter-select" style="max-width: 180px;" onchange="this.form.submit()">
                <option value="">All groups</option>
                @foreach($joinedGroups as $group)
                    <option value="{{ $group->GroupID }}" {{ request('group_id') == $group->GroupID ? 'selected' : '' }}>{{ $group->GroupName }}</option>
                @endforeach
            </select>
            
            <select name="topic_id" class="form-select filter-select" style="max-width: 180px;" onchange="this.form.submit()">
                <option value="">All topics</option>
                @foreach($topics as $topic)
                    <option value="{{ $topic->TopicID }}" {{ request('topic_id') == $topic->TopicID ? 'selected' : '' }}>{{ $topic->Title }}</option>
                @endforeach
            </select>
            
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" class="form-control search-input" placeholder="Search conversation..." value="{{ request('search') }}">
            </div>
        </form>

        <!-- Dynamic Content Panel -->
        <div class="conversation-panel">
            <!-- Chat Messages Container Viewport -->
            <div id="chat-messages-container" style="padding: 24px; background: #f0f2f5; display: flex; flex-direction: column; gap: 16px; min-height: 350px; max-height: 500px; overflow-y: auto;">
                
                <!-- DEFAULT DEMO MESSAGES (FROM OTHER USERS) WITH AVATARS & DYNAMIC TIMESTAMPS -->
                <div class="msg-bubble-wrapper theirs-wrapper" data-sender="Alex Johnson" data-role="Lead Architect" data-email="alex.j@university.edu">
                    <div class="avatar-circle-ui avatar-orange view-sender-profile" style="cursor: pointer;">AJ</div>
                    <span class="reply-action-btn" onclick="setReplyContext('Alex Johnson', 'Hey team! Did everyone finish reading through the latest project constraints...')"><i class="fa-solid fa-reply"></i> Reply</span>
                    
                    <div style="padding: 12px 16px; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-radius: 0px 12px 12px 12px; background-color: #ffffff; font-family: 'Inter', sans-serif; flex-grow: 1;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 4px;">
                            <span class="view-sender-profile" style="font-weight: 600; font-size: 0.85rem; color: var(--primary-color); cursor: pointer;">Alex Johnson</span>
                            <!-- Dynamic timestamp calculation anchor -->
                            <span class="live-timestamp" data-timestamp="{{ now()->subMinutes(5)->timestamp }}" style="font-size: 0.7rem; color: var(--text-muted); margin-left: auto;"></span>
                        </div>
                        <div class="message-actual-body" style="color: #344054; line-height: 1.4; font-size: 0.92rem; word-break: break-word; white-space: pre-wrap;">Hey team! Did everyone finish reading through the latest project constraints before the review meeting?</div>
                    </div>
                </div>

                <div class="msg-bubble-wrapper theirs-wrapper" data-sender="Sarah Smith" data-role="Research Assistant" data-email="sarah.s@university.edu">
                    <div class="avatar-circle-ui avatar-purple view-sender-profile" style="cursor: pointer;">SS</div>
                    <span class="reply-action-btn" onclick="setReplyContext('Sarah Smith', 'Yes, I looked over them! I added the technical checklist asset...')"><i class="fa-solid fa-reply"></i> Reply</span>
                    
                    <div style="padding: 12px 16px; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-radius: 0px 12px 12px 12px; background-color: #ffffff; font-family: 'Inter', sans-serif; flex-grow: 1;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 4px;">
                            <span class="view-sender-profile" style="font-weight: 600; font-size: 0.85rem; color: var(--primary-color); cursor: pointer;">Sarah Smith</span>
                            <span class="live-timestamp" data-timestamp="{{ now()->subMinutes(2)->timestamp }}" style="font-size: 0.7rem; color: var(--text-muted); margin-left: auto;"></span>
                        </div>
                        <div class="message-actual-body" style="color: #344054; line-height: 1.4; font-size: 0.92rem; word-break: break-word; white-space: pre-wrap;">Yes, I looked over them! I added the technical checklist asset to our hub workspace folder as well. Let me know what you think.</div>
                        <div style="margin-top: 8px; padding: 6px 10px; background: rgba(0,0,0,0.04); border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 0.8rem;">
                            <i class="fa-solid fa-paperclip" style="color: var(--text-muted);"></i>
                            <a href="#" onclick="return false;" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">Technical_Requirements.pdf</a>
                        </div>
                    </div>
                </div>
                <!-- END DEFAULT DEMO MESSAGES -->

                @foreach($threadedPosts as $post)
                    @php
                        $isMine = ($post->author?->id === auth()->id() || $post->AuthorID === auth()->id());
                        $senderName = $post->author?->UserName ?? $post->author?->name ?? 'Student';
                        
                        // Parse sender initials safely
                        $loopParts = explode(' ', $senderName);
                        $loopInitials = collect($loopParts)->filter()->map(fn($p) => mb_substr($p,0,1))->take(2)->implode('');
                    @endphp

                    <div class="msg-bubble-wrapper {{ $isMine ? 'mine-wrapper' : 'theirs-wrapper' }}" data-sender="{{ $senderName }}" data-role="Verified Contributor" data-email="{{ $post->author?->email ?? 'unspecified@domain.edu' }}">
                        @if(!$isMine)
                            <div class="avatar-circle-ui avatar-green view-sender-profile" style="cursor: pointer;">{{ $loopInitials ?: 'ST' }}</div>
                        @endif
                        
                        <span class="reply-action-btn" onclick="setReplyContext('{{ $isMine ? 'You' : $senderName }}', '{{ Str::limit(addslashes($post->Content), 50) }}')"><i class="fa-solid fa-reply"></i> Reply</span>
                        
                        <div style="padding: 12px 16px; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-radius: {{ $isMine ? '12px 0px 12px 12px' : '0px 12px 12px 12px' }}; background-color: {{ $isMine ? '#d9fdd3' : '#ffffff' }}; font-family: 'Inter', sans-serif; flex-grow: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 4px;">
                                @if(!$isMine)
                                    <span class="view-sender-profile" style="font-weight: 600; font-size: 0.85rem; color: var(--primary-color); cursor: pointer;">
                                        {{ $senderName }}
                                    </span>
                                @endif
                                <span class="live-timestamp" data-timestamp="{{ $post->CreatedAt ? $post->CreatedAt->timestamp : now()->timestamp }}" style="font-size: 0.7rem; color: var(--text-muted); margin-left: auto;"></span>
                            </div>

                            @if(!empty($post->parent_reply_text))
                                <div style="background: rgba(0,0,0,0.05); border-left: 3px solid var(--primary-color); padding: 6px 10px; font-size: 0.8rem; border-radius: 4px; margin-bottom: 8px; color: var(--text-muted);">
                                    <i class="fa-solid fa-quote-left" style="font-size:0.65rem; margin-right:4px; opacity:0.5;"></i> {{ $post->parent_reply_text }}
                                </div>
                            @endif

                            <div class="message-actual-body" style="color: #344054; line-height: 1.4; font-size: 0.92rem; word-break: break-word; white-space: pre-wrap;">{{ $post->Content }}</div>
                            
                            @if(!empty($post->attachment_path))
                                <div style="margin-top: 8px; padding: 6px 10px; background: rgba(0,0,0,0.04); border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 0.8rem;">
                                    <i class="fa-solid fa-paperclip" style="color: var(--text-muted);"></i>
                                    <a href="{{ asset('storage/' . $post->attachment_path) }}" target="_blank" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">View Attached Document</a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Composer Input Area -->
            <div style="padding: 16px 20px; background: #fff; border-top: 1px solid var(--border-color);">
                <form method="POST" action="{{ route('messages.store') }}" id="whatsapp-form" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="topic_id" value="{{ request('topic_id') ?: ($topics->first()?->TopicID ?? '') }}">
                    
                    <!-- Nested Reply Metadata Trackers -->
                    <input type="hidden" name="parent_reply_text" id="parent-reply-text-input" value="">

                    <!-- Active Reply HUD Banner Indicator -->
                    <div id="reply-context-banner" style="display: none; padding: 10px 16px; background: #f2f4f7; border-left: 4px solid var(--primary-color); align-items: center; gap: 12px; margin-bottom: 8px; border-radius: 4px;">
                        <div style="font-size: 0.85rem; flex-grow: 1; color: #344054; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <span style="font-weight: 700; color: var(--primary-color);" id="reply-banner-sender"></span>: 
                            <span id="reply-banner-body" style="font-style: italic;"></span>
                        </div>
                        <i class="fa-solid fa-xmark" onclick="clearReplyContext()" style="cursor: pointer; color: var(--text-muted); font-size: 1.1rem;"></i>
                    </div>

                    <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; background: #fff;">
                        <textarea name="content" id="composer-textarea" rows="3" style="border: none; width: 100%; padding: 14px 16px; font-size: 0.95rem; outline: none; resize: none; font-family: 'Inter', sans-serif;" placeholder="Type a message... (Press Enter to Send)" required></textarea>
                        
                        <!-- File Upload Info Bar -->
                        <div id="file-preview-status" style="display: none; padding: 6px 16px; background: #f8fafc; border-top: 1px solid #f2f4f7; font-size: 0.82rem; color: #027a48; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-circle-check"></i> <span id="file-name-label"></span>
                            <i class="fa-solid fa-xmark" id="clear-file" style="cursor: pointer; margin-left: auto; color: var(--text-muted);"></i>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #f9fafb; border-top: 1px solid #f2f4f7;">
                            <div>
                                <label style="cursor: pointer; color: var(--text-muted); font-size: 0.85rem; display: flex; align-items: center; gap: 6px; user-select: none;">
                                    <i class="fa-solid fa-paperclip"></i> Add Asset
                                    <input type="file" name="attachment" id="file-input-field" style="display: none;">
                                </label>
                            </div>
                            <div>
                                <button type="submit" style="background: var(--primary-color); border: none; color: white; font-weight: 600; padding: 8px 18px; border-radius: var(--radius-sm); cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                    Send <i class="fa-solid fa-paper-plane" style="font-size: 0.8rem;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Information Panel -->
    <div class="right-info-panel">
        <div>
            <div class="panel-section-title">Student Profile</div>
            <div class="student-profile-box">
                <div class="avatar-circle-ui">{{ $initials ?: 'SU' }}</div>
                <div>
                    <div style="color: var(--text-main); font-weight: 600; font-size: 0.95rem;">{{ auth()->user()->UserName ?? auth()->user()->name }}</div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Student Verified Account</div>
                </div>
            </div>
        </div>

        <div style="background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px;">
            <div class="panel-section-title">Workspace Actions</div>
            <a href="#" style="background: var(--primary-color); border:none; font-weight:600; font-size:0.9rem; padding: 10px 14px; border-radius: var(--radius-sm); display:block; text-align:center; text-decoration:none; color:#fff; margin-bottom:12px;">
                <i class="fa-solid fa-file-export" style="margin-right: 6px;"></i> Export to pdf
            </a>
            
            <div class="share-grid">
                <a href="#" class="btn-share" style="color: #25D366;"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                <a href="#" class="btn-share" style="color: #667085;"><i class="fa-regular fa-envelope"></i> Email</a>
            </div>
        </div>
    </div>
</div>

<!-- DYNAMIC PROFILE CONTROLLER MODAL HUD -->
<div class="profile-modal-overlay" id="global-profile-modal">
    <div class="profile-modal-box">
        <div style="background: var(--primary-color); padding: 32px 24px; text-align: center; position: relative;">
            <i class="fa-solid fa-xmark" id="close-profile-modal" style="position: absolute; top: 16px; right: 16px; color: rgba(255,255,255,0.7); cursor: pointer; font-size: 1.2rem;"></i>
            <div id="modal-avatar-placeholder" style="width: 72px; height: 72px; background: #fff; color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px auto; font-size: 1.8rem; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-transform: uppercase;"></div>
            <h3 id="modal-profile-name" style="color: #fff; margin: 0; font-size: 1.25rem; font-weight: 600;">Sender Profile</h3>
            <p id="modal-profile-role" style="color: rgba(255,255,255,0.8); margin: 4px 0 0 0; font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"></p>
        </div>
        <div style="padding: 24px; display: flex; flex-direction: column; gap: 16px;">
            <div>
                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 4px;">Email Channel</label>
                <div id="modal-profile-email" style="color: var(--text-main); font-weight: 500; font-size: 0.95rem;"></div>
            </div>
            <div>
                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 4px;">Account Status</label>
                <div style="color: #027a48; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-circle-check"></i> Active & Verified Participant
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Global orchestration loop to build dynamic humanized timestamps
    function updateTimestamps() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.live-timestamp').forEach(el => {
            const timestamp = parseInt(el.getAttribute('data-timestamp'));
            if (!timestamp) return;

            const diff = now - timestamp;
            if (diff < 60) {
                el.textContent = 'Just now';
            } else if (diff < 3600) {
                el.textContent = `${Math.floor(diff / 60)}m ago`;
            } else if (diff < 86400) {
                el.textContent = `${Math.floor(diff / 3600)}h ago`;
            } else {
                el.textContent = `${Math.floor(diff / 86400)}d ago`;
            }
        });
    }

    function setReplyContext(sender, textSnippet) {
        document.getElementById('parent-reply-text-input').value = `${sender}: "${textSnippet}"`;
        document.getElementById('reply-banner-sender').textContent = sender;
        document.getElementById('reply-banner-body').textContent = textSnippet;
        document.getElementById('reply-context-banner').style.display = 'flex';
        document.getElementById('composer-textarea').focus();
    }

    function clearReplyContext() {
        document.getElementById('parent-reply-text-input').value = '';
        document.getElementById('reply-context-banner').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const chatForm = document.getElementById('whatsapp-form');
        const chatTextArea = document.getElementById('composer-textarea');
        const messagesContainer = document.getElementById('chat-messages-container');
        const fileInputField = document.getElementById('file-input-field');
        const filePreviewStatus = document.getElementById('file-preview-status');
        const fileNameLabel = document.getElementById('file-name-label');
        const clearFileBtn = document.getElementById('clear-file');

        const profileModal = document.getElementById('global-profile-modal');
        const closeProfileBtn = document.getElementById('close-profile-modal');

        // Fire & initialize continuous calculation loop
        updateTimestamps();
        setInterval(updateTimestamps, 60000);

        if(messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Delegate Profile View Interactions securely
        if (messagesContainer) {
            messagesContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('view-sender-profile')) {
                    const parentWrapper = e.target.closest('.msg-bubble-wrapper');
                    if (parentWrapper) {
                        const name = parentWrapper.getAttribute('data-sender') || 'Unknown User';
                        const role = parentWrapper.getAttribute('data-role') || 'Student Contributor';
                        const email = parentWrapper.getAttribute('data-email') || 'notshared@university.edu';
                        
                        document.getElementById('modal-profile-name').textContent = name;
                        document.getElementById('modal-profile-role').textContent = role;
                        document.getElementById('modal-profile-email').textContent = email;
                        
                        const initials = name.split(' ').filter(Boolean).map(p => p[0]).slice(0,2).join('');
                        document.getElementById('modal-avatar-placeholder').textContent = initials || 'ST';
                        
                        profileModal.style.display = 'flex';
                    }
                }
            });
        }

        if (closeProfileBtn) {
            closeProfileBtn.addEventListener('click', function() {
                profileModal.style.display = 'none';
            });
        }

        if (fileInputField) {
            fileInputField.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    fileNameLabel.textContent = `Attached: ${this.files[0].name}`;
                    filePreviewStatus.style.display = 'flex';
                }
            });
        }

        if (clearFileBtn) {
            clearFileBtn.addEventListener('click', function() {
                fileInputField.value = '';
                filePreviewStatus.style.display = 'none';
            });
        }

        if (chatTextArea) {
            chatTextArea.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }

        if (chatForm && chatTextArea && messagesContainer) {
            chatForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const messageText = chatTextArea.value.trim();
                const parentReplyVal = document.getElementById('parent-reply-text-input').value;
                if (!messageText && (!fileInputField.files || fileInputField.files.length === 0)) return;

                const wrapperDiv = document.createElement('div');
                wrapperDiv.className = "msg-bubble-wrapper mine-wrapper";
                wrapperDiv.setAttribute('data-sender', 'You');
                wrapperDiv.setAttribute('data-role', 'Student User');
                wrapperDiv.setAttribute('data-email', 'your.account@university.edu');

                const currentUnix = Math.floor(Date.now() / 1000);

                let attachmentHTML = '';
                if (fileInputField.files && fileInputField.files.length > 0) {
                    attachmentHTML = `
                        <div style="margin-top: 8px; padding: 6px 10px; background: rgba(0,0,0,0.05); border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 0.8rem;">
                            <i class="fa-solid fa-file-lines" style="color: #128c7e;"></i>
                            <span style="color: #128c7e; font-weight: 500;">${fileInputField.files[0].name}</span>
                        </div>
                    `;
                }

                let replyBoxHTML = '';
                if (parentReplyVal) {
                    replyBoxHTML = `
                        <div style="background: rgba(0,0,0,0.05); border-left: 3px solid var(--primary-color); padding: 6px 10px; font-size: 0.8rem; border-radius: 4px; margin-bottom: 8px; color: var(--text-muted); text-align: left;">
                            <i class="fa-solid fa-quote-left" style="font-size:0.65rem; margin-right:4px; opacity:0.5;"></i> ${parentReplyVal}
                        </div>
                    `;
                }

                // Escaping content snippets dynamically to avoid inline syntax ruptures
                const escapedSnippet = messageText.substring(0, 50).replace(/'/g, "\\'").replace(/"/g, '&quot;');

                wrapperDiv.innerHTML = `
                    <span class="reply-action-btn" onclick="setReplyContext('You', '${escapedSnippet}')"><i class="fa-solid fa-reply"></i> Reply</span>
                    <div style="padding: 12px 16px; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-radius: 12px 0px 12px 12px; background-color: #d9fdd3; font-family: 'Inter', sans-serif; flex-grow: 1;">
                        <div style="display: flex; align-items: center; justify-content: flex-end; margin-bottom: 4px;">
                            <span class="live-timestamp" data-timestamp="${currentUnix}" style="font-size: 0.7rem; color: #667085;">Just now</span>
                        </div>
                        ${replyBoxHTML}
                        <div class="message-actual-body" style="color: #344054; line-height: 1.4; font-size: 0.92rem; word-break: break-word; white-space: pre-wrap;">${messageText.replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}</div>
                        ${attachmentHTML}
                    </div>
                `;

                messagesContainer.appendChild(wrapperDiv);
                updateTimestamps();
                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                const formData = new FormData(chatForm);
                fetch(chatForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(error => console.error('Database connection intercept error:', error));

                chatTextArea.value = '';
                fileInputField.value = '';
                filePreviewStatus.style.display = 'none';
                clearReplyContext();
            });
        }
    });
</script>
@endsection