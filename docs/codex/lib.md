# Shared Library Reference — `includes/functions.php`

Single shared library for the whole app (~5400 lines, 144 top-level functions, no namespaces/classes). Included via `require_once __DIR__ . '/includes/functions.php'` at the top of every controller. Also transitively includes `includes/db_connect.php` and, on every request, eagerly bootstraps/migrates the "core" ~18 tables (see bottom of file). A further ~9 tables are created lazily by scattered `ensure*Table()` helpers, only when the feature function that owns them is actually called — see the "Schema is not all created by one eager pass" note at the top of `docs/codex/schema.md` before assuming a table exists. No autoloader, no Composer — everything is one flat global-function namespace.

## Auth & Session
| Function | Line | Purpose |
|---|---|---|
| `registerUser($username, $password, $role, $first_name = null, $last_name = null, $gender = null)` | 261 | Insert new user with hashed password |
| `loginUser($username, $password)` | 277 | Verify username/password against active users |
| `getUserRole($user_id)` | 289 | Fetch raw role column, maps legacy `parent` → `main_parent` |
| `updateUserPassword($user_id, $new_password)` | 676 | Hash and save a new password for a user |

## Family / Role Resolution & Permissions
| Function | Line | Purpose |
|---|---|---|
| `getDisplayName($user_id)` | 15 | Build a display name from first/last/name/username fallback chain |
| `getFamilyLinkRole($user_id)` | 31 | Look up `role_type` from `family_links` for a linked user |
| `getFamilyRootId($user_id)` | 38 | Resolve the main-parent (family root) user id for any family member |
| `getParentTitle($user_id)` | 66 | Get "mother"/"father" title if set on the user |
| `getEffectiveRole($user_id)` | 74 | Resolve role, substituting linked role for `family_member` |
| `getUserRoleLabel($user_id)` | 86 | Human-readable badge label for a user's role |
| `userCanManageAll($user_id)` | 299 | True if main parent or secondary-parent-linked family member |
| `isCaregiver($user_id)` | 312 | Check effective role is caregiver |
| `isFamilyMember($user_id)` | 316 | Check effective role is family_member |
| `canCreateContent($user_id)` | 320 | True for any role permitted to create tasks/goals/etc |
| `canAddEditChild($user_id)` | 325 | True for main/secondary parent only |
| `canAddEditCaregiver($user_id)` | 330 | Alias of `canAddEditChild` |
| `canAddEditFamilyMember($user_id)` | 334 | True for parents plus existing family members |

## Time-of-Day Helpers
Mirrored client-side in `js/time-of-day.js` — keep both in sync if changed.
| Function | Line | Purpose |
|---|---|---|
| `timeOfDayOrder(): array` | 134 | Canonical display order: morning/afternoon/evening/anytime |
| `timeOfDayLabel(string $timeOfDay): string` | 138 | Human label for a time-of-day key |
| `timeOfDayIcon(string $timeOfDay): string` | 150 | Font Awesome icon class for a time-of-day group |
| `sortTasksForTimeOfDayDisplay(array $items, ?callable $getter = null): array` | 163 | Groups and flattens items into display order with `_tod_group` tags |
| `timeOfDayFromTime(?string $time): string` | 178 | Derives morning/afternoon/evening/anytime from a clock time |
| `groupByTimeOfDay(array $items, ?callable $getter = null): array` | 199 | Buckets items into time-of-day groups |
| `compareWithinTimeOfDayGroup(array $a, array $b): int` | 213 | Sort comparator: due time, then sequence order, then title |

