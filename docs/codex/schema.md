# Schema Reference

MySQL/MariaDB (InnoDB, utf8mb4). No ORM — raw PDO + prepared statements. Schema is **not** managed by migration files in the traditional sense, and it is **not all created by one eager pass** — two separate mechanisms build it up:
1. **Eager bootstrap** (`includes/functions.php`, ~line 4740–5380, runs unconditionally on *every* request that includes `functions.php`): `CREATE TABLE IF NOT EXISTS` + `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` for the "core" tables only — `users`, `child_profiles`, `tasks`, `task_instances`, `reward_templates`, `rewards`, `goals`, `goal_progress`, `goal_task_targets`, `routines`, `goal_routine_targets`, `preset_tasks`, `routine_preset_tasks`, `routine_preferences`, `routine_overtime_logs`, `family_links`, `child_points`, `child_streak_records` (also re-created here, see below), plus `schema_migrations`.
2. **Lazy `ensure*Table()` helpers** (scattered through the file, each called only from inside the specific feature function that needs the table — e.g. `ensureFamilyLevelSettingsTable()` inside `getFamilyStarsPerLevel()`/`updateFamilyStarsPerLevel()`, `ensureChildLevelsTable()` inside `updateChildLevelState()`/`getChildLevelState()`, `ensureRoutineCompletionTables()` inside `getChildRollingStarsAverage()`/`logRoutineCompletionSession()`/`getChildStreaks()`, `ensureRewardTemplateDisabledChildrenTable()` inside the reward-template-disable functions, `ensureChildStarAdjustmentsTable()`, `ensureChildStreaksTable()`, `ensureChildNotificationsTable()`, `ensureParentNotificationsTable()`, `ensureRoutinePointsLogsTable()`): these tables (`child_notifications`, `parent_notifications`, `routine_points_logs`, `routine_completion_logs`, `routine_completion_tasks`, `family_level_settings`, `child_levels`, `child_star_adjustments`, `reward_template_disabled_children`) are **not** guaranteed to exist after a request that never calls the owning feature — e.g. a request that only reaches registration/login will not create them. `child_point_adjustments` and `child_streak_records` are each created both ways (present in the eager block *and* lazily re-ensured before their first write), so those two are always safe to assume exist.

`schema_migrations` tracks one named, one-time migration (`preset_tasks_v1`, see below). When adding a column to any table, match the mechanism it already uses (eager block vs. its owning `ensure*Table()`) rather than assuming the eager block is authoritative for all 29 tables. `tests/fixtures/legacy_schema.sql` is a frozen **pre-migration** snapshot used only as a test fixture (old names `routine_tasks` / `routines_routine_tasks`).

Legend: `PK`=primary key, `FK`=foreign key, `UQ`=unique key. All FKs reference `users(id)` unless noted. Timestamps are `datetime`, not `timestamp`, unless noted.

## users
Root identity table for every account (parent or child both live here).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| username | varchar(50) | no | | UQ |
| password | varchar(255) | no | | bcrypt/password_hash |
| role | enum('main_parent','family_member','caregiver','child') | no | | legacy `'parent'` values migrated to `main_parent` at boot |
| is_secondary | tinyint(1) | yes | 0 | |
| name | varchar(50) | yes | NULL | |
| gender | enum('male','female') | yes | NULL | |
| role_badge_label | varchar(50) | yes | NULL | custom label shown instead of role |
| use_role_badge_label | tinyint(1) | yes | 0 | |
| first_name | varchar(50) | yes | NULL | |
| last_name | varchar(50) | yes | NULL | |
| parent_title | enum('mother','father') | yes | NULL | |
| deleted_at | datetime | yes | NULL | soft delete |

## child_profiles
Per-child extension of `users` (a child is a `users` row + one of these).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| child_user_id | int FK→users.id CASCADE | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | |
| child_name | varchar(50) | yes | NULL | |
| age | int | yes | NULL | |
| avatar | varchar(255) | yes | NULL | |
| birthday | date | yes | NULL | |
| deleted_at | datetime | yes | NULL | soft delete (archived, not FK-cascaded) |
| deleted_by | int | yes | NULL | |
| rewards_shop_open | tinyint(1) | no | 1 | parent can close a child's reward shop |

