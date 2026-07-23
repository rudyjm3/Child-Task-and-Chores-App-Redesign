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
| `approveTask($task_id, $instance_date = null)` | 1898 | Approve completed task, award points, notify child, refresh goals |
| `rejectTask($task_id, $parent_user_id, $note = '', $reactivate = false, $actor_id = null, $instance_date = null)` | 1944 | Reject a completed task, optionally reactivate it for redo |

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
| `logRoutinePointsAward($routine_id, $child_id, $task_points, $bonus_points)` | 1412 | Record a routine points-award event |
| `ensureFamilyLevelSettingsTable()` | 1477 | Create `family_level_settings` table |
| `ensureChildLevelsTable()` | 1490 | Create `child_levels` table |
| `getFamilyStarsPerLevel(int $parent_user_id): int` | 1508 | Get (or default-insert) stars-per-level threshold for a family |
| `updateFamilyStarsPerLevel(int $parent_user_id, int $stars_per_level): bool` | 1523 | Upsert the family's stars-per-level setting |
| `calculateRoutineTaskStars(int $scheduledSeconds, int $actualSeconds): int` | 1538 | Award 1-3 stars based on how overtime a routine task was |
| `ensureChildStarAdjustmentsTable(): void` | 1554 | Create `child_star_adjustments` table |
| `getChildRollingStarsAverage(int $child_user_id, int $parent_user_id, int $weeks = 4): float` | 1569 | Sum stars earned/adjusted over a rolling multi-week window |
| `updateChildLevelState(int $child_user_id, int $parent_user_id, bool $triggerCelebration = false): array` | 1624 | Recompute and persist a child's level from rolling stars average |
| `getChildLevelState(int $child_user_id, int $parent_user_id): array` | 1693 | Read-only wrapper around `updateChildLevelState` (no celebration trigger) |
| `clearChildLevelCelebration(int $child_user_id, int $parent_user_id): void` | 1697 | Clear the pending level-up celebration flag |
| `updateChildPoints($child_id, $points)` | 4263 | Add/subtract points via upsert into `child_points` |
| `getChildTotalPoints($child_id)` | 4276 | Fetch a child's current total points |
| `adjustChildPoints(int $child_id, int $delta, string $reason, int $created_by): string` | 4291 | Manually adjust points, log it, notify child, return summary message |
| `adjustChildStars(int $child_id, int $delta, string $reason, int $created_by, int $main_parent_id): string` | 4320 | Manually adjust stars, log it, notify child, return summary with new level |

## Routines
| Function | Line | Purpose |
|---|---|---|
| `ensureRoutineCompletionTables()` | 1425 | Create `routine_completion_logs`/`routine_completion_tasks` tables |
| `logRoutineCompletionSession($routine_id, $child_id, $parent_id, $completed_by, $started_at, $completed_at, array $tasks = [])` | 1708 | Persist a full routine completion session with per-task detail |
| `completeRoutineAsParent(int $routine_id, array $selected, array $completed_at_map, bool $grant_bonus, int $family_root_id): array` | 1781 | Parent manually marks routine steps done, awards points/bonus, logs session |
| `createRoutine(...)` | 4339 | Insert a new routine row |
| `updateRoutine(...)` | 4358 | Update a routine's schedule/config fields |
| `deleteRoutine($routine_id, $parent_user_id)` | 4376 | Delete a routine |
| `addStepToRoutine($routine_id, $preset_task_id, $sequence_order, $dependency_id = null, $status = 'pending', ?array $preset_row = null)` | 4384 | Insert a routine step, snapshotting preset values at add time |
| `removeStepFromRoutine($routine_id, $preset_task_id)` | 4410 | Delete a step from a routine |
| `reorderRoutineSteps($routine_id, $new_order)` | 4416 | Bulk-update step sequence order |
| `getRoutines($user_id)` | 4425 | Fetch routines for a parent's family or a specific child, with steps attached |
| `getRoutineStepRows($routine_id)` | 4490 | Fetch a routine's steps, preferring snapshot over live preset values |
| `getRoutineWithTasks($routine_id)` | 4525 | Fetch a single routine plus its step rows |
| `completeRoutine($routine_id, $child_id, $grant_bonus = true)` | 4536 | Award routine bonus points to a child |
| `resetRoutineStepStatuses($routine_id)` | 4569 | Reset all steps of a routine back to pending |
| `setRoutineStepStatus($routine_id, $preset_task_id, $status, $completed_at = null)` | 4575 | Update a single routine step's status/completion timestamp |
| `getRoutineOvertimeLogs($parent_user_id, $limit = 25)` | 4602 | Fetch recent routine-task overtime log entries |
| `getRoutineOvertimeStats($parent_user_id)` | 4639 | Aggregate overtime totals by child and by routine |
| `logRoutineOvertime($routine_id, $preset_task_id, $child_user_id, $scheduled_seconds, $actual_seconds, $overtime_seconds)` | 4117 | Insert an overtime log row for a routine task |
| `calculateRoutineDurationMinutes($start_time, $end_time)` | 3939 | Compute duration in minutes between two clock times |
| `replaceRoutineSteps($routine_id, array $steps)` | 3971 | Transactionally replace all of a routine's steps |
| `getRoutinePreferences($parent_user_id)` | 4021 | Fetch (or default) timer/countdown/sound routine preferences |
| `saveRoutinePreferences($parent_user_id, $timer_warnings_enabled, $sub_timer_label, $show_countdown, $progress_style = 'bar', $sound_effects_enabled = 1, $background_music_enabled = 1)` | 4068 | Upsert routine display/timer preferences |

