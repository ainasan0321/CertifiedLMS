export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');
    if (!root) return;

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const tabAll = root.querySelector('[data-notification-popover-tab="all"]');
    const tabUnread = root.querySelector('[data-notification-popover-tab="unread"]');
    const tabUnreadCount = root.querySelector('[data-notification-popover-unread-count]');
    const markAllBtn = root.querySelector('[data-notification-popover-mark-all]');
    const loadingEl = root.querySelector('[data-notification-popover-loading]');
    const emptyEl = root.querySelector('[data-notification-popover-empty]');
    const itemsEl = root.querySelector('[data-notification-popover-items]');
    const template = root.querySelector('[data-notification-popover-row-template]');

    if (!trigger || !panel || !template) return;

    let isOpen = false;
    let currentTab = 'all';
    let notifications = [];
    let csrfReady = false;

    const getCookie = (name) => {
        const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[2]) : '';
    };

    const ensureCsrf = async () => {
        if (csrfReady && getCookie('XSRF-TOKEN')) return;
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
        csrfReady = true;
    };

    const updateUnreadCountUI = (count) => {
        const safeCount = Math.max(0, Number(count) || 0);
        if (badge) {
            badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
            badge.classList.toggle('hidden', safeCount <= 0);
        }
        if (tabUnreadCount) {
            tabUnreadCount.textContent = String(safeCount);
        }
        trigger.setAttribute('aria-label', `通知 (${safeCount} 件未読)`);
    };

    const renderList = () => {
        const filtered = currentTab === 'unread'
            ? notifications.filter((n) => n.is_unread)
            : notifications;

        itemsEl.innerHTML = '';

        if (filtered.length === 0) {
            emptyEl?.classList.remove('hidden');
            return;
        }
        emptyEl?.classList.add('hidden');

        filtered.forEach((item) => {
            const clone = template.content.cloneNode(true);
            const row = clone.querySelector('[data-notification-popover-row]');
            const dot = clone.querySelector('[data-notification-popover-row-dot]');
            const title = clone.querySelector('[data-notification-popover-row-title]');
            const message = clone.querySelector('[data-notification-popover-row-message]');
            const time = clone.querySelector('[data-notification-popover-row-time]');

            row.href = item.redirect_url;
            row.dataset.unread = item.is_unread ? 'true' : 'false';
            row.setAttribute('aria-data-unread', item.is_unread ? 'true' : 'false');
            if (!item.is_unread && dot) {
                dot.classList.add('invisible');
            }
            title.textContent = item.title;
            message.textContent = item.body;
            time.textContent = item.created_at_human;

            row.addEventListener('click', async (e) => {
                e.preventDefault();
                if (item.is_unread) {
                    try {
                        await ensureCsrf();
                        const res = await fetch(`/api/v1/notifications/${item.id}/read`, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
                            },
                        });
                        if (res.ok) {
                            const data = await res.json();
                            updateUnreadCountUI(data.unread_count);
                        }
                    } catch (_) { }
                }
                window.location.href = item.redirect_url;
            });

            itemsEl.appendChild(clone);
        });
    };

    const fetchNotifications = async () => {
        loadingEl?.classList.remove('hidden');
        emptyEl?.classList.add('hidden');
        itemsEl.innerHTML = '';

        try {
            const res = await fetch('/api/v1/notifications', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            notifications = data.notifications || [];
            updateUnreadCountUI(data.unread_count);
            renderList();
        } finally {
            loadingEl?.classList.add('hidden');
        }
    };

    const openPanel = () => {
        isOpen = true;
        trigger.setAttribute('aria-expanded', 'true');
        panel.style.display = 'flex';
        panel.classList.remove('hidden');
        requestAnimationFrame(() => {
            panel.classList.remove('opacity-0', '-translate-y-1');
        });
        fetchNotifications();
    };

    const closePanel = () => {
        isOpen = false;
        trigger.setAttribute('aria-expanded', 'false');
        panel.classList.add('opacity-0', '-translate-y-1');
        setTimeout(() => {
            if (!isOpen) {
                panel.classList.add('hidden');
                panel.style.display = 'none';
            }
        }, 150);
    };

    trigger.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        isOpen ? closePanel() : openPanel();
    });

    tabAll?.addEventListener('click', () => {
        currentTab = 'all';
        tabAll.setAttribute('aria-selected', 'true');
        tabUnread?.setAttribute('aria-selected', 'false');
        renderList();
    });

    tabUnread?.addEventListener('click', () => {
        currentTab = 'unread';
        tabUnread.setAttribute('aria-selected', 'true');
        tabAll?.setAttribute('aria-selected', 'false');
        renderList();
    });

    markAllBtn?.addEventListener('click', async () => {
        await ensureCsrf();
        const res = await fetch('/api/v1/notifications/read-all', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
        });
        if (res.ok) {
            notifications = notifications.map((n) => ({ ...n, is_unread: false }));
            updateUnreadCountUI(0);
            renderList();
        }
    });

    document.addEventListener('click', (e) => {
        if (isOpen && !root.contains(e.target)) closePanel();
    });

    document.addEventListener('keydown', (e) => {
        if (isOpen && e.key === 'Escape') closePanel();
    });
}