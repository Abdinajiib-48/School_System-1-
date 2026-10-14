-- ============================================================
-- Lesson plans
--
-- A teacher writes a lesson plan, saves it as a draft while they work,
-- then SUBMITS it. The admin reads what has been submitted and either
-- approves it or sends it back with a comment. A plan sent back can be
-- edited and submitted again.
--
--   draft          only the teacher sees it
--   submitted      waiting for the admin
--   approved       the admin accepted it
--   needs_changes  the admin sent it back with a comment
--
-- The long fields (objectives, materials, activities...) hold one item
-- per line. They are shown as bullet points, which keeps the form a
-- plain textarea the teacher can type into freely.
-- ============================================================

CREATE TABLE IF NOT EXISTS lesson_plans (
    id              INT AUTO_INCREMENT PRIMARY KEY,

    teacher_id      INT NOT NULL,
    class_id        INT NULL,
    subject_id      INT NULL,

    -- the heading of the plan
    topic           VARCHAR(200) NOT NULL,
    lesson_date     DATE NULL,
    duration        VARCHAR(50)  DEFAULT '50 Minutes',
    teaching_method VARCHAR(100) DEFAULT 'Lecture',
    curriculum      VARCHAR(100) DEFAULT '',
    region          VARCHAR(100) DEFAULT '',

    -- the body, one item per line
    objectives        TEXT,
    materials         TEXT,
    previous_knowledge TEXT,
    introduction      TEXT,
    development       TEXT,
    activities        TEXT,
    assessment        TEXT,
    homework          TEXT,
    reflection        TEXT,

    -- where it is in the process
    status        ENUM('draft','submitted','approved','needs_changes') NOT NULL DEFAULT 'draft',
    submitted_at  DATETIME NULL,
    reviewed_by   INT NULL,              -- the admin's users.id
    reviewed_at   DATETIME NULL,
    admin_comment TEXT,

    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_lp_teacher (teacher_id),
    KEY idx_lp_status  (status),

    -- deleting a teacher takes their plans with them;
    -- a deleted class or subject just leaves the field blank
    CONSTRAINT lp_teacher_fk FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE,
    CONSTRAINT lp_class_fk   FOREIGN KEY (class_id)   REFERENCES classes  (id) ON DELETE SET NULL,
    CONSTRAINT lp_subject_fk FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