## Rewards
| Function | Line | Purpose |
|---|---|---|
| `createReward($parent_user_id, $title, $description, $point_cost, $child_user_id = null, $template_id = null)` | 2021 | Insert a new reward (optionally child-scoped or from a template) |
| `updateReward($parent_user_id, $reward_id, $title, $description, $point_cost)` | 2036 | Edit a reward while still available |
| `deleteReward($parent_user_id, $reward_id)` | 2060 | Delete a reward while still available |
| `createRewardTemplate($parent_user_id, $title, $description, $point_cost, $level_required = 1, $creator_user_id = null, $icon_class = null, $icon_color = null)` | 2071 | Insert a reusable reward template |
| `deleteRewardTemplate($parent_user_id, $template_id)` | 2087 | Delete a reward template |
| `updateRewardTemplate(...)` | 2097 | Edit a reward template's fields |
| `duplicateRewardTemplate($parent_user_id, $template_id, $creator_user_id = null)` | 2124 | Clone a template (with its per-child disabled list) as "Copy of..." |
| `getRewardTemplates($parent_user_id)` | 2180 | List a family's reward templates |
| `ensureRewardTemplateDisabledChildrenTable()` | 2187 | Create `reward_template_disabled_children` table |
| `getRewardTemplateDisabledMap($parent_user_id, array $child_user_ids = [])` | 2205 | Fetch which templates are disabled per child |
| `setRewardTemplateDisabledChildren($parent_user_id, $template_id, array $child_user_ids): bool` | 2232 | Replace the disabled-child list for a template |
| `assignTemplateToChildren($parent_user_id, $template_id, array $child_user_ids, $creator_user_id = null)` | 2259 | Create child-scoped reward instances from a template |
| `redeemReward($child_user_id, $reward_id)` | 2305 | Child spends points to redeem an available reward, notifies parent |
| `purchaseRewardTemplate($child_user_id, $template_id, &$error = null)` | 2366 | Child buys directly from a template (checks level/disabled/points) |
| `fulfillReward($reward_id, $parent_user_id, $actor_user_id)` | 2464 | Parent marks a redeemed reward fulfilled, notifies child |
| `denyReward($reward_id, $parent_user_id, $actor_user_id, $note = null)` | 2496 | Parent denies a redeemed reward, refunds points, notifies child |

