-- ============================================================
-- School_db1 - PARENT MODULE v2
-- Changes:
--   * Parents now have their OWN username (no longer the phone)
--   * Phone is optional, plus a new Occupation field
--   * A student can be linked to ONE parent only
-- (Run parent_schema.sql FIRST, then this file)
-- ============================================================
-- HOW TO IMPORT:
-- 1. Open phpMyAdmin → http://localhost/phpmyadmin
-- 2. Click database "School_db1" on the left side
-- 3. Click the "Import" tab → Choose this file → click "Go"
-- ============================================================

USE `School_db1`;

-- ------------------------------------------------------------
-- 1. New Occupation column
-- ------------------------------------------------------------
ALTER TABLE `parents`
  ADD COLUMN IF NOT EXISTS `occupation` VARCHAR(100) DEFAULT NULL AFTER `email`;

-- ------------------------------------------------------------
-- 2. Phone is no longer the login, so it becomes optional
--    and does not have to be unique any more.
-- ------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = 'School_db1'
              AND TABLE_NAME   = 'parents'
              AND INDEX_NAME   = 'unique_parent_phone');
SET @sql = IF(@idx > 0, 'ALTER TABLE `parents` DROP INDEX `unique_parent_phone`', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

ALTER TABLE `parents` MODIFY `phone` VARCHAR(20) DEFAULT NULL;

-- ------------------------------------------------------------
-- 3. One parent per student
--    (if this fails, a student is already linked to two parents -
--     remove the extra link on Admin → Parents first)
-- ------------------------------------------------------------
SET @idx2 = (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = 'School_db1'
               AND TABLE_NAME   = 'parent_students'
               AND INDEX_NAME   = 'unique_student_one_parent');
SET @sql2 = IF(@idx2 = 0,
  'ALTER TABLE `parent_students` ADD UNIQUE KEY `unique_student_one_parent` (`student_id`)',
  'SELECT 1');
PREPARE st2 FROM @sql2; EXECUTE st2; DEALLOCATE PREPARE st2;

-- ============================================================
-- DONE!
-- ✅ parents.occupation added
-- ✅ phone optional (login is now a username you choose)
-- ✅ each student can belong to only ONE parent
-- ============================================================
