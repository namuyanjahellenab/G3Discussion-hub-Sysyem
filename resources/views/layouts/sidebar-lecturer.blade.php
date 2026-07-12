<button id="sidebar-toggle-btn" onclick="toggleAppSidebar()" title="Toggle sidebar"
    style="position: fixed; top: 16px; left: 16px; z-index: 1000; width: 36px; height: 36px;
    background: #ffffff; border: 1px solid #e4e7ec; border-radius: 8px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(16,24,40,0.08);">
    <i class="fa-solid fa-bars" style="color: #667085;"></i>
</button>

<style>
    body.sidebar-collapsed .sidebar-panel {
        width: 0 !important;
        min-width: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
        border: none !important;
    }
    body.sidebar-collapsed .dashboard-grid-container { grid-template-columns: 0 1fr 340px !important; }

    #notification-bell-wrapper { position: relative; margin-left: auto; padding-right: 8px; }
    #notification-bell { cursor: pointer; font-size: 1.05rem; }
    #notification-badge {
        display: none; position: absolute; top: -6px; right: 2px;
        background: #e74c3c; color: #fff; border-radius: 50%;
        font-size: 0.65rem; line-height: 1; padding: 3px 5px; font-weight: 700;
    }
    #notification-dropdown {
        display: none; position: absolute; left: 0; top: 32px; width: 300px;
        max-height: 380px; overflow-y: auto; background: #fff; border: 1px solid #e4e7ec;
        border-radius: 10px; box-shadow: 0 8px 24px rgba(16,24,40,0.14); z-index: 2000;
    }
    #notification-dropdown .notif-header {
        padding: 12px 16px; border-bottom: 1px solid #e4e7ec;
        display: flex; justify-content: space-between; align-items: center;
    }
    #notification-dropdown .notif-item {
        padding: 10px 16px; border-bottom: 1px solid #f2f4f7; cursor: pointer;
    }
    #notification-dropdown .notif-item:last-child { border-bottom: none; }
    #mark-all-read { font-size: 0.75rem; cursor: pointer; font-weight: 600; }
</style>

<div class="sidebar-panel">
    <div class="sidebar-brand" style="display: flex; align-items: center;">
        <i class="fa-solid fa-comments"></i><span>DISCUSSION HUB</span>

        <div id="notification-bell-wrapper">
            <i class="fa-solid fa-bell" id="notification-bell"></i>
            <span id="notification-badge"></span>

            <div id="notification-dropdown">
                <div class="notif-header">
                    <strong style="font-size: 0.85rem; color: #101828;">Notifications</strong>
                    <span id="mark-all-read">Mark all read</span>
                </div>
                <div id="notification-list">
                    <div style="padding: 16px; font-size: 0.8rem; color: #667085;">Loading...</div>
                </div>
            </div>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        </li>
        <li class="{{ request()->routeIs('forum.index') ? 'active' : '' }}">
            <a href="{{ route('forum.index') }}"><i class="fa-regular fa-comments"></i> Forum</a>
        </li>
        <!-- <li class="{{ request()->routeIs('messages.index') ? 'active' : '' }}">
            <a href="{{ route('student.messages', ['groupId' => $currentGroupId]) }}">
        </li> -->
        <li class="{{ request()->routeIs('messages.index') ? 'active' : '' }}">
    @if($currentGroupId)
        <a href="{{ route('student.messages', ['groupId' => $currentGroupId]) }}"><i class="fa-regular fa-comments"></i> Messages</a>
    @else
        <a href="#" class="disabled"><i class="fa-regular fa-comments"></i> Messages</a>
    @endif
</li>
        <li class="{{ request()->routeIs('marks.index') ? 'active' : '' }}">
            <a href="{{ route('marks.index') }}"><i class="fa-regular fa-star"></i> Marks</a>
        </li>
       <li class="{{ request()->routeIs('quiz.schedule') ? 'active' : '' }}">
    <a href="{{ route('quiz.schedule') }}"><i class="fa-solid fa-plus"></i> Schedule Quiz</a>
</li>
<li class="{{ request()->routeIs('quiz.results') ? 'active' : '' }}">
    <a href="{{ route('dashboard') }}"><i class="fa-solid fa-chart-simple"></i> Quiz Results</a>
</li>
        <!-- <li class="{{ request()->routeIs('recommend.index') ? 'active' : '' }}">
            <a href="{{ route('recommend.index') }}"><i class="fa-regular fa-thumbs-up"></i> Recommend</a>
        </li> -->
        <li class="{{ request()->routeIs('settings.index') ? 'active' : '' }}">
            <a href="{{ route('settings.index') }}"><i class="fa-solid fa-gear"></i> Settings</a>
        </li>
    </ul>
</div>

<script>
    function toggleAppSidebar() {
        document.body.classList.toggle('sidebar-collapsed');
    }
</script>

@once
    @include('layouts.sidebar-notifications-script')
@endonce