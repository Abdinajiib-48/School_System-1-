<?php
include_once __DIR__ . '/auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// Shared timetable helper - used by the admin builder and by the
// teacher, student and parent views so they all agree.
//
// School week: Monday (1) to Friday (5)
// ============================================================

if (!function_exists('tt_days')) {

    // The school week
    function tt_days() {
        return [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday',6 => 'Saturday' , 7 => 'Sunday'];
    }

    function tt_installed($conn) {
        return mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'timetable'")) > 0
            && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'periods'")) > 0;
    }

    // All time slots of a school day, in order
    function tt_periods($conn) {
        $out = [];
        if (!tt_installed($conn)) return $out;
        $res = mysqli_query($conn, "SELECT * FROM periods ORDER BY sort_order, start_time");
        if ($res) { while ($r = mysqli_fetch_assoc($res)) $out[] = $r; }
        return $out;
    }

    // "08:00 - 08:45"
    function tt_time($p) {
        return substr($p['start_time'], 0, 5) . ' - ' . substr($p['end_time'], 0, 5);
    }

    // The whole week for ONE class, as $grid[period_id][day] = lesson
    function tt_class_grid($conn, $class_id) {
        $grid = [];
        if (!tt_installed($conn)) return $grid;
        $class_id = (int)$class_id;
        $res = mysqli_query($conn,
            "SELECT tt.*, s.name AS subject_name, t.name AS teacher_name
             FROM timetable tt
             JOIN subjects s ON tt.subject_id = s.id
             LEFT JOIN teachers t ON tt.teacher_id = t.id
             WHERE tt.class_id = $class_id");
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $grid[(int)$r['period_id']][(int)$r['day_of_week']] = $r;
            }
        }
        return $grid;
    }

    // The whole week for ONE teacher, as $grid[period_id][day] = lesson
    function tt_teacher_grid($conn, $teacher_id) {
        $grid = [];
        if (!tt_installed($conn)) return $grid;
        $teacher_id = (int)$teacher_id;
        $res = mysqli_query($conn,
            "SELECT tt.*, s.name AS subject_name, c.grade
             FROM timetable tt
             JOIN subjects s ON tt.subject_id = s.id
             JOIN classes c ON tt.class_id = c.id
             WHERE tt.teacher_id = $teacher_id");
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $grid[(int)$r['period_id']][(int)$r['day_of_week']] = $r;
            }
        }
        return $grid;
    }

    // Subjects that can be taught to this class, with the teacher who
    // teaches each one. A teacher must be linked to the class first
    // (homeroom teacher, or ticked under Subject Teachers).
    function tt_class_subjects($conn, $class_id) {
        $out = [];
        $class_id = (int)$class_id;
        if ($class_id <= 0) return $out;

        $has_ct = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'class_teachers'")) > 0;
        $union  = $has_ct
            ? "SELECT teacher_id AS tid, class_id AS cid FROM class_teachers
               UNION
               SELECT homeroom_teacher_id, id FROM classes WHERE homeroom_teacher_id IS NOT NULL"
            : "SELECT homeroom_teacher_id AS tid, id AS cid FROM classes WHERE homeroom_teacher_id IS NOT NULL";

        // A teacher can teach several subjects, so join through
        // teacher_subjects when it exists.
        $has_ts = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'teacher_subjects'")) > 0;
        $subj_join = $has_ts
            ? "JOIN teacher_subjects ts ON ts.teacher_id = t.id
               JOIN subjects s ON s.id = ts.subject_id"
            : "JOIN subjects s ON s.id = t.subject_id";

        $res = mysqli_query($conn,
            "SELECT DISTINCT s.id AS subject_id, s.name AS subject_name,
                    t.id AS teacher_id, t.name AS teacher_name
             FROM ($union) x
             JOIN teachers t ON t.id = x.tid
             $subj_join
             WHERE x.cid = $class_id
             ORDER BY s.name, t.name");
        if ($res) { while ($r = mysqli_fetch_assoc($res)) $out[] = $r; }
        return $out;
    }

    // Is this teacher already busy in another class at this day/period?
    // Returns the clashing class name, or null when free.
    function tt_teacher_clash($conn, $teacher_id, $day, $period_id, $ignore_class_id = 0) {
        $teacher_id = (int)$teacher_id;
        $day        = (int)$day;
        $period_id  = (int)$period_id;
        $ignore     = (int)$ignore_class_id;
        if ($teacher_id <= 0) return null;

        $row = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT c.grade FROM timetable tt
             JOIN classes c ON tt.class_id = c.id
             WHERE tt.teacher_id = $teacher_id
               AND tt.day_of_week = $day
               AND tt.period_id = $period_id
               AND tt.class_id != $ignore
             LIMIT 1"));
        return $row ? $row['grade'] : null;
    }

    // How many lessons are filled in for a class
    function tt_class_filled($conn, $class_id) {
        $class_id = (int)$class_id;
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM timetable WHERE class_id = $class_id"));
        return (int)$r['n'];
    }

    // Today's lessons for a class (used on dashboards)
    function tt_today_for_class($conn, $class_id) {
        $out = [];
        if (!tt_installed($conn)) return $out;
        $class_id = (int)$class_id;
        $day = (int)date('N');              // 1 = Monday ... 7 = Sunday
        if ($day > 5) return $out;          // weekend
        $res = mysqli_query($conn,
            "SELECT tt.*, s.name AS subject_name, t.name AS teacher_name,
                    p.name AS period_name, p.start_time, p.end_time
             FROM timetable tt
             JOIN periods p ON tt.period_id = p.id
             JOIN subjects s ON tt.subject_id = s.id
             LEFT JOIN teachers t ON tt.teacher_id = t.id
             WHERE tt.class_id = $class_id AND tt.day_of_week = $day
             ORDER BY p.sort_order");
        if ($res) { while ($r = mysqli_fetch_assoc($res)) $out[] = $r; }
        return $out;
    }

    // Shared styles for every timetable grid
    function tt_styles() {
        ?>
        <style>
            .tt-wrap { overflow-x: auto; }
            table.tt { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; table-layout: fixed; }
            table.tt th, table.tt td { border: 1px solid #e0e0e0; padding: 8px 6px; text-align: center; vertical-align: middle; }
            table.tt thead th { background: #4CAF50; color: white; font-weight: 600; font-size: 13px; }
            table.tt .slot { background: #f8f9fa; font-weight: 600; color: #444; text-align: left; padding-left: 10px; width: 130px; }
            table.tt .slot small { display: block; color: #999; font-weight: normal; font-size: 11px; }
            table.tt tr.break-row td { background: #fff8e1; color: #8a6d00; font-weight: 600; font-style: italic; }
            table.tt td.lesson { background: #e8f5e9; }
            table.tt td.lesson .subj { font-weight: 700; color: #2e7d32; display: block; }
            table.tt td.lesson .who  { color: #666; font-size: 11px; display: block; margin-top: 2px; }
            table.tt td.lesson .room { color: #999; font-size: 11px; }
            table.tt td.free { color: #ccc; }
            table.tt td.today-col { box-shadow: inset 0 0 0 2px #2196F3; }
            table.tt thead th.today-col { background: #2196F3; }
            .tt-legend { display: flex; gap: 16px; flex-wrap: wrap; font-size: 12px; color: #666; margin-top: 12px; }
            .tt-legend i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 5px; vertical-align: middle; }
            @media print {
                .no-print { display: none !important; }
                table.tt { font-size: 11px; }
            }
        </style>
        <?php
    }

    // Draws a read-only timetable grid.
    // $mode 'class'   -> cells show Subject + Teacher
    // $mode 'teacher' -> cells show Subject + Class
    function tt_render_grid($conn, $grid, $mode = 'class') {
        $periods = tt_periods($conn);
        $days    = tt_days();
        $today   = (int)date('N');
        ?>
        <div class="tt-wrap">
        <table class="tt">
            <thead>
                <tr>
                    <th class="slot">Time</th>
                    <?php foreach ($days as $d => $label): ?>
                        <th class="<?php echo $d === $today ? 'today-col' : ''; ?>"><?php echo $label; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periods as $p): ?>
                    <?php if ($p['is_break']): ?>
                        <tr class="break-row">
                            <td class="slot"><?php echo htmlspecialchars($p['name'] ?? ''); ?>
                                <small><?php echo tt_time($p); ?></small></td>
                            <td colspan="<?php echo count($days); ?>">
                                <?php echo htmlspecialchars($p['name'] ?? ''); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td class="slot"><?php echo htmlspecialchars($p['name'] ?? ''); ?>
                                <small><?php echo tt_time($p); ?></small></td>
                            <?php foreach ($days as $d => $label):
                                $cell = isset($grid[(int)$p['id']][$d]) ? $grid[(int)$p['id']][$d] : null; ?>
                                <td class="<?php echo $cell ? 'lesson' : 'free'; ?> <?php echo $d === $today ? 'today-col' : ''; ?>">
                                    <?php if ($cell): ?>
                                        <span class="subj"><?php echo htmlspecialchars($cell['subject_name'] ?? ''); ?></span>
                                        <span class="who">
                                            <?php if ($mode === 'teacher'): ?>
                                                Class <?php echo htmlspecialchars($cell['grade'] ?? ''); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($cell['teacher_name'] ?: '—'); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if (!empty($cell['room'])): ?>
                                            <span class="room">📍 <?php echo htmlspecialchars($cell['room'] ?? ''); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="tt-legend">
            <span><i style="background:#e8f5e9;border:1px solid #2e7d32;"></i>Lesson</span>
            <span><i style="background:#fff8e1;border:1px solid #8a6d00;"></i>Break / Lunch</span>
            <span><i style="background:#2196F3;"></i>Today</span>
        </div>
        <?php
    }
}
?>