## Child Profile Management
| Function | Line | Purpose |
|---|---|---|
| `calculateAge($birthday)` | 236 | Compute age in years from a birthday |
| `createChildProfile($parent_user_id, $first_name, $last_name, $child_username, $child_password, $birthday, $avatar, $gender)` | 340 | Create child user+profile, restoring a soft-deleted match if found |
| `findSoftDeletedChild($parent_user_id, $child_name, $birthday)` | 431 | Locate soft-deleted child by name+birthday match |
| `findSoftDeletedChildByUsername($parent_user_id, $username)` | 451 | Locate soft-deleted child by username |
| `softDeleteChild($parent_user_id, $child_user_id, $actor_user_id = null)` | 470 | Mark child profile + user as deleted, preserving data |
| `hardDeleteChild($parent_user_id, $child_user_id)` | 512 | Permanently delete child user (cascades via FK) |
| `restoreChildProfile($child_user_id, $parent_user_id, array $updates = [])` | 542 | Undelete a child and refresh credentials/profile fields |
| `addLinkedUser($main_parent_id, $username, $password, $first_name, $last_name, $roleType = 'secondary_parent')` | 631 | Create + link a secondary parent/family member/caregiver |
| `updateChildProfile($child_user_id, $first_name, $last_name, $birthday, $avatar, $gender = null)` | 687 | Update child profile and linked user record |

## Dashboard Data
| Function | Line | Purpose |
|---|---|---|
| `getDashboardData($user_id)` | 731 | Dispatch to parent or child dashboard data builder by role |
| `getParentDashboardData($user_id, $role = 'main_parent')` | 742 | Aggregate children, tasks, points, goals, rewards, adjustments for parent view |
| `getChildDashboardData($user_id)` | 1081 | Aggregate points, level, streaks, rewards, goals, notifications for child view |

## Tasks
| Function | Line | Purpose |
|---|---|---|
| `createTask(...)` | 1193 | Insert a new task row (recurring or one-off) |
| `getTasks($user_id)` | 1217 | Fetch tasks for a parent's family or a specific child |
| `completeTask($task_id, $child_id, $photo_proof = null, $instance_date = null)` | 1257 | Mark task (or its recurring instance) completed with optional photo |
| `approveTask($task_id, $instance_date = null)` | 2211 | Approve completed task, award points, notify child, refresh goals |
| `rejectTask($task_id, $parent_user_id, $note = '', $reactivate = false, $actor_id = null, $instance_date = null)` | 2257 | Reject a completed task, optionally reactivate it for redo |

## Notifications
| Function | Line | Purpose |
|---|---|---|
| `ensureChildNotificationsTable()` | 1292 | Create/upgrade `child_notifications` table |
| `addChildNotification($child_id, $type, $message, $link_url = null)` | 1315 | Insert a notification row for a child |
| `ensureParentNotificationsTable()` | 1327 | Create/upgrade `parent_notifications` table |
| `addParentNotification($parent_user_id, $type, $message, $link_url = null)` | 1350 | Insert a notification row for a parent |
| `getParentNotifications($parent_user_id)` | 1362 | Fetch + purge-old, split into new/read/deleted buckets |
| `getChildNotifications($child_user_id)` | 1379 | Fetch + purge-old, split into new/read/deleted buckets |