## Goals
| Function | Line | Purpose |
|---|---|---|
| `normalizeGoalDateTimeInput($value, $offsetMinutes = null)` | 2568 | Normalize client datetime input (with timezone offset) to server format |
| `createGoal($parent_user_id, $child_user_id, $title, $start_date, $end_date, $reward_id = null, $creator_user_id = null, array $options = [])` | 2600 | Insert a goal (manual/task_quota/routine_streak/routine_count) with targets |
| `updateGoal($goal_id, $parent_user_id, $title, $start_date, $end_date, $reward_id = null, array $options = [])` | 2654 | Edit a goal's fields/targets and refresh its progress |
| `saveGoalTaskTargets($goal_id, array $task_ids)` | 2748 | Replace a goal's linked task targets |
| `saveGoalRoutineTargets($goal_id, array $routine_ids)` | 2763 | Replace a goal's linked routine targets |
| `getGoalTaskTargetIds($goal_id)` | 2778 | Fetch task target ids for a goal |
| `getGoalRoutineTargetIds($goal_id)` | 2785 | Fetch routine target ids for a goal |
| `getRoutineCompletionSummary(array $routine_ids, $child_id, $require_on_time, $start_date = null, $end_date = null)` | 2792 | Aggregate per-routine completion dates/counts, optionally excluding overtime days |
| `awardGoalReward($goal, $child_id)` | 2923 | Mark a goal's linked reward redeemed, notify both sides |
| `markGoalCompleted($goal, $child_id, $note = null, $notifyParent = true)` | 2948 | Complete goal, notify, award points and/or reward per `award_mode` |
| `markGoalPendingApproval($goal)` | 2979 | Move an active goal to pending-approval, notify parent+child |
| `markGoalIncomplete($goal, $child_id, $reason = null)` | 3000 | Mark a goal rejected/incomplete after its window expired |
| `getGoalWindowRange(array $goal)` | 3034 | Resolve a goal's effective start/end DateTime window |
| `getDueTimestampForTask(array $task, string $dateKey = null)` | 3374 | Compute a task's effective due timestamp for a given date, incl. time-of-day fallback |
| `calculateGoalProgress(array $goal, $child_id)` | 3395 | Compute current/target/streak progress for any goal type |
| `refreshGoalProgress(array $goal, $child_id)` | 3603 | Recalculate progress, persist, and auto-transition goal status |
| `autoCloseExpiredGoals($parent_id = null, $child_id = null)` | 3650 | Refresh progress for all active goals past their end date |
| `refreshTaskGoalsForChild($child_id)` | 3674 | Refresh progress on all active task_quota goals for a child |
| `refreshRoutineGoalsForChild($child_id, $routine_id)` | 3684 | Refresh progress on active routine goals tied to a given routine |
| `getGoalProgressSnapshot(array $goal, $child_id)` | 3703 | Refresh progress and report whether a completion celebration is due |
| `markGoalCelebrationShown($goal_id)` | 3720 | Flag a goal's completion celebration as already shown |
| `requestGoalCompletion($goal_id, $child_user_id)` | 3727 | Child requests completion; auto-completes or routes to approval |
| `approveGoal($goal_id, $parent_user_id)` | 3742 | Parent approves a pending goal, marking it completed |
| `rejectGoal($goal_id, $parent_user_id, $rejection_comment, &$error = null)` | 3784 | Parent rejects a pending goal with a comment |
| `reactivateGoal($goal_id, $parent_user_id)` | 4174 | Move a rejected goal back to active |
| `completeGoal($child_user_id, $goal_id)` | 4200 | Directly complete an active goal for a child |
| `deleteGoal($goal_id, $parent_user_id)` | 4225 | Delete a goal owned by the parent |

## Streaks
| Function | Line | Purpose |
|---|---|---|
| `ensureChildStreaksTable()` | 3055 | Create/upgrade `child_streak_records` table |
| `calculateConsecutiveStreak(array $dates): int` | 3083 | Count consecutive days ending today (or yesterday) from a date list |
| `getChildStreaks(int $child_user_id, int $parent_user_id = null): array` | 3105 | Compute routine/task streaks, weekly dates, on-time rates, best streaks |

## Preset Tasks
"Preset Task" = reusable template in the routine/task library (see `docs/codex/schema.md` `preset_tasks`).
| Function | Line | Purpose |
|---|---|---|
| `createPresetTask(...)` | 3834 | Insert a new reusable preset (routine library) task |
| `getPresetTasks($parent_user_id, $include_archived = false)` | 3864 | List global + parent-owned presets |
| `presetTaskReferenceCounts($preset_task_id)` | 3879 | Count references to a preset across routines/tasks/history/overtime |
| `archivePresetTask($preset_task_id, $parent_user_id)` | 3903 | Soft-disable a preset instead of deleting it |
| `restorePresetTask($preset_task_id, $parent_user_id)` | 3910 | Re-activate an archived preset |
| `getPresetTasksByIds($parent_user_id, array $task_ids)` | 3917 | Bulk-fetch presets by id, indexed by id |
| `updatePresetTask($preset_task_id, $updates)` | 4131 | Dynamic partial update of a preset's fields |
| `deletePresetTask($preset_task_id, $parent_user_id)` | 4146 | Hard-delete if unreferenced, else archive; returns status string |

## DB Schema / Migration Helpers
| Function | Line | Purpose |
|---|---|---|
| `dbTableExists(PDO $db, string $table): bool` | 4701 | Check table existence via information_schema |
| `dbColumnExists(PDO $db, string $table, string $column): bool` | 4707 | Check column existence via information_schema |
| `dbForeignKeyExists(PDO $db, string $table, string $fkName): bool` | 4713 | Check a named foreign key exists |
| `dbDropForeignKeysOnColumn(PDO $db, string $table, string $column, string $referencedTable, array $keepNames = []): void` | 4722 | Discover and drop all FKs on a column referencing a table (except keep-list) |
| `migratePresetTasksSchema(PDO $db): void` | 4734 | One-time migration: renames legacy `routine_tasks`→`preset_tasks`, adds snapshot/archive columns, backfills data |
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