## family_links
Links secondary parents/caregivers/children to the main parent (family membership + role).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| main_parent_id | int FK→users.id CASCADE | no | | |
| linked_user_id | int FK→users.id CASCADE | no | | |
| role_type | enum('child','secondary_parent','family_member','caregiver') | no | | |
| created_at | datetime | yes | CURRENT_TIMESTAMP | |

`getFamilyRootId()` walks this table to resolve the main_parent_id for any linked user; most tables scope data by `parent_user_id = family_root_id`, i.e. the whole family shares one data namespace owned by the main parent.

## family_level_settings
Per-family config for the child leveling system.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| parent_user_id | int PK, FK→users.id CASCADE | no | | 1:1 with main parent |
| stars_per_level | int | no | 10 | |
| created_at / updated_at | datetime | no | CURRENT_TIMESTAMP | updated_at auto-updates |

## child_levels
Current level state per child (computed from stars).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | |
| child_user_id | int FK→users.id CASCADE | no | | UQ (parent_user_id, child_user_id) |
| current_level | int | no | 1 | |
| pending_level_up | tinyint(1) | no | 0 | flag for "level up" celebration UI |
| last_calculated_at | datetime | yes | NULL | |
| updated_at | datetime | no | CURRENT_TIMESTAMP | auto-updates |

## child_points
Running point total per child (denormalized cache).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| child_user_id | int PK, FK→users.id CASCADE | no | | |
| total_points | int | yes | 0 | updated via `updateChildPoints()` |

## child_point_adjustments
Audit log of manual point +/- adjustments by a parent.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| child_user_id | int | no | | index (child_user_id, created_at); **no FK declared** |
| delta_points | int | no | | signed |
| reason | varchar(255) | no | | |
| created_by | int | no | | |
| created_at | datetime | no | | |

## child_star_adjustments
Same pattern as above but for "stars" (level-up currency, distinct from points).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| child_user_id | int | no | | index (child_user_id, created_at); **no FK declared** |
| delta_stars | int | no | | signed |
| reason | varchar(255) | no | | |
| created_by | int | no | | |
| created_at | datetime | no | | |

## child_streak_records
Best-ever streak counters per child (routines vs. individual tasks tracked separately).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| child_user_id | int PK, FK→users.id CASCADE | no | | |
| routine_best_streak | int | no | 0 | |
| task_best_streak | int | no | 0 | |
| updated_at | datetime | no | | |

## child_notifications / parent_notifications
Identical shape, separate tables for child-facing vs parent-facing notification feeds. Soft-deleted (trash), not hard-deleted, until explicit permanent delete.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| child_user_id / parent_user_id | int | no | | **no FK declared**; indexed (user_id, is_read, created_at) and (user_id, deleted_at) |
| type | varchar(64) | no | | e.g. `points_added`, `task_approved` — free-form string keys used as switch cases in `includes/notifications_child.php` / `notifications_parent.php` |
| message | varchar(255) | no | | |
| link_url | varchar(255) | yes | NULL | |
| is_read | tinyint(1) | no | 0 | |
| deleted_at | datetime | yes | NULL | soft trash |
| created_at | datetime | no | | |

## tasks
Individual (non-routine) chores assigned to one child.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | family owner |
| child_user_id | int FK→users.id CASCADE | no | | assignee |
| title | varchar(100) | no | | |
| description | text | yes | NULL | |
| due_date | datetime | yes | NULL | |
| points | int | yes | NULL | |
| recurrence | enum('daily','weekly','') | yes | '' | recurring tasks spawn `task_instances` |
| category | enum('hygiene','homework','household') | yes | 'household' | |
| timing_mode | enum('timer','suggested','no_limit') | yes | 'no_limit' | |
| timer_minutes | int | yes | NULL | |
| time_of_day | enum('anytime','morning','afternoon','evening') | yes | 'anytime' | grouping key; see `timeOfDayFromTime()` |
| recurrence_days | varchar(32) | yes | NULL | CSV of weekday codes |
| end_date | date | yes | NULL | |
| status | enum('pending','completed','approved','rejected') | yes | 'pending' | |
| photo_proof | varchar(255) | yes | NULL | upload path |
| photo_proof_required | tinyint(1) | yes | 0 | |
| completed_at / approved_at / rejected_at | datetime | yes | NULL | |
| rejected_note | text | yes | NULL | |
| rejected_by | int | yes | NULL | |
| created_by | int FK→users.id SET NULL | yes | NULL | |
| created_at | datetime | yes | CURRENT_TIMESTAMP | |
| preset_task_id | int FK→preset_tasks.id SET NULL | yes | NULL | set when task was created from a Preset Task; values are **snapshotted**, not live-linked |

