// preset-picker.js - Shared "Pick a Preset Task" modal.
// Fetches presets from preset_tasks_api.php and lets the parent search,
// filter by category, and pick one. Used by the individual task form
// (task.php) and the routine builder (routine.php).
//
// Usage:
//   var picker = PresetPicker.create({
//       onSelect: function (preset) { ... },       // required
//       getDisabledIds: function () { return []; } // optional: ids to disable
//       disabledNote: 'Already in this routine'    // optional badge text
//   });
//   picker.open();
(function (global) {
    'use strict';

    var API_URL = 'preset_tasks_api.php';

    var CATEGORY_LABELS = { all: 'All', hygiene: 'Hygiene', homework: 'Homework', household: 'Household' };

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function create(options) {
        options = options || {};
        var onSelect = typeof options.onSelect === 'function' ? options.onSelect : function () {};
        var getDisabledIds = typeof options.getDisabledIds === 'function' ? options.getDisabledIds : function () { return []; };
        var disabledNote = options.disabledNote || 'Already added';

        var state = {
            presets: null,
            loading: false,
            error: null,
            search: '',
            category: 'all',
            open: false,
            lastFocus: null
        };

        // --- Build DOM (once, appended lazily on first open) ---
        var overlay = el('div', 'preset-picker-overlay');
        overlay.setAttribute('aria-hidden', 'true');

        var modal = el('div', 'preset-picker');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'preset-picker-title');

        var header = el('header', 'preset-picker__header');
        var title = el('h3', 'preset-picker__title', 'Pick a Preset Task');
        title.id = 'preset-picker-title';
        var closeBtn = el('button', 'preset-picker__close');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Close preset task picker');
        closeBtn.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
        header.appendChild(title);
        header.appendChild(closeBtn);

        var controls = el('div', 'preset-picker__controls');
        var searchWrap = el('div', 'preset-picker__search');
        var searchIcon = el('span', 'preset-picker__search-icon');
        searchIcon.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';
        var searchInput = el('input', 'preset-picker__search-input');
        searchInput.type = 'search';
        searchInput.placeholder = 'Search preset tasks…';
        searchInput.setAttribute('aria-label', 'Search preset tasks by name');
        searchWrap.appendChild(searchIcon);
        searchWrap.appendChild(searchInput);
        var chips = el('div', 'preset-picker__chips');
        chips.setAttribute('role', 'group');
        chips.setAttribute('aria-label', 'Filter by category');
        Object.keys(CATEGORY_LABELS).forEach(function (key) {
            var chip = el('button', 'preset-picker__chip' + (key === 'all' ? ' is-active' : ''), CATEGORY_LABELS[key]);
            chip.type = 'button';
            chip.dataset.category = key;
            chip.setAttribute('aria-pressed', key === 'all' ? 'true' : 'false');
            chips.appendChild(chip);
        });
        controls.appendChild(searchWrap);
        controls.appendChild(chips);

        var body = el('div', 'preset-picker__body');

        modal.appendChild(header);
        modal.appendChild(controls);
        modal.appendChild(body);
        overlay.appendChild(modal);

        var attached = false;
        function attach() {
            if (!attached) {
                document.body.appendChild(overlay);
                attached = true;
            }
        }

        // --- Rendering ---
        function render() {
            body.innerHTML = '';
            if (state.loading) {
                var loading = el('div', 'preset-picker__state');
                loading.innerHTML = '<span class="preset-picker__spinner" aria-hidden="true"></span> Loading preset tasks…';
                body.appendChild(loading);
                return;
            }
            if (state.error) {
                var errorBox = el('div', 'preset-picker__state preset-picker__state--error');
                errorBox.appendChild(el('p', null, state.error));
                var retry = el('button', 'button secondary', 'Try Again');
                retry.type = 'button';
                retry.addEventListener('click', load);
                errorBox.appendChild(retry);
                body.appendChild(errorBox);
                return;
            }
            var presets = state.presets || [];
            var query = state.search.trim().toLowerCase();
            var filtered = presets.filter(function (preset) {
                if (state.category !== 'all' && preset.category !== state.category) return false;
                if (query && preset.title.toLowerCase().indexOf(query) === -1) return false;
                return true;
            });
            if (!filtered.length) {
                var empty = el('div', 'preset-picker__state');
                empty.appendChild(el('p', null, presets.length
                    ? 'No preset tasks match your search.'
                    : 'No preset tasks yet. Create one from the Preset Tasks screen, or make a custom task instead.'));
                body.appendChild(empty);
                return;
            }
            var disabledIds = (getDisabledIds() || []).map(Number);
            var list = el('div', 'preset-picker__list');
            filtered.forEach(function (preset) {
                var isDisabled = disabledIds.indexOf(Number(preset.id)) !== -1;
                var card = el('button', 'preset-picker__item' + (isDisabled ? ' is-disabled' : ''));
                card.type = 'button';
                if (isDisabled) {
                    card.disabled = true;
                }
                var main = el('span', 'preset-picker__item-main');
                var titleRow = el('span', 'preset-picker__item-title', preset.title);
                if (isDisabled) {
                    titleRow.appendChild(el('span', 'preset-picker__item-note', disabledNote));
                }
                main.appendChild(titleRow);
                var metaParts = [];
                if (preset.time_limit) metaParts.push(preset.time_limit + ' min');
                metaParts.push(CATEGORY_LABELS[preset.category] || preset.category);
                if (global.TimeOfDay && preset.default_time_of_day && preset.default_time_of_day !== 'anytime') {
                    metaParts.push(global.TimeOfDay.label(preset.default_time_of_day));
                }
                main.appendChild(el('span', 'preset-picker__item-meta', metaParts.join(' · ')));
                if (preset.description) {
                    main.appendChild(el('span', 'preset-picker__item-desc', preset.description));
                }
                var points = el('span', 'preset-picker__item-points');
                points.innerHTML = '<i class="fa-solid fa-coins" aria-hidden="true"></i> ' + preset.point_value;
                card.appendChild(main);
                card.appendChild(points);
                card.addEventListener('click', function () {
                    if (isDisabled) return;
                    close();
                    onSelect(preset);
                });
                list.appendChild(card);
            });
            body.appendChild(list);
        }

        // --- Data ---
        function load() {
            state.loading = true;
            state.error = null;
            render();
            fetch(API_URL, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (payload) {
                    state.presets = payload.presets || [];
                    state.loading = false;
                    render();
                })
                .catch(function () {
                    state.loading = false;
                    state.error = 'Could not load preset tasks. Check your connection and try again.';
                    render();
                });
        }

        // --- Open/close ---
        function open() {
            attach();
            state.lastFocus = document.activeElement;
            state.open = true;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            if (state.presets === null && !state.loading) {
                load();
            } else {
                render();
            }
            searchInput.focus();
        }

        function close() {
            state.open = false;
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
                state.lastFocus.focus();
            }
        }

        // --- Events ---
        closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) close();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && state.open) close();
        });
        searchInput.addEventListener('input', function () {
            state.search = searchInput.value;
            render();
        });
        chips.addEventListener('click', function (event) {
            var chip = event.target.closest('.preset-picker__chip');
            if (!chip) return;
            state.category = chip.dataset.category || 'all';
            Array.prototype.forEach.call(chips.children, function (node) {
                var active = node === chip;
                node.classList.toggle('is-active', active);
                node.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            render();
        });
        // Basic focus trap within the dialog.
        modal.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab') return;
            var focusable = modal.querySelectorAll('button:not([disabled]), input');
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        return { open: open, close: close, reload: load };
    }

    global.PresetPicker = { create: create };
})(window);
