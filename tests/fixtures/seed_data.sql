-- Seed data for migration/parity tests. Loaded on top of legacy_schema.sql.
-- All dates are relative to the run date so streak/goal math stays deterministic.
SET FOREIGN_KEY_CHECKS=0;

INSERT INTO users (id, username, password, role, name, first_name) VALUES
 (1,'parent1','x','main_parent','Pat','Pat'),
 (2,'child1','x','child','Jonah','Jonah'),
 (3,'child2','x','child','Mia','Mia');

INSERT INTO child_profiles (id, child_user_id, parent_user_id, child_name, age) VALUES
 (1,2,1,'Jonah',8),
 (2,3,1,'Mia',6);

-- Routine Task Library (becomes Preset Tasks after migration)
INSERT INTO routine_tasks (id, parent_user_id, title, description, time_limit, point_value, category, minimum_seconds, minimum_enabled, created_by) VALUES
 (1,1,'Brush Teeth','Two full minutes',5,10,'hygiene',NULL,0,1),
 (2,1,'Make Bed','Pillows on top',10,15,'household',60,1,1),
 (3,1,'Do Homework','Math worksheet',30,20,'homework',NULL,0,1),
 (4,1,'Feed the Dog','Fill bowl with one scoop',5,5,'household',NULL,0,1);

INSERT INTO routines (id, parent_user_id, child_user_id, title, start_time, end_time, recurrence, bonus_points, time_of_day, recurrence_days, routine_date, created_by) VALUES
 (1,1,2,'Morning Routine','07:00:00','08:00:00','daily',5,'morning',NULL,NULL,1),
 (2,1,2,'Evening Routine','19:00:00','20:00:00','daily',0,'evening',NULL,NULL,1);

INSERT INTO routines_routine_tasks (routine_id, routine_task_id, sequence_order, dependency_id, status) VALUES
 (1,1,1,NULL,'pending'),
 (1,2,2,NULL,'pending'),
 (2,3,1,NULL,'pending'),
 (2,4,2,3,'pending');

-- Individual tasks: approved one-off, recurring daily with two instances, pending one-off
INSERT INTO tasks (id, parent_user_id, child_user_id, title, description, due_date, points, recurrence, category, timing_mode, timer_minutes, time_of_day, recurrence_days, end_date, status, completed_at, approved_at, created_by) VALUES
 (1,1,2,'Clean Room','Floor and desk', CONCAT(CURDATE() - INTERVAL 2 DAY,' 17:00:00'),10,'','household','no_limit',NULL,'afternoon',NULL,NULL,'approved', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, 1),
 (2,1,2,'Read 20 Minutes','Any book', CONCAT(CURDATE(),' 19:30:00'),5,'daily','homework','timer',20,'evening',NULL,NULL,'pending',NULL,NULL,1),
 (3,1,3,'Water Plants','Back porch too', CONCAT(CURDATE(),' 23:59:00'),5,'','household','no_limit',NULL,'anytime',NULL,NULL,'pending',NULL,NULL,1);

INSERT INTO task_instances (task_id, date_key, status, completed_at, approved_at) VALUES
 (2, CURDATE() - INTERVAL 1 DAY, 'approved', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
 (2, CURDATE(), 'completed', NOW(), NULL);

INSERT INTO child_points (child_user_id, total_points) VALUES (2, 75), (3, 0);

-- Morning routine completed yesterday and today
INSERT INTO routine_points_logs (id, routine_id, child_user_id, task_points, bonus_points, created_at) VALUES
 (1,1,2,25,5, NOW() - INTERVAL 1 DAY),
 (2,1,2,25,5, NOW());

INSERT INTO routine_completion_logs (id, routine_id, child_user_id, parent_user_id, completed_by, started_at, completed_at) VALUES
 (1,1,2,1,'child', NOW() - INTERVAL 1 DAY - INTERVAL 20 MINUTE, NOW() - INTERVAL 1 DAY),
 (2,1,2,1,'child', NOW() - INTERVAL 25 MINUTE, NOW());

INSERT INTO routine_completion_tasks (completion_log_id, routine_task_id, sequence_order, completed_at, scheduled_seconds, actual_seconds, stars_awarded) VALUES
 (1,1,1, NOW() - INTERVAL 1 DAY, 300, 240, 3),
 (1,2,2, NOW() - INTERVAL 1 DAY, 600, 500, 3),
 (2,1,1, NOW(), 300, 280, 3),
 (2,2,2, NOW(), 600, 700, 2);

INSERT INTO routine_overtime_logs (routine_id, routine_task_id, child_user_id, scheduled_seconds, actual_seconds, overtime_seconds, occurred_at) VALUES
 (1,2,2,600,700,100, NOW());

-- Goals: task_quota targeting task 2, routine_count + routine_streak targeting routine 1
INSERT INTO goals (id, parent_user_id, child_user_id, title, goal_type, target_count, streak_required, status, points_awarded, award_mode) VALUES
 (1,1,2,'Read 5 times','task_quota',5,0,'active',20,'points'),
 (2,1,2,'Morning routine 10 times','routine_count',10,0,'active',30,'points'),
 (3,1,2,'3-day morning streak','routine_streak',0,3,'active',15,'points');

INSERT INTO goal_task_targets (goal_id, task_id) VALUES (1, 2);
INSERT INTO goal_routine_targets (goal_id, routine_id) VALUES (2, 1), (3, 1);

SET FOREIGN_KEY_CHECKS=1;
