<?php
// Preset archive/delete rules and snapshot edit-isolation.
require __DIR__ . '/lib.php';

t_fresh_db();
t_load_sql('fixtures/legacy_schema.sql');
t_load_sql('fixtures/seed_data.sql');
app_boot();

// --- Edit isolation: editing a preset must not change existing routine steps ---
$before = getRoutineWithTasks(1);
$beforeStep = $before['tasks'][0]; // preset 1 (Brush Teeth), snapshot title/points
updatePresetTask(1, ['title' => 'Brush Teeth v2', 'point_value' => 99, 'time_limit' => 45]);
$after = getRoutineWithTasks(1);
$afterStep = $after['tasks'][0];
t_assert_eq($beforeStep['title'], $afterStep['title'], 'edit isolation: step title unchanged');
t_assert_eq($beforeStep['point_value'], $afterStep['point_value'], 'edit isolation: step points unchanged');
t_assert_eq($beforeStep['time_limit'], $afterStep['time_limit'], 'edit isolation: step timer unchanged');
// ...but a NEW step added after the edit freezes the new values.
$presetRow = getPresetTasksByIds(1, [1])[1];
addStepToRoutine(2, 1, 3, null, 'pending', $presetRow);
$r2 = getRoutineWithTasks(2);
$newStep = null;
foreach ($r2['tasks'] as $s) {
    if ((int) $s['id'] === 1) { $newStep = $s; }
}
t_assert_eq('Brush Teeth v2', $newStep['title'], 'new step uses edited title');
t_assert_eq(99, (int) $newStep['point_value'], 'new step uses edited points');

// Completion history titles also unaffected by the edit.
t_assert_eq(0, (int) t_scalar("SELECT COUNT(*) FROM routine_completion_tasks WHERE preset_task_id = 1 AND task_title <> 'Brush Teeth'"),
    'edit isolation: history titles unchanged');

// --- Archive / restore ---
t_assert(archivePresetTask(2, 1), 'archive preset 2');
t_assert_eq(0, (int) t_scalar("SELECT is_active FROM preset_tasks WHERE id = 2"), 'preset 2 inactive');
t_assert(t_scalar("SELECT archived_at FROM preset_tasks WHERE id = 2") !== null, 'archived_at set');
// Archived preset still renders in existing routines via snapshot/live values.
$r1 = getRoutineWithTasks(1);
t_assert_eq(2, count($r1['tasks']), 'routine keeps archived preset step');
t_assert(restorePresetTask(2, 1), 'restore preset 2');
t_assert_eq(1, (int) t_scalar("SELECT is_active FROM preset_tasks WHERE id = 2"), 'preset 2 active again');

// getPresetTasks default excludes archived
archivePresetTask(2, 1);
$active = getPresetTasks(1);
$all = getPresetTasks(1, true);
t_assert_eq(3, count($active), 'active list excludes archived');
t_assert_eq(4, count($all), 'full list includes archived');

// --- Delete rules ---
// Preset 1 is referenced (routine steps + history) -> archived, not deleted.
t_assert_eq('archived', deletePresetTask(1, 1), 'referenced preset archives on delete');
t_assert_eq(1, (int) t_scalar("SELECT COUNT(*) FROM preset_tasks WHERE id = 1"), 'preset row still exists');
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routine_completion_tasks"), 'history untouched');
// A brand-new unreferenced preset hard-deletes.
createPresetTask(1, 'Throwaway', '', 5, 1, 'household', null, 0, null, null, 1, 'evening');
$newId = (int) t_scalar("SELECT id FROM preset_tasks WHERE title = 'Throwaway'");
t_assert_eq('evening', t_scalar("SELECT default_time_of_day FROM preset_tasks WHERE id = $newId"), 'create stores default_time_of_day');
t_assert_eq('deleted', deletePresetTask($newId, 1), 'unreferenced preset hard-deletes');
t_assert_eq(0, (int) t_scalar("SELECT COUNT(*) FROM preset_tasks WHERE id = $newId"), 'row gone');

// Reference counts
$refs = presetTaskReferenceCounts(3);
t_assert($refs['routine_steps'] >= 1, 'reference counts see routine steps');

t_done();