## task_instances
Per-date completion record for a **recurring** task (one `tasks` row → many dated instances).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| task_id | int FK→tasks.id CASCADE | no | | UQ (task_id, date_key) |
| date_key | date | no | | |
| status | enum('completed','approved','rejected') | no | | |
| note | text | yes | NULL | |
| photo_proof | varchar(255) | yes | NULL | |
| completed_at / approved_at / rejected_at | datetime | yes | NULL | |
| created_at / updated_at | datetime | yes | CURRENT_TIMESTAMP | updated_at auto-updates |

## preset_tasks
*Renamed from `routine_tasks` in the `preset_tasks_v1` migration (July 2026).* Global reusable task template library, used both by the routine builder and to spawn one-off `tasks`.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | |
| title | varchar(100) | no | | |
| description | text | yes | NULL | |
| time_limit | int | yes | NULL | seconds |
| point_value | int | yes | NULL | |
| category | enum('hygiene','homework','household') | yes | 'household' | |
| minimum_seconds | int | yes | NULL | **module-gated**: only enforced if `minimum_enabled` |
| minimum_enabled | tinyint(1) | no | 0 | gate flag for minimum_seconds |
| default_time_of_day | enum('anytime','morning','afternoon','evening') | no | 'anytime' | added in preset_tasks_v1 |
| is_active | tinyint(1) | no | 1 | added in preset_tasks_v1; soft "archive" flag |
| archived_at | datetime | yes | NULL | added in preset_tasks_v1; set instead of hard-delete when preset is still referenced |
| icon_url / audio_url | varchar(255) | yes | NULL | |
| created_by | int FK→users.id SET NULL | yes | NULL | |
| status | enum('pending','completed','approved') | yes | 'pending' | legacy column, largely unused post-migration |
| created_at | datetime | yes | CURRENT_TIMESTAMP | |

## routine_preset_tasks
*Renamed from `routines_routine_tasks`.* Junction: which presets belong to which routine, in what order, with **add-time snapshots** so later preset edits never retroactively change an existing routine.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| routine_id | int PK, FK→routines.id CASCADE | no | | composite PK (routine_id, preset_task_id) |
| preset_task_id | int PK, FK→preset_tasks.id RESTRICT | no | | `fk_rps_preset`; RESTRICT so an in-use preset can't be hard-deleted |
| sequence_order | int | no | | |
| dependency_id | int FK→preset_tasks.id SET NULL | yes | NULL | `fk_rps_dependency`; another step that gates this one |
| status | enum('pending','completed') | yes | 'pending' | this run's status |
| completed_at | datetime | yes | NULL | |
| title, description, time_limit, point_value, minimum_seconds, minimum_enabled, category, icon_url, audio_url | (mixed) | yes | NULL | **snapshot columns** — copied from `preset_tasks` at add time, immune to later preset edits |

## routines
A named, scheduled group of preset tasks for one child.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | |
| child_user_id | int FK→users.id CASCADE | no | | |
| title | varchar(100) | no | | |
| start_time / end_time | time | yes | NULL | |
| recurrence | enum('daily','weekly','') | yes | '' | |
| bonus_points | int | yes | 0 | awarded on full on-time completion |
| time_of_day | enum('anytime','morning','afternoon','evening') | yes | 'anytime' | |
| recurrence_days | varchar(32) | yes | NULL | CSV weekday codes |
| routine_date | date | yes | NULL | for one-off (non-recurring) routines |
| created_by | int FK→users.id SET NULL | yes | NULL | |
| created_at | datetime | yes | CURRENT_TIMESTAMP | |

## routine_completion_logs
One row per full routine run by a child.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| routine_id | int FK→routines.id CASCADE | no | | |
| child_user_id | int FK→users.id CASCADE | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | |
| completed_by | enum('child','parent') | no | 'child' | parent can complete on child's behalf |
| started_at | datetime | yes | NULL | |
| completed_at | datetime | no | | |
| status_screen_seconds | int | no | 0 | time spent on inter-step status screen |
| created_at | datetime | no | CURRENT_TIMESTAMP | |

