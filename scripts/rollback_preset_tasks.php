<?php
/**
 * scripts/rollback_preset_tasks.php
 *
 * Reverses the preset_tasks_v1 schema migration: renames tables/columns back
 * to the legacy Routine Task Library names and drops the columns the migration
 * added. Snapshot columns are derived data (copied from presets), so dropping
 * them loses no user-entered information.
 *
 * IMPORTANT: schema and code travel together in this app. Only run this while
 * also checking out the pre-refactor code (git checkout <pre-refactor-commit>),
 * or restore your SQL backup instead — that is always the safest rollback.
 *
 * Usage: php scripts/rollback_preset_tasks.php --yes
 */

if (PHP_SAPI !== 'cli') {
    die("Run from the command line.\n");
}
if (!in_array('--yes', $argv, true)) {
    echo "This restores the legacy routine_tasks schema. Run with --yes to proceed.\n";
    echo "Pair with checking out the pre-refactor code, or restore a SQL backup instead.\n";
    exit(0);
}

require_once __DIR__ . '/../includes/db_connect.php';

function tblExists(PDO $db, string $t): bool {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $s->execute([$t]);
    return (int) $s->fetchColumn() > 0;
}
function colExists(PDO $db, string $t, string $c): bool {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $s->execute([$t, $c]);
    return (int) $s->fetchColumn() > 0;
}
function dropFks(PDO $db, string $t, string $c, string $ref): void {
    $s = $db->prepare("SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?");
    $s->execute([$t, $c, $ref]);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $fk) {
        $db->exec("ALTER TABLE `$t` DROP FOREIGN KEY `$fk`");
    }
}

echo "Rolling back preset_tasks_v1...\n";

// tasks.preset_task_id
if (tblExists($db, 'tasks') && colExists($db, 'tasks', 'preset_task_id')) {
    dropFks($db, 'tasks', 'preset_task_id', 'preset_tasks');
    $db->exec("ALTER TABLE tasks DROP COLUMN preset_task_id");
    echo "  dropped tasks.preset_task_id\n";
}

// routine_completion_tasks: restore column name + CASCADE FK, drop snapshots
if (tblExists($db, 'routine_completion_tasks')) {
    if (colExists($db, 'routine_completion_tasks', 'preset_task_id')) {
        dropFks($db, 'routine_completion_tasks', 'preset_task_id', 'preset_tasks');
        $db->exec("DELETE FROM routine_completion_tasks WHERE preset_task_id IS NULL");
        $db->exec("ALTER TABLE routine_completion_tasks CHANGE COLUMN preset_task_id routine_task_id INT NOT NULL");
    }
    foreach (['task_title', 'points_awarded'] as $c) {
        if (colExists($db, 'routine_completion_tasks', $c)) {
            $db->exec("ALTER TABLE routine_completion_tasks DROP COLUMN `$c`");
        }
    }
    echo "  restored routine_completion_tasks\n";
}

// routine_overtime_logs
if (tblExists($db, 'routine_overtime_logs') && colExists($db, 'routine_overtime_logs', 'preset_task_id')) {
    dropFks($db, 'routine_overtime_logs', 'preset_task_id', 'preset_tasks');
    $db->exec("DELETE FROM routine_overtime_logs WHERE preset_task_id IS NULL");
    $db->exec("ALTER TABLE routine_overtime_logs CHANGE COLUMN preset_task_id routine_task_id INT NOT NULL");
    echo "  restored routine_overtime_logs\n";
}

// junction: drop snapshots, restore column name
if (tblExists($db, 'routine_preset_tasks')) {
    dropFks($db, 'routine_preset_tasks', 'preset_task_id', 'preset_tasks');
    dropFks($db, 'routine_preset_tasks', 'dependency_id', 'preset_tasks');
    foreach (['title', 'description', 'time_limit', 'point_value', 'minimum_seconds', 'minimum_enabled', 'category', 'icon_url', 'audio_url'] as $c) {
        if (colExists($db, 'routine_preset_tasks', $c)) {
            $db->exec("ALTER TABLE routine_preset_tasks DROP COLUMN `$c`");
        }
    }
    if (colExists($db, 'routine_preset_tasks', 'preset_task_id')) {
        $db->exec("ALTER TABLE routine_preset_tasks CHANGE COLUMN preset_task_id routine_task_id INT NOT NULL");
    }
    $db->exec("RENAME TABLE routine_preset_tasks TO routines_routine_tasks");
    echo "  restored routines_routine_tasks\n";
}

// preset_tasks: drop new columns, rename back
if (tblExists($db, 'preset_tasks')) {
    foreach (['default_time_of_day', 'is_active', 'archived_at'] as $c) {
        if (colExists($db, 'preset_tasks', $c)) {
            $db->exec("ALTER TABLE preset_tasks DROP COLUMN `$c`");
        }
    }
    // minimum_seconds / minimum_enabled are kept: legacy code wrote them too.
    $db->exec("RENAME TABLE preset_tasks TO routine_tasks");
    echo "  restored routine_tasks\n";
}

// legacy CASCADE FKs back on the referencing tables
if (tblExists($db, 'routines_routine_tasks')) {
    $db->exec("ALTER TABLE routines_routine_tasks ADD CONSTRAINT fk_legacy_rrt_task FOREIGN KEY (routine_task_id) REFERENCES routine_tasks(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE routines_routine_tasks ADD CONSTRAINT fk_legacy_rrt_dependency FOREIGN KEY (dependency_id) REFERENCES routine_tasks(id) ON DELETE SET NULL");
}
if (tblExists($db, 'routine_completion_tasks')) {
    $db->exec("ALTER TABLE routine_completion_tasks ADD CONSTRAINT fk_legacy_rct_task FOREIGN KEY (routine_task_id) REFERENCES routine_tasks(id) ON DELETE CASCADE");
}
if (tblExists($db, 'routine_overtime_logs')) {
    $db->exec("ALTER TABLE routine_overtime_logs ADD CONSTRAINT fk_legacy_rol_task FOREIGN KEY (routine_task_id) REFERENCES routine_tasks(id) ON DELETE CASCADE");
}

try {
    $db->exec("DELETE FROM schema_migrations WHERE name = 'preset_tasks_v1'");
} catch (PDOException $e) {
}

echo "Rollback complete. Now check out the matching pre-refactor code.\n";
