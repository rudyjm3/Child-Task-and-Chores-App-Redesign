# Route Reference

No framework, no router. Every top-level `.php` file in the repo root is directly web-accessible and is its own "controller" — it renders HTML on `GET` and, on `POST`, dispatches on the **presence of a specific `$_POST` key** (a sentinel field name, e.g. `isset($_POST['create_task'])`), not a generic `action` param. A few pages also branch to a **JSON response** when a specific `$_GET` key is present, short-circuiting before any HTML is emitted. `routine.php`'s runtime/AJAX actions are the one exception — those use `$_POST['action'] = '...'`.

Auth is not centralized/middleware-based — each file re-checks `$_SESSION['user_id']` (and often a role helper) in its own top few lines. Role helpers (all in `includes/functions.php`):
- `getUserRole($id)` — raw `users.role` (legacy `'parent'` value migrated to `main_parent`).
- `getEffectiveRole($id)` — resolves a `family_member`/`caregiver` to the role recorded in `family_links` for them.
- `canCreateContent($id)` — true for `main_parent`, `secondary_parent`, `family_member`, `caregiver` (i.e. any non-child).
- `canAddEditChild($id)` — true only for `main_parent`, `secondary_parent`.
- `getFamilyRootId($id)` — resolves any linked user to the owning main parent's id; nearly all data is scoped by this id, not `$_SESSION['user_id']` directly.

## Auth guard per file (top-of-file gate)
| File | Guard |
|---|---|
| dashboard_parent.php | `session_start()`; `!isset($_SESSION['user_id']) \|\| !canCreateContent()` → redirect `login.php` |
| dashboard_child.php | `session_start()`; `!isset($_SESSION['user_id']) \|\| $_SESSION['role'] !== 'child'` → redirect `login.php` (raw session role, not `getEffectiveRole()`) |
| children.php | via `includes/page_setup.php`: `!isset($_SESSION['user_id'])` → redirect; then own check `!canCreateContent()` → redirect |
| task.php | `!isset($_SESSION['user_id'])` → redirect `login.php` (any logged-in role may GET; POST actions individually gate `canCreateContent()`/`canAddEditChild()`) |
| routine.php | same pattern as task.php; computes `$isParentContext = canCreateContent()` once, reused as gate for the whole parent-management POST block |
| goal.php | logged-in required; mutations require `canCreateContent()` except child-only `request_completion` |
| rewards.php | logged-in required; splits into a child-only branch (renders shop, `exit`s) and an adult branch (`canCreateContent()`) |
| profile.php | logged-in required; editing another user requires `main_parent`/`secondary_parent` (else silently falls back to self) |
| index.php | none — public landing page; redirects to `dashboard_{role}.php` if already logged in |
| login.php | none — redirects to dashboard if already logged in |
| register.php | none |
| logout.php | none |
| preset_tasks_api.php | session required + `canCreateContent()` (401/403 JSON on failure) |

## dashboard_parent.php
| Trigger | Auth | Purpose |
|---|---|---|
| `GET dashboard_parent.php` | guard | Parent dashboard: children overview, rewards, goals, family members, notifications |
| `POST [mark_parent_notifications_read]` | guard | `UPDATE parent_notifications SET is_read=1` for given ids |
| `POST [move_parent_notifications_trash]` / `[trash_parent_single]` | guard | Soft-delete (`deleted_at=NOW()`) notifications, bulk or single |
| `POST [delete_parent_notifications_perm]` / `[delete_parent_single_perm]` | guard | Hard `DELETE FROM parent_notifications`, bulk or single |
| `POST [reject_task_notification]` | guard + task ownership | `rejectTask()` |
| `POST [approve_task_notification]` | guard + task ownership | Validates instance is `completed`, `approveTask()`, marks notification read |
| `POST [create_reward]` | guard | `createReward()` → `rewards` |
| `POST [update_reward]` | guard | `updateReward()` (blocked once redeemed) |
| `POST [delete_reward]` | guard | `deleteReward()` (only if still available) |
| `POST [create_goal]` | guard | `createGoal()` → `goals` |
| `POST [adjust_child_points]` | guard + role in `[main_parent,secondary_parent]` | Lazy-creates `child_point_adjustments`, `updateChildPoints()`, logs adjustment, notifies child |
| `POST [approve_goal]` / `[reject_goal]` | guard | `approveGoal()` / `rejectGoal()` |
| `POST [fulfill_reward]` | guard | `fulfillReward()`; relabels linked `parent_notifications` row `reward_fulfilled` |
| `POST [deny_reward]` | guard | `denyReward()` + note; relabels notification `reward_denied` |
| `POST [add_child]` | guard + `canAddEditChild()` | Avatar upload/resize, `createChildProfile()` (creates or restores) |
| `POST [add_new_user]` | guard + `canAddEditFamilyMember()` | `addLinkedUser()` → new linked account + `family_links` row |
| `POST [delete_user]` | guard + role in `[main_parent,secondary_parent]` | Child → `hardDeleteChild()`/`softDeleteChild()` per `delete_mode`; else `DELETE FROM users` scoped to `family_links` |
| `GET ?week_schedule=1&child_id=&week_start=` | guard + child must be in caller's own children | JSON: week's tasks+routines via local closure `$buildWeekSchedule` (reads `tasks`, `task_instances`, `routines`, `routine_points_logs`) — **works correctly** |

