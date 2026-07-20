<?php
// Unit tests for the centralized time-of-day helpers.
require __DIR__ . '/lib.php';

t_fresh_db();
app_boot(); // bootstrap fresh schema; helpers live in functions.php

// Boundaries
t_assert_eq('morning', timeOfDayFromTime('00:00'), '00:00 -> morning');
t_assert_eq('morning', timeOfDayFromTime('11:59'), '11:59 -> morning');
t_assert_eq('afternoon', timeOfDayFromTime('12:00'), '12:00 -> afternoon');
t_assert_eq('afternoon', timeOfDayFromTime('16:59'), '16:59 -> afternoon');
t_assert_eq('evening', timeOfDayFromTime('17:00'), '17:00 -> evening');
t_assert_eq('evening', timeOfDayFromTime('23:30'), '23:30 -> evening');
t_assert_eq('anytime', timeOfDayFromTime(null), 'null -> anytime');
t_assert_eq('anytime', timeOfDayFromTime(''), 'empty -> anytime');
t_assert_eq('anytime', timeOfDayFromTime('not-a-time'), 'garbage -> anytime');
t_assert_eq('morning', timeOfDayFromTime('2026-07-20 08:30:00'), 'datetime -> morning');

// Order and labels
t_assert_eq(['morning', 'afternoon', 'evening', 'anytime'], timeOfDayOrder(), 'group order');
t_assert_eq('Morning', timeOfDayLabel('morning'), 'label morning');
t_assert_eq('Anytime', timeOfDayLabel('bogus'), 'unknown label -> Anytime');

// Grouping (unknown values land in anytime; order preserved)
$items = [
    ['title' => 'B', 'time_of_day' => 'evening'],
    ['title' => 'A', 'time_of_day' => 'morning'],
    ['title' => 'C', 'time_of_day' => 'weird'],
    ['title' => 'D'],
];
$groups = groupByTimeOfDay($items);
t_assert_eq(1, count($groups['morning']), 'one morning item');
t_assert_eq(1, count($groups['evening']), 'one evening item');
t_assert_eq(2, count($groups['anytime']), 'unknown + missing -> anytime');
t_assert_eq(['morning', 'afternoon', 'evening', 'anytime'], array_keys($groups), 'group keys in display order');

// Within-group sorting: due time, then sequence order, then title
$sorted = sortTasksForTimeOfDayDisplay([
    ['title' => 'Zeta', 'time_of_day' => 'morning'],
    ['title' => 'Alpha', 'time_of_day' => 'morning'],
    ['title' => 'Timed late', 'time_of_day' => 'morning', 'due_date' => '2026-07-20 09:00:00'],
    ['title' => 'Timed early', 'time_of_day' => 'morning', 'due_date' => '2026-07-20 07:00:00'],
    ['title' => 'Step 2', 'time_of_day' => 'morning', 'sequence_order' => 2],
    ['title' => 'Step 1', 'time_of_day' => 'morning', 'sequence_order' => 1],
    ['title' => 'Any', 'time_of_day' => 'anytime'],
]);
$titles = array_column($sorted, 'title');
t_assert_eq(['Timed early', 'Timed late', 'Step 1', 'Step 2', 'Alpha', 'Zeta', 'Any'], $titles,
    'within-group sort: time, then step order, then title; anytime last');
t_assert_eq('morning', $sorted[0]['_tod_group'], 'items tagged with group');

t_done();
