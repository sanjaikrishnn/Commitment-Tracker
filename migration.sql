-- CommitTrack Migration v2
-- Adds: classrooms, classroom_members, tasks, task_assignments
-- SAFE: never drops existing tables or data

USE `committrack`;

-- 1. Classrooms
CREATE TABLE IF NOT EXISTS `classrooms` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id`    INT NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `section`     VARCHAR(60) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `invite_code` CHAR(12) NOT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_invite_code` (`invite_code`),
    CONSTRAINT `fk_classroom_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Classroom memberships (student ↔ classroom)
CREATE TABLE IF NOT EXISTS `classroom_members` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `classroom_id` INT NOT NULL,
    `student_id`   INT NOT NULL,
    `joined_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_membership` (`classroom_id`, `student_id`),
    CONSTRAINT `fk_member_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_member_student`   FOREIGN KEY (`student_id`)   REFERENCES `users`(`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tasks (owned by admin)
CREATE TABLE IF NOT EXISTS `tasks` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id`     INT NOT NULL,
    `classroom_id` INT NOT NULL,
    `title`        VARCHAR(200) NOT NULL,
    `description`  TEXT DEFAULT NULL,
    `deadline`     DATE DEFAULT NULL,
    `priority`     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_task_admin`      FOREIGN KEY (`admin_id`)     REFERENCES `users`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_task_classroom`  FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Per-student task assignments and status tracking
CREATE TABLE IF NOT EXISTS `task_assignments` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `task_id`    INT NOT NULL,
    `student_id` INT NOT NULL,
    `status`     ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_assignment` (`task_id`, `student_id`),
    CONSTRAINT `fk_assign_task`    FOREIGN KEY (`task_id`)    REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assign_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