## dashboard_child.php
| Trigger | Auth | Purpose |
|---|---|---|
| `GET dashboard_child.php` | guard | Child dashboard: hero stats, week strip, goals, quick-nav |
| `POST [request_completion]` | guard | `requestGoalCompletion()` → goal `pending_approval` |
| `POST [mark_notifications_read]` | guard | `UPDATE child_notifications SET is_read=1` |
| `POST [move_notifications_trash]` / `[trash_single]` | guard | Soft-delete, bulk or single |
| `POST [delete_notifications_perm]` / `[delete_single_perm]` | guard | Hard delete, bulk or single |
| `POST [redeem_reward]` | guard | `redeemReward()`; PRG redirect to `?open_rewards=1&reward_tab=available` |

No GET-JSON endpoints in this file.

## children.php
| Trigger | Auth | Purpose |
|---|---|---|
| `GET children.php` | guard | Per-child detail cards: level/star progress, streaks, points/stars totals+history, today's schedule |
| `POST [adjust_child_points]` | guard + `$canAdjust` (`main_parent`/`secondary_parent`) + child must belong to family | `adjustChildPoints()`, PRG redirect |
| `POST [adjust_child_stars]` | same | `adjustChildStars()`, PRG redirect |
| `GET ?week_schedule=1&child_id=&week_start=` | guard + child must be in caller's own family (`$allowedChildIds`, scoped via `child_profiles.parent_user_id = family_root_id`) | JSON: week's tasks+routines via `serveWeekScheduleJson()` → `buildChildWeekSchedule()` (both now real functions in `includes/functions.php`, promoted from `dashboard_parent.php`'s `$buildWeekSchedule` closure) — **works correctly** |

## task.php
Single `if ($_SERVER['REQUEST_METHOD']==='POST'){ if/elseif }` dispatch block, lines 27-273. No GET-JSON/AJAX endpoints in this file.

| Trigger | Auth | Purpose |
|---|---|---|
| `GET task.php` (+ `?child_id=`, `?status=`, `?category=`, `?time_of_day=`, `?photo_required=`, `?timed=`, `?repeat=` filters) | guard | Task dashboard: pending/completed/approved/expired sections + week calendar. Parent sees whole family (filterable); child sees own only |
| `POST [create_task]` | `canCreateContent()` | Inserts one `tasks` row per selected `child_user_ids[]` (`createTask()`); re-validates `preset_task_id` still active/in-family or falls back to custom |
| `POST [update_task]` | `canCreateContent()` && `canAddEditChild()` | `UPDATE tasks` (only `status='pending'`, family-owned); >1 child selected clones via `createTask()` |
| `POST [delete_task]` | `canCreateContent()` && `canAddEditChild()` | `DELETE FROM tasks` (only `status='pending'`, family-owned) |
| `POST [complete_task]` | owning child, or parent proxy (`canCreateContent()` && `canAddEditChild()` && family-owned) | Optional photo-proof upload, `completeTask()`, notifies parent; parent-completed tasks auto `approveTask()` |
| `POST [approve_task]` | `canCreateContent()` && `canAddEditChild()` | `approveTask()` |
| `POST [reject_task]` | `canCreateContent()` && `canAddEditChild()` | `rejectTask()` with note; `reject_action=reactivate` reopens as pending instead |

## routine.php
Runtime/AJAX actions (JSON, keyed by `$_POST['action']`) live in one block, lines 296-667. Parent-management sentinel-key actions live in a second block, lines 669-1230. (A stray `isset($_POST['create_routine'])` check at line 1314 only re-derives form state for re-render — not a separate route.)

| Trigger | Auth | Purpose |
|---|---|---|
| `GET routine.php` | guard | Routine builder/list. Parent: management + preset library + overtime insights + preferences. Child: own routines w/ today's completion state |
| `POST [action=log_overtime]` (JSON body `overtime_payload`) | guard + child role | Bulk-logs `routine_overtime_logs` via `logRoutineOvertime()`, verifies routine ownership. Returns JSON |
| `POST [action=reset_routine_steps]` | guard + child role | `resetRoutineStepStatuses()` after `routineBelongsToChild()`; clears `$_SESSION['routine_awards']`. Returns JSON |
| `POST [action=set_routine_task_status]` | guard + child role | `setRoutineStepStatus()` toggle, gated by `routineBelongsToChild()` + `routineIsScheduledToday()` (rejects `not_today`). Returns JSON |
| `POST [action=complete_routine_flow]` | guard + child role | Finalizes routine run: award points/stars, `updateChildPoints()`, `completeRoutine()` bonus, writes `routine_points_logs`+completion rows, `updateChildLevelState()`, `refreshRoutineGoalsForChild()`, notifies parent. Blocks duplicate same-day completion. Returns JSON |
| `POST [create_routine]` (+ optional `duplicate_child_id`) | `$isParentContext` | Validates timeframe (`normalizeRoutineStructure`), inserts `routines` row per selected child (`createRoutine()`) + steps (`replaceRoutineSteps()`), transactional |
| `POST [update_routine]` | `$isParentContext` + `routineBelongsToParent()` | `updateRoutine()` + `replaceRoutineSteps()` for primary child; extra selected children get cloned routines |
| `POST [delete_routine]` | `$isParentContext` + `routineBelongsToParent()` | `deleteRoutine()` |
| `POST [create_routine_task]` | `$isParentContext` | `createPresetTask()` — new reusable preset step |
| `POST [update_routine_task]` | `$isParentContext` | `updatePresetTask()` — partial update; existing routines keep their snapshot |
| `POST [delete_routine_task]` | `$isParentContext` | `deletePresetTask()` — hard delete if unreferenced, else soft-archive |
| `POST [archive_preset_task]` / `[restore_preset_task]` | `$isParentContext` | Toggle preset `is_active` |
| `POST [save_routine_preferences]` | `$isParentContext` | `saveRoutinePreferences()` — family routine-timer/UI settings |
| `POST [parent_complete_routine]` | `$isParentContext` + `routineBelongsToParent()` | Manual parent override: checklist + per-task timestamps, `setRoutineStepStatus()` per step, `updateChildPoints()`, conditional bonus, logs, blocks same-day double completion |

## goal.php
| Trigger | Auth | Purpose |
|---|---|---|
| `GET goal.php` (`?status=` UI filter only) | logged in | Parent/adult: family-wide `goals` (joined `rewards`,`routines`,`users`,`goal_routine_targets`,`goal_task_targets`) + creation-form data. Child: own `goals` only |
| `POST [create_goal]` | `canCreateContent()` | `createGoal()` — manual/routine_streak/routine_count/task_quota types; resolves reward via template or existing reward; sets target junction rows |
| `POST [update_goal]` | `canCreateContent()` | `updateGoal()`; optional `reactivate_on_save` also calls `reactivateGoal()` + `refreshGoalProgress()` |
| `POST [delete_goal]` | `canCreateContent()` | `deleteGoal()`, scoped to family |
| `POST [reactivate_goal]` | `canCreateContent()` | `reactivateGoal()` |
| `POST [request_completion]` | child role | `requestGoalCompletion()` → `pending_approval` |
| `POST [approve_goal]` / `[reject_goal]` | `canCreateContent()` | `approveGoal()` / `rejectGoal()` with optional comment |

## rewards.php
Two structurally separate branches by `getEffectiveRole()`: child "shop" view (renders + `exit`s ~line 444) vs. adult management view (rest of file).

| Trigger | Auth | Purpose |
|---|---|---|
| `GET rewards.php` (child) | child | Reward shop: `child_profiles.rewards_shop_open`, `rewards` (today's redemption count), templates, disabled-template map, points/level |
| `POST [purchase_template]` (child) | child | `purchaseRewardTemplate()` — inserts `rewards` row, deducts points; checked against shop-open, balance, level, per-template disable list |
| `GET rewards.php` (adult) | `canCreateContent()` | Reward-management dashboard: children, templates, disabled map, stars-per-level |
| `POST [toggle_shop_access]` | `canCreateContent()` | `child_profiles.rewards_shop_open` toggle |
| `POST [update_reward]` | `canCreateContent()` | `updateReward()` |
| `POST [delete_reward]` | `canCreateContent()` | `deleteReward()` (only if `available`) |
| `POST [fulfill_reward]` | `canCreateContent()` | `fulfillReward()`; relabels `parent_notifications` `reward_fulfilled` |
| `POST [deny_reward]` | `canCreateContent()` | `denyReward()` + note; relabels notification `reward_denied` |
| `POST [create_template]` | `canCreateContent()` | `createRewardTemplate()` + `setRewardTemplateDisabledChildren()` |
| `POST [update_template]` | `canCreateContent()` | `updateRewardTemplate()` + disabled-children list |
| `POST [duplicate_template]` | `canCreateContent()` | `duplicateRewardTemplate()` |
| `POST [delete_template]` | `canCreateContent()` | `deleteRewardTemplate()` |
| `POST [update_level_settings]` | `canCreateContent()` | `updateFamilyStarsPerLevel()` |

## profile.php
| Trigger | Auth | Purpose |
|---|---|---|
| `GET ?self=1` | logged in | View/edit own profile |
| `GET ?type=child&user_id=N` / `?edit_user=N&role_type=...` | parent/family roles to target others; falls back to self otherwise | Edit another family member's profile (child avatar/birthday/gender via `child_profiles`, or adult name/badge/parent_title via `users`) |
| `POST [update_password]` | self, or `main_parent`/`secondary_parent` editing another | `updateUserPassword()`; PRG redirect if editing someone else |
| `POST [update_child_profile]` | self (child), or `main_parent`/`secondary_parent` | `updateChildProfile()`; avatar upload to `uploads/avatars/` (≤3MB, jpg/png/gif/webp, GD-resized to 100×100) |
| `POST [update_parent_profile]` | self, or `main_parent`/`secondary_parent` | Updates name/badge fields; if target is a parent role, also gender + `parent_title` (uniqueness check: one mother/one father per family) |

## index.php / login.php / register.php / logout.php
| Trigger | Auth | Purpose |
|---|---|---|
| `GET index.php` | none (redirects to dashboard if logged in) | Static marketing/landing page; no DB writes |
| `GET login.php` | none (redirects if logged in) | Login form |
| `POST login.php` (no sentinel — any POST) | none | `loginUser()`: `password_verify` against `users`; sets `$_SESSION` (`user_id`,`role`,`role_type`,`username`,`name`); redirects to `dashboard_{role}.php` |
| `GET register.php` | none | Parent registration form |
| `POST register.php` (no sentinel) | none | `registerUser()`: inserts `main_parent` row, auto-login, redirect to `dashboard_parent.php?setup_family=1` |
| `GET/POST logout.php` | none | `session_destroy()`, redirect to `index.php` |

## preset_tasks_api.php
| Trigger | Auth | Purpose |
|---|---|---|
| `GET preset_tasks_api.php` (+ `?include_archived=1`) | session + `canCreateContent()` | Only true JSON REST-style endpoint in the app. Returns family's `preset_tasks` as `{presets:[...]}`. Consumed by `js/preset-picker.js` (used from both `task.php` and `routine.php`) |

---
Last generated: 2026-07-23. Triggered by: initial /docs/codex reference generation request (full read of every root .php controller + includes/functions.php auth helpers). Regenerate when a `.php` controller's POST sentinel keys, GET params, or auth guards change.