## routine_completion_tasks
Per-step detail rows for a `routine_completion_logs` entry.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| completion_log_id | int FK→routine_completion_logs.id CASCADE | no | | index (completion_log_id, sequence_order) |
| preset_task_id | int FK→preset_tasks.id SET NULL | yes | NULL | `fk_rct_preset`; nullable so history survives a hard-deleted preset |
| sequence_order | int | no | 0 | |
| completed_at | datetime | yes | NULL | |
| scheduled_seconds / actual_seconds | int | yes | NULL | |
| status_screen_seconds | int | no | 0 | |
| stars_awarded | tinyint | no | 0 | |
| task_title | varchar(100) | yes | NULL | snapshot, survives preset delete |
| points_awarded | int | yes | NULL | snapshot |
| created_at | datetime | no | CURRENT_TIMESTAMP | |

## routine_overtime_logs
Logs when a child exceeds a preset's time limit during a routine.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| routine_id | int FK→routines.id CASCADE | no | | |
| preset_task_id | int FK→preset_tasks.id SET NULL | yes | NULL | `fk_rol_preset` |
| child_user_id | int FK→users.id CASCADE | no | | |
| scheduled_seconds / actual_seconds / overtime_seconds | int | no | | |
| occurred_at | datetime | yes | CURRENT_TIMESTAMP | |

## routine_points_logs
Points-earned audit trail per routine run (task points vs. bonus points split out).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| routine_id | int | no | | **no FK declared**; index |
| child_user_id | int | no | | index (child_user_id, created_at) |
| task_points | int | no | 0 | |
| bonus_points | int | no | 0 | |
| created_at | datetime | no | | |

## routine_preferences
Per-family UX settings for the routine-runner screen.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | UQ |
| timer_warnings_enabled | tinyint(1) | yes | 1 | |
| sub_timer_label | varchar(50) | yes | 'hurry_goal' | |
| show_countdown | tinyint(1) | yes | 1 | |
| progress_style | varchar(12) | yes | 'bar' | `bar` vs alternative UI style |
| sound_effects_enabled | tinyint(1) | yes | 1 | |
| background_music_enabled | tinyint(1) | yes | 1 | |
| updated_at / created_at | datetime | yes | CURRENT_TIMESTAMP | updated_at auto-updates |

## goals
Parent-set targets for a child, either manual (point total) or auto-tracked against a task/routine/category.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | |
| child_user_id | int FK→users.id CASCADE | no | | |
| title | varchar(100) | no | | |
| description | text | yes | NULL | |
| target_points | int | no | 0 | |
| start_date / end_date | datetime | yes | NULL | |
| status | enum('active','pending_approval','completed','rejected') | yes | 'active' | |
| reward_id | int FK→rewards.id SET NULL | yes | NULL | |
| goal_type | varchar(24) | yes | 'manual' | `manual`\|task-linked\|routine-linked\|category |
| routine_id | int | yes | NULL | see `goal_routine_targets` for the real FK link |
| task_category | varchar(50) | yes | NULL | category-based auto goal |
| target_count | int | no | 0 | for count-based goals |
| streak_required | int | no | 0 | for streak-based goals |
| require_on_time | tinyint(1) | no | 0 | |
| points_awarded | int | no | 0 | |
| award_mode | varchar(12) | yes | 'both' | e.g. points/stars/both |
| requires_parent_approval | tinyint(1) | no | 1 | |
| completed_at / requested_at / rejected_at | datetime | yes | NULL | |
| rejection_comment | text | yes | NULL | |
| created_by | int FK→users.id SET NULL | yes | NULL | |
| created_at | datetime | yes | CURRENT_TIMESTAMP | |

## goal_progress
Live progress counter per goal (1:1).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| goal_id | int FK→goals.id CASCADE | no | | UQ |
| child_user_id | int FK→users.id CASCADE | no | | index (child_user_id, goal_id) |
| current_count | int | no | 0 | |
| current_streak | int | no | 0 | |
| last_progress_date | date | yes | NULL | |
| next_needed_hint | varchar(255) | yes | NULL | UI hint string |
| celebration_shown | tinyint(1) | no | 0 | |
| updated_at | datetime | yes | CURRENT_TIMESTAMP | auto-updates |

