<?php
/**
 * scripts/migrate_preset_tasks.php
 *
 * Migrates the legacy Routine Task Library schema to the global Preset Tasks
 * schema (migration marker: preset_tasks_v1).
 *
 *   BACK UP FIRST:  mysqldump child_chore_app > backup_before_preset_tasks.sql
 *
 * Usage:
 *   php scripts/migrate_preset_tasks.php            # dry-run report only
 *   php scripts/migrate_preset_tasks.php --yes      # apply the migration
 *
 * The migration is idempotent: every step checks current state before acting,
 * so re-running after an interruption resumes where it left off. Note that the
 * app also runs this migration automatically on first page load after deploy;
 * this script exists so you can migrate deliberately and see a report.
 *
 * To roll back, restore your SQL backup or run scripts/rollback_preset_tasks.php
 * together with checking out the pre-refactor code (code and schema travel
 * together in this app).
 */

if (PHP_SAPI !== 'cli') {
    die("Run from the command line.\n");
}

$apply = in_array('--yes', $argv, true);

require_once __DIR__ . '/../includes/db_connect.php';
// NOTE: functions.php would auto-run the migration at include time; for a
// controlled run we re-declare nothing and load it only when applying.

function tableExists(PDO $db, string $t): bool {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $s->execute([$t]);
    return (int) $s->fetchColumn() > 0;
}

function rowCount(PDO $db, string $t): int {
    try {
        return (int) $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    } catch (PDOException $e) {
        return -1;
    }
}

echo "Preset Tasks migration (preset_tasks_v1)\n";
echo "Database: " . DB_NAME . "\n\n";

$migrated = false;
try {
    $migrated = (bool) $db->query("SELECT 1 FROM schema_migrations WHERE name = 'preset_tasks_v1' LIMIT 1")->fetchColumn();
} catch (PDOException $e) {
    // marker table missing -> not migrated
}

if ($migrated) {
    echo "Already migrated (marker present). Nothing to do.\n";
    exit(0);
}

echo "Current state:\n";
foreach (['routine_tasks', 'routines_routine_tasks', 'preset_tasks', 'routine_preset_tasks',
          'routine_completion_tasks', 'routine_overtime_logs', 'tasks'] as $t) {
    $exists = tableExists($db, $t);
    echo sprintf("  %-28s %s%s\n", $t, $exists ? 'EXISTS' : 'absent',
        $exists ? ' (' . rowCount($db, $t) . ' rows)' : '');
}

echo "\nPlanned changes:\n";
echo "  - RENAME routine_tasks -> preset_tasks; routines_routine_tasks -> routine_preset_tasks\n";
echo "  - Rename FK column routine_task_id -> preset_task_id on the junction,\n";
echo "    routine_completion_tasks, and routine_overtime_logs (history FKs become SET NULL)\n";
echo "  - Add preset columns: minimum_seconds/minimum_enabled (declared), default_time_of_day, is_active, archived_at\n";
echo "  - Add snapshot columns to routine steps and completion history; add tasks.preset_task_id\n";
echo "  - Backfill snapshots from current live values (no visible behavior change)\n";

if (!$apply) {
    echo "\nDry run only. BACK UP FIRST (mysqldump " . DB_NAME . " > backup.sql), then re-run with --yes to apply.\n";
    exit(0);
}

echo "\nApplying...\n";
require_once __DIR__ . '/../includes/functions.php'; // include-time bootstrap runs migratePresetTasksSchema()

$ok = false;
try {
    $ok = (bool) $db->query("SELECT 1 FROM schema_migrations WHERE name = 'preset_tasks_v1' LIMIT 1")->fetchColumn();
} catch (PDOException $e) {
}

if (!$ok) {
    echo "ERROR: migration marker not found after run. Inspect the error log and re-run.\n";
    exit(1);
}

echo "Migration applied. Verification:\n";
foreach (['preset_tasks', 'routine_preset_tasks', 'tasks'] as $t) {
    echo sprintf("  %-24s %d rows\n", $t, rowCount($db, $t));
}
$unfilled = (int) $db->query("SELECT COUNT(*) FROM routine_preset_tasks WHERE title IS NULL AND preset_task_id IS NOT NULL")->fetchColumn();
echo "  routine steps missing snapshots: $unfilled (expected 0 unless a preset row was already gone)\n";
echo "Done.\n";
