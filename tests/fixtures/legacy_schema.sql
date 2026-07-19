SET FOREIGN_KEY_CHECKS=0;
/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `child_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `current_level` int(11) NOT NULL DEFAULT 1,
  `pending_level_up` tinyint(1) NOT NULL DEFAULT 0,
  `last_calculated_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_child_level` (`parent_user_id`,`child_user_id`),
  KEY `child_user_id` (`child_user_id`),
  CONSTRAINT `child_levels_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `child_levels_ibfk_2` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `child_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_user_id` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_child_read` (`child_user_id`,`is_read`,`created_at`),
  KEY `idx_child_deleted` (`child_user_id`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `child_points` (
  `child_user_id` int(11) NOT NULL,
  `total_points` int(11) DEFAULT 0,
  PRIMARY KEY (`child_user_id`),
  CONSTRAINT `child_points_ibfk_1` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `child_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_user_id` int(11) NOT NULL,
  `parent_user_id` int(11) NOT NULL,
  `child_name` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `rewards_shop_open` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `child_user_id` (`child_user_id`),
  KEY `idx_child_profiles_deleted` (`parent_user_id`,`deleted_at`),
  CONSTRAINT `child_profiles_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `child_profiles_ibfk_2` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `child_star_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `child_user_id` int(11) NOT NULL,
  `delta_stars` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_child_created` (`child_user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `child_streak_records` (
  `child_user_id` int(11) NOT NULL,
  `routine_best_streak` int(11) NOT NULL DEFAULT 0,
  `task_best_streak` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`child_user_id`),
  CONSTRAINT `child_streak_records_ibfk_1` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_level_settings` (
  `parent_user_id` int(11) NOT NULL,
  `stars_per_level` int(11) NOT NULL DEFAULT 10,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`parent_user_id`),
  CONSTRAINT `family_level_settings_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `main_parent_id` int(11) NOT NULL,
  `linked_user_id` int(11) NOT NULL,
  `role_type` enum('child','secondary_parent','family_member','caregiver') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `main_parent_id` (`main_parent_id`),
  KEY `linked_user_id` (`linked_user_id`),
  CONSTRAINT `family_links_ibfk_1` FOREIGN KEY (`main_parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `family_links_ibfk_2` FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goal_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `current_count` int(11) NOT NULL DEFAULT 0,
  `current_streak` int(11) NOT NULL DEFAULT 0,
  `last_progress_date` date DEFAULT NULL,
  `next_needed_hint` varchar(255) DEFAULT NULL,
  `celebration_shown` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_goal_progress` (`goal_id`),
  KEY `idx_goal_child` (`child_user_id`,`goal_id`),
  CONSTRAINT `goal_progress_ibfk_1` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goal_progress_ibfk_2` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_routine_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goal_id` int(11) NOT NULL,
  `routine_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_goal_routine` (`goal_id`,`routine_id`),
  KEY `routine_id` (`routine_id`),
  CONSTRAINT `goal_routine_targets_ibfk_1` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goal_routine_targets_ibfk_2` FOREIGN KEY (`routine_id`) REFERENCES `routines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_task_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goal_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_goal_task` (`goal_id`,`task_id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `goal_task_targets_ibfk_1` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goal_task_targets_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `target_points` int(11) NOT NULL DEFAULT 0,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` enum('active','pending_approval','completed','rejected') DEFAULT 'active',
  `reward_id` int(11) DEFAULT NULL,
  `goal_type` varchar(24) DEFAULT 'manual',
  `routine_id` int(11) DEFAULT NULL,
  `task_category` varchar(50) DEFAULT NULL,
  `target_count` int(11) NOT NULL DEFAULT 0,
  `streak_required` int(11) NOT NULL DEFAULT 0,
  `require_on_time` tinyint(1) NOT NULL DEFAULT 0,
  `points_awarded` int(11) NOT NULL DEFAULT 0,
  `award_mode` varchar(12) DEFAULT 'both',
  `requires_parent_approval` tinyint(1) NOT NULL DEFAULT 1,
  `completed_at` datetime DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_comment` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_user_id` (`parent_user_id`),
  KEY `child_user_id` (`child_user_id`),
  KEY `reward_id` (`reward_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `goals_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goals_ibfk_2` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goals_ibfk_3` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goals_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parent_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent_read` (`parent_user_id`,`is_read`,`created_at`),
  KEY `idx_parent_deleted` (`parent_user_id`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reward_template_disabled_children` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_template_child` (`template_id`,`child_user_id`),
  KEY `idx_parent_template` (`parent_user_id`,`template_id`),
  KEY `child_user_id` (`child_user_id`),
  CONSTRAINT `reward_template_disabled_children_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reward_template_disabled_children_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `reward_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reward_template_disabled_children_ibfk_3` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reward_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `point_cost` int(11) NOT NULL,
  `level_required` int(11) NOT NULL DEFAULT 1,
  `icon_class` varchar(64) DEFAULT NULL,
  `icon_color` varchar(16) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_user_id` (`parent_user_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `reward_templates_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reward_templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `child_user_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `point_cost` int(11) NOT NULL,
  `status` enum('available','redeemed') DEFAULT 'available',
  `created_on` timestamp NULL DEFAULT current_timestamp(),
  `redeemed_by` int(11) DEFAULT NULL,
  `redeemed_on` datetime DEFAULT NULL,
  `fulfilled_on` datetime DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `denied_on` datetime DEFAULT NULL,
  `denied_by` int(11) DEFAULT NULL,
  `denied_note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_user_id` (`parent_user_id`),
  KEY `redeemed_by` (`redeemed_by`),
  KEY `fulfilled_by` (`fulfilled_by`),
  KEY `created_by` (`created_by`),
  KEY `fk_rewards_child_user` (`child_user_id`),
  KEY `fk_rewards_template` (`template_id`),
  CONSTRAINT `fk_rewards_child_user` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rewards_template` FOREIGN KEY (`template_id`) REFERENCES `reward_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rewards_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rewards_ibfk_2` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rewards_ibfk_3` FOREIGN KEY (`template_id`) REFERENCES `reward_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rewards_ibfk_4` FOREIGN KEY (`redeemed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rewards_ibfk_5` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rewards_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routine_completion_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `routine_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `parent_user_id` int(11) NOT NULL,
  `completed_by` enum('child','parent') NOT NULL DEFAULT 'child',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime NOT NULL,
  `status_screen_seconds` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_routine_completion_parent` (`parent_user_id`,`completed_at`),
  KEY `idx_routine_completion_child` (`child_user_id`,`completed_at`),
  KEY `routine_id` (`routine_id`),
  CONSTRAINT `routine_completion_logs_ibfk_1` FOREIGN KEY (`routine_id`) REFERENCES `routines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routine_completion_logs_ibfk_2` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routine_completion_logs_ibfk_3` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routine_completion_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `completion_log_id` int(11) NOT NULL,
  `routine_task_id` int(11) NOT NULL,
  `sequence_order` int(11) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `scheduled_seconds` int(11) DEFAULT NULL,
  `actual_seconds` int(11) DEFAULT NULL,
  `status_screen_seconds` int(11) NOT NULL DEFAULT 0,
  `stars_awarded` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_routine_completion_task` (`completion_log_id`,`sequence_order`),
  KEY `routine_task_id` (`routine_task_id`),
  CONSTRAINT `routine_completion_tasks_ibfk_1` FOREIGN KEY (`completion_log_id`) REFERENCES `routine_completion_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routine_completion_tasks_ibfk_2` FOREIGN KEY (`routine_task_id`) REFERENCES `routine_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routine_overtime_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `routine_id` int(11) NOT NULL,
  `routine_task_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `scheduled_seconds` int(11) NOT NULL,
  `actual_seconds` int(11) NOT NULL,
  `overtime_seconds` int(11) NOT NULL,
  `occurred_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `routine_id` (`routine_id`),
  KEY `routine_task_id` (`routine_task_id`),
  KEY `child_user_id` (`child_user_id`),
  CONSTRAINT `routine_overtime_logs_ibfk_1` FOREIGN KEY (`routine_id`) REFERENCES `routines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routine_overtime_logs_ibfk_2` FOREIGN KEY (`routine_task_id`) REFERENCES `routine_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routine_overtime_logs_ibfk_3` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routine_points_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `routine_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `task_points` int(11) NOT NULL DEFAULT 0,
  `bonus_points` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_routine_points_child` (`child_user_id`,`created_at`),
  KEY `idx_routine_points_routine` (`routine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routine_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `timer_warnings_enabled` tinyint(1) DEFAULT 1,
  `sub_timer_label` varchar(50) DEFAULT 'hurry_goal',
  `show_countdown` tinyint(1) DEFAULT 1,
  `progress_style` varchar(12) DEFAULT 'bar',
  `sound_effects_enabled` tinyint(1) DEFAULT 1,
  `background_music_enabled` tinyint(1) DEFAULT 1,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parent` (`parent_user_id`),
  CONSTRAINT `routine_preferences_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routine_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `point_value` int(11) DEFAULT NULL,
  `category` enum('hygiene','homework','household') DEFAULT 'household',
  `icon_url` varchar(255) DEFAULT NULL,
  `audio_url` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('pending','completed','approved') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `minimum_seconds` int(11) DEFAULT NULL,
  `minimum_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `parent_user_id` (`parent_user_id`),
  KEY `fk_routine_tasks_created_by` (`created_by`),
  CONSTRAINT `fk_routine_tasks_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `routine_tasks_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routine_tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `recurrence` enum('daily','weekly','') DEFAULT '',
  `bonus_points` int(11) DEFAULT 0,
  `time_of_day` enum('anytime','morning','afternoon','evening') DEFAULT 'anytime',
  `recurrence_days` varchar(32) DEFAULT NULL,
  `routine_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_user_id` (`parent_user_id`),
  KEY `child_user_id` (`child_user_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `routines_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routines_ibfk_2` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routines_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `routines_routine_tasks` (
  `routine_id` int(11) NOT NULL,
  `routine_task_id` int(11) NOT NULL,
  `sequence_order` int(11) NOT NULL,
  `dependency_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`routine_id`,`routine_task_id`),
  KEY `routine_task_id` (`routine_task_id`),
  KEY `dependency_id` (`dependency_id`),
  CONSTRAINT `routines_routine_tasks_ibfk_1` FOREIGN KEY (`routine_id`) REFERENCES `routines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routines_routine_tasks_ibfk_2` FOREIGN KEY (`routine_task_id`) REFERENCES `routine_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routines_routine_tasks_ibfk_3` FOREIGN KEY (`dependency_id`) REFERENCES `routine_tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_instances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `date_key` date NOT NULL,
  `status` enum('completed','approved','rejected') NOT NULL,
  `note` text DEFAULT NULL,
  `photo_proof` varchar(255) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_date` (`task_id`,`date_key`),
  KEY `idx_task_status` (`task_id`,`status`),
  CONSTRAINT `task_instances_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `recurrence` enum('daily','weekly','') DEFAULT '',
  `category` enum('hygiene','homework','household') DEFAULT 'household',
  `timing_mode` enum('timer','suggested','no_limit') DEFAULT 'no_limit',
  `timer_minutes` int(11) DEFAULT NULL,
  `time_of_day` enum('anytime','morning','afternoon','evening') DEFAULT 'anytime',
  `recurrence_days` varchar(32) DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending','completed','approved','rejected') DEFAULT 'pending',
  `photo_proof` varchar(255) DEFAULT NULL,
  `photo_proof_required` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_note` text DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_user_id` (`parent_user_id`),
  KEY `child_user_id` (`child_user_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('main_parent','family_member','caregiver','child') NOT NULL,
  `is_secondary` tinyint(1) DEFAULT 0,
  `name` varchar(50) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `role_badge_label` varchar(50) DEFAULT NULL,
  `use_role_badge_label` tinyint(1) DEFAULT 0,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `parent_title` enum('mother','father') DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET FOREIGN_KEY_CHECKS=1;
