-- ============================================================
-- School_db1 - FINANCE MODULE v2
-- Adds: Expenses, Payroll, Discounts/Scholarships
-- (Run finance_schema.sql FIRST, then this file)
-- ============================================================
-- HOW TO IMPORT:
-- 1. Open phpMyAdmin → http://localhost/phpmyadmin
-- 2. Click database "School_db1" on the left side
-- 3. Click the "Import" tab → Choose this file → click "Go"
-- ============================================================

USE `School_db1`;

-- ------------------------------------------------------------
-- 1. Expense categories (Salaries, Utilities, Maintenance ...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`    INT(11) NOT NULL AUTO_INCREMENT,
  `name`  VARCHAR(60) NOT NULL,
  `icon`  VARCHAR(10) DEFAULT '💼',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `expense_categories` (`name`, `icon`) VALUES
  ('Salaries',        '💰'),
  ('Utilities',       '💡'),
  ('Maintenance',     '🔧'),
  ('Office Supplies', '📎'),
  ('Transportation',  '🚌'),
  ('Other',           '💼');

-- ------------------------------------------------------------
-- 2. Expenses - every payment the school makes
--    source = 'Manual'  -> typed in on the Expenses page
--    source = 'Payroll' -> created automatically by a salary payment
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `category_id`       INT(11) NOT NULL,
  `title`             VARCHAR(150) NOT NULL,
  `amount`            DECIMAL(10,2) NOT NULL,
  `expense_date`      DATE NOT NULL,
  `method`            ENUM('Cash','Bank Transfer','Mobile Money','Cheque') NOT NULL DEFAULT 'Cash',
  `vendor`            VARCHAR(100) DEFAULT '',
  `notes`             VARCHAR(255) DEFAULT '',
  `source`            ENUM('Manual','Payroll') NOT NULL DEFAULT 'Manual',
  `salary_payment_id` INT(11) DEFAULT NULL,
  `recorded_by`       INT(11) DEFAULT NULL,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`expense_date`),
  KEY `idx_category` (`category_id`),
  CONSTRAINT `exp_fk_cat`  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`),
  CONSTRAINT `exp_fk_user` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 3. Monthly salary for each teacher
-- ------------------------------------------------------------
ALTER TABLE `teachers`
  ADD COLUMN IF NOT EXISTS `monthly_salary` DECIMAL(10,2) NOT NULL DEFAULT 0;

-- ------------------------------------------------------------
-- 4. Salary payments (payroll) - one row per teacher per month
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `salary_payments` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `teacher_id`   INT(11) NOT NULL,
  `pay_month`    VARCHAR(7) NOT NULL,            -- e.g. 2026-09
  `basic`        DECIMAL(10,2) NOT NULL DEFAULT 0,
  `bonus`        DECIMAL(10,2) NOT NULL DEFAULT 0,
  `deduction`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `net`          DECIMAL(10,2) NOT NULL DEFAULT 0,
  `payment_date` DATE NOT NULL,
  `method`       ENUM('Cash','Bank Transfer','Mobile Money','Cheque') NOT NULL DEFAULT 'Bank Transfer',
  `payslip_no`   VARCHAR(20) NOT NULL,
  `notes`        VARCHAR(255) DEFAULT '',
  `paid_by`      INT(11) DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_month` (`teacher_id`, `pay_month`),
  UNIQUE KEY `unique_payslip` (`payslip_no`),
  KEY `idx_month` (`pay_month`),
  CONSTRAINT `sal_fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `sal_fk_user`    FOREIGN KEY (`paid_by`)    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 5. Student discounts / scholarships (one per student)
--    Percent -> value is a % of the class fee (e.g. 50 = 50% off)
--    Amount  -> value is a fixed amount off (e.g. 500)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_discounts` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `student_id`    INT(11) NOT NULL,
  `discount_type` ENUM('Percent','Amount') NOT NULL DEFAULT 'Percent',
  `value`         DECIMAL(10,2) NOT NULL DEFAULT 0,
  `reason`        VARCHAR(100) DEFAULT 'Scholarship',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_discount` (`student_id`),
  CONSTRAINT `disc_fk_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 6. Sample monthly salaries so Payroll has something to show
-- ------------------------------------------------------------
UPDATE `teachers` SET `monthly_salary` = 800.00 WHERE `monthly_salary` = 0;

-- ============================================================
-- DONE!
-- ✅ expense_categories + expenses     (Expense Management)
-- ✅ teachers.monthly_salary           (Payroll setup)
-- ✅ salary_payments                   (Payroll + payslips)
-- ✅ student_discounts                 (Scholarships / discounts)
-- ============================================================
