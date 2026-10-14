<?php
include_once __DIR__ . '/auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// lesson_plans_helper.php - the teacher's lesson plan
//
// A teacher writes a plan, saves it as a draft while they work, then
// SUBMITS it. The admin reads it and either approves it or sends it
// back with a comment, which the teacher then acts on and resubmits.
//
// The shape of the form lives in lp_sections() - ONE list used by the
// teacher's form, the admin's review page and the printable copy, so
// the three can never drift apart. Adding a field to the plan means
// adding one line there and one column to the table.
//
// Run lesson_plan_schema.sql once to create the table.
// ============================================================

if (!function_exists('lp_installed')) {

    function lp_installed($conn) {
        if (isset($GLOBALS['__lp_installed'])) return $GLOBALS['__lp_installed'];
        $r = @mysqli_query($conn, "SHOW TABLES LIKE 'lesson_plans'");
        $GLOBALS['__lp_installed'] = $r && mysqli_num_rows($r) > 0;
        return $GLOBALS['__lp_installed'];
    }

    /**
     * Every long section of the plan, in the order it is written and read.
     *
     *   column => [label, helper text, placeholder, 'list' or 'text']
     *
     * 'list'  - one item per line, shown as bullet points
     * 'text'  - a paragraph
     */
    function lp_sections() {
        return [
            'objectives' => [
                'Learning Objectives', 'list',
                'What should students be able to do by the end? One per line.',
                "Identify the main organelles of an animal cell\nDescribe the function of each major organelle\nDifferentiate between animal cells and plant cells",
            ],
            'materials' => [
                'Teaching Materials', 'list',
                'Everything you need to bring or prepare. One per line.',
                "Whiteboard and markers\nChart or poster of an animal cell\nTextbook (Grade 10 Biology)",
            ],
            'previous_knowledge' => [
                'Previous Knowledge', 'text',
                'What do students already know that this lesson builds on?',
                'Students should already know that the cell is the basic unit of life.',
            ],
            'introduction' => [
                'Lesson Introduction', 'text',
                'How will you open the lesson and get their attention?',
                "Ask students: 'What do you think is happening inside your body right now that you cannot see?'",
            ],
            'development' => [
                'Lesson Development', 'list',
                'The steps of the lesson, in order. One step per line.',
                "Step 1: Introduce the animal cell as the basic unit of the body\nStep 2: Draw a labelled diagram on the board\nStep 3: Explain the function of key organelles",
            ],
            'activities' => [
                'Classroom Activities', 'list',
                'What the students actually do. One activity per line.',
                "Activity 1: Students copy and label the diagram in their notebooks\nActivity 2: Pair discussion explaining one organelle to a partner",
            ],
            'assessment' => [
                'Assessment', 'list',
                'How you will check they understood. One question or check per line.',
                "Name three organelles found in an animal cell\nWhat is the function of the mitochondria?",
            ],
            'homework' => [
                'Homework', 'list',
                'What they take home. One task per line.',
                "Draw and label a full animal cell diagram at home\nWrite short notes on the function of five organelles",
            ],
            'reflection' => [
                'Reflection', 'list',
                'Fill this in after the lesson - what worked, what did not.',
                "Did students correctly identify and label the organelles?\nWhich part of the lesson needed more time?",
            ],
        ];
    }

    // The short fields at the top of the plan
    function lp_duration_options() {
        return ['30 Minutes', '40 Minutes', '45 Minutes', '50 Minutes',
                '60 Minutes', '80 Minutes', '90 Minutes', 'Double Period'];
    }

    function lp_method_options() {
        return ['Lecture', 'Discussion', 'Demonstration', 'Group Work',
                'Question and Answer', 'Practical / Laboratory', 'Project Work', 'Mixed'];
    }

    // ------------------------------------------------------------
    // Status
    // ------------------------------------------------------------

    function lp_status_label($status) {
        $map = [
            'draft'         => ['📝 Draft',          'lp-draft',   'Only you can see this. Submit it when it is ready.'],
            'submitted'     => ['📤 Waiting for admin','lp-submitted','Sent to the admin. You cannot edit it while it is being checked.'],
            'approved'      => ['✅ Approved',        'lp-approved','The admin has approved this plan.'],
            'needs_changes' => ['✏️ Changes needed',  'lp-changes', 'The admin has asked for changes. Read the comment, edit and send again.'],
        ];
        return isset($map[$status]) ? $map[$status] : ['Unknown', 'lp-draft', ''];
    }

    function lp_status_badge($status) {
        list($label, $css) = lp_status_label($status);
        return '<span class="lp-badge ' . $css . '">' . $label . '</span>';
    }

    // A teacher may only edit a plan that is theirs to work on
    function lp_is_editable($status) {
        return $status === 'draft' || $status === 'needs_changes';
    }

    /**
     * Print a lesson date, or a dash when there is none.
     *
     * MySQL stores a missing DATE as 0000-00-00, not NULL, if an empty
     * string ever reaches the column. strtotime() turns that into
     * "30 Nov -0001", so every screen has to guard against it.
     */
    function lp_date($value, $format = 'j M Y') {
        if (empty($value) || $value === '0000-00-00') return '-';
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : '-';
    }

    // The same value, ready for an <input type="date">
    function lp_date_input($value) {
        return (empty($value) || $value === '0000-00-00') ? '' : $value;
    }

    // How many plans are waiting for the admin? Used for the menu badge.
    function lp_pending_count($conn) {
        if (!lp_installed($conn)) return 0;
        $r = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) n FROM lesson_plans WHERE status = 'submitted'"));
        return (int)$r['n'];
    }

    // ------------------------------------------------------------
    // Reading plans
    // ------------------------------------------------------------

    // One plan with the teacher, class and subject names filled in
    function lp_get($conn, $id) {
        $id = (int)$id;
        return mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT lp.*, t.name AS teacher_name, c.grade AS class_name,
                    s.name AS subject_name, u.username AS reviewer_name
               FROM lesson_plans lp
               JOIN teachers t  ON t.id = lp.teacher_id
          LEFT JOIN classes  c  ON c.id = lp.class_id
          LEFT JOIN subjects s  ON s.id = lp.subject_id
          LEFT JOIN users    u  ON u.id = lp.reviewed_by
              WHERE lp.id = $id"));
    }

    /**
     * A list of plans.
     *
     * @param array $where  e.g. ['teacher_id' => 4, 'status' => 'submitted']
     */
    function lp_list($conn, $where = []) {
        if (!lp_installed($conn)) return [];

        $sql = [];
        if (!empty($where['teacher_id'])) $sql[] = 'lp.teacher_id = ' . (int)$where['teacher_id'];
        if (!empty($where['status']))     $sql[] = "lp.status = '" . mysqli_real_escape_string($conn, $where['status']) . "'";
        if (!empty($where['not_draft']))  $sql[] = "lp.status <> 'draft'";

        $clause = $sql ? 'WHERE ' . implode(' AND ', $sql) : '';

        $out = [];
        $res = mysqli_query($conn,
            "SELECT lp.*, t.name AS teacher_name, c.grade AS class_name, s.name AS subject_name
               FROM lesson_plans lp
               JOIN teachers t  ON t.id = lp.teacher_id
          LEFT JOIN classes  c  ON c.id = lp.class_id
          LEFT JOIN subjects s  ON s.id = lp.subject_id
             $clause
           ORDER BY
             -- plans waiting for the admin float to the top
             CASE lp.status WHEN 'submitted' THEN 0 WHEN 'needs_changes' THEN 1 ELSE 2 END,
             COALESCE(lp.submitted_at, lp.updated_at) DESC");
        if ($res) { while ($r = mysqli_fetch_assoc($res)) $out[] = $r; }
        return $out;
    }

    // ------------------------------------------------------------
    // Showing a plan
    // ------------------------------------------------------------

    /**
     * Print one section's value: bullet points for a list, paragraphs
     * for a text block. Blank lines are skipped so stray Enters do not
     * leave empty bullets.
     */
    function lp_render_value($value, $kind) {
        $value = trim((string)$value);
        if ($value === '') {
            echo '<p class="lp-empty">Not filled in.</p>';
            return;
        }

        $lines = preg_split('/\r\n|\r|\n/', $value);
        $lines = array_values(array_filter(array_map('trim', $lines), function ($l) { return $l !== ''; }));

        if ($kind === 'list') {
            echo '<ul class="lp-list">';
            foreach ($lines as $l) {
                // the teacher may have typed their own bullet - do not double it
                $l = preg_replace('/^[-*\x{2022}]\s*/u', '', $l);
                echo '<li>' . htmlspecialchars($l) . '</li>';
            }
            echo '</ul>';
        } else {
            foreach ($lines as $l) {
                echo '<p class="lp-para">' . htmlspecialchars($l) . '</p>';
            }
        }
    }

    /**
     * The whole plan, read-only. Used by the admin's review page, the
     * teacher's own preview and the printable copy - so everyone is
     * looking at exactly the same thing.
     */
    function lp_render_plan($plan) {
        ?>
        <div class="lp-head">
            <h2><?php echo htmlspecialchars($plan['topic'] ?? ''); ?></h2>
            <div class="lp-facts">
                <span><strong>Teacher:</strong> <?php echo htmlspecialchars($plan['teacher_name'] ?? '-'); ?></span>
                <span><strong>Subject:</strong> <?php echo htmlspecialchars($plan['subject_name'] ?: '-'); ?></span>
                <span><strong>Class:</strong> <?php echo htmlspecialchars($plan['class_name'] ?: '-'); ?></span>
                <span><strong>Date:</strong> <?php echo lp_date($plan['lesson_date'] ?? ''); ?></span>
                <span><strong>Duration:</strong> <?php echo htmlspecialchars($plan['duration'] ?: '-'); ?></span>
                <span><strong>Method:</strong> <?php echo htmlspecialchars($plan['teaching_method'] ?: '-'); ?></span>
                <?php if (!empty($plan['curriculum'])): ?>
                    <span><strong>Curriculum:</strong> <?php echo htmlspecialchars($plan['curriculum']); ?></span>
                <?php endif; ?>
                <?php if (!empty($plan['region'])): ?>
                    <span><strong>Region:</strong> <?php echo htmlspecialchars($plan['region']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach (lp_sections() as $key => $meta): ?>
            <div class="lp-section">
                <h3><?php echo htmlspecialchars($meta[0]); ?></h3>
                <?php lp_render_value(isset($plan[$key]) ? $plan[$key] : '', $meta[1]); ?>
            </div>
        <?php endforeach;
    }

    // Shared styles for every lesson-plan screen
    function lp_styles() {
        ?>
        <style>
            .lp-badge {
                display: inline-block; padding: 4px 11px; border-radius: 12px;
                font-size: 12px; font-weight: 700; white-space: nowrap;
            }
            .lp-draft     { background: #eceef3; color: #5a6172; }
            .lp-submitted { background: #e3f0ff; color: #1b5fa8; }
            .lp-approved  { background: #e8f5e9; color: #2e7d32; }
            .lp-changes   { background: #fff3cd; color: #8a6100; }

            .lp-head { border-bottom: 2px solid #eceef3; padding-bottom: 16px; margin-bottom: 20px; }
            .lp-head h2 { font-size: 23px; color: #2d3748; margin-bottom: 12px; }
            .lp-facts { display: flex; flex-wrap: wrap; gap: 8px 22px; font-size: 13.5px; color: #5a6172; }
            .lp-facts strong { color: #333; }

            .lp-section { margin-bottom: 22px; }
            .lp-section h3 {
                font-size: 14px; text-transform: uppercase; letter-spacing: .5px;
                color: #667eea; margin-bottom: 9px; font-weight: 700;
            }
            .lp-list { margin: 0 0 0 20px; }
            .lp-list li { margin-bottom: 6px; line-height: 1.65; color: #3d4350; }
            .lp-para { margin-bottom: 8px; line-height: 1.75; color: #3d4350; }
            .lp-empty { color: #b6bdca; font-style: italic; }

            .lp-comment {
                background: #fff8e1; border-left: 4px solid #ffb300;
                padding: 14px 16px; border-radius: 8px; margin-bottom: 20px;
                color: #7a5c00; font-size: 13.5px; line-height: 1.75;
            }
            .lp-comment strong { display: block; margin-bottom: 4px; color: #8a6100; }

            @media print {
                .sidebar, .top-bar, .no-print { display: none !important; }
                .main-content { margin-left: 0 !important; }
                .page-content { padding: 0 !important; }
                .lp-section { page-break-inside: avoid; }
            }
        </style>
        <?php
    }
}
?>
