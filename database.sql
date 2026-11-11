-- CommitTrack Database Schema
-- Complete College Project Database Schema for CommitTrack
-- Compatible with XAMPP MySQL / MariaDB

CREATE DATABASE IF NOT EXISTS `committrack` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `committrack`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL,
    `email`         VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Classrooms Table
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

-- 3. Classroom Memberships Table (Student <-> Classroom)
CREATE TABLE IF NOT EXISTS `classroom_members` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `classroom_id` INT NOT NULL,
    `student_id`   INT NOT NULL,
    `joined_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_membership` (`classroom_id`, `student_id`),
    CONSTRAINT `fk_member_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_member_student`   FOREIGN KEY (`student_id`)   REFERENCES `users`(`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tasks Table
CREATE TABLE IF NOT EXISTS `tasks` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id`          INT NOT NULL,
    `classroom_id`      INT NOT NULL,
    `title`             VARCHAR(200) NOT NULL,
    `description`       TEXT DEFAULT NULL,
    `deadline`          DATE DEFAULT NULL,
    `original_deadline` DATE DEFAULT NULL,
    `priority`          ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_task_admin`      FOREIGN KEY (`admin_id`)     REFERENCES `users`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_task_classroom`  FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Task Assignments Table (Per-Student Task Tracking)
CREATE TABLE IF NOT EXISTS `task_assignments` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `task_id`         INT NOT NULL,
    `student_id`      INT NOT NULL,
    `status`          ENUM('pending', 'in_progress', 'completed') NOT NULL DEFAULT 'pending',
    `custom_deadline` DATE DEFAULT NULL,
    `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_assignment` (`task_id`, `student_id`),
    CONSTRAINT `fk_assign_task`    FOREIGN KEY (`task_id`)    REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assign_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Extension Requests Table
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

-- Default Demo Accounts (Pass: Student@123 / Admin@123)
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES
('Demo Student', 'student@committrack.com', '$2y$10$WCDRDqlgnsTtziMMJe3gQONfHQejaIW9poDp42DQgfnFzR.6SZB4W', 'student'),
('Administrator', 'admin@committrack.com', '$2y$10$.pXsYmJWBzPl.qCIBS.n3evT7BF0Vcic23w9qtzhZ3xhvtAmscOY.', 'admin')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
