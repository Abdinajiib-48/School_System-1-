<?php
// ============================================================
// parent/attendance.php - The child's attendance record.
//
// Who can open it: parent
// ============================================================
include '../config.php';
include 'parent_common.php';

// Selected month (YYYY-MM)
$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');

$all_time = $child ? child_attendance($conn, $child_id) : null;          // overall
$this_mon = $child ? child_attendance($conn, $child_id, $month) : null;  // selected month

// Build a day => status map for the calendar
$day_status = [];
if ($this_mon) {
    foreach ($this_mon['rows'] as $r) {
        $day_status[(int)date('j', strtotime($r['date']))] = $r;
    }
}

$first_ts    = strtotime($month . '-01');
$days_in_mon = (int)date('t', $first_ts);
$start_dow    = (int)date('w', $first_ts);   // 0 = Sunday
$month_label = date('F Y', $first_ts);
$prev_month  = date('Y-m', strtotime($month . '-01 -1 month'));
$next_month  = date('Y-m', strtotime($month . '-01 +1 month'));

include 'sidebar.php';
?>

<style>
    .cal { width: 100%; border-collapse: collapse; margin-top: 15px; table-layout: fixed; }
    .cal th { padding: 9px; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: .5px; }
    .cal td { height: 66px; vertical-align: top; border: 1px solid #eee; padding: 6px; border-radius: 6px; }
    .cal td .d { font-size: 12px; color: #999; font-weight: 600; }
    .cal td .s { font-size: 11px; font-weight: 700; margin-top: 4px; display: block; }
    .cal td.present { background: #e8f5e9; } .cal td.present .s { color: #2e7d32; }
    .cal td.absent  { background: #ffebee; } .cal td.absent  .s { color: #c62828; }
    .cal td.late    { background: #fff8e1; } .cal td.late    .s { color: #ef6c00; }
    .cal td.blank   { background: #fafafa; border-color: #f5f5f5; }
    .cal td.today   { outline: 2px solid #4CAF50; }

    .mon-bar { display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    .legend { display: flex; gap: 16px; font-size: 12px; color: #666; margin-top: 14px; flex-wrap: wrap; }
    .legend i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 6px; vertical-align: middle; }
</style>

<?php if (!$child): ?>
    <div class="card"><p class="empty">No child linked to your account. Please contact the school admin.</p></div>
<?php elseif ($all_time['total'] == 0): ?>
    <div class="card">
        <h2 class="section-title">📋 Attendance — <?php echo htmlspecialchars($child['name'] ?? ''); ?></h2>
        <p class="empty">No attendance has been recorded yet. It will appear here once teachers start taking attendance.</p>
    </div>
<?php else: ?>

<div class="card">
    <h2 class="section-title">📋 Attendance — <?php echo htmlspecialchars($child['name'] ?? ''); ?> (All Time)</h2>
    <div class="stats-container">
        <div class="stat-card"><h3>School Days</h3><div class="stat-value" style="color:#2196F3;"><?php echo $all_time['total']; ?></div></div>
        <div class="stat-card"><h3>Present</h3><div class="stat-value"><?php echo $all_time['present']; ?></div></div>
        <div class="stat-card"><h3>Absent</h3><div class="stat-value" style="color:#c62828;"><?php echo $all_time['absent']; ?></div></div>
        <div class="stat-card"><h3>Late</h3><div class="stat-value" style="color:#ef6c00;"><?php echo $all_time['late']; ?></div></div>
        <div class="stat-card">
            <h3>Attendance Rate</h3>
            <div class="stat-value" style="color:<?php echo $all_time['percent'] >= 75 ? '#2e7d32' : '#c62828'; ?>;"><?php echo $all_time['percent']; ?>%</div>
        </div>
    </div>

    <div class="progress" style="margin-top:18px;"><div style="width:<?php echo $all_time['percent']; ?>%"></div></div>

    <?php if ($all_time['percent'] >= 90): ?>
        <div class="note note-ok">✅ Excellent attendance. Keep it up!</div>
    <?php elseif ($all_time['percent'] >= 75): ?>
        <div class="note note-info">ℹ️ Attendance is acceptable, but there is room to improve.</div>
    <?php else: ?>
        <div class="note note-bad">⚠️ Attendance is below 75%. Please contact the class teacher if your child has been unwell.</div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="mon-bar">
        <h2 class="section-title" style="margin-bottom:0;">📅 <?php echo $month_label; ?></h2>
        <div class="no-print">
            <a href="attendance.php?child=<?php echo $child_id; ?>&month=<?php echo $prev_month; ?>" class="btn btn-grey btn-sm">← Previous</a>
            <a href="attendance.php?child=<?php echo $child_id; ?>&month=<?php echo date('Y-m'); ?>" class="btn btn-sm">This Month</a>
            <a href="attendance.php?child=<?php echo $child_id; ?>&month=<?php echo $next_month; ?>" class="btn btn-grey btn-sm">Next →</a>
        </div>
    </div>

    <p style="color:#666;font-size:14px;margin-top:12px;">
        <?php if ($this_mon['total'] > 0): ?>
            <?php echo $this_mon['present']; ?> present ·
            <?php echo $this_mon['absent']; ?> absent ·
            <?php echo $this_mon['late']; ?> late ·
            <strong><?php echo $this_mon['percent']; ?>%</strong> this month
        <?php else: ?>
            No attendance recorded in this month.
        <?php endif; ?>
    </p>

    <table class="cal">
        <thead>
            <tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr>
        </thead>
        <tbody>
            <tr>
            <?php
            for ($i = 0; $i < $start_dow; $i++) echo '<td class="blank"></td>';
            $col = $start_dow;
            for ($d = 1; $d <= $days_in_mon; $d++) {
                if ($col == 7) { echo '</tr><tr>'; $col = 0; }
                $cls = '';
                $lbl = '';
                if (isset($day_status[$d])) {
                    $st  = $day_status[$d]['status'];
                    $cls = strtolower($st);
                    $lbl = ($st === 'Present' ? '✓ Present' : ($st === 'Absent' ? '✗ Absent' : '⏰ Late'));
                }
                $is_today = ($month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT)) === date('Y-m-d');
                echo '<td class="' . $cls . ($is_today ? ' today' : '') . '">';
                echo '<span class="d">' . $d . '</span>';
                if ($lbl) echo '<span class="s">' . $lbl . '</span>';
                echo '</td>';
                $col++;
            }
            while ($col < 7) { echo '<td class="blank"></td>'; $col++; }
            ?>
            </tr>
        </tbody>
    </table>

    <div class="legend">
        <span><i style="background:#e8f5e9;border:1px solid #2e7d32;"></i>Present</span>
        <span><i style="background:#ffebee;border:1px solid #c62828;"></i>Absent</span>
        <span><i style="background:#fff8e1;border:1px solid #ef6c00;"></i>Late</span>
    </div>
</div>

<?php
// Absent / late history (all time)
$problems = [];
foreach ($all_time['rows'] as $r) if ($r['status'] !== 'Present') $problems[] = $r;
?>
<div class="card">
    <h2 class="section-title">⚠️ Absent &amp; Late History</h2>
    <?php if (count($problems) == 0): ?>
        <p class="empty">🎉 <?php echo htmlspecialchars($child['name'] ?? ''); ?> has never been absent or late.</p>
    <?php else: ?>
        <table class="data">
            <thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Teacher's Note</th></tr></thead>
            <tbody>
                <?php foreach ($problems as $r): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                        <td><?php echo date('l', strtotime($r['date'])); ?></td>
                        <td>
                            <span class="badge <?php echo $r['status'] === 'Absent' ? 'b-red' : 'b-orange'; ?>">
                                <?php echo $r['status'] === 'Absent' ? '✗ Absent' : '⏰ Late'; ?>
                            </span>
                        </td>
                        <td><?php echo $r['remarks'] ? htmlspecialchars($r['remarks'] ?? '') : '<span style="color:#bbb;">—</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include 'footer.php'; ?>