## goal_task_targets / goal_routine_targets
Junctions linking an auto-tracked goal to specific `tasks` or `routines` it monitors.
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| goal_id | int FK→goals.id CASCADE | no | | UQ (goal_id, task_id) / (goal_id, routine_id) |
| task_id / routine_id | int FK CASCADE | no | | |

## reward_templates
Family-level reusable reward catalog (the "library" a specific `rewards` row can be minted from).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | |
| title | varchar(100) | no | | |
| description | text | yes | NULL | |
| point_cost | int | no | | |
| level_required | int | no | 1 | **module-gated**: reward hidden until child reaches this level |
| icon_class / icon_color | varchar | yes | NULL | |
| created_by | int FK→users.id SET NULL | yes | NULL | |
| created_at | datetime | yes | CURRENT_TIMESTAMP | |

## reward_template_disabled_children
Per-child opt-out of a reward template (template stays in catalog but hidden for that child).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | index (parent_user_id, template_id) |
| template_id | int FK→reward_templates.id CASCADE | no | | UQ (template_id, child_user_id) |
| child_user_id | int FK→users.id CASCADE | no | | |
| created_at | datetime | no | CURRENT_TIMESTAMP | |

## rewards
Concrete redeemable reward instances (may be minted from a template or created ad hoc; may be family-wide or child-specific).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | int PK AI | no | | |
| parent_user_id | int FK→users.id CASCADE | no | | |
| child_user_id | int FK→users.id CASCADE | yes | NULL | NULL = available to whole family |
| template_id | int FK→reward_templates.id SET NULL | yes | NULL | |
| title | varchar(100) | no | | |
| description | text | yes | NULL | |
| point_cost | int | no | | |
| status | enum('available','redeemed') | yes | 'available' | |
| created_on | timestamp | yes | CURRENT_TIMESTAMP | note: `timestamp` not `datetime` (only exception) |
| redeemed_by | int FK→users.id SET NULL | yes | NULL | |
| redeemed_on | datetime | yes | NULL | |
| fulfilled_on | datetime | yes | NULL | parent marks reward as delivered |
| fulfilled_by | int FK→users.id SET NULL | yes | NULL | |
| created_by | int FK→users.id SET NULL | yes | NULL | |
| denied_on | datetime | yes | NULL | parent can deny a redemption |
| denied_by | int | yes | NULL | |
| denied_note | varchar(255) | yes | NULL | |

## schema_migrations
One-time migration marker table (not a general migration framework — only `preset_tasks_v1` is ever inserted as of this writing).
| Field | Type | Null | Default | Notes |
|---|---|---|---|---|
| name | varchar(64) PK | no | | |
| applied_at | datetime | yes | CURRENT_TIMESTAMP | |

## Cross-cutting notes
- **No `sessions` table** — PHP native `$_SESSION` (file-based) is the only session store.
- **No subscriptions/billing table** despite `docs/child-app-development-build-outline.md` planning one — monetization was never built; treat any "paid feature" references in docs as aspirational/unimplemented.
- **Soft delete** is inconsistent: `users`, `child_profiles` use `deleted_at`; notifications use `deleted_at` as a *trash* state (separate from permanent delete); most other tables have no soft delete and rely on `ON DELETE CASCADE`.
- **Snapshot pattern**: `routine_preset_tasks`, `routine_completion_tasks`, and `tasks.preset_task_id` all copy preset field values at write time rather than joining live — this is deliberate (see `docs/preset-tasks-migration.md`) so editing a `preset_tasks` row never silently changes history or in-flight routines.
- A handful of high-traffic tables (`child_point_adjustments`, `child_star_adjustments`, `child_notifications`, `parent_notifications`, `routine_points_logs`) skip declaring an FK on their `*_user_id` column even though it always references `users.id` — an artifact of being added later via inline `ensure*Table()` helpers rather than the main bootstrap block.

---
Last generated: 2026-07-23. Triggered by: initial /docs/codex reference generation request (full-repo scan of `includes/functions.php` schema bootstrap + `tests/fixtures/legacy_schema.sql`). Regenerate when table/column DDL in `includes/functions.php` changes.
