-- ============================================================
-- School_db1 - CASHIER ROLE
--
-- Adds a "cashier" login role. A cashier can open the fee
-- collection side of Finance (Dashboard, Fees, Discounts,
-- Payments, Reports, receipts, invoices) and can post fee
-- reminder announcements.
--
-- They CANNOT open Expenses, Payroll, Teachers, Students,
-- Classes, Subjects or Parents - those stay admin only.
-- ============================================================
-- HOW TO IMPORT:
-- 1. Open phpMyAdmin -> http://localhost/phpmyadmin
-- 2. Click database "School_db1" -> "Import" tab
-- 3. Choose this file -> click "Go"
-- ============================================================

USE `School_db1`;

-- ------------------------------------------------------------
-- 1. Allow 'cashier' as a login role
-- ------------------------------------------------------------
ALTER TABLE `users`
  MODIFY `role` ENUM('admin','teacher','student','parent','cashier') NOT NULL;

-- ------------------------------------------------------------
-- 2. Cashier staff records (login username is chosen by admin)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cashiers` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) DEFAULT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20) DEFAULT NULL,
  `email`      VARCHAR(100) DEFAULT NULL,
  `address`    TEXT DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cashier_user` (`user_id`),
  CONSTRAINT `cash_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 3. A log of every fee reminder that was sent
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_reminders` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` INT(11) DEFAULT NULL,
  `students_owing`  INT(11) NOT NULL DEFAULT 0,
  `total_owed`      DECIMAL(10,2) NOT NULL DEFAULT 0,
  `due_date`        DATE DEFAULT NULL,
  `audience`        VARCHAR(20) NOT NULL DEFAULT 'All',
  `sent_by`         INT(11) DEFAULT NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `rem_fk_ann`  FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE SET NULL,
  CONSTRAINT `rem_fk_user` FOREIGN KEY (`sent_by`)         REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- DONE!
-- users.role now supports 'cashier'
-- cashiers table created
-- fee_reminders table created (history of reminders sent)
--
-- Next: Admin -> Cashiers -> add a cashier account.
-- ============================================================