## Points / Stars / Levels
| Function | Line | Purpose |
|---|---|---|
| `ensureRoutinePointsLogsTable()` | 1396 | Create `routine_points_logs` table |
| `buildChildWeekSchedule(int $childId, DateTime $weekStart, DateTime $weekEnd, array $weekDates)` | 1412 | Build a per-day schedule (tasks + routines, recurrence-expanded, with `completed`/`overdue` flags) for one child across a date range. Shared by `children.php` and mirrors the logic `dashboard_parent.php` keeps as its own local `$buildWeekSchedule` closure |
| `serveWeekScheduleJson(array $allowedChildIds)` | 1689 | No-op unless `$_GET['week_schedule']` is set; otherwise validates `child_id`/`week_start`, checks `child_id` against `$allowedChildIds`, calls `buildChildWeekSchedule()`, and `echo`s JSON + `exit`s. Used by `children.php`'s `?week_schedule=1` AJAX endpoint |
| `logRoutinePointsAward($routine_id, $child_id, $task_points, $bonus_points)` | 1725 | Record a routine points-award event |
| `ensureFamilyLevelSettingsTable()` | 1790 | Create `family_level_settings` table |
| `ensureChildLevelsTable()` | 1803 | Create `child_levels` table |
| `getFamilyStarsPerLevel(int $parent_user_id): int` | 1821 | Get (or default-insert) stars-per-level threshold for a family |
| `updateFamilyStarsPerLevel(int $parent_user_id, int $stars_per_level): bool` | 1836 | Upsert the family's stars-per-level setting |
| `calculateRoutineTaskStars(int $scheduledSeconds, int $actualSeconds): int` | 1851 | Award 1-3 stars based on how overtime a routine task was |
| `ensureChildStarAdjustmentsTable(): void` | 1867 | Create `child_star_adjustments` table |
| `getChildRollingStarsAverage(int $child_user_id, int $parent_user_id, int $weeks = 4): float` | 1882 | Sum stars earned/adjusted over a rolling multi-week window |
| `updateChildLevelState(int $child_user_id, int $parent_user_id, bool $triggerCelebration = false): array` | 1937 | Recompute and persist a child's level from rolling stars average |
| `getChildLevelState(int $child_user_id, int $parent_user_id): array` | 2006 | Read-only wrapper around `updateChildLevelState` (no celebration trigger) |
| `clearChildLevelCelebration(int $child_user_id, int $parent_user_id): void` | 2010 | Clear the pending level-up celebration flag |
| `updateChildPoints($child_id, $points)` | 4576 | Add/subtract points via upsert into `child_points` |
| `getChildTotalPoints($child_id)` | 4589 | Fetch a child's current total points |
| `adjustChildPoints(int $child_id, int $delta, string $reason, int $created_by): string` | 4604 | Manually adjust points, log it, notify child, return summary message |
| `adjustChildStars(int $child_id, int $delta, string $reason, int $created_by, int $main_parent_id): string` | 4633 | Manually adjust stars, log it, notify child, return summary with new level |

## Routines
| Function | Line | Purpose |
|---|---|---|
| `ensureRoutineCompletionTables()` | 1738 | Create `routine_completion_logs`/`routine_completion_tasks` tables |
| `logRoutineCompletionSession($routine_id, $child_id, $parent_id, $completed_by, $started_at, $completed_at, array $tasks = [])` | 2021 | Persist a full routine completion session with per-task detail |
| `completeRoutineAsParent(int $routine_id, array $selected, array $completed_at_map, bool $grant_bonus, int $family_root_id): array` | 2094 | Parent manually marks routine steps done, awards points/bonus, logs session |
| `createRoutine(...)` | 4652 | Insert a new routine row |
| `updateRoutine(...)` | 4671 | Update a routine's schedule/config fields |
| `deleteRoutine($routine_id, $parent_user_id)` | 4689 | Delete a routine |
| `addStepToRoutine($routine_id, $preset_task_id, $sequence_order, $dependency_id = null, $status = 'pending', ?array $preset_row = null)` | 4697 | Insert a routine step, snapshotting preset values at add time |
| `removeStepFromRoutine($routine_id, $preset_task_id)` | 4723 | Delete a step from a routine |
| `reorderRoutineSteps($routine_id, $new_order)` | 4729 | Bulk-update step sequence order |
| `getRoutines($user_id)` | 4738 | Fetch routines for a parent's family or a specific child, with steps attached |
| `getRoutineStepRows($routine_id)` | 4803 | Fetch a routine's steps, preferring snapshot over live preset values |
| `getRoutineWithTasks($routine_id)` | 4838 | Fetch a single routine plus its step rows |
| `completeRoutine($routine_id, $child_id, $grant_bonus = true)` | 4849 | Award routine bonus points to a child |
| `resetRoutineStepStatuses($routine_id)` | 4882 | Reset all steps of a routine back to pending |
| `setRoutineStepStatus($routine_id, $preset_task_id, $status, $completed_at = null)` | 4888 | Update a single routine step's status/completion timestamp |
| `getRoutineOvertimeLogs($parent_user_id, $limit = 25)` | 4915 | Fetch recent routine-task overtime log entries |
| `getRoutineOvertimeStats($parent_user_id)` | 4952 | Aggregate overtime totals by child and by routine |
| `logRoutineOvertime($routine_id, $preset_task_id, $child_user_id, $scheduled_seconds, $actual_seconds, $overtime_seconds)` | 4430 | Insert an overtime log row for a routine task |
| `calculateRoutineDurationMinutes($start_time, $end_time)` | 4252 | Compute duration in minutes between two clock times |
| `replaceRoutineSteps($routine_id, array $steps)` | 4284 | Transactionally replace all of a routine's steps |
| `getRoutinePreferences($parent_user_id)` | 4334 | Fetch (or default) timer/countdown/sound routine preferences |
| `saveRoutinePreferences($parent_user_id, $timer_warnings_enabled, $sub_timer_label, $show_countdown, $progress_style = 'bar', $sound_effects_enabled = 1, $background_music_enabled = 1)` | 4381 | Upsert routine display/timer preferences |

