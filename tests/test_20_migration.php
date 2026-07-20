<?php
// Verifies the preset_tasks_v1 migration on a legacy-schema database:
// schema shape, data preservation, snapshot backfill, and idempotent re-run.
require __DIR__ . '/lib.php';

t_fresh_db();
t_load_sql('fixtures/legacy_schema.sql');
t_load_sql('fixtures/seed_data.sql');
app_boot(); // include-time bootstrap runs migratePresetTasksSchema()

// --- Schema shape ---
t_assert(t_table_exists('preset_tasks'), 'preset_tasks exists');
t_assert(!t_table_exists('routine_tasks'), 'routine_tasks renamed away');
t_assert(t_table_exists('routine_preset_tasks'), 'routine_preset_tasks exists');
t_assert(!t_table_exists('routines_routine_tasks'), 'routines_routine_tasks renamed away');
t_assert(t_column_exists('routine_preset_tasks', 'preset_task_id'), 'junction column renamed');
t_assert(!t_column_exists('routine_preset_tasks', 'routine_task_id'), 'old junction column gone');
t_assert(t_column_exists('routine_preset_tasks', 'title'), 'junction snapshot title column');
t_assert(t_column_exists('routine_completion_tasks', 'preset_task_id'), 'completion column renamed');
t_assert(t_column_exists('routine_completion_tasks', 'task_title'), 'completion task_title column');
t_assert(t_column_exists('routine_overtime_logs', 'preset_task_id'), 'overtime column renamed');
t_assert(t_column_exists('tasks', 'preset_task_id'), 'tasks.preset_task_id added');
t_assert(t_column_exists('preset_tasks', 'default_time_of_day'), 'preset default_time_of_day');
t_assert(t_column_exists('preset_tasks', 'is_active'), 'preset is_active');
t_assert(t_column_exists('preset_tasks', 'minimum_seconds'), 'preset minimum_seconds declared');

// --- Data preservation ---
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM preset_tasks"), 'all library rows preserved');
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routine_preset_tasks"), 'all junction rows preserved');
t_assert_eq(2, (int) t_scalar("SELECT COUNT(*) FROM routines"), 'routines preserved');
t_assert_eq(3, (int) t_scalar("SELECT COUNT(*) FROM tasks"), 'tasks preserved');
t_assert_eq(2, (int) t_scalar("SELECT COUNT(*) FROM task_instances"), 'task instances preserved');
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routine_completion_tasks"), 'completion history preserved');
t_assert_eq(2, (int) t_scalar("SELECT COUNT(*) FROM routine_points_logs"), 'points logs preserved');
t_assert_eq(75, (int) t_scalar("SELECT total_points FROM child_points WHERE child_user_id = 2"), 'points balance preserved');
t_assert_eq(1, (int) t_scalar("SELECT COUNT(*) FROM routine_overtime_logs"), 'overtime log preserved');
t_assert_eq(3, (int) t_scalar("SELECT COUNT(*) FROM goals"), 'goals preserved');

// Sequence order preserved
$order = t_rows("SELECT preset_task_id, sequence_order FROM routine_preset_tasks WHERE routine_id = 1 ORDER BY sequence_order");
t_assert_eq([['preset_task_id' => 1, 'sequence_order' => 1], ['preset_task_id' => 2, 'sequence_order' => 2]],
    array_map(fn($r) => ['preset_task_id' => (int)$r['preset_task_id'], 'sequence_order' => (int)$r['sequence_order']], $order),
    'routine 1 step order preserved');

// Dependency preserved
t_assert_eq(3, (int) t_scalar("SELECT dependency_id FROM routine_preset_tasks WHERE routine_id = 2 AND preset_task_id = 4"), 'dependency preserved');

// --- Snapshot backfill equals live values ---
$mismatch = (int) t_scalar("SELECT COUNT(*) FROM routine_preset_tasks rps JOIN preset_tasks pt ON pt.id = rps.preset_task_id
    WHERE rps.title <> pt.title OR rps.point_value <> pt.point_value OR rps.time_limit <> pt.time_limit");
t_assert_eq(0, $mismatch, 'junction snapshots equal live preset values');
$noTitle = (int) t_scalar("SELECT COUNT(*) FROM routine_completion_tasks WHERE task_title IS NULL");
t_assert_eq(0, $noTitle, 'completion history titles backfilled');

// Presets active by default after migration
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM preset_tasks WHERE is_active = 1"), 'all presets active');

// Marker written
t_assert_eq(1, (int) t_scalar("SELECT COUNT(*) FROM schema_migrations WHERE name = 'preset_tasks_v1'"), 'migration marker present');

// --- Idempotent re-run (fresh process would be identical; call directly) ---
global $db;
migratePresetTasksSchema($db);
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM preset_tasks"), 'second run is a no-op');

// --- FK behavior: deleting a preset must not delete completion history ---
// Preset 4 is only used by routine 2 (no completion history references it).
$db->exec("DELETE FROM routine_preset_tasks WHERE preset_task_id = 4");
$db->exec("DELETE FROM preset_tasks WHERE id = 4");
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routine_completion_tasks"), 'history intact after preset delete');
// Hard-deleting a preset WITH history must keep rows (FK SET NULL) and titles.
$db->exec("DELETE FROM routine_preset_tasks WHERE preset_task_id = 1");
$db->exec("DELETE FROM preset_tasks WHERE id = 1");
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routine_completion_tasks"), 'history rows survive preset hard delete');
t_assert_eq(2, (int) t_scalar("SELECT COUNT(*) FROM routine_completion_tasks WHERE preset_task_id IS NULL"), 'history FK set null');
t_assert_eq('Brush Teeth', t_scalar("SELECT task_title FROM routine_completion_tasks WHERE preset_task_id IS NULL LIMIT 1"), 'history keeps snapshot title');
t_assert_eq(1, (int) t_scalar("SELECT COUNT(*) FROM routine_overtime_logs"), 'overtime log survives deletes');

t_done();
