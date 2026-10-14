-- ============================================================
-- Mark out of the weight: Assignment /20, Mid Exam /30, Final /50
--
-- Before this, every assessment was marked out of 100 and the weight
-- did the scaling. Schools normally hand out papers worth 20, 30 and
-- 50 marks, so the teacher should type the paper mark directly.
--
-- Marks already stored were out of 100, so each one is scaled:
--
--     new mark = old mark * new max / 100
--
-- 90/100 becomes 18/20 - the same 90%, so no subject score moves.
-- Some land on halves (65/100 -> 19.5/30), which is why
-- marks_obtained becomes a decimal first.
-- ============================================================

-- 1. half marks are now possible, and normal when marking out of 20
ALTER TABLE marks MODIFY marks_obtained DECIMAL(6,2) NOT NULL;

-- 2. scale every stored mark to the new maximum
UPDATE marks m
  JOIN assessment_types at ON at.id = m.assessment_type_id
   SET m.marks_obtained = ROUND(m.marks_obtained * at.weight / at.max_marks, 2)
 WHERE at.max_marks <> at.weight;

-- 3. the teacher now marks out of the weight
UPDATE assessment_types SET max_marks = weight WHERE weight > 0;
