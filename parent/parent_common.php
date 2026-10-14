<?php
include_once __DIR__ . '/../auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// Shared helper for all parent pages.
// Include AFTER config.php. Provides:
//   $parent          - the logged-in parent row
//   $children        - all children linked to this parent
//   $child           - the currently selected child (or null)
//   $child_id        - selected child's students.id
// Plus helper functions used across the parent pages.
// ============================================================

require_role('parent');

$user_id = (int)$_SESSION['user_id'];
$parent  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM parents WHERE user_id = $user_id"));

if (!$parent) {
    header('Location: ../logout.php');
    exit();
}

$parent_id = (int)$parent['id'];

// ---- All children of this parent ----
$children = [];
$ch_res = mysqli_query($conn,
    "SELECT s.*, ps.relationship, c.grade, c.academic_year, c.semester,
            t.name AS homeroom_teacher
     FROM parent_students ps
     JOIN students s ON ps.student_id = s.id
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id
     WHERE ps.parent_id = $parent_id
     ORDER BY s.name");
while ($row = mysqli_fetch_assoc($ch_res)) {
    $children[(int)$row['id']] = $row;
}

// ---- Which child is selected? Only children of THIS parent are accepted ----
$child_id = 0;
if (isset($_GET['child']) && isset($children[(int)$_GET['child']])) {
    $child_id = (int)$_GET['child'];
} elseif (count($children) > 0) {
    $child_id = (int)array_key_first($children);
}
$child = $child_id > 0 ? $children[$child_id] : null;

// ============================================================
// Helpers
// ============================================================

// Marks + average + grade + rank for one child
function child_academics($conn, $student_id, $class_id) {
    $student_id = (int)$student_id;
    $out = ['subjects' => [], 'marks' => [], 'total' => null, 'counted' => 0,
            'average' => null, 'status' => 'N/A', 'rank' => '-', 'class_size' => 0,
            'complete' => false, 'missing' => [], 'missing_count' => 0];

    $sub_res = mysqli_query($conn, "SELECT * FROM subjects ORDER BY id");
    while ($r = mysqli_fetch_assoc($sub_res)) $out['subjects'][] = $r;

    // A subject is built from its assessments. $out['marks'] holds one
    // score per FINISHED subject; $out['raw'] keeps the individual parts
    // so the page can say which assessment is still outstanding.
    include_once __DIR__ . '/../assessments_helper.php';
    $types = assessment_types($conn);

    $out['types'] = $types;
    $out['raw']   = student_marks_raw($conn, $student_id);
    $out['marks'] = subject_scores_from_raw($out['raw'], $types);

    // Total / average / PASS / FAIL - but ONLY when every subject has a
    // mark. Averaging just the subjects that happen to be entered would
    // show a child with one good mark as passing, which is not true.
    include_once __DIR__ . '/../results_helper.php';
    $r = calc_result($out['marks'], $out['subjects']);

    $out['counted']       = $r['counted'];
    $out['complete']      = $r['complete'];
    $out['missing']       = $r['missing'];
    $out['missing_count'] = $r['missing_count'];
    $out['total']         = $r['total'];      // null until every subject is in
    $out['average']       = $r['average'];    // null until every subject is in
    $out['status']        = $r['status'];     // PASS / FAIL / INCOMPLETE

    // Rank inside the class - only for a child whose result is complete
    if ($class_id && $out['complete']) {
        $class_id = (int)$class_id;
        $r_res = mysqli_query($conn,
            "SELECT s.id, COALESCE(SUM(m.marks_obtained),0) AS total
             FROM students s LEFT JOIN marks m ON m.student_id = s.id
             WHERE s.class_id = $class_id
             GROUP BY s.id ORDER BY total DESC");
        $pos = 0; $prev = null; $rank = 0;
        while ($r = mysqli_fetch_assoc($r_res)) {
            $pos++;
            if ($prev === null || (float)$r['total'] < $prev) { $rank = $pos; $prev = (float)$r['total']; }
            if ((int)$r['id'] === $student_id) $out['rank'] = $rank;
            $out['class_size'] = $pos;
        }
    }
    return $out;
}

// Letter grade for a mark out of 100
function grade_for($mark) {
    if (!is_numeric($mark))  return ['grade' => '-',  'color' => '#999',    'remark' => 'Not Available'];
    if ($mark >= 90)         return ['grade' => 'A+', 'color' => '#2e7d32', 'remark' => 'Excellent'];
    if ($mark >= 80)         return ['grade' => 'A',  'color' => '#388e3c', 'remark' => 'Very Good'];
    if ($mark >= 70)         return ['grade' => 'B',  'color' => '#689f38', 'remark' => 'Good'];
    if ($mark >= 60)         return ['grade' => 'C',  'color' => '#f9a825', 'remark' => 'Satisfactory'];
    if ($mark >= 50)         return ['grade' => 'D',  'color' => '#ef6c00', 'remark' => 'Pass'];
    return ['grade' => 'F', 'color' => '#c62828', 'remark' => 'Fail'];
}

// Attendance summary for one child (optionally for one month 'YYYY-MM')
function child_attendance($conn, $student_id, $month = null) {
    $student_id = (int)$student_id;
    $out = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'percent' => 0, 'rows' => []];
    if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'attendance'")) == 0) return $out;

    $w = "WHERE student_id = $student_id";
    if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) $w .= " AND DATE_FORMAT(date, '%Y-%m') = '$month'";

    $res = mysqli_query($conn, "SELECT date, status, remarks FROM attendance $w ORDER BY date DESC");
    while ($r = mysqli_fetch_assoc($res)) {
        $out['rows'][] = $r;
        $out['total']++;
        if ($r['status'] === 'Present')     $out['present']++;
        elseif ($r['status'] === 'Absent')  $out['absent']++;
        elseif ($r['status'] === 'Late')    $out['late']++;
    }
    // Late still counts as attending
    $out['percent'] = $out['total'] > 0 ? round(($out['present'] + $out['late']) / $out['total'] * 100) : 0;
    return $out;
}

