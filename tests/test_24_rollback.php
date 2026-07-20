<?php
// Round-trip: legacy fixture -> migrate (app boot) -> rollback script -> assert
// the legacy schema shape and data are restored.
require __DIR__ . '/lib.php';

t_fresh_db();
t_load_sql('fixtures/legacy_schema.sql');
t_load_sql('fixtures/seed_data.sql');
app_boot(); // migrates

t_assert(t_table_exists('preset_tasks'), 'precondition: migrated');

$out = [];
$exit = 0;
exec('php ' . escapeshellarg(dirname(__DIR__) . '/scripts/rollback_preset_tasks.php') . ' --yes 2>&1', $out, $exit);
t_assert_eq(0, $exit, 'rollback script exits 0' . ($exit !== 0 ? ' -> ' . implode(' | ', $out) : ''));

t_assert(t_table_exists('routine_tasks'), 'rollback: routine_tasks restored');
t_assert(!t_table_exists('preset_tasks'), 'rollback: preset_tasks gone');
t_assert(t_table_exists('routines_routine_tasks'), 'rollback: junction restored');
t_assert(t_column_exists('routines_routine_tasks', 'routine_task_id'), 'rollback: junction column restored');
t_assert(!t_column_exists('routines_routine_tasks', 'title'), 'rollback: snapshot columns dropped');
t_assert(!t_column_exists('tasks', 'preset_task_id'), 'rollback: tasks column dropped');
t_assert(t_column_exists('routine_completion_tasks', 'routine_task_id'), 'rollback: completion column restored');
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routine_tasks"), 'rollback: library rows intact');
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routines_routine_tasks"), 'rollback: junction rows intact');
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routine_completion_tasks"), 'rollback: history intact');
t_assert_eq(0, (int) t_scalar("SELECT COUNT(*) FROM schema_migrations WHERE name = 'preset_tasks_v1'"), 'rollback: marker removed');

t_done();
