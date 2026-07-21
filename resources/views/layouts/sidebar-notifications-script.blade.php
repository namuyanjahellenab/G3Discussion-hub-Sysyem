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

        // The sidebar panel is `position: sticky`, which creates its own
        // stacking context — the dropdown's z-index only wins inside that
        // context, so it was rendering behind the main content instead of
        // on top of it (visible only as a thin sliver, needing a scroll/
        // squint to read). Moving it to <body> and positioning it as
        // `fixed` from the bell's actual screen position escapes that
        // context entirely, so it always renders on top, fully visible.
        function openDropdown() {
            if (dropdown.parentElement !== document.body) {
                document.body.appendChild(dropdown);
            }
            const rect = document.getElementById('notification-bell-wrapper').getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top = (rect.bottom + 6) + 'px';
            dropdown.style.left = Math.max(8, rect.right - 300) + 'px';
            dropdown.style.zIndex = 3000;
            dropdown.style.display = 'block';
        }

        function closeDropdown() {
            dropdown.style.display = 'none';
        }

        bell.addEventListener('click', function (e) {
            e.stopPropagation();
            if (dropdown.style.display === 'block') {
                closeDropdown();
            } else {
                openDropdown();
                markAllSeen();
            }
        });

        // Opening the dropdown means the user has seen the notifications,
        // so the unread badge should clear immediately rather than waiting
        // for the user to click "mark all read" or individual items.
        async function markAllSeen() {
            badge.style.display = 'none';
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
                console.error('Mark all seen error', err);
            }
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#notification-bell-wrapper') && !e.target.closest('#notification-dropdown')) {
                closeDropdown();
            }
        });

        window.addEventListener('resize', closeDropdown);

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
                        // Previously only recolored this one row - the badge
                        // count and every other unread item stayed stale
                        // until the next 5s poll happened to catch up.
                        // markAllBtn already re-polls right after marking
                        // read for the same reason; do the same here.
                        pollNotifications();
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