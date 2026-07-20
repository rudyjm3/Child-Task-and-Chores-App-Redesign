// js/child-detail.js — Children page interactivity
// History modals (points + stars), adjust modals (points + stars), week schedule modal.
// Ported from dashboard_parent.php inline script; week fetch targets the current page.

document.addEventListener('DOMContentLoaded', function () {
    const setBodyScrollLocked = (locked) => {
        document.body.classList.toggle('modal-open', !!locked);
        document.body.classList.toggle('no-scroll', !!locked);
        document.body.classList.toggle('show-mobile-nav', !!locked);
    };

    // ── History modals (points + stars share the same markup pattern) ──
    const historyModals = Array.from(document.querySelectorAll('[data-child-history-modal]'));
    const applyHistoryFilter = (modal, filter) => {
        const items = Array.from(modal.querySelectorAll('[data-history-item]'));
        const groups = Array.from(modal.querySelectorAll('[data-history-day]'));
        const empty = modal.querySelector('[data-history-empty]');
        if (!items.length) {
            if (empty) empty.style.display = 'none';
            return;
        }
        let anyVisible = false;
        items.forEach(item => {
            const type = (item.dataset.historyType || '').toLowerCase();
            const show = filter === 'all' ? true : type === filter;
            item.style.display = show ? '' : 'none';
            item.dataset.hidden = show ? '0' : '1';
            if (show) anyVisible = true;
        });
        groups.forEach(group => {
            const groupItems = Array.from(group.querySelectorAll('[data-history-item]'));
            const hasVisible = groupItems.some(item => item.dataset.hidden !== '1');
            group.style.display = hasVisible ? '' : 'none';
        });
        if (empty) empty.style.display = anyVisible ? 'none' : 'block';
    };
    document.querySelectorAll('[data-child-history-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const childId = btn.dataset.childHistoryId;
            const kind = btn.dataset.childHistoryKind || 'points';
            const modal = document.querySelector(`[data-child-history-modal][data-child-history-id="${childId}"][data-child-history-kind="${kind}"]`);
            if (!modal) return;
            modal.classList.add('open');
            setBodyScrollLocked(true);
            const filterButtons = Array.from(modal.querySelectorAll('[data-history-filter]'));
            filterButtons.forEach(button => {
                button.classList.toggle('active', (button.dataset.historyFilter || 'all') === 'all');
            });
            applyHistoryFilter(modal, 'all');
        });
    });
    historyModals.forEach((modal) => {
        const closeModal = () => {
            modal.classList.remove('open');
            setBodyScrollLocked(false);
        };
        modal.querySelectorAll('[data-child-history-close]').forEach(btn => btn.addEventListener('click', closeModal));
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        const filterButtons = Array.from(modal.querySelectorAll('[data-history-filter]'));
        if (filterButtons.length) {
            filterButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    filterButtons.forEach(btn => btn.classList.toggle('active', btn === button));
                    applyHistoryFilter(modal, button.dataset.historyFilter || 'all');
                });
            });
            applyHistoryFilter(modal, 'all');
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        historyModals.forEach((modal) => {
            if (modal.classList.contains('open')) {
                modal.classList.remove('open');
                setBodyScrollLocked(false);
            }
        });
    });

    // ── Adjust modals (points + stars) ──
    // Each modal root carries data-adjust-modal="points|stars"; trigger buttons carry
    // data-adjust-open="points|stars" plus child data attributes.
    const setupAdjustModal = (mode) => {
        const modal = document.querySelector(`[data-adjust-modal="${mode}"]`);
        if (!modal) return;
        const childIdInput = modal.querySelector('[data-role="adjust-child-id"]');
        const historyList = modal.querySelector('[data-role="adjust-history-list"]');
        const childNameEl = modal.querySelector('[data-role="adjust-child-name"]');
        const childAvatarEl = modal.querySelector('[data-role="adjust-child-avatar"]');
        const currentEl = modal.querySelector('[data-role="adjust-current-points"]');
        const warningEl = modal.querySelector('[data-role="adjust-points-warning"]');
        const valueInput = modal.querySelector('[data-role="adjust-value-input"]');
        const reasonInput = modal.querySelector('[data-role="adjust-reason-input"]');
        const deltaIcon = mode === 'stars' ? 'fa-solid fa-star' : 'fa-solid fa-coins';
        let baseValue = 0;

        const updateTotal = () => {
            if (!currentEl || !valueInput) return;
            const delta = parseInt(valueInput.value || '0', 10) || 0;
            const total = baseValue + delta;
            currentEl.textContent = Math.max(0, total);
            if (warningEl) {
                warningEl.style.display = total < 0 ? 'block' : 'none';
            }
        };

        const renderHistory = (history) => {
            if (!historyList) return;
            historyList.innerHTML = '';
            if (!history || !history.length) {
                const li = document.createElement('li');
                li.textContent = 'No recent adjustments.';
                historyList.appendChild(li);
                return;
            }
            history.forEach(item => {
                const deltaValue = typeof item.delta_points !== 'undefined' ? item.delta_points : item.delta_stars;
                const li = document.createElement('li');
                const info = document.createElement('div');
                info.className = 'adjust-history-item-info';
                const reason = document.createElement('span');
                reason.textContent = item.reason || 'No reason';
                const meta = document.createElement('span');
                meta.className = 'adjust-history-meta';
                meta.textContent = item.created_at ? new Date(item.created_at).toLocaleString() : '';
                info.appendChild(reason);
                info.appendChild(meta);
                const delta = document.createElement('span');
                delta.className = 'adjust-history-points' + (deltaValue < 0 ? ' is-negative' : '');
                delta.innerHTML = `<i class="${deltaIcon}"></i> ` + (deltaValue >= 0 ? '+' : '') + deltaValue;
                li.appendChild(info);
                li.appendChild(delta);
                historyList.appendChild(li);
            });
        };

        document.querySelectorAll(`[data-adjust-open="${mode}"]`).forEach(btn => {
            btn.addEventListener('click', () => {
                const childName = btn.dataset.childName || 'Child';
                const childAvatar = btn.dataset.childAvatar || 'images/avatar_images/default-avatar.png';
                let history = [];
                try { history = JSON.parse(btn.dataset.history || '[]'); } catch (e) { history = []; }
                if (childNameEl) childNameEl.textContent = childName;
                if (childAvatarEl) { childAvatarEl.src = childAvatar; childAvatarEl.alt = childName; }
                baseValue = parseInt(btn.dataset.childValue || '0', 10) || 0;
                if (currentEl) currentEl.textContent = baseValue;
                if (childIdInput) childIdInput.value = btn.dataset.childId || '';
                if (valueInput) valueInput.value = 1;
                if (reasonInput) reasonInput.value = '';
                renderHistory(history);
                updateTotal();
                modal.classList.add('open');
                setBodyScrollLocked(true);
            });
        });

        const closeModal = () => {
            modal.classList.remove('open');
            setBodyScrollLocked(false);
        };
        modal.querySelectorAll('[data-action="close-adjust"]').forEach(btn => btn.addEventListener('click', closeModal));
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        const decBtn = modal.querySelector('[data-action="decrement-points"]');
        const incBtn = modal.querySelector('[data-action="increment-points"]');
        if (decBtn && valueInput) {
            decBtn.addEventListener('click', () => {
                valueInput.value = (parseInt(valueInput.value || '0', 10) || 0) - 1;
                updateTotal();
            });
        }
        if (incBtn && valueInput) {
            incBtn.addEventListener('click', () => {
                valueInput.value = (parseInt(valueInput.value || '0', 10) || 0) + 1;
                updateTotal();
            });
        }
        if (valueInput) valueInput.addEventListener('input', updateTotal);
    };
    setupAdjustModal('points');
    setupAdjustModal('stars');

    // ── Week schedule modal ──
    const weekModal = document.querySelector('[data-week-modal]');
    const weekModalBody = weekModal ? weekModal.querySelector('[data-week-modal-body]') : null;
    const weekModalTitle = weekModal ? weekModal.querySelector('#week-modal-title') : null;
    const weekModalClose = weekModal ? weekModal.querySelector('[data-week-modal-close]') : null;
    const startOfWeek = (date) => {
        const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const diff = (d.getDay() + 6) % 7;
        d.setDate(d.getDate() - diff);
        d.setHours(0, 0, 0, 0);
        return d;
    };
    const addDays = (date, days) => {
        const d = new Date(date.getTime());
        d.setDate(d.getDate() + days);
        d.setHours(0, 0, 0, 0);
        return d;
    };
    const formatDateKey = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    const formatWeekRange = (startDate) => {
        const endDate = addDays(startDate, 6);
        const options = { month: 'short', day: 'numeric' };
        return `${startDate.toLocaleDateString(undefined, options)} - ${endDate.toLocaleDateString(undefined, options)}`;
    };
    const buildWeekModalSkeleton = (childName) => `
        <section class="task-calendar-section week-modal-calendar">
            <div class="task-calendar-card">
                <div class="calendar-header">
                    <div>
                        <h2>Weekly Calendar</h2>
                        <p class="calendar-subtitle">Tasks and routines for ${childName}.</p>
                    </div>
                    <div class="calendar-nav">
                        <div class="calendar-view-toggle" role="group" aria-label="Calendar view">
                            <button type="button" class="calendar-view-button active" data-calendar-view="calendar" aria-pressed="true" title="Calendar view">
                                <i class="fa-solid fa-calendar-days"></i>
                            </button>
                            <button type="button" class="calendar-view-button" data-calendar-view="list" aria-pressed="false" title="List view">
                                <i class="fa-solid fa-list"></i>
                            </button>
                        </div>
                        <button type="button" class="calendar-nav-button" data-week-nav="-1">Previous Week</button>
                        <div class="calendar-range" data-week-range></div>
                        <button type="button" class="calendar-nav-button" data-week-nav="1">Next Week</button>
                    </div>
                </div>
                <div class="task-week-calendar" data-week-calendar>
                    <div class="task-week-scroll">
                        <div class="week-days week-days-header" data-week-days></div>
                        <div class="week-grid" data-week-grid></div>
                    </div>
                    <div class="calendar-empty" data-calendar-empty>No tasks or routines for this week.</div>
                </div>
                <div class="task-week-list" data-week-list></div>
            </div>
        </section>
    `;
    const openWeekModal = (btn) => {
        if (!weekModal || !weekModalBody) return;
        const childName = btn.getAttribute('data-child-name') || 'Child';
        const childId = parseInt(btn.getAttribute('data-child-id'), 10);
        let schedule = {};
        try {
            schedule = JSON.parse(btn.getAttribute('data-week-schedule') || '{}');
        } catch (e) {
            schedule = {};
        }
        if (weekModalTitle) {
            weekModalTitle.textContent = childName + ' - Week Schedule';
        }
        weekModalBody.innerHTML = buildWeekModalSkeleton(childName);
        const calendarWrap = weekModalBody.querySelector('[data-week-calendar]');
        const listWrap = weekModalBody.querySelector('[data-week-list]');
        const weekDaysEl = weekModalBody.querySelector('[data-week-days]');
        const weekGridEl = weekModalBody.querySelector('[data-week-grid]');
        const weekRangeEl = weekModalBody.querySelector('[data-week-range]');
        const emptyEl = weekModalBody.querySelector('[data-calendar-empty]');
        const viewButtons = Array.from(weekModalBody.querySelectorAll('[data-calendar-view]'));
        const navButtons = Array.from(weekModalBody.querySelectorAll('[data-week-nav]'));
        let currentWeekStart = startOfWeek(new Date());
        let currentView = 'calendar';

        const setView = (view) => {
            currentView = view === 'list' ? 'list' : 'calendar';
            if (calendarWrap) calendarWrap.classList.toggle('is-hidden', currentView === 'list');
            if (listWrap) listWrap.classList.toggle('active', currentView === 'list');
            viewButtons.forEach((button) => {
                const isActive = button.getAttribute('data-calendar-view') === currentView;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        };

        const buildBadge = (item, useTextDual) => {
            if (!item) return null;
            if (item.completed && item.overdue) {
                const group = document.createElement('span');
                group.className = 'calendar-task-badge-group';
                const doneBadge = document.createElement('span');
                doneBadge.className = useTextDual ? 'calendar-task-badge completed' : 'calendar-task-badge completed compact';
                doneBadge.title = 'Done';
                const doneIcon = document.createElement('i');
                doneIcon.className = 'fa-solid fa-check';
                doneBadge.appendChild(doneIcon);
                if (useTextDual) doneBadge.appendChild(document.createTextNode(' Done'));
                const overdueBadge = document.createElement('span');
                overdueBadge.className = useTextDual ? 'calendar-task-badge overdue' : 'calendar-task-badge overdue compact';
                overdueBadge.title = 'Overdue';
                if (useTextDual) {
                    overdueBadge.appendChild(document.createTextNode('Overdue'));
                } else {
                    const overdueIcon = document.createElement('i');
                    overdueIcon.className = 'fa-solid fa-triangle-exclamation';
                    overdueBadge.appendChild(overdueIcon);
                }
                group.appendChild(doneBadge);
                group.appendChild(overdueBadge);
                return group;
            }
            if (item.completed) {
                const badge = document.createElement('span');
                badge.className = 'calendar-task-badge completed';
                badge.title = 'Done';
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-check';
                badge.appendChild(icon);
                badge.appendChild(document.createTextNode(' Done'));
                return badge;
            }
            if (item.overdue) {
                const badge = document.createElement('span');
                badge.className = 'calendar-task-badge overdue';
                badge.title = 'Overdue';
                badge.textContent = 'Overdue';
                return badge;
            }
            return null;
        };

        const buildTaskItem = (item, useTextDual = false) => {
            const wrapper = document.createElement(item.link ? 'a' : 'div');
            wrapper.className = 'calendar-task-item';
            if (item.link) wrapper.href = item.link;
            const header = document.createElement('div');
            header.className = 'calendar-task-header';
            const typeIcon = document.createElement('i');
            typeIcon.className = item.type === 'Routine'
                ? 'fa-solid fa-repeat calendar-task-type-icon is-routine'
                : 'fa-solid fa-list-check calendar-task-type-icon is-task';
            const titleWrap = document.createElement('span');
            titleWrap.className = 'calendar-task-title-wrap';
            const title = document.createElement('span');
            title.className = 'calendar-task-title';
            title.textContent = item.title || 'Item';
            titleWrap.appendChild(title);
            const points = document.createElement('span');
            points.className = 'calendar-task-points';
            points.textContent = `${item.points || 0}`;
            const badge = buildBadge(item, useTextDual);
            header.appendChild(typeIcon);
            header.appendChild(titleWrap);
            header.appendChild(points);
            if (badge) header.appendChild(badge);
            wrapper.appendChild(header);
            if (item.time_label) {
                const meta = document.createElement('span');
                meta.className = 'calendar-task-meta';
                const metaIcon = document.createElement('i');
                metaIcon.className = 'fa-solid fa-clock';
                meta.appendChild(metaIcon);
                meta.appendChild(document.createTextNode(` ${item.time_label}`));
                wrapper.appendChild(meta);
            }
            return wrapper;
        };

        const renderList = (weekDates) => {
            if (!listWrap) return 0;
            listWrap.innerHTML = '';
            const todayKey = formatDateKey(new Date());
            let totalItems = 0;
            const sections = [
                { key: 'anytime', label: 'Due Today' },
                { key: 'morning', label: 'Morning' },
                { key: 'afternoon', label: 'Afternoon' },
                { key: 'evening', label: 'Evening' }
            ];
            weekDates.forEach(({ date, dateKey }) => {
                const items = (schedule[dateKey] || []).slice();
                items.sort((a, b) => {
                    const timeCompare = (a.time || '99:99').localeCompare(b.time || '99:99');
                    if (timeCompare !== 0) return timeCompare;
                    return String(a.title || '').localeCompare(String(b.title || ''));
                });
                totalItems += items.length;
                const dayCard = document.createElement('div');
                dayCard.className = `week-list-day${dateKey === todayKey ? ' is-today' : ''}`;
                const header = document.createElement('div');
                header.className = 'week-list-day-header';
                const name = document.createElement('span');
                name.className = 'week-list-day-name';
                name.textContent = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getDay()];
                const dateLabel = document.createElement('span');
                dateLabel.className = 'week-list-day-date';
                dateLabel.textContent = date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                header.appendChild(name);
                header.appendChild(dateLabel);
                dayCard.appendChild(header);
                const sectionsWrap = document.createElement('div');
                sectionsWrap.className = 'week-list-sections';
                sections.forEach((section) => {
                    const sectionItems = items.filter((entry) => (entry.time_of_day || 'anytime') === section.key);
                    if (!sectionItems.length) return;
                    const sectionWrap = document.createElement('div');
                    const sectionTitle = document.createElement('div');
                    sectionTitle.className = 'week-list-section-title';
                    sectionTitle.textContent = section.label;
                    const itemsWrap = document.createElement('div');
                    itemsWrap.className = 'week-list-items';
                    sectionItems.forEach((entry) => {
                        itemsWrap.appendChild(buildTaskItem(entry, true));
                    });
                    sectionWrap.appendChild(sectionTitle);
                    sectionWrap.appendChild(itemsWrap);
                    sectionsWrap.appendChild(sectionWrap);
                });
                if (!sectionsWrap.childElementCount) {
                    const empty = document.createElement('div');
                    empty.className = 'week-list-empty';
                    empty.textContent = 'No tasks or routines';
                    dayCard.appendChild(empty);
                } else {
                    dayCard.appendChild(sectionsWrap);
                }
                listWrap.appendChild(dayCard);
            });
            return totalItems;
        };

        const renderWeek = () => {
            if (!weekDaysEl || !weekGridEl) return;
            weekDaysEl.innerHTML = '';
            weekGridEl.innerHTML = '';
            const weekDates = [];
            const todayKey = formatDateKey(new Date());
            for (let i = 0; i < 7; i += 1) {
                const date = addDays(currentWeekStart, i);
                const dateKey = formatDateKey(date);
                weekDates.push({ date, dateKey });
                const dayCell = document.createElement('div');
                dayCell.className = `week-day${dateKey === todayKey ? ' is-today' : ''}`;
                const nameSpan = document.createElement('span');
                nameSpan.className = 'week-day-name';
                nameSpan.textContent = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][date.getDay()];
                const numSpan = document.createElement('span');
                numSpan.className = 'week-day-num';
                numSpan.textContent = date.getDate();
                dayCell.appendChild(nameSpan);
                dayCell.appendChild(numSpan);
                weekDaysEl.appendChild(dayCell);
            }
            let totalItems = 0;
            weekDates.forEach(({ dateKey }) => {
                const items = (schedule[dateKey] || []).slice();
                items.sort((a, b) => {
                    const timeCompare = (a.time || '99:99').localeCompare(b.time || '99:99');
                    if (timeCompare !== 0) return timeCompare;
                    return String(a.title || '').localeCompare(String(b.title || ''));
                });
                totalItems += items.length;
                const column = document.createElement('div');
                column.className = 'week-column';
                const list = document.createElement('div');
                list.className = 'week-column-tasks';
                if (!items.length) {
                    const empty = document.createElement('div');
                    empty.className = 'calendar-day-empty';
                    empty.textContent = 'No items';
                    list.appendChild(empty);
                } else {
                    items.forEach((entry) => {
                        list.appendChild(buildTaskItem(entry, false));
                    });
                }
                column.appendChild(list);
                weekGridEl.appendChild(column);
            });
            if (weekRangeEl) weekRangeEl.textContent = formatWeekRange(currentWeekStart);
            if (emptyEl) emptyEl.classList.toggle('active', totalItems === 0);
            renderList(weekDates);
        };

        viewButtons.forEach((button) => {
            button.addEventListener('click', () => setView(button.getAttribute('data-calendar-view')));
        });
        const fetchWeekSchedule = async (startDate) => {
            if (!childId || Number.isNaN(childId)) return schedule;
            const params = new URLSearchParams({
                week_schedule: '1',
                child_id: String(childId),
                week_start: formatDateKey(startDate)
            });
            const response = await fetch(`${window.location.pathname}?${params.toString()}`, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error('Failed to load week schedule.');
            }
            const payload = await response.json();
            return payload.week_schedule || {};
        };
        const loadWeekSchedule = async () => {
            try {
                const newSchedule = await fetchWeekSchedule(currentWeekStart);
                if (newSchedule) schedule = newSchedule;
            } catch (e) {
                // Keep existing schedule on failure.
            }
            renderWeek();
        };
        navButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const delta = parseInt(button.getAttribute('data-week-nav'), 10);
                if (Number.isNaN(delta)) return;
                currentWeekStart = addDays(currentWeekStart, delta * 7);
                loadWeekSchedule();
            });
        });

        setView(currentView);
        loadWeekSchedule();
        weekModal.classList.add('open');
        document.body.classList.add('modal-open');
    };
    document.querySelectorAll('[data-week-view]').forEach((btn) => {
        btn.addEventListener('click', () => openWeekModal(btn));
    });
    if (weekModal && weekModalClose) {
        const closeWeekModal = () => {
            weekModal.classList.remove('open');
            document.body.classList.remove('modal-open');
        };
        weekModalClose.addEventListener('click', closeWeekModal);
        weekModal.addEventListener('click', (e) => { if (e.target === weekModal) closeWeekModal(); });
    }
});