## Rewards
| Function | Line | Purpose |
|---|---|---|
| `createReward($parent_user_id, $title, $description, $point_cost, $child_user_id = null, $template_id = null)` | 2334 | Insert a new reward (optionally child-scoped or from a template) |
| `updateReward($parent_user_id, $reward_id, $title, $description, $point_cost)` | 2349 | Edit a reward while still available |
| `deleteReward($parent_user_id, $reward_id)` | 2373 | Delete a reward while still available |
| `createRewardTemplate($parent_user_id, $title, $description, $point_cost, $level_required = 1, $creator_user_id = null, $icon_class = null, $icon_color = null)` | 2384 | Insert a reusable reward template |
| `deleteRewardTemplate($parent_user_id, $template_id)` | 2400 | Delete a reward template |
| `updateRewardTemplate(...)` | 2410 | Edit a reward template's fields |
| `duplicateRewardTemplate($parent_user_id, $template_id, $creator_user_id = null)` | 2437 | Clone a template (with its per-child disabled list) as "Copy of..." |
| `getRewardTemplates($parent_user_id)` | 2493 | List a family's reward templates |
| `ensureRewardTemplateDisabledChildrenTable()` | 2500 | Create `reward_template_disabled_children` table |
| `getRewardTemplateDisabledMap($parent_user_id, array $child_user_ids = [])` | 2518 | Fetch which templates are disabled per child |
| `setRewardTemplateDisabledChildren($parent_user_id, $template_id, array $child_user_ids): bool` | 2545 | Replace the disabled-child list for a template |
| `assignTemplateToChildren($parent_user_id, $template_id, array $child_user_ids, $creator_user_id = null)` | 2572 | Create child-scoped reward instances from a template |
| `redeemReward($child_user_id, $reward_id)` | 2618 | Child spends points to redeem an available reward, notifies parent |
| `purchaseRewardTemplate($child_user_id, $template_id, &$error = null)` | 2679 | Child buys directly from a template (checks level/disabled/points) |
| `fulfillReward($reward_id, $parent_user_id, $actor_user_id)` | 2777 | Parent marks a redeemed reward fulfilled, notifies child |
| `denyReward($reward_id, $parent_user_id, $actor_user_id, $note = null)` | 2809 | Parent denies a redeemed reward, refunds points, notifies child |

