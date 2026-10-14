<?php
include_once __DIR__ . '/auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// Shared announcements helper - used by the parent, student
// and teacher portals so they all read the same board.
//
// Audience rules:
//   'Parents'  -> a parent sees  'All' + 'Parents'
//   'Students' -> a student sees 'All' + 'Students'
//   'Teachers' -> a teacher sees 'All' + 'Teachers'
// ============================================================

if (!function_exists('get_announcements')) {

    // $audience must be 'Parents', 'Students' or 'Teachers'
    function get_announcements($conn, $audience, $limit = 50, $type = 'All') {
        $out = [];
        if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'announcements'")) == 0) return $out;

        if (!in_array($audience, ['Parents', 'Students', 'Teachers'])) $audience = 'Parents';
        $limit = (int)$limit;

        $where = "WHERE a.audience IN ('All', '$audience')";
        if (in_array($type, ['News', 'Event', 'Holiday', 'Exam'])) {
            $where .= " AND a.type = '$type'";
        }

        $res = mysqli_query($conn,
            "SELECT a.*, u.username AS posted_by_name
             FROM announcements a
             LEFT JOIN users u ON a.posted_by = u.id
             $where
             ORDER BY a.is_pinned DESC, a.created_at DESC
             LIMIT $limit");

        if ($res) { while ($r = mysqli_fetch_assoc($res)) $out[] = $r; }
        return $out;
    }

    // Icon for each announcement type
    function announcement_icon($type) {
        $icons = ['News' => '📰', 'Event' => '🎉', 'Holiday' => '🏖️', 'Exam' => '📝'];
        return isset($icons[$type]) ? $icons[$type] : '📰';
    }

    // How many were posted in the last 7 days (for the "new" badge)
    function count_new_announcements($conn, $audience, $days = 7) {
        $n = 0;
        foreach (get_announcements($conn, $audience, 100) as $a) {
            if (strtotime($a['created_at']) >= strtotime("-$days days")) $n++;
        }
        return $n;
    }
}
?>
