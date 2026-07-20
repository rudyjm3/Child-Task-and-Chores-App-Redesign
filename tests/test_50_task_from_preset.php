<?php
// Individual tasks created from a Preset Task: provenance + snapshot isolation.
require __DIR__ . '/lib.php';

t_fresh_db();
t_load_sql('fixtures/legacy_schema.sql');
t_load_sql('fixtures/seed_data.sql');
app_boot();

// Create a task for child 2 from preset 1 (Brush Teeth: 10 pts, 5 min, hygiene)
$preset = getPresetTasksByIds(1, [1])[1];
$ok = createTask(1, 2, $preset['title'], $preset['description'], date('Y-m-d') . ' 08:00:00', null,
    (int) $preset['point_value'], '', null, $preset['category'], 'timer', (int) $preset['time_limit'],
    'morning', 0, 1, (int) $preset['id']);
t_assert($ok, 'task created from preset');
$taskId = (int) t_scalar("SELECT id FROM tasks WHERE preset_task_id = 1 AND child_user_id = 2");
t_assert($taskId > 0, 'task row has preset provenance');

// Same preset assigned again (evening) for the same child - allowed.
createTask(1, 2, $preset['title'], $preset['description'], date('Y-m-d') . ' 19:00:00', null,
    (int) $preset['point_value'], '', null, $preset['category'], 'timer', (int) $preset['time_limit'],
    'evening', 0, 1, (int) $preset['id']);
t_assert_eq(2, (int) t_scalar("SELECT COUNT(*) FROM tasks WHERE preset_task_id = 1 AND child_user_id = 2"),
    'same preset can be assigned morning and evening');
$tods = t_rows("SELECT time_of_day FROM tasks WHERE preset_task_id = 1 AND child_user_id = 2 ORDER BY id");
t_assert_eq(['morning', 'evening'], array_column($tods, 'time_of_day'), 'assignments keep their own time-of-day');

// Editing the preset later must NOT change the assignments (snapshot on tasks row).
updatePresetTask(1, ['title' => 'Renamed Preset', 'point_value' => 55]);
t_assert_eq($preset['title'], t_scalar("SELECT title FROM tasks WHERE id = $taskId"), 'task title frozen after preset edit');
t_assert_eq((int) $preset['point_value'], (int) t_scalar("SELECT points FROM tasks WHERE id = $taskId"), 'task points frozen after preset edit');

// Archiving the preset leaves assignments alone.
archivePresetTask(1, 1);
t_assert_eq(2, (int) t_scalar("SELECT COUNT(*) FROM tasks WHERE preset_task_id = 1"), 'assignments survive preset archive');

// Hard-deleting a preset (simulated direct delete) nulls provenance but keeps the task.
$db = t_db();
$db->exec("DELETE FROM routine_preset_tasks WHERE preset_task_id = 1");
$db->exec("DELETE FROM preset_tasks WHERE id = 1");
t_assert_eq(2, (int) t_scalar("SELECT COUNT(*) FROM tasks WHERE title = '" . $preset['title'] . "'"), 'tasks survive preset hard delete');
t_assert_eq(2, (int) t_scalar("SELECT COUNT(*) FROM tasks WHERE preset_task_id IS NULL AND title = '" . $preset['title'] . "'"), 'provenance set null');

// A custom task may share a preset's name; provenance stays null.
createTask(1, 3, 'Make Bed', 'custom duplicate name', date('Y-m-d') . ' 23:59:00', null, 5, '', null, 'household', 'no_limit', null, 'anytime', 0, 1);
t_assert_eq(1, (int) t_scalar("SELECT COUNT(*) FROM tasks WHERE title = 'Make Bed' AND preset_task_id IS NULL"), 'custom task with preset name allowed');

t_done();
