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

    /* Notification bell (Requirement #2) */
    #notification-bell-wrapper { position: relative; margin-left: auto; padding-right: 8px; }
    #notification-bell { cursor: pointer; font-size: 1.05rem; color: #667085; }
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
    #mark-all-read { font-size: 0.75rem; color: #0d52cc; cursor: pointer; font-weight: 600; }
</style>

<div class="sidebar-panel">
    <div class="sidebar-brand" style="display: flex; align-items: center;">
        <i class="fa-solid fa-comments"></i><span>DISCUSSION HUB</span>

        <!-- Notification bell (Requirement #2: notify a post's author when someone replies) -->
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
@if(auth()->user()->Role === 'Admin')
    <li class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
    </li>
    <li class="{{ request()->routeIs('admin.statistics') ? 'active' : '' }}">
        <a href="#"><i class="fa-solid fa-chart-simple"></i> Statistics</a>
    </li>
    <li class="{{ request()->routeIs('admin.blacklist') ? 'active' : '' }}">
        <a href="#"><i class="fa-solid fa-ban"></i> Blacklist</a>
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
@else
    <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <a href="{{ route('dashboard') }}"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
    </li>
    <li class="{{ request()->routeIs('forum.index') ? 'active' : '' }}">
        <a href="{{ route('forum.index') }}"><i class="fa-regular fa-comments"></i> Forum</a>
    </li>
    <li class="{{ request()->routeIs('messages.index') ? 'active' : '' }}">
        <a href="{{ route('messages.index') }}"><i class="fa-regular fa-envelope"></i> Messages</a>
    </li>
    <li class="{{ request()->routeIs('marks.index') ? 'active' : '' }}">
        @if(auth()->user()->Role === 'Lecturer')
            <a href="{{ route('dashboard') }}"><i class="fa-regular fa-star"></i> Marks</a>
        @else
            <a href="{{ route('marks.index') }}"><i class="fa-regular fa-star"></i> Marks</a>
        @endif
    </li>
    @if(auth()->user()->Role === 'Lecturer')
        <li class="dropdown {{ request()->routeIs('quiz.schedule') || request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-regular fa-file-lines"></i> Quizzes
            </a>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item" href="{{ route('quiz.schedule') }}">
                        <i class="fa-solid fa-plus"></i> Schedule Quiz
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('dashboard') }}">
                        <i class="fa-solid fa-chart-simple"></i> Quiz Results
                    </a>
                </li>
            </ul>
        </li>
    @else
        <li class="{{ request()->routeIs('quizzes.index') ? 'active' : '' }}">
            <a href="{{ route('quizzes.index') }}"><i class="fa-regular fa-file-lines"></i> Quizzes</a>
        </li>
    @endif
    <li class="{{ request()->routeIs('recommend.index') ? 'active' : '' }}">
        <a href="{{ route('recommend.index') }}"><i class="fa-regular fa-thumbs-up"></i> Recommend</a>
    </li>
    <li class="{{ request()->routeIs('settings.index') ? 'active' : '' }}">
        <a href="{{ route('settings.index') }}"><i class="fa-solid fa-gear"></i> Settings</a>
    </li>
@endif
</ul>
</div>

<script>
    function toggleAppSidebar() {
        document.body.classList.toggle('sidebar-collapsed');
    }
</script>

@once
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const bell = document.getElementById('notification-bell');
        const dropdown = document.getElementById('notification-dropdown');
        const badge = document.getElementById('notification-badge');
        const list = document.getElementById('notification-list');
        const markAllBtn = document.getElementById('mark-all-read');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value
            || '';

        if (!bell) return;

        bell.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#notification-bell-wrapper')) {
                dropdown.style.display = 'none';
            }
        });

        if (markAllBtn) {
            markAllBtn.addEventListener('click', async function (e) {
                e.stopPropagation();
                try {
                    await fetch('/notifications/read-all', {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                    });
                    pollNotifications();
                } catch (err) {
                    console.error('Mark all read error', err);
                }
            });
        }

        function renderNotifications(notifications) {
            if (!notifications || notifications.length === 0) {
                list.innerHTML = '<div style="padding:16px; font-size:0.8rem; color:#667085;">No notifications yet.</div>';
                return;
            }

            list.innerHTML = notifications.map(function (n) {
                const bg = n.status ? '#fff' : '#eef4ff';
                return '<div class="notif-item" data-id="' + n.id + '" style="background:' + bg + ';">'
                    + '<div style="font-size:0.83rem; color:#344054;">' + n.message + '</div>'
                    + '<div style="font-size:0.7rem; color:#667085; margin-top:4px;">' + n.time + '</div>'
                    + '</div>';
            }).join('');

            list.querySelectorAll('.notif-item').forEach(function (item) {
                item.addEventListener('click', async function () {
                    const id = this.getAttribute('data-id');
                    try {
                        await fetch('/notifications/' + id + '/read', {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                        });
                        this.style.background = '#fff';
                    } catch (err) {
                        console.error('Mark read error', err);
                    }
                });
            });
        }

        async function pollNotifications() {
            try {
                const res = await fetch('/notifications/poll', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;

                const data = await res.json().catch(() => null);
                if (!data || data.success !== true) return;

                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }

                renderNotifications(data.notifications);
            } catch (e) {
                console.error('Notification poll error', e);
            }
        }

        pollNotifications();
        setInterval(pollNotifications, 5000);
    });
</script>
@endonce