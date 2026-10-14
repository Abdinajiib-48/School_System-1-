-- ============================================================
-- Assessment types: Assignment, Mid Exam, Final Exam
--
-- THE PROBLEM THIS FIXES
-- Until now `marks` had UNIQUE (student_id, subject_id), so a student
-- could hold exactly ONE mark per subject. A teacher had no way to
-- record an assignment mark AND a mid exam AND a final exam.
--
-- HOW IT WORKS NOW
-- Every mark belongs to an assessment type. Each type carries a
-- weight - its share of the subject's 100%. The subject score is:
--
--     score = SUM( mark / max_marks * weight )   for every active type
--
-- With the values seeded below - Assignment out of 20, Mid Exam out of
-- 30, Final Exam out of 50 - each paper is marked out of exactly what it
-- is worth, so the subject score is just the marks added together:
--     16 + 24 + 40 = 80 out of 100
--
-- A school that prefers marking everything out of 100 can set "Out of"
-- to 100 on the Assessments page; the weights then do the scaling and
-- the answer comes out the same.
--
-- Half marks are normal out of 20 or 30, so marks are DECIMAL.
-- ============================================================

CREATE TABLE IF NOT EXISTS assessment_types (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50)  NOT NULL UNIQUE,
    weight     INT          NOT NULL DEFAULT 0,    -- share of the subject's 100%
    max_marks  INT          NOT NULL DEFAULT 100,  -- what the teacher marks out of
    sort_order INT          NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1     -- inactive types are not required and not counted
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Each paper is marked out of exactly what it is worth, so the subject
-- score is simply the three marks added together: 16 + 24 + 40 = 80.
INSERT IGNORE INTO assessment_types (name, weight, max_marks, sort_order) VALUES
    ('Assignment', 20, 20, 1),
    ('Mid Exam',   30, 30, 2),
    ('Final Exam', 50, 50, 3);


-- ------------------------------------------------------------
-- Attach every mark to an assessment type
-- ------------------------------------------------------------

-- 1. the new column
ALTER TABLE marks
    ADD COLUMN assessment_type_id INT NULL AFTER subject_id;

-- 1b. half marks (17.5 out of 20) must survive
ALTER TABLE marks MODIFY marks_obtained DECIMAL(6,2) NOT NULL;

-- 2. marks entered before this change were the whole subject mark,
--    out of 100. They become Final Exam marks, value untouched.
UPDATE marks
   SET assessment_type_id = (SELECT id FROM assessment_types WHERE name = 'Final Exam')
 WHERE assessment_type_id IS NULL;

-- 3. one mark per student, per subject, PER TYPE - this is the fix
ALTER TABLE marks DROP INDEX unique_student_subject;
ALTER TABLE marks
    ADD UNIQUE KEY unique_student_subject_type (student_id, subject_id, assessment_type_id);

-- 4. deleting a type takes its marks with it
ALTER TABLE marks
    ADD CONSTRAINT marks_assessment_fk
    FOREIGN KEY (assessment_type_id) REFERENCES assessment_types (id) ON DELETE CASCADE;
