-- ============================================================
-- School_db1 - A TEACHER CAN TEACH SEVERAL SUBJECTS
--
-- BEFORE: teachers.subject_id held ONE subject, so a teacher
--         could only ever enter marks for that single subject.
--
-- NOW:    teacher_subjects lists every subject a teacher teaches.
--         One teacher can take Physics AND Maths for the same
--         class, which is normal in smaller schools.
--
--         teachers.subject_id is kept as the MAIN subject so the
--         older parts of the system keep working.
-- ============================================================
-- HOW TO IMPORT:
-- 1. Open phpMyAdmin -> http://localhost/phpmyadmin
-- 2. Click database "School_db1" -> "Import" tab
-- 3. Choose this file -> click "Go"
-- ============================================================

USE `School_db1`;

CREATE TABLE IF NOT EXISTS `teacher_subjects` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` INT(11) NOT NULL,
  `subject_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_subject` (`teacher_id`, `subject_id`),
  KEY `idx_subject` (`subject_id`),
  CONSTRAINT `ts_fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `ts_fk_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Every teacher keeps the subject they already had
INSERT IGNORE INTO `teacher_subjects` (`teacher_id`, `subject_id`)
SELECT `id`, `subject_id` FROM `teachers`
WHERE `subject_id` IS NOT NULL AND `subject_id` > 0;

-- ============================================================
-- DONE!
-- Now open Admin -> Teachers -> Edit and tick extra subjects.
-- ============================================================
