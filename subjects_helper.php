<?php
include_once __DIR__ . '/auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// Which subjects does a teacher teach?
//
// A teacher used to have ONE subject (teachers.subject_id).
// Now they can have several, listed in teacher_subjects — so the
// same teacher can take Physics AND Maths for a class.
//
// If teacher_subjects has not been imported yet, everything falls
// back to the old single subject, so nothing breaks.
// ============================================================

if (!function_exists('ts_installed')) {

    function ts_installed($conn) {
        return mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'teacher_subjects'")) > 0;
    }

    // SQL selecting every subject this teacher teaches
    function teacher_subject_sql($conn, $teacher_id) {
        $teacher_id = (int)$teacher_id;
        if (ts_installed($conn)) {
            return "SELECT s.* FROM subjects s
                    JOIN teacher_subjects ts ON ts.subject_id = s.id
                    WHERE ts.teacher_id = $teacher_id
                    ORDER BY s.id";
        }
        return "SELECT s.* FROM subjects s
                JOIN teachers t ON t.subject_id = s.id
                WHERE t.id = $teacher_id
                ORDER BY s.id";
    }

    // The subjects as an array of rows
    function teacher_subjects($conn, $teacher_id) {
        $out = [];
        $res = mysqli_query($conn, teacher_subject_sql($conn, $teacher_id));
        if ($res) { while ($r = mysqli_fetch_assoc($res)) $out[] = $r; }
        return $out;
    }

    // Just the ids - handy for ticking checkboxes
    function teacher_subject_ids($conn, $teacher_id) {
        $out = [];
        foreach (teacher_subjects($conn, $teacher_id) as $s) $out[] = (int)$s['id'];
        return $out;
    }

    // "Maths, Physics"
    function teacher_subject_names($conn, $teacher_id) {
        $names = [];
        foreach (teacher_subjects($conn, $teacher_id) as $s) $names[] = $s['name'];
        return implode(', ', $names);
    }

    // May this teacher enter marks for this subject?
    function teacher_teaches_subject($conn, $teacher_id, $subject_id) {
        $subject_id = (int)$subject_id;
        foreach (teacher_subjects($conn, $teacher_id) as $s) {
            if ((int)$s['id'] === $subject_id) return true;
        }
        return false;
    }

    // Replace a teacher's whole subject list
    function save_teacher_subjects($conn, $teacher_id, $subject_ids) {
        if (!ts_installed($conn)) return;
        $teacher_id = (int)$teacher_id;
        mysqli_query($conn, "DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id");
        foreach ($subject_ids as $sid) {
            $sid = (int)$sid;
            if ($sid > 0) {
                mysqli_query($conn, "INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES ($teacher_id, $sid)");
            }
        }
    }
}
?>
