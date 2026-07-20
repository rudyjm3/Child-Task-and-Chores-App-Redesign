// time-of-day.js - Shared time-of-day grouping helpers (JS mirror of the PHP
// helpers in includes/functions.php). Boundaries: morning < 12:00, afternoon
// 12:00-16:59, evening >= 17:00, anytime = no specific time.
(function (global) {
    'use strict';

    var ORDER = ['morning', 'afternoon', 'evening', 'anytime'];

    var LABELS = {
        morning: 'Morning',
        afternoon: 'Afternoon',
        evening: 'Evening',
        anytime: 'Anytime'
    };

    var ICONS = {
        morning: 'fa-sun',
        afternoon: 'fa-cloud-sun',
        evening: 'fa-moon',
        anytime: 'fa-clock'
    };

    function normalize(timeOfDay) {
        return ORDER.indexOf(timeOfDay) !== -1 ? timeOfDay : 'anytime';
    }

    // Accepts 'HH:MM', 'HH:MM:SS', or a Date; returns the group name.
    function fromTime(time) {
        if (time === null || time === undefined || time === '') {
            return 'anytime';
        }
        var hour = null;
        if (time instanceof Date) {
            hour = time.getHours();
        } else {
            var match = /^(\d{1,2})[:h]/.exec(String(time).trim());
            if (!match) {
                return 'anytime';
            }
            hour = parseInt(match[1], 10);
        }
        if (isNaN(hour)) return 'anytime';
        if (hour < 12) return 'morning';
        if (hour < 17) return 'afternoon';
        return 'evening';
    }

    // Groups items into {morning: [], afternoon: [], evening: [], anytime: []}
    // in display order. getter maps an item to its time_of_day (defaults to
    // item.time_of_day).
    function group(items, getter) {
        var groups = {};
        ORDER.forEach(function (key) { groups[key] = []; });
        (items || []).forEach(function (item) {
            var tod = normalize(getter ? getter(item) : (item && item.time_of_day));
            groups[tod].push(item);
        });
        return groups;
    }

    global.TimeOfDay = {
        ORDER: ORDER,
        LABELS: LABELS,
        ICONS: ICONS,
        normalize: normalize,
        fromTime: fromTime,
        group: group,
        label: function (tod) { return LABELS[normalize(tod)]; },
        icon: function (tod) { return ICONS[normalize(tod)]; }
    };
})(window);
