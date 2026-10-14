-- ============================================================
-- School_db1 - CLASS TIMETABLE
--
-- Two tables:
--   periods    - the time slots of a school day (Period 1, Break, ...)
--   timetable  - which subject/teacher a class has, per day per period
--
-- School week: Monday (1) to Friday (5)
-- ============================================================
-- HOW TO IMPORT:
-- 1. Open phpMyAdmin -> http://localhost/phpmyadmin
-- 2. Click database "School_db1" -> "Import" tab
-- 3. Choose this file -> click "Go"
-- ============================================================

USE `School_db1`;

-- ------------------------------------------------------------
-- 1. The time slots of a school day
--    is_break = 1 means Break / Lunch (no lesson is taught)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `periods` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(40) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time`   TIME NOT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_break`   TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_period_name` (`name`),
  KEY `idx_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default school day: 6 lessons with a break and a lunch
INSERT IGNORE INTO `periods` (`name`, `start_time`, `end_time`, `sort_order`, `is_break`) VALUES
  ('Period 1', '08:00:00', '08:45:00', 1, 0),
  ('Period 2', '08:45:00', '09:30:00', 2, 0),
  ('Break',    '09:30:00', '09:45:00', 3, 1),
  ('Period 3', '09:45:00', '10:30:00', 4, 0),
  ('Period 4', '10:30:00', '11:15:00', 5, 0),
  ('Lunch',    '11:15:00', '12:00:00', 6, 1),
  ('Period 5', '12:00:00', '12:45:00', 7, 0),
  ('Period 6', '12:45:00', '13:30:00', 8, 0);

-- ------------------------------------------------------------
-- 2. The timetable itself
--    day_of_week: 1 = Monday ... 5 = Friday
--
--    unique_class_slot  -> one class cannot have two lessons
--                          in the same period
--    unique_teacher_slot-> one teacher cannot be in two classes
--                          at the same time
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `timetable` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `class_id`    INT(11) NOT NULL,
  `day_of_week` TINYINT(1) NOT NULL,
  `period_id`   INT(11) NOT NULL,
  `subject_id`  INT(11) NOT NULL,
  `teacher_id`  INT(11) DEFAULT NULL,
  `room`        VARCHAR(40) DEFAULT '',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_slot`   (`class_id`, `day_of_week`, `period_id`),
  UNIQUE KEY `unique_teacher_slot` (`teacher_id`, `day_of_week`, `period_id`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_period`  (`period_id`),
  CONSTRAINT `tt_fk_class`   FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `tt_fk_period`  FOREIGN KEY (`period_id`)  REFERENCES `periods`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `tt_fk_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  CONSTRAINT `tt_fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- DONE!
-- periods   - 8 slots created (6 lessons + break + lunch)
-- timetable - empty, ready to fill
--
-- Next: Admin -> Timetable, pick a class and click a cell.
-- ============================================================
