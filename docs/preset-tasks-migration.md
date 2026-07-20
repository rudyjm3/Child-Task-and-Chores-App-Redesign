# Preset Tasks Migration & Regression Guide

Update date: July 2026 — "Global Task System" release.

This release merges the two task systems into one global concept:

- **Preset Task** (`preset_tasks`, formerly the "Routine Task Library" /
  `routine_tasks`): a reusable template with title, description, timer,
  points, category, minimum-time settings, and a **default time of day**.
- **Individual Task Assignment** (`tasks`): a task assigned directly to a
  child. May be custom, or created from a preset (tracked via
  `tasks.preset_task_id`; all values are **snapshotted** onto the row).
- **Routine Task Assignment** (`routine_preset_tasks`, formerly
  `routines_routine_tasks`): a preset connected to a routine with order,
  dependency, and per-run status. Preset values are **snapshotted** onto the
  step at add time, so editing a preset never changes existing routines.

Completion history (`routine_completion_tasks`) additionally snapshots
`task_title` and `points_awarded`, and its preset FK is `ON DELETE SET NULL`,
so history can never be lost to a preset edit or delete. Deleting a preset
that is still referenced anywhere **archives** it (`is_active = 0`) instead.

Tasks are displayed grouped by time of day everywhere, in this order:
**Morning (before 12:00), Afternoon (12:00–4:59 PM), Evening (5:00 PM+),
Anytime**. The boundaries live in one place: `timeOfDayFromTime()` in
`includes/functions.php` and the mirror in `js/time-of-day.js`.

## Migration runbook

The migration (`preset_tasks_v1`) runs **automatically** on the first page
load after deploying this code — the schema bootstrap in
`includes/functions.php` calls `migratePresetTasksSchema()` before creating
tables. To migrate deliberately with a report instead:

```bash
# 1. ALWAYS back up first
mysqldump child_chore_app > backup_before_preset_tasks.sql

# 2. Dry run (prints current state and planned changes, changes nothing)
php scripts/migrate_preset_tasks.php

# 3. Apply
php scripts/migrate_preset_tasks.php --yes
```

What it does (all steps idempotent — an interrupted run resumes by
re-running; the marker row in `schema_migrations` is written last):

1. `RENAME TABLE routine_tasks → preset_tasks`, `routines_routine_tasks →
   routine_preset_tasks`.
2. Renames FK column `routine_task_id → preset_task_id` on the junction,
   `routine_completion_tasks`, and `routine_overtime_logs`, discovering the
   per-install auto-generated FK names via `information_schema` and re-adding
   deterministic ones (`fk_rps_preset` RESTRICT, `fk_rps_dependency`,
   `fk_rct_preset`, `fk_rol_preset`, `fk_tasks_preset` — history FKs SET NULL).
3. Declares the previously hand-added `minimum_seconds`/`minimum_enabled`
   columns; adds `default_time_of_day`, `is_active`, `archived_at` to presets;
   snapshot columns to routine steps; `task_title`/`points_awarded` to
   completion history; `preset_task_id` to `tasks`.
4. Backfills all snapshots from the **current live preset values** inside a
   transaction, so nothing visible changes at cutover.

No rows are deleted or rewritten: routines, steps and their order,
assignments, completion history, points, streaks, goals, notifications, and
overtime logs are preserved as-is. Goals reference only `tasks.id` and
`routines.id`, neither of which changes, so existing goals keep working
without being recreated.

### Rollback

Restoring the SQL backup is always the safest rollback. Alternatively:

```bash
php scripts/rollback_preset_tasks.php --yes
git checkout <pre-refactor-commit>   # code and schema travel together
```

The rollback renames everything back and drops the added columns. Snapshot
columns are derived data (copies of preset values), so no user-entered data
is lost. Note: completion-history/overtime rows whose preset was hard-deleted
after migration (preset FK NULL) cannot be re-attached and are removed by the
rollback's NOT NULL restoration — another reason to prefer the SQL backup.

## Behavior changes to be aware of

- **Editing a preset no longer live-updates existing routines.** Steps keep
  the values they had when added (the edit form says so). This also protects
  scheduled/completed tasks and history from later edits.
- **Deleting a referenced preset archives it** instead of cascading. Archived
  presets are hidden from pickers, visible under the library's
  Active/Archived/All filter, and restorable. Hard delete happens only when
  nothing references the preset.
- The child dashboard / calendar "Due Today" section is now labeled
  "Anytime" and appears after Morning/Afternoon/Evening per the global
  ordering rule.
- Fresh installs work from an empty database (two latent bootstrap bugs were
  fixed: an early `ALTER TABLE users` before the table existed, and a
  duplicate column in the `reward_templates` CREATE).
- Legacy `preset_tasks.status` enum column is unused ("dead") but retained
  for cheap rollback; do not build on it.

## Automated tests

Requires local MariaDB (root@localhost, no password — same as
`includes/db_connect.php`) and PHP CLI:

```bash
php tests/run.php            # all suites
php tests/run.php 20         # only test_20_*
```