// Fee status for one child: fee, discount, payable, paid, balance, payments
function child_fees($conn, $student_id) {
    $student_id = (int)$student_id;
    $out = ['has_fees' => false, 'fee' => 0, 'discount' => 0, 'reason' => null,
            'payable' => 0, 'paid' => 0, 'balance' => 0, 'pct' => 0, 'payments' => []];

    $has_fin = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'payments'")) > 0
            && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'fee_structure'")) > 0;
    if (!$has_fin) return $out;

    $has_disc  = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'student_discounts'")) > 0;
    $disc_join = $has_disc ? "LEFT JOIN student_discounts d ON d.student_id = s.id" : "";
    $disc_cols = $has_disc ? "d.discount_type, COALESCE(d.value,0) AS discount_value, d.reason AS discount_reason"
                           : "NULL AS discount_type, 0 AS discount_value, NULL AS discount_reason";

    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(f.amount, 0) AS fee, $disc_cols,
                COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id),0) AS paid
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN fee_structure f ON f.class_id = c.id AND f.academic_year = c.academic_year
         $disc_join
         WHERE s.id = $student_id"));

    if (!$row || $row['fee'] <= 0) return $out;

    $fee  = (float)$row['fee'];
    $val  = (float)$row['discount_value'];
    $disc = 0;
    if ($val > 0) {
        $disc = ($row['discount_type'] === 'Percent') ? $fee * $val / 100 : $val;
        if ($disc > $fee) $disc = $fee;
    }
    $payable = $fee - $disc;
    $paid    = (float)$row['paid'];

    $out['has_fees'] = true;
    $out['fee']      = $fee;
    $out['discount'] = $disc;
    $out['reason']   = $row['discount_reason'];
    $out['payable']  = $payable;
    $out['paid']     = $paid;
    $out['balance']  = $payable - $paid;
    $out['pct']      = $payable > 0 ? round($paid / $payable * 100) : 100;

    $pr = mysqli_query($conn,
        "SELECT id, receipt_no, amount, payment_date, method, notes
         FROM payments WHERE student_id = $student_id
         ORDER BY payment_date DESC, id DESC");
    while ($r = mysqli_fetch_assoc($pr)) $out['payments'][] = $r;

    return $out;
}

// Announcements a parent should see
function parent_announcements($conn, $limit = 20) {
    include_once __DIR__ . '/../announcements_helper.php';
    return get_announcements($conn, 'Parents', $limit);
}

function money_p($n) { return '$' . number_format((float)$n, 2); }

// Keeps the selected child when moving between parent pages
function child_link($page, $child_id, $extra = '') {
    return $page . '?child=' . (int)$child_id . $extra;
}
?>
