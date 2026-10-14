-- ============================================================
-- School_db1 - DEMO / SAMPLE DATA
-- Target for a complete demo:
--   5 teachers · 5+ subjects · 3 classes · 2+ parents
--
-- SAFE TO RE-RUN: every insert is guarded, so nothing is
-- duplicated if you import this file twice.
-- ============================================================
-- HOW LOGINS ARE CREATED (do not change this by hand):
--   TEACHER  -> the `before_teacher_insert` trigger creates the login
--               username = the name exactly as typed
--               password = name in lowercase, no spaces, + "123"
--               e.g. "Nasra" -> Nasra / nasra123
--   STUDENT  -> the `before_student_insert` trigger creates the login
--               username = the Student ID exactly
--               password = name in lowercase, letters only, + "123"
--               e.g. "Mohamed Omar" -> ABC001/16 / mohamedomar123
--   PARENT   -> created on Admin -> Parents (you type the username
--               and password yourself; the default is parent123)
--   ADMIN    -> created by hand in the users table
-- ============================================================

USE `School_db1`;

-- ------------------------------------------------------------
-- TEACHERS  (target: 5)
-- Do NOT set user_id here - the trigger fills it in.
-- ------------------------------------------------------------

-- Nasra - Chemistry - Science department
INSERT INTO `teachers` (`name`, `department_id`, `subject_id`, `email`, `phone`, `is_homeroom`)
SELECT 'Nasra',
       (SELECT id FROM departments WHERE name = 'Science'   LIMIT 1),
       (SELECT id FROM subjects    WHERE name = 'Chemistry' LIMIT 1),
       'nasra@school.com', '0615550104', 0
WHERE NOT EXISTS (SELECT 1 FROM teachers WHERE name = 'Nasra');

-- Yusuf - Physics - Science department
INSERT INTO `teachers` (`name`, `department_id`, `subject_id`, `email`, `phone`, `is_homeroom`)
SELECT 'Yusuf',
       (SELECT id FROM departments WHERE name = 'Science'  LIMIT 1),
       (SELECT id FROM subjects    WHERE name = 'Physics'  LIMIT 1),
       'yusuf@school.com', '0615550105', 0
WHERE NOT EXISTS (SELECT 1 FROM teachers WHERE name = 'Yusuf');

-- Monthly salary so the Payroll page has data for everyone
UPDATE `teachers` SET `monthly_salary` = 750 WHERE `name` = 'Nasra' AND `monthly_salary` = 0;
UPDATE `teachers` SET `monthly_salary` = 700 WHERE `name` = 'Yusuf' AND `monthly_salary` = 0;

-- ------------------------------------------------------------
-- Give the new teachers classes to work with
-- (they are not homeroom teachers, so they are linked as
--  SUBJECT teachers through class_teachers)
-- ------------------------------------------------------------
INSERT IGNORE INTO `class_teachers` (`class_id`, `teacher_id`)
SELECT c.id, t.id FROM classes c, teachers t
WHERE t.name = 'Nasra' AND c.grade IN ('9A', '9B');

INSERT IGNORE INTO `class_teachers` (`class_id`, `teacher_id`)
SELECT c.id, t.id FROM classes c, teachers t
WHERE t.name = 'Yusuf' AND c.grade IN ('9A', '9B', '9C');

-- ------------------------------------------------------------
-- SUBJECTS  (target: 5) - already present, added only if missing
-- ------------------------------------------------------------
INSERT INTO `subjects` (`name`, `code`, `max_marks`, `pass_mark`, `department_id`)
SELECT 'Maths', 'MATH101', 100, 50, (SELECT id FROM departments WHERE name='Mathematics' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Maths');

INSERT INTO `subjects` (`name`, `code`, `max_marks`, `pass_mark`, `department_id`)
SELECT 'English', 'ENG101', 100, 50, (SELECT id FROM departments WHERE name='Languages' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'English');

INSERT INTO `subjects` (`name`, `code`, `max_marks`, `pass_mark`, `department_id`)
SELECT 'Biology', 'BIO101', 100, 50, (SELECT id FROM departments WHERE name='Science' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Biology');

INSERT INTO `subjects` (`name`, `code`, `max_marks`, `pass_mark`, `department_id`)
SELECT 'Chemistry', 'CHEM101', 100, 50, (SELECT id FROM departments WHERE name='Science' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Chemistry');

INSERT INTO `subjects` (`name`, `code`, `max_marks`, `pass_mark`, `department_id`)
SELECT 'Physics', 'PHY101', 100, 50, (SELECT id FROM departments WHERE name='Science' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Physics');

-- ------------------------------------------------------------
-- CLASSES  (target: 3) - already present, added only if missing
-- ------------------------------------------------------------
INSERT INTO `classes` (`grade`, `academic_year`, `semester`)
SELECT '9A', '2016', 'I'  WHERE NOT EXISTS (SELECT 1 FROM classes WHERE grade='9A' AND academic_year='2016');
INSERT INTO `classes` (`grade`, `academic_year`, `semester`)
SELECT '9B', '2016', 'II' WHERE NOT EXISTS (SELECT 1 FROM classes WHERE grade='9B' AND academic_year='2016');
INSERT INTO `classes` (`grade`, `academic_year`, `semester`)
SELECT '9C', '2016', 'I'  WHERE NOT EXISTS (SELECT 1 FROM classes WHERE grade='9C' AND academic_year='2016');

-- ------------------------------------------------------------
-- FEES for every class, so the Finance demo works
-- ------------------------------------------------------------
INSERT IGNORE INTO `fee_structure` (`class_id`, `academic_year`, `amount`, `description`)
SELECT `id`, `academic_year`, 2000.00, 'Tuition Fee' FROM `classes`;

-- ============================================================
-- DONE
-- Check the result with:
--   SELECT COUNT(*) FROM teachers;   -- 5
--   SELECT COUNT(*) FROM subjects;   -- 6
--   SELECT COUNT(*) FROM classes;    -- 3
--   SELECT COUNT(*) FROM parents;    -- 3
-- ============================================================