## Goals
| Function | Line | Purpose |
|---|---|---|
| `normalizeGoalDateTimeInput($value, $offsetMinutes = null)` | 2881 | Normalize client datetime input (with timezone offset) to server format |
| `createGoal($parent_user_id, $child_user_id, $title, $start_date, $end_date, $reward_id = null, $creator_user_id = null, array $options = [])` | 2913 | Insert a goal (manual/task_quota/routine_streak/routine_count) with targets |
| `updateGoal($goal_id, $parent_user_id, $title, $start_date, $end_date, $reward_id = null, array $options = [])` | 2967 | Edit a goal's fields/targets and refresh its progress |
| `saveGoalTaskTargets($goal_id, array $task_ids)` | 3061 | Replace a goal's linked task targets |
| `saveGoalRoutineTargets($goal_id, array $routine_ids)` | 3076 | Replace a goal's linked routine targets |
| `getGoalTaskTargetIds($goal_id)` | 3091 | Fetch task target ids for a goal |
| `getGoalRoutineTargetIds($goal_id)` | 3098 | Fetch routine target ids for a goal |
| `getRoutineCompletionSummary(array $routine_ids, $child_id, $require_on_time, $start_date = null, $end_date = null)` | 3105 | Aggregate per-routine completion dates/counts, optionally excluding overtime days |
| `awardGoalReward($goal, $child_id)` | 3236 | Mark a goal's linked reward redeemed, notify both sides |
| `markGoalCompleted($goal, $child_id, $note = null, $notifyParent = true)` | 3261 | Complete goal, notify, award points and/or reward per `award_mode` |
| `markGoalPendingApproval($goal)` | 3292 | Move an active goal to pending-approval, notify parent+child |
| `markGoalIncomplete($goal, $child_id, $reason = null)` | 3313 | Mark a goal rejected/incomplete after its window expired |
| `getGoalWindowRange(array $goal)` | 3347 | Resolve a goal's effective start/end DateTime window |
| `getDueTimestampForTask(array $task, string $dateKey = null)` | 3687 | Compute a task's effective due timestamp for a given date, incl. time-of-day fallback |
| `calculateGoalProgress(array $goal, $child_id)` | 3708 | Compute current/target/streak progress for any goal type |
| `refreshGoalProgress(array $goal, $child_id)` | 3916 | Recalculate progress, persist, and auto-transition goal status |
| `autoCloseExpiredGoals($parent_id = null, $child_id = null)` | 3963 | Refresh progress for all active goals past their end date |
| `refreshTaskGoalsForChild($child_id)` | 3987 | Refresh progress on all active task_quota goals for a child |
| `refreshRoutineGoalsForChild($child_id, $routine_id)` | 3997 | Refresh progress on active routine goals tied to a given routine |
| `getGoalProgressSnapshot(array $goal, $child_id)` | 4016 | Refresh progress and report whether a completion celebration is due |
| `markGoalCelebrationShown($goal_id)` | 4033 | Flag a goal's completion celebration as already shown |
| `requestGoalCompletion($goal_id, $child_user_id)` | 4040 | Child requests completion; auto-completes or routes to approval |
| `approveGoal($goal_id, $parent_user_id)` | 4055 | Parent approves a pending goal, marking it completed |
| `rejectGoal($goal_id, $parent_user_id, $rejection_comment, &$error = null)` | 4097 | Parent rejects a pending goal with a comment |
| `reactivateGoal($goal_id, $parent_user_id)` | 4487 | Move a rejected goal back to active |
| `completeGoal($child_user_id, $goal_id)` | 4513 | Directly complete an active goal for a child |
| `deleteGoal($goal_id, $parent_user_id)` | 4538 | Delete a goal owned by the parent |

## Streaks
| Function | Line | Purpose |
|---|---|---|
| `ensureChildStreaksTable()` | 3368 | Create/upgrade `child_streak_records` table |
| `calculateConsecutiveStreak(array $dates): int` | 3396 | Count consecutive days ending today (or yesterday) from a date list |
| `getChildStreaks(int $child_user_id, int $parent_user_id = null): array` | 3418 | Compute routine/task streaks, weekly dates, on-time rates, best streaks |

