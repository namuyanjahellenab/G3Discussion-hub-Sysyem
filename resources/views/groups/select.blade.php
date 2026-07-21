<!-- Inter Font & Icons -->
@vite(['resources/css/icons.css'])

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
        background-color: #26658C;
        color: white;
        font-weight: 600;
        font-size: 0.95rem;
        padding: 10px 32px;
        border-radius: 8px !important;
        border: none;
        white-space: nowrap;
    }
    .btn-search:hover {
        background-color: #023859;
    }

    /* Was its own one-off design (white bg, 16px radius, colored 6px left
       accent border, drop shadow, course-badge + member-pill) that never
       matched the .group-card used everywhere else once you've actually
       joined a group (Dashboard's My Groups panel, Forum, groups/index.blade.php
       - all fixed 220x220, var(--surface-bg)/var(--surface-border), 10px
       radius, no shadow, no accent border, icon chip). This page doesn't
       extend layouts.app (a deliberately distraction-free onboarding screen,
       no sidebar/navbar), so those CSS custom properties from admin-theme.css
       aren't in scope here - using their literal hex values instead so the
       cards are pixel-for-pixel the same as the post-join ones. */
    .cards-row {
        display: grid;
        grid-template-columns: repeat(auto-fill, 220px);
        gap: 16px;
        justify-content: center;
    }
    .card-col {
        padding: 0;
        box-sizing: border-box;
    }

    .group-card {
        width: 220px;
        height: 220px;
        box-sizing: border-box;
        background: #F4F8FA;
        border: 1px solid #E1E9ED;
        border-radius: 10px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .group-card-icon {
        width: 40px;
        height: 40px;
        background: #A7EBF2;
        color: #023859;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        font-size: 1rem;
    }
    .member-pill {
        color: #6B8094;
        font-size: 0.8rem;
        margin: 0 0 16px 0;
    }
    .group-title {
        font-size: 1rem;
        font-weight: 700;
        color: #011C40;
        margin: 0 0 2px 0;
    }
    .btn-group-join {
        background-color: #26658C;
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
        background-color: #023859;
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
        background-color: #26658C;
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
            <input type="text" id="groupSearchInput" class="search-input" placeholder="Search for courses, lecturers or topics...">
            <button class="btn-search" type="button" id="groupSearchBtn">Search &nbsp;<i class="fa-solid fa-arrow-right style='font-size: 0.8rem;'"></i></button>
        </div>
    </div>

    <!-- Strictly Forced Side-by-Side 2-Column Grid Layout -->
    <form method="POST" action="{{ route('groups.select.multiple') }}" id="groupSelectionForm">
        @csrf
        <div class="cards-row" id="groupCardsRow" style="margin-bottom: 2.5rem;">
            @foreach($groups as $group)
                <div class="card-col" data-group-name="{{ strtolower($group->GroupName) }}">
                    <div class="group-card">
                        <div class="group-card-icon"><i class="fa-solid fa-users"></i></div>
                        <h2 class="group-title">{{ $group->GroupName }}</h2>
                        <p class="member-pill">{{ $group->member_count ?? 0 }} members</p>

                        <label style="display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; font-size: 0.85rem; color: #33455A; font-weight: 500;">
                            <input type="checkbox" name="groups[]" value="{{ $group->GroupID }}"
                                   {{ $group->userJoined ? 'checked' : '' }}
                                   style="width: 16px; height: 16px; cursor: pointer; accent-color: #26658C;">
                            <span>{{ $group->userJoined ? 'Selected' : 'Select' }}</span>
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
        <p id="groupSearchEmpty" style="display: none; text-align: center; color: #667085; margin-bottom: 2.5rem;">No groups match your search.</p>

        <!-- Proceed Button -->
        <div style="text-align: center; margin-top: 2rem;">
            <button type="submit" class="btn-group-join" style="max-width: 400px; margin: 0 auto; font-size: 1.1rem; padding: 14px;">
                <span>Proceed to Dashboard</span>
                <span style="font-size: 1rem; margin-left: 8px;"><i class="fa-solid fa-arrow-right"></i></span>
            </button>
        </div>
    </form>

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

<script>
    (function () {
        const input = document.getElementById('groupSearchInput');
        const btn = document.getElementById('groupSearchBtn');
        const cards = Array.from(document.querySelectorAll('#groupCardsRow .card-col'));
        const emptyMsg = document.getElementById('groupSearchEmpty');

        function applyFilter() {
            const query = input.value.trim().toLowerCase();
            let visibleCount = 0;
            cards.forEach(function (card) {
                const matches = !query || card.dataset.groupName.includes(query);
                card.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });
            emptyMsg.style.display = visibleCount === 0 ? '' : 'none';
        }

        input.addEventListener('input', applyFilter);
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            applyFilter();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });
    })();
</script>