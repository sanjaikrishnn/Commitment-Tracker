-- CommitTrack Migration v3
-- Adds: extension_requests table, original_deadline column in tasks, custom_deadline column in task_assignments
-- Backward-compatible, safe, never drops existing data

USE `committrack`;

-- 1. Add original_deadline to tasks if not exists
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'committrack' AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'original_deadline';
SET @query = IF(@col_exists = 0, 'ALTER TABLE `tasks` ADD COLUMN `original_deadline` DATE NULL DEFAULT NULL AFTER `deadline`', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill original_deadline with existing deadline where NULL
UPDATE `tasks` SET `original_deadline` = `deadline` WHERE `original_deadline` IS NULL AND `deadline` IS NOT NULL;

-- 2. Add custom_deadline to task_assignments if not exists
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'committrack' AND TABLE_NAME = 'task_assignments' AND COLUMN_NAME = 'custom_deadline';
SET @query = IF(@col_exists = 0, 'ALTER TABLE `task_assignments` ADD COLUMN `custom_deadline` DATE NULL DEFAULT NULL AFTER `status`', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Create extension_requests table
CREATE TABLE IF NOT EXISTS `extension_requests` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `task_id`            INT NOT NULL,
    `classroom_id`       INT NOT NULL,
    `student_id`         INT NOT NULL,
    `request_type`       ENUM('individual', 'group') NOT NULL DEFAULT 'individual',
    `original_deadline`  DATE NULL,
    `requested_deadline` DATE NOT NULL,
    `reason`             TEXT NOT NULL,
    `status`             ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `teacher_comment`    TEXT NULL,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at`        TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_ext_status` (`status`),
    INDEX `idx_ext_task_student` (`task_id`, `student_id`),
    CONSTRAINT `fk_ext_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ext_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ext_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
