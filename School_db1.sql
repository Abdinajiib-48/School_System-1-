-- ============================================================
-- School_db1 - UPGRADE SCRIPT
-- Run this on your existing database to add missing features
-- ============================================================
-- HOW TO IMPORT:
-- 1. Open phpMyAdmin → http://localhost/phpmyadmin
-- 2. Click on your database "school_db" on the left side
-- 3. Click the "Import" tab at the top
-- 4. Choose this file → click "Go"
-- ============================================================

USE `School_db1`;

-- ============================================================
-- STEP 1: Fix users table — add 'student' role support
-- ============================================================
ALTER TABLE `users`
  MODIFY `role` ENUM('admin','teacher','student') NOT NULL;

-- ============================================================
-- STEP 2: Add missing columns to students table
-- ============================================================
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(255) DEFAULT 'default-avatar.png',
  ADD COLUMN IF NOT EXISTS `user_id` INT DEFAULT NULL;

-- Add foreign key only if it doesn't exist yet
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = 'school_db'
    AND TABLE_NAME = 'students'
    AND CONSTRAINT_NAME = 'students_fk_user'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE students ADD CONSTRAINT students_fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- STEP 3: Create attendance table (if not exists)
-- ============================================================
CREATE TABLE IF NOT EXISTS `attendance` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `student_id`  INT(11) NOT NULL,
  `class_id`    INT(11) NOT NULL,
  `date`        DATE NOT NULL,
  `status`      ENUM('Present','Absent','Late') NOT NULL DEFAULT 'Present',
  `remarks`     VARCHAR(255) DEFAULT '',
  `marked_by`   INT(11) DEFAULT NULL,
  `marked_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_date` (`student_id`, `date`),
  KEY `idx_class_date` (`class_id`, `date`),
  KEY `idx_student` (`student_id`),
  CONSTRAINT `att_fk_student`  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  CONSTRAINT `att_fk_class`    FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `att_fk_teacher`  FOREIGN KEY (`marked_by`)  REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- STEP 4: Create class_teachers table (subject teachers per class)
-- ============================================================
CREATE TABLE IF NOT EXISTS `class_teachers` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `class_id`   INT(11) NOT NULL,
  `teacher_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_teacher` (`class_id`, `teacher_id`),
  CONSTRAINT `ct_fk_class`   FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `ct_fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- STEP 5: Create student login accounts for existing students
-- Each student gets: username = their student_id, password = name+123
-- ============================================================

-- Student: Ahmed Ali  (student_id = ABC001/16, class 1)
INSERT IGNORE INTO `users` (`username`, `password`, `role`)
  VALUES ('ABC001/16', 'ahmedali123', 'student');
UPDATE `students` SET `user_id` = LAST_INSERT_ID()
  WHERE `student_id` = 'ABC001/16' AND (`user_id` IS NULL OR `user_id` = 0);

-- Student: Farah Mohamed  (ABC006/15)
INSERT IGNORE INTO `users` (`username`, `password`, `role`)
  VALUES ('ABC006/15', 'farahmohamed123', 'student');
UPDATE `students` SET `user_id` = LAST_INSERT_ID()
  WHERE `student_id` = 'ABC006/15' AND (`user_id` IS NULL OR `user_id` = 0);

-- Student: Aisha mahamed  (ABC007/15)
INSERT IGNORE INTO `users` (`username`, `password`, `role`)
  VALUES ('ABC007/15', 'aishamahamed123', 'student');
UPDATE `students` SET `user_id` = LAST_INSERT_ID()
  WHERE `student_id` = 'ABC007/15' AND (`user_id` IS NULL OR `user_id` = 0);

-- Student: Mohamed mohamuud  (ABC005/15)
INSERT IGNORE INTO `users` (`username`, `password`, `role`)
  VALUES ('ABC005/15', 'mohamedmohamuud123', 'student');
UPDATE `students` SET `user_id` = LAST_INSERT_ID()
  WHERE `student_id` = 'ABC005/15' AND (`user_id` IS NULL OR `user_id` = 0);

-- Student: C/qani Yusuf  (ABC004/16)
INSERT IGNORE INTO `users` (`username`, `password`, `role`)
  VALUES ('ABC004/16', 'cqaniyusuf123', 'student');
UPDATE `students` SET `user_id` = LAST_INSERT_ID()
  WHERE `student_id` = 'ABC004/16' AND (`user_id` IS NULL OR `user_id` = 0);

-- Student: Fatima Ahmed  (ABC002/16)
INSERT IGNORE INTO `users` (`username`, `password`, `role`)
  VALUES ('ABC002/16', 'fatimaahmed123', 'student');
UPDATE `students` SET `user_id` = LAST_INSERT_ID()
  WHERE `student_id` = 'ABC002/16' AND (`user_id` IS NULL OR `user_id` = 0);

-- Student: Rahma Ahmed  (ABC008/16)
INSERT IGNORE INTO `users` (`username`, `password`, `role`)
  VALUES ('ABC008/16', 'rahmaahmed123', 'student');
UPDATE `students` SET `user_id` = LAST_INSERT_ID()
  WHERE `student_id` = 'ABC008/16' AND (`user_id` IS NULL OR `user_id` = 0);

-- ============================================================
-- DONE! Summary of what was added:
-- ✅ users.role now supports 'student'
-- ✅ students table now has profile_photo and user_id columns
-- ✅ attendance table created
-- ✅ class_teachers table created
-- ✅ Login accounts created for all 7 existing students
-- ============================================================
