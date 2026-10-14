-- ============================================================
-- School_db1 - PARENT DASHBOARD MODULE
-- Adds parent accounts, parent-child links, and announcements
-- ============================================================
-- HOW TO IMPORT:
-- 1. Open phpMyAdmin → http://localhost/phpmyadmin
-- 2. Click database "School_db1" on the left side
-- 3. Click the "Import" tab → Choose this file → click "Go"
-- ============================================================

USE `School_db1`;

-- ------------------------------------------------------------
-- 1. Allow 'parent' as a login role
-- ------------------------------------------------------------
ALTER TABLE `users`
  MODIFY `role` ENUM('admin','teacher','student','parent') NOT NULL;

-- ------------------------------------------------------------
-- 2. Parents
--    Login username = phone number (stored in users.username)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parents` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) DEFAULT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20) NOT NULL,
  `email`      VARCHAR(100) DEFAULT NULL,
  `address`    TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parent_phone` (`phone`),
  UNIQUE KEY `unique_parent_user`  (`user_id`),
  CONSTRAINT `par_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 3. Which children belong to which parent
--    (a parent can have several children; a child can have
--     both a mother and a father account)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parent_students` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `parent_id`     INT(11) NOT NULL,
  `student_id`    INT(11) NOT NULL,
  `relationship`  ENUM('Father','Mother','Guardian') NOT NULL DEFAULT 'Guardian',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parent_student` (`parent_id`, `student_id`),
  KEY `idx_student` (`student_id`),
  CONSTRAINT `ps_fk_parent`  FOREIGN KEY (`parent_id`)  REFERENCES `parents`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `ps_fk_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 4. School announcements (news, events, holidays, exam dates)
--    audience = who should see it
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcements` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(150) NOT NULL,
  `body`        TEXT NOT NULL,
  `type`        ENUM('News','Event','Holiday','Exam') NOT NULL DEFAULT 'News',
  `audience`    ENUM('All','Parents','Students','Teachers') NOT NULL DEFAULT 'All',
  `event_date`  DATE DEFAULT NULL,
  `is_pinned`   TINYINT(1) NOT NULL DEFAULT 0,
  `posted_by`   INT(11) DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `ann_fk_user` FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 5. Sample announcement so the board is not empty
-- ------------------------------------------------------------
INSERT IGNORE INTO `announcements` (`id`, `title`, `body`, `type`, `audience`, `is_pinned`, `posted_by`)
VALUES (1, 'Welcome to the Parent Portal',
        'Dear parents, you can now follow your child''s results, attendance and school fees online. Please contact the school office if you need help signing in.',
        'News', 'All', 1, 1);

-- ============================================================
-- DONE!
-- ✅ users.role now supports 'parent'
-- ✅ parents table            (parent accounts, login = phone)
-- ✅ parent_students table    (links parents to their children)
-- ✅ announcements table      (news, events, holidays, exams)
-- ============================================================