| Suite | Covers |
|---|---|
| test_05_lint | `php -l` over every PHP file |
| test_10_baseline | Parity gate: goals, points, streaks, routines identical before/after migration (fixtures frozen pre-refactor via `gen_baseline.php`) |
| test_20_migration | Schema shape, data preservation, order/dependency preservation, snapshot backfill, idempotent re-run, history survives preset deletes |
| test_22_migration_resume | Interrupted migration resumes correctly |
| test_24_rollback | Full migrate→rollback round trip restores the legacy schema with data intact |
| test_30_preset_semantics | Edit isolation (steps + history keep snapshots; new steps freeze new values), archive/restore, archive-on-referenced-delete, default time of day |
| test_40_time_of_day | Boundary cases (11:59/12:00/16:59/17:00/null), group order, within-group sorting |
| test_50_task_from_preset | Provenance, same preset morning + evening, snapshot isolation, provenance nulling, same-name custom tasks |

## Manual regression checklist

Run after deploying (parent account + child account). Boxes checked = passed
in the release regression run against the seeded test family (see report
below).

### Preset Tasks
- [x] Preset Tasks screen opens from the routines page; no "Routine Task Library" wording anywhere
- [x] Create preset (with default time of day), edit preset (info note shown), duplicate preset
- [x] Search by name, filter by category, filter Active/Archived/All
- [x] Archive → preset leaves pickers, existing routines unchanged; Restore brings it back
- [x] Delete referenced preset → archived with explanatory message; delete unreferenced → removed

### Individual tasks
- [x] Create custom task (once + recurring)
- [x] Create task via "Pick a Preset Task": search/browse, select, form prefills, all fields editable, hidden provenance saved
- [x] Preset archived while form open → task saves as custom with warning
- [x] Multi-child creation carries the preset link to each child
- [x] Edit, complete (child), approve/reject (parent); points awarded on approval
- [x] Same preset assigned Morning and Evening to the same child

### Routines
- [x] Build routine with "Pick a Preset Task" picker (duplicates disabled) and "Create Custom Task"
- [x] Reorder steps (drag), dependencies, duration warning
- [x] Steps keep snapshot values after the preset is edited
- [x] Child runs routine: timers, stars, time-scaled points, bonus, overtime logging
- [x] Duplicate completion same day rejected; per-step statuses reset next day
- [x] Parent manual completion
- [x] Completion history shows snapshot titles even for edited/removed presets

### Time-of-day grouping
- [x] Parent task list: Active + Pending Approval grouped Morning/Afternoon/Evening/Anytime with icon headings
- [x] Child task list + child dashboard schedule grouped, completed tasks stay in their group, empty groups hidden
- [x] Parent dashboard today/week views and task calendar (calendar + list views) use the same order
- [x] Groups render correctly at mobile/tablet/desktop widths

### Integrations
- [x] Goals: task-quota, routine-count, routine-streak progress unchanged after migration and correct after new completions; no double counting
- [x] Points balances and streaks unchanged after migration
- [x] Notifications sent on completion/approval; overtime report shows task names (snapshot fallback)
- [x] Rewards, profile, goal pages render without errors

## Release regression report (2026-07-19)

- Automated: **8/8 suites, 149 assertions passed** (lint, baseline parity,
  migration, resume, rollback, preset semantics, time-of-day, task-from-preset).
- End-to-end over HTTP (php -S + seeded MariaDB): parent + child login; all
  six main pages render with zero PHP warnings/errors; preset created with
  default TOD; routine created from picker structure with snapshots; task
  created from preset with provenance; child completed the routine through
  `complete_routine_flow` earning time-scaled points + bonus (75 → 105) with
  title/points snapshots written to history; duplicate completion rejected;
  `preset_tasks_api.php` returns scoped JSON.

## Legacy code removed or deprecated

- Removed: `library_status` mapping in `getRoutines`/`getRoutineWithTasks`
  (nothing consumed it); the bare `<select>` routine-step picker; hardcoded
  time-of-day section arrays in four pages (replaced by shared helpers);
  "Routine Task Library" wording and aria labels.
- Renamed (no wrappers kept): `createRoutineTask→createPresetTask`,
  `getRoutineTasks→getPresetTasks`, `getRoutineTasksByIds→getPresetTasksByIds`,
  `updateRoutineTask→updatePresetTask`, `deleteRoutineTask→deletePresetTask`,
  `replaceRoutineTasks→replaceRoutineSteps`,
  `addRoutineTaskToRoutine→addStepToRoutine`,
  `removeRoutineTaskFromRoutine→removeStepFromRoutine`,
  `reorderRoutineTasks→reorderRoutineSteps`,
  `resetRoutineTaskStatuses→resetRoutineStepStatuses`,
  `setRoutineTaskStatus→setRoutineStepStatus`; wire names
  `routine_task_id→preset_task_id`, `reset_routine_tasks→reset_routine_steps`.
- Deprecated but retained: `preset_tasks.status` legacy enum column (unused).