## Preset Tasks
"Preset Task" = reusable template in the routine/task library (see `docs/codex/schema.md` `preset_tasks`).
| Function | Line | Purpose |
|---|---|---|
| `createPresetTask(...)` | 4147 | Insert a new reusable preset (routine library) task |
| `getPresetTasks($parent_user_id, $include_archived = false)` | 4177 | List global + parent-owned presets |
| `presetTaskReferenceCounts($preset_task_id)` | 4192 | Count references to a preset across routines/tasks/history/overtime |
| `archivePresetTask($preset_task_id, $parent_user_id)` | 4216 | Soft-disable a preset instead of deleting it |
| `restorePresetTask($preset_task_id, $parent_user_id)` | 4223 | Re-activate an archived preset |
| `getPresetTasksByIds($parent_user_id, array $task_ids)` | 4230 | Bulk-fetch presets by id, indexed by id |
| `updatePresetTask($preset_task_id, $updates)` | 4444 | Dynamic partial update of a preset's fields |
| `deletePresetTask($preset_task_id, $parent_user_id)` | 4459 | Hard-delete if unreferenced, else archive; returns status string |

## DB Schema / Migration Helpers
| Function | Line | Purpose |
|---|---|---|
| `dbTableExists(PDO $db, string $table): bool` | 5014 | Check table existence via information_schema |
| `dbColumnExists(PDO $db, string $table, string $column): bool` | 5020 | Check column existence via information_schema |
| `dbForeignKeyExists(PDO $db, string $table, string $fkName): bool` | 5026 | Check a named foreign key exists |
| `dbDropForeignKeysOnColumn(PDO $db, string $table, string $column, string $referencedTable, array $keepNames = []): void` | 5035 | Discover and drop all FKs on a column referencing a table (except keep-list) |
| `migratePresetTasksSchema(PDO $db): void` | 5047 | One-time migration: renames legacy `routine_tasks`→`preset_tasks`, adds snapshot/archive columns, backfills data |
| *(eager schema bootstrap block, not a function)* | ~4870–5388 | Top-level `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` statements bootstrapping/migrating the ~18 core tables only (not the full schema — see `docs/codex/schema.md`); runs on every request after `migratePresetTasksSchema()`. The remaining tables are created by the lazy `ensure*Table()` helpers listed individually above under their owning category (Notifications, Points/Stars/Levels, Rewards, Streaks), each invoked only from the specific function that needs the table |

## Other shared files (small, not part of functions.php)
| File | Exports | Purpose |
|---|---|---|
| `includes/db_connect.php` | `$db`/`$pdo` (global PDO instance) | Opens MySQL connection, creates DB if missing, sets `APP_TIMEZONE` (`America/New_York`) |
| `includes/notifications_bootstrap.php` | sets `$isParentNotificationUser`, `$isChildNotificationUser`, `$parentNotices`, `$childNotices`, `$parentNotificationCount`, `$notificationCount` | Loads current user's notification state; included by `page_setup.php` |
| `includes/notifications_child.php` | (render partial) | Child notification bell dropdown/modal markup |
| `includes/notifications_parent.php` | (render partial) | Parent notification bell dropdown/modal markup |
| `js/time-of-day.js` | `TimeOfDay.normalize/fromTime/label/icon/order` (global `TimeOfDay` object) | Client-side mirror of the PHP time-of-day helpers — **keep boundaries in sync manually**, no shared source of truth across languages |
| `js/preset-picker.js` | `PresetPicker.create(options)` (global `PresetPicker` object) | Reusable "pick a preset task" modal; fetches `preset_tasks_api.php`; used by `task.php` and `routine.php` |
| `js/number-stepper.js` | (auto-init on DOMContentLoaded) | Wraps every `<input type="number">` with +/- stepper buttons unless `data-stepper="false"` |
| `js/child-detail.js` | (auto-init on DOMContentLoaded) | Powers `children.php`: history modals, adjust modals, week-schedule modal/fetch |

---
Last generated: 2026-07-23. Triggered by: initial /docs/codex reference generation request (full function-by-function pass over includes/functions.php, ~5400 lines / 144 functions). Regenerate when functions are added/removed/renamed in includes/functions.php.
