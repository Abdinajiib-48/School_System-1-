-- ============================================================
-- School_db1 - FINANCE MODULE
-- Adds fee structure + payments tables for the Financial Dashboard
-- ============================================================
-- HOW TO IMPORT:
-- 1. Open phpMyAdmin → http://localhost/phpmyadmin
-- 2. Click on database "School_db1" on the left side
-- 3. Click the "Import" tab → Choose this file → click "Go"
-- ============================================================

USE `School_db1`;

-- ------------------------------------------------------------
-- fee_structure: how much each class must pay for a year
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_structure` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `class_id`      INT(11) NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `amount`        DECIMAL(10,2) NOT NULL DEFAULT 0,
  `description`   VARCHAR(100) DEFAULT 'Tuition Fee',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_year` (`class_id`, `academic_year`),
  CONSTRAINT `fee_fk_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- payments: every payment made by a student
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `student_id`   INT(11) NOT NULL,
  `amount`       DECIMAL(10,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `method`       ENUM('Cash','Bank Transfer','Mobile Money','Cheque') NOT NULL DEFAULT 'Cash',
  `receipt_no`   VARCHAR(20) NOT NULL,
  `notes`        VARCHAR(255) DEFAULT '',
  `received_by`  INT(11) DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_receipt` (`receipt_no`),
  KEY `idx_student` (`student_id`),
  KEY `idx_date` (`payment_date`),
  CONSTRAINT `pay_fk_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  CONSTRAINT `pay_fk_user`    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Sample data (optional) - fee for each existing class
-- ------------------------------------------------------------
INSERT IGNORE INTO `fee_structure` (`class_id`, `academic_year`, `amount`, `description`)
SELECT `id`, `academic_year`, 2000.00, 'Tuition Fee' FROM `classes`;

-- ============================================================
-- DONE!
-- ✅ fee_structure table created (one fee per class per year)
-- ✅ payments table created
-- ✅ Default fee of 2000 added for every existing class
-- ============================================================
