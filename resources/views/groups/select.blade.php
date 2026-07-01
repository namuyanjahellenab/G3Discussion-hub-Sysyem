<!-- Inter Font & Icons -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght=400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    html, body {
        background-color: #fcfcfd !important;
        font-family: 'Inter', sans-serif !important;
        margin: 0;
        padding: 0;
    }
    .main-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 3rem 2rem;
    }
    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: #101828;
        letter-spacing: -0.5px;
        margin-bottom: 6px;
    }
    .page-subtitle {
        font-size: 1.05rem;
        color: #667085;
        font-weight: 400;
        margin-bottom: 2rem;
    }
    /* Search Bar Design matching Screenshot_20260629_152926_Samsung Notes.jpg exactly */
    .search-wrapper {
        background: #ffffff;
        border: 1px solid #d0d5dd;
        border-radius: 12px;
        padding: 8px 10px 8px 20px;
        box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05);
        display: flex;
        align-items: center;
        width: 100%;
    }
    .search-input {
        border: none;
        font-size: 1rem;
        color: #1d2939;
        width: 100%;
        background: transparent;
    }
    .search-input::placeholder {
        color: #667085;
    }
    .search-input:focus {
        outline: none;
        box-shadow: none;
        background: transparent;
    }
    .btn-search {
        background-color: #0d52cc;
        color: white;
        font-weight: 600;
        font-size: 0.95rem;
        padding: 10px 32px;
        border-radius: 8px !important;
        border: none;
        white-space: nowrap;
    }
    .btn-search:hover {
        background-color: #0a44b0;
    }

    /* Custom Forced Side-by-Side Grid Row */
    .cards-row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -12px;
        margin-left: -12px;
    }
    .card-col {
        flex: 0 0 50%;
        max-width: 50%;
        padding: 12px;
        box-sizing: border-box;
    }

    /* Group Card Custom Layout Styles */
    .group-card {
        background: #ffffff;
        border: 1px solid #e4e7ec;
        border-radius: 16px;
        padding: 1.5rem 1.75rem;
        box-shadow: 0px 2px 12px rgba(16, 24, 40, 0.02);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    /* Dynamic Left Accent Thick Borders matching screenshot colors */
    .bar-algorithms { border-left: 6px solid #2f80ed; }
    .bar-databases { border-left: 6px solid #56ccf2; }
    .bar-software { border-left: 6px solid #4f5e71; }
    .bar-networks { border-left: 6px solid #b91c1c; }

    .card-meta-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .course-badge {
        color: #2f80ed;
        background-color: #f0f6fe;
        font-weight: 700;
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 6px;
        letter-spacing: 0.5px;
    }
    .bar-databases .course-badge { color: #56ccf2; background-color: #f0faff; }
    .bar-software .course-badge { color: #4f5e71; background-color: #f3f4f6; }
    .bar-networks .course-badge { color: #b91c1c; background-color: #fef2f2; }

    .member-pill {
        background-color: #eef4ff;
        color: #0d52cc;
        font-weight: 500;
        font-size: 0.8rem;
        padding: 5px 14px;
        border-radius: 20px;
    }
    .group-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: #101828;
        letter-spacing: -0.4px;
        margin: 0 0 1.5rem 0;
    }
    .btn-group-join {
        background-color: #0d52cc;
        color: white;
        font-weight: 600;
        font-size: 0.95rem;
        border: none;
        border-radius: 8px;
        padding: 12px;
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        transition: background-color 0.15s ease;
        cursor: pointer;
    }
    .btn-group-join:hover {
        background-color: #0a44b0;
    }
    .btn-group-join:disabled {
        background-color: #f2f4f7;
        color: #98a2b3;
        border: 1px solid #e4e7ec;
        cursor: not-allowed;
    }
    .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #d0d5dd;
        display: inline-block;
    }
    .dot.active {
        background-color: #0d52cc;
    }
    .footer-note {
        font-size: 0.85rem;
        color: #98a2b3;
        font-style: italic;
    }
</style>

<div class="main-container">
    <!-- Component Starts Directly Here to Match Screenshot_20260629_152926_Samsung Notes.jpg Exactly -->
    <h1 class="page-title">Select a Discussion Group</h1>
    <p class="page-subtitle">Browse available groups below and click Join to enroll in the conversation.</p>

    <!-- Search Section Bar Full Width -->
    <div style="margin-bottom: 2.5rem;">
        <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass text-muted" style="font-size: 1rem; margin-right: 1rem;"></i>
            <input type="text" class="search-input" placeholder="Search for courses, lecturers or topics...">
            <button class="btn-search" type="button">Search &nbsp;<i class="fa-solid fa-arrow-right style='font-size: 0.8rem;'"></i></button>
        </div>
    </div>

    <!-- Strictly Forced Side-by-Side 2-Column Grid Layout -->
    <div class="cards-row" style="margin-bottom: 2.5rem;">
        @foreach($groups as $group)
            @php
                $courseCode = match($group->GroupName) {
                    'Algorithms' => 'CSC301',
                    'Databases' => 'CSC302',
                    'Software Engineering', 'Software Eng.' => 'CSC303',
                    'Networks' => 'CSC304',
                    default => 'CSC300',
                };

                $accentClass = match($group->GroupName) {
                    'Algorithms' => 'bar-algorithms',
                    'Databases' => 'bar-databases',
                    'Software Engineering', 'Software Eng.' => 'bar-software',
                    'Networks' => 'bar-networks',
                    default => '',
                };
            @endphp

            <div class="card-col">
                <div class="group-card {{ $accentClass }}">
                    
                    <!-- Top Info Meta Row -->
                    <div class="card-meta-line">
                        <span class="course-badge">{{ $courseCode }}</span>
                        <span class="member-pill">
                            <i class="fa-solid fa-users" style="font-size: 0.75rem; opacity: 0.8; margin-right: 4px;"></i> 
                            {{ $group->member_count ?? 0 }} members
                        </span>
                    </div>

                    <!-- Main Group Display Header Text -->
                    <h2 class="group-title">{{ $group->GroupName }}</h2>

                    <!-- Action Form Button -->
                    <div style="margin-top: auto;">
                        <form method="POST" action="{{ route('groups.join', $group->GroupID) }}" style="margin: 0;">
                            @csrf
                            <button type="submit" class="btn-group-join" {{ $group->userJoined ? 'disabled' : '' }}>
                                <span>{{ $group->userJoined ? 'Joined' : 'Join Group' }}</span>
                                <span style="font-size: 0.85rem; margin-left: 2px;"><i class="fa-solid fa-chevron-right" style="font-size: 0.75rem;"></i></span>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        @endforeach
    </div>

    <!-- Bottom Pagination Indicators & Subtext Note Elements -->
    <div style="text-align: center; margin-top: 2rem;">
        <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-bottom: 1.5rem;">
            <span class="dot active"></span>
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
        </div>
        <p class="footer-note">You can join additional groups at any time from your dashboard. Some groups may require administrative approval.</p>
    </div>
</div>