<?php
include_once __DIR__ . '/auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// assessments_helper.php - Assignment / Mid Exam / Final Exam
//
// A subject is no longer one mark. It is one mark per assessment
// type, and each type carries a weight - its share of the subject's
// 100%.
//
//     subject score = SUM( mark / max_marks * weight )
//
// With the seeded values (marks out of 100, weights 20/30/50) a
// student on 80, 60 and 70 ends with
//     80*0.20 + 60*0.30 + 70*0.50 = 69
//
// A subject only produces a score once EVERY active type has a mark,
// for the same reason a student only gets a result once every subject
// is marked: two marks out of three is not a subject score.
//
// Run assessment_schema.sql once to create the tables. If it has not
// been run, every function here falls back to the old behaviour - one
// mark per subject - so nothing breaks.
// ============================================================

if (!function_exists('at_installed')) {

    // Is the assessment_types table there? Checked once per page load.
    function at_installed($conn) {
        if (isset($GLOBALS['__at_installed'])) return $GLOBALS['__at_installed'];
        $r = @mysqli_query($conn, "SHOW TABLES LIKE 'assessment_types'");
        $ok = $r && mysqli_num_rows($r) > 0;
        // the column on marks matters just as much
        if ($ok) {
            $c = @mysqli_query($conn, "SHOW COLUMNS FROM marks LIKE 'assessment_type_id'");
            $ok = $c && mysqli_num_rows($c) > 0;
        }
        $GLOBALS['__at_installed'] = $ok;
        return $ok;
    }

    /**
     * The assessment types, in the order they should be shown.
     *
     * @param bool $only_active  false also returns switched-off types
     *                           (the admin page needs those)
     */
    function assessment_types($conn, $only_active = true) {
        if (!at_installed($conn)) return [];

        $key = $only_active ? '__at_active' : '__at_all';
        if (isset($GLOBALS[$key])) return $GLOBALS[$key];

        $where = $only_active ? 'WHERE is_active = 1' : '';
        $out   = [];
        $res   = mysqli_query($conn, "SELECT * FROM assessment_types $where ORDER BY sort_order, id");
        if ($res) { while ($r = mysqli_fetch_assoc($res)) $out[] = $r; }

        $GLOBALS[$key] = $out;
        return $out;
    }

    // Forget the cached list after the admin edits the types
    function at_cache_clear() {
        unset($GLOBALS['__at_active'], $GLOBALS['__at_all'], $GLOBALS['__at_installed']);
    }

    // Do the active weights add up to 100? The admin page warns when they do not.
    function at_total_weight($conn) {
        $t = 0;
        foreach (assessment_types($conn) as $a) $t += (int)$a['weight'];
        return $t;
    }

    /**
     * Every mark a student holds, as $raw[subject_id][type_id] = mark.
     *
     * Without the new tables the type key is 0, which still works:
     * one "type" per subject, exactly as before.
     */
    function student_marks_raw($conn, $student_id) {
        $student_id = (int)$student_id;
        $raw = [];

        if (at_installed($conn)) {
            $res = mysqli_query($conn,
                "SELECT subject_id, assessment_type_id, marks_obtained
                   FROM marks WHERE student_id = $student_id");
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $raw[(int)$r['subject_id']][(int)$r['assessment_type_id']] = $r['marks_obtained'];
                }
            }
        } else {
            $res = mysqli_query($conn,
                "SELECT subject_id, marks_obtained FROM marks WHERE student_id = $student_id");
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $raw[(int)$r['subject_id']][0] = $r['marks_obtained'];
                }
            }
        }
        return $raw;
    }

    /**
     * The same thing for a whole class in ONE query, instead of one
     * query per student. Returns $all[student_id][subject_id][type_id].
     */
    function class_marks_raw($conn, $class_id) {
        $class_id = (int)$class_id;
        $all = [];

        $type_col = at_installed($conn) ? 'm.assessment_type_id' : '0';
        $res = mysqli_query($conn,
            "SELECT m.student_id, m.subject_id, $type_col AS tid, m.marks_obtained
               FROM marks m
               JOIN students s ON s.id = m.student_id
              WHERE s.class_id = $class_id");
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $all[(int)$r['student_id']][(int)$r['subject_id']][(int)$r['tid']] = $r['marks_obtained'];
            }
        }
        return $all;
    }

    /**
     * Turn one subject's component marks into a score out of 100.
     *
     * @param array $marks_for_subject  [type_id => mark], may be empty
     * @param array $types              rows from assessment_types()
     *
     * @return array complete  true only when every active type has a mark
     *               score     0-100, or null while a component is missing
     *               missing   names of the components with no mark yet
     *               entered   how many components are in
     */
    function subject_score($marks_for_subject, $types) {
        // No assessment types set up: behave exactly as the old system did,
        // where the single mark WAS the subject score.
        if (empty($types)) {
            $mark = isset($marks_for_subject[0]) ? $marks_for_subject[0] : null;
            return [
                'complete' => $mark !== null && $mark !== '',
                'score'    => ($mark !== null && $mark !== '') ? (float)$mark : null,
                'missing'  => ($mark !== null && $mark !== '') ? [] : ['Mark'],
                'entered'  => ($mark !== null && $mark !== '') ? 1 : 0,
                'expected' => 1,
            ];
        }

        $score   = 0;
        $missing = [];
        $entered = 0;

        foreach ($types as $t) {
            $tid = (int)$t['id'];
            // isset() on purpose: a mark of 0 IS a mark. empty() would
            // treat a genuine zero as "not entered yet".
            if (isset($marks_for_subject[$tid]) && $marks_for_subject[$tid] !== '') {
                $max = (float)$t['max_marks'];
                if ($max <= 0) $max = 100;                  // never divide by zero
                $score += ((float)$marks_for_subject[$tid] / $max) * (float)$t['weight'];
                $entered++;
            } else {
                $missing[] = $t['name'];
            }
        }

        $complete = ($entered === count($types));

        return [
            'complete' => $complete,
            'score'    => $complete ? round($score, 1) : null,
            'missing'  => $missing,
            'entered'  => $entered,
            'expected' => count($types),
        ];
    }

    /**
     * The bridge to the rest of the system.
     *
     * Returns [subject_id => score out of 100] holding ONLY the subjects
     * where every component is in. Pages that used to do
     *
     *     $marks[$subject_id] = $row['marks_obtained'];
     *
     * now call this instead, and results_helper.php keeps working
     * unchanged - a subject with a component missing simply is not in
     * the array, so it counts as unmarked.
     *
     * @param array $raw  from student_marks_raw() / class_marks_raw()[student]
     */
    function subject_scores_from_raw($raw, $types) {
        $out = [];
        foreach ($raw as $subject_id => $components) {
            $r = subject_score($components, $types);
            if ($r['complete']) {
                $out[(int)$subject_id] = $r['score'];
            }
        }
        return $out;
    }

    // Convenience: one student, straight from the database
    function student_subject_scores($conn, $student_id, $types = null) {
        if ($types === null) $types = assessment_types($conn);
        return subject_scores_from_raw(student_marks_raw($conn, $student_id), $types);
    }

    /**
     * Which components are still missing across every subject.
     * Used to tell a student exactly what they are waiting for.
     *
     * @return array ['Maths - Mid Exam', 'Biology - Assignment', ...]
     */
    function missing_components($raw, $subjects, $types) {
        $out = [];
        foreach ($subjects as $s) {
            $sid  = (int)$s['id'];
            $comp = isset($raw[$sid]) ? $raw[$sid] : [];
            $r    = subject_score($comp, $types);
            foreach ($r['missing'] as $m) {
                $out[] = $s['name'] . ' - ' . $m;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------
    // Shared display
    // ------------------------------------------------------------

    // "20%" - shown in table headers so everyone can see what a column is worth
    function at_weight_label($t) {
        return at_show($t['weight']) . '%';
    }

    /**
     * A mark, tidied for showing.
     *
     * Marks are stored as DECIMAL so half marks survive - out of 20 or 30,
     * 17.5 is an ordinary mark. The database hands them back as "18.00" and
     * "19.50", which is noisy on screen, so drop the pointless zeros:
     *
     *     18.00 -> 18        19.50 -> 19.5       17.25 -> 17.25
     */
    function at_show($value) {
        if ($value === null || $value === '') return '';
        $s = number_format((float)$value, 2, '.', '');
        if (strpos($s, '.') !== false) {
            $s = rtrim(rtrim($s, '0'), '.');
        }
        return $s === '' ? '0' : $s;
    }

    function assessment_styles() {
        ?>
        <style>
            .at-head small {
                display: block;
                font-weight: normal;
                font-size: 10px;
                opacity: .85;
                margin-top: 2px;
            }
            .at-mark      { color: #333; }
            .at-missing   { color: #c98a00; background: #fff6e5; font-weight: bold; }
            .at-subtotal  { background: #eef2ff; font-weight: 700; }
            .at-chip {
                display: inline-block; background: #eef2ff; color: #4c51bf;
                border-radius: 10px; padding: 2px 9px; font-size: 11px;
                margin: 2px 3px 2px 0; white-space: nowrap;
            }
        </style>
        <?php
    }
}
?>
