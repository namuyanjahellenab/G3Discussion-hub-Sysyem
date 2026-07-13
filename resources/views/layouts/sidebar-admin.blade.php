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

    .sidebar-menu li {
        margin: 0;
    }

    .sidebar-menu li a {
        padding: 12px 24px !important;
        font-size: 0.95rem !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        line-height: 1.2;
        text-decoration: none !important;
        font-weight: 500 !important;
    }

    .sidebar-menu li a i {
        width: 1.15rem;
        flex: 0 0 1.15rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        background: linear-gradient(135deg, var(--luna-mid) 0%, var(--luna-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }
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
        <li class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <a href="{{ route('admin.dashboard') }}"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        </li>
        <li class="{{ request()->routeIs('admin.statistics') ? 'active' : '' }}">
            <a href="{{ route('admin.statistics') }}"><i class="fa-solid fa-chart-simple"></i> Statistics</a>
        </li>
        <li class="{{ request()->routeIs('admin.blacklist') ? 'active' : '' }}">
            <a href="{{ route('admin.blacklist') }}"><i class="fa-solid fa-ban"></i> Blacklist</a>
        </li>
        <li class="{{ request()->routeIs('admin.groups.index') ? 'active' : '' }}">
            <a href="{{ route('admin.groups.index') }}"><i class="fa-solid fa-people-group"></i> Groups</a>
        </li>
        <li class="{{ request()->routeIs('admin.announcements') ? 'active' : '' }}">
            <a href="#"><i class="fa-solid fa-bullhorn"></i> Announcements</a>
        </li>
        <li class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}">
            <a href="#"><i class="fa-solid fa-gear"></i> Settings</a>
        </li>
        <li class="{{ request()->routeIs('admin.lecturer-staff.index') ? 'active' : '' }}">
            <a href="{{ route('admin.lecturer-staff.index') }}"><i class="fa-solid fa-id-card"></i> Lecturer Staff IDs</a>
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