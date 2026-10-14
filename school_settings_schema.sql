-- ============================================================
-- School settings  (logo, school name, ...)
--
-- One simple key -> value table. Adding a new setting later
-- (address, phone, motto) needs no change to the table.
-- ============================================================

CREATE TABLE IF NOT EXISTS school_settings (
    setting_key   VARCHAR(50) NOT NULL PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Starting values. INSERT IGNORE so re-running never wipes a real logo.
INSERT IGNORE INTO school_settings (setting_key, setting_value) VALUES
    ('school_name',  'HARAMAYA HIGH SCHOOL'),
    ('school_motto', 'Student Academic System'),
    ('school_logo',  '');
