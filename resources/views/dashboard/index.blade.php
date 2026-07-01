@extends('layouts.app')

@section('content')
@php
    $nameParts = explode(' ', auth()->user()->name ?? '');
    $initials = collect($nameParts)
        ->filter()
        ->map(fn($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<!-- Inter Font & Icons -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght=400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* Prevent layout flashing while JavaScript runs */
    .dashboard-grid-container {
        display: grid !important;
        grid-template-columns: 260px 1fr 340px !important;
        min-height: 100vh !important;
        width: 100% !important;
        background-color: #fcfcfd !important;
        font-family: 'Inter', sans-serif !important;
    }

    /* LEFT SIDEBAR PANEL */
    .sidebar-panel {
        background: #ffffff !important;
        border-right: 1px solid #e4e7ec !important;
        padding-top: 24px !important;
    }
    .sidebar-brand {
        padding: 0 24px 24px 24px !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        border-bottom: 1px solid #f2f4f7 !important;
        color: #0d52cc !important; /* Forces DISCUSSION HUB Blue */
        font-weight: 700 !important;
        font-size: 1.2rem !important;
        letter-spacing: -0.5px !important;
    }
    .sidebar-menu {
        list-style: none !important;
        padding: 20px 0 !important;
        margin: 0 !important;
    }
    .sidebar-menu li a {
        padding: 12px 24px !important;
        font-size: 0.95rem !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        color: #667085 !important;
        text-decoration: none !important;
        font-weight: 500 !important;
    }
    .sidebar-menu li.active a {
        color: #0d52cc !important;
        background: #eef4ff !important;
        border-radius: 0 24px 24px 0 !important;
        margin-right: 12px !important;
        font-weight: 600 !important;
    }

    /* MIDDLE WORKSPACE PANEL */
    .content-workspace {
        padding: 3rem 2.5rem !important;
        background: #fcfcfd !important;
    }
    
    /* STRICTLY FORCED SIDE-BY-SIDE 2-COLUMN GRID FOR JOINED GROUPS */
    .groups-side-by-side-row {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 20px !important;
        width: 100% !important;
    }
    .dashboard-group-card {
        background: #ffffff !important;
        border: 1px solid #e4e7ec !important;
        border-radius: 16px !important;
        box-shadow: 0px 2px 12px rgba(16, 24, 40, 0.02) !important;
        padding: 24px !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        text-align: center !important;
    }
    .group-card-icon {
        width: 44px !important;
        height: 44px !important;
        background: #eef4ff !important;
        color: #0d52cc !important;
        border-radius: 10px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin-bottom: 16px !important;
    }
    .btn-view-forum {
        background-color: #0d52cc !important;
        color: white !important;
        font-weight: 600 !important;
        font-size: 0.85rem !important;
        border-radius: 8px !important;
        padding: 12px !important;
        border: none !important;
        text-transform: uppercase !important;
        text-decoration: none !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        width: 100 !important;
        margin-top: auto !important;
    }

    /* EXTREME RIGHT PANEL */
    .right-info-panel {
        border-left: 1px solid #e4e7ec !important;
        background: #ffffff !important;
        padding: 3rem 2rem !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 2.5rem !important;
        box-sizing: border-box !important;
    }
    .student-profile-box {
        background: #f8fafc !important;
        border: 1px solid #e4e7ec !important;
        border-radius: 14px !important;
        padding: 1.25rem !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
    }
    .profile-avatar {
        width: 44px !important;
        height: 44px !important;
        background: #0d52cc !important;
        color: white !important;
        font-weight: 700 !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    /* Extreme Right Blue Navigation Link */
    .see-all-groups-blue-link {
        color: #0d52cc !important;
        font-weight: 700 !important;
        font-size: 0.95rem !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
    }
    .see-all-groups-blue-link:hover {
        text-decoration: underline !important;
    }

    .announcement-banner {
        background: #0d52cc !important;
        color: white !important;
        border-radius: 12px !important;
        padding: 1.25rem !important;
        margin-top: auto !important;
    }
</style>

<!-- Main Wrapper Layout Container -->
<div class="dashboard-grid-container" id="clean-dashboard-root">

    <!-- COLUMN 1: LEFT SIDEBAR -->
    <div class="sidebar-panel">
        <div class="sidebar-brand">
            <i class="fa-solid fa-comments"></i>
            <span>DISCUSSION HUB</span>
        </div>
        <ul class="sidebar-menu">
            <li class="active"><a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-columns"></i> Dashboard</a></li>
            <li><a href="{{ route('forum.index') }}"><i class="fa-regular fa-comments"></i> Forum</a></li>
            <li><a href="{{ route('messages.index') }}"><i class="fa-regular fa-envelope"></i> Messages</a></li>
            <li><a href="{{ route('marks.index') }}"><i class="fa-regular fa-star"></i> Marks</a></li>
            <li><a href="{{ route('quizzes.index') }}"><i class="fa-regular fa-file-lines"></i> Quizzes</a></li>
            <li><a href="{{ route('recommend.index') }}"><i class="fa-regular fa-thumbs-up"></i> Recommend</a></li>
            <li><a href="{{ route('settings.index') }}"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>
    </div>

    <!-- COLUMN 2: WORKSPACE CONTENT -->
    <div class="content-workspace">
        <div style="margin-bottom: 2.5rem;">
            <p style="text-transform: uppercase; color: #667085; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px 0;">Overview</p>
            <h1 style="letter-spacing: -0.5px; color: #101828; font-size: 2rem; font-weight: 700; margin: 0;">MY DASHBOARD</h1>
        </div>

        <!-- Joined Groups List Row -->
        <div style="margin-bottom: 3rem;">
            <div style="margin-bottom: 1.25rem;">
                <h2 style="color: #101828; font-size: 1.1rem; font-weight: 700; margin: 0; letter-spacing: -0.2px;">MY GROUPS</h2>
            </div>
            
            <div class="groups-side-by-side-row">
                @forelse($joined_groups as $group)
                    <div class="dashboard-group-card">
                        <div class="group-card-icon">
                            <i class="fa-solid fa-users fs-5"></i>
                        </div>
                        <h5 style="color: #101828; font-size: 1.15rem; font-weight: 700; margin: 0 0 4px 0;">{{ $group->GroupName }}</h5>
                        <p style="color: #667085; font-size: 0.85rem; margin: 0 0 24px 0;">{{ $group->member_count ?? 0 }} members</p>
                        <a href="{{ route('groups.forum', $group) }}" class="btn-view-forum">
                            <span>View Forum</span>
                            <i class="fa-solid fa-arrow-right" style="font-size: 0.8rem;"></i>
                        </a>
                    </div>
                @empty
                    <div style="grid-column: span 2; padding: 2rem; text-align: center; background: #ffffff; border: 1px solid #e4e7ec; border-radius: 12px; color: #667085;">
                        You haven't joined any groups yet.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Notifications Container Block -->
        <div style="background: #ffffff; border: 1px solid #e4e7ec; border-radius: 16px; padding: 24px; box-shadow: 0px 2px 12px rgba(16, 24, 40, 0.02);">
            <h3 style="color: #101828; font-size: 1.05rem; font-weight: 700; margin: 0 0 1.5rem 0;">Recent Notifications</h3>
            <div>
                @forelse($notifications as $notification)
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 12px; padding: 16px 0; border-bottom: 1px solid #f2f4f7;">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <span style="width: 8px; height: 8px; background-color: #0d52cc; border-radius: 50%; margin-top: 6px; flex-shrink: 0;"></span>
                            <div>
                                <div style="color: #101828; font-weight: 700; font-size: 0.95rem;">{{ $notification->Type }}</div>
                                <div style="color: #667085; font-size: 0.85rem; margin-top: 2px;">{{ $notification->Message }}</div>
                            </div>
                        </div>
                        <small style="color: #98a2b3; white-space: nowrap; font-size: 0.8rem;">{{ $notification->CreatedAt->diffForHumans() }}</small>
                    </div>
                @empty
                    <div style="color: #667085; font-size: 0.9rem; padding: 0.5rem 0;">No notifications yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    
    <div class="right-info-panel">
    
    <div class="right-info-panel">
    <div>
        <div style="color: #667085; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; margin-bottom: 8px;">
            Student Info
        </div>
        
        <div class="dropdown">
            <div class="profile-info-card dropdown-toggle" id="studentInfoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                <div class="circle-avatar">
                    @php
                        // Directly use Auth::user() to guarantee it fetches the logged-in session
                        $displayName = Auth::user()->UserName ?? Auth::user()->name ?? 'Student User';
                    @endphp
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($displayName) }}&background=0052CC&color=fff&rounded=true"
                         alt="Avatar" class="rounded-circle" width="32" height="32">
                </div>
                <div>
                    <div style="color: #101828; font-weight: 700; font-size: 0.95rem;">
                        {{ $displayName }}
                    </div>
                    <div style="color: #667085; font-size: 0.8rem;">Student Account</div>
                </div>
            </div>

            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="studentInfoDropdown">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

        <!-- Blue See All Groups Link Actions -->
        <div>
            <div style="color: #667085; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; margin-bottom: 8px;">Group Management</div>
            <a href="{{ route('groups.index') }}" class="see-all-groups-blue-link">
                <span>See All Groups </span>
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </a>
        </div>

        <!-- Announcement Alert -->
        <div class="announcement-banner">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <i class="fa-solid fa-bullhorn"></i>
                <span style="text-transform: uppercase; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9;">Announcement</span>
            </div>
            <div style="font-size: 0.88rem; font-weight: 500; line-height: 1.4;">
                Upcoming Quiz: Week 5 - Programming Principles starts at 15:00 today.
            </div>
        </div>
    </div>
</div>

<!-- FORCED CLEANUP JAVASCRIPT -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Find our clean grid root container
        var rootGrid = document.getElementById('clean-dashboard-root');
        if (rootGrid) {
            // Select everything that is a sibling or parent before our root wrapper and hide it
            var parentContainer = rootGrid.parentElement;
            
            // Loop through all nodes inside the parent container
            Array.from(parentContainer.children).forEach(function(element) {
                if (element !== rootGrid && element.tagName !== 'STYLE' && element.tagName !== 'SCRIPT') {
                    element.style.setProperty('display', 'none', 'important');
                }
            });
            
            // Clean up any extra floating elements directly inside the body tag
            Array.from(document.body.children).forEach(function(element) {
                if (!element.contains(rootGrid) && element !== rootGrid && element.tagName !== 'STYLE' && element.tagName !== 'SCRIPT') {
                    element.style.setProperty('display', 'none', 'important');
                }
            });
        }
    });
</script>
@endsection