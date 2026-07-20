<?php
// Simulates an interrupted migration: performs only the table renames (the
// state a crash mid-migration could leave), then boots the app and asserts the
// migration resumes and completes correctly.
require __DIR__ . '/lib.php';

t_fresh_db();
t_load_sql('fixtures/legacy_schema.sql');
t_load_sql('fixtures/seed_data.sql');

// Partial state: renames done, nothing else (no marker, no new columns).
$pdo = t_db();
$pdo->exec("RENAME TABLE routine_tasks TO preset_tasks");
$pdo->exec("RENAME TABLE routines_routine_tasks TO routine_preset_tasks");

app_boot(); // must resume the migration

t_assert(t_column_exists('routine_preset_tasks', 'preset_task_id'), 'resume: junction column renamed');
t_assert(t_column_exists('routine_preset_tasks', 'title'), 'resume: snapshot columns added');
t_assert(t_column_exists('tasks', 'preset_task_id'), 'resume: tasks.preset_task_id added');
t_assert_eq(1, (int) t_scalar("SELECT COUNT(*) FROM schema_migrations WHERE name = 'preset_tasks_v1'"), 'resume: marker written');
$mismatch = (int) t_scalar("SELECT COUNT(*) FROM routine_preset_tasks rps JOIN preset_tasks pt ON pt.id = rps.preset_task_id
    WHERE rps.title <> pt.title");
t_assert_eq(0, $mismatch, 'resume: snapshots backfilled');
t_assert_eq(4, (int) t_scalar("SELECT COUNT(*) FROM routine_preset_tasks"), 'resume: junction rows intact');

t_done();
