<?php
// ============================================================
// parent/results.php - The child's marks and report card.
//
// Who can open it: parent
// ============================================================
include '../config.php';
include_once '../settings_helper.php';
include 'parent_common.php';

$acad = $child ? child_academics($conn, $child_id, $child['class_id']) : null;

include 'sidebar.php';
?>

<style>
    .report-head { text-align: center; margin-bottom: 22px; display: none; }
    .report-head h1 { font-size: 22px; color: #333; }
    .report-head p  { color: #666; font-size: 13px; }
    @media print { .report-head { display: block; } }

    .bar-wrap { background: #eee; border-radius: 6px; height: 10px; width: 100px; overflow: hidden; display: inline-block; vertical-align: middle; }
    .bar-wrap > div { height: 100%; border-radius: 6px; }
</style>

<?php if (!$child): ?>
    <div class="card"><p class="empty">No child linked to your account. Please contact the school admin.</p></div>
<?php else: ?>

<div class="report-head">
    <h1><?php echo htmlspecialchars(school_name($conn)); ?></h1>
    <p>Student Report Card</p>
</div>

<div class="card">
    <h2 class="section-title">📝 Academic Results — <?php echo htmlspecialchars($child['name'] ?? ''); ?></h2>

    <div class="stats-container">
        <div class="stat-card"><h3>Subjects Graded</h3><div class="stat-value"><?php echo $acad['counted']; ?> / <?php echo count($acad['subjects']); ?></div></div>
        <div class="stat-card"><h3>Total Marks</h3><div class="stat-value"><?php echo $acad['complete'] ? $acad['total'] : '—'; ?></div></div>
        <div class="stat-card"><h3>Average</h3><div class="stat-value"><?php echo $acad['complete'] ? number_format($acad['average'], 1) . '%' : '—'; ?></div></div>
        <div class="stat-card"><h3>Class Rank</h3><div class="stat-value"><?php echo $acad['rank']; ?><?php if ($acad['class_size']): ?><span style="font-size:15px;color:#999;"> / <?php echo $acad['class_size']; ?></span><?php endif; ?></div></div>
        <div class="stat-card">
            <h3>Overall Result</h3>
            <div class="stat-value" style="font-size:<?php echo $acad['complete'] ? '21px' : '15px'; ?>;color:<?php echo $acad['status'] === 'PASS' ? '#2e7d32' : ($acad['status'] === 'FAIL' ? '#c62828' : '#b26a00'); ?>;">
                <?php echo $acad['status'] === 'PASS' ? '✅ PASS' : ($acad['status'] === 'FAIL' ? '❌ FAIL' : '⏳ Waiting for marks'); ?>
            </div>
        </div>
    </div>

    <div style="margin-top:20px;" class="no-print">
        <button class="btn" onclick="window.print()">🖨️ Print Report Card</button>
    </div>
</div>

<div class="card">
    <h2 class="section-title">📚 Subject Grades</h2>
    <?php if (count($acad['subjects']) == 0): ?>
        <p class="empty">No subjects set up yet.</p>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Marks</th>
                    <th>Out of</th>
                    <th>Progress</th>
                    <th>Grade</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($acad['subjects'] as $s):
                    $mk = isset($acad['marks'][(int)$s['id']]) ? $acad['marks'][(int)$s['id']] : null;
                    $g  = grade_for($mk);
                    $max = (int)($s['max_marks'] ?: 100);
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($s['name'] ?? ''); ?></strong></td>
                        <td><?php echo $mk !== null ? $mk : '<span style="color:#bbb;">-</span>'; ?></td>
                        <td><?php echo $max; ?></td>
                        <td>
                            <?php if ($mk !== null): ?>
                                <span class="bar-wrap"><div style="width:<?php echo min(round($mk / $max * 100), 100); ?>%;background:<?php echo $g['color']; ?>;"></div></span>
                                <small style="color:#666;margin-left:6px;"><?php echo round($mk / $max * 100); ?>%</small>
                            <?php else: ?>
                                <span style="color:#bbb;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:<?php echo $g['color']; ?>;font-weight:bold;"><?php echo $g['grade']; ?></td>
                        <td style="color:<?php echo $g['color']; ?>;"><?php echo $g['remark']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $failing = [];
        foreach ($acad['subjects'] as $s) {
            $mk = isset($acad['marks'][(int)$s['id']]) ? $acad['marks'][(int)$s['id']] : null;
            if ($mk !== null && $mk < 50) $failing[] = $s['name'];
        }
        ?>
        <?php if (count($failing) > 0): ?>
            <div class="note note-bad">
                ⚠️ <strong><?php echo htmlspecialchars($child['name'] ?? ''); ?></strong> is below the pass mark (50) in:
                <strong><?php echo htmlspecialchars(implode(', ', $failing)); ?></strong>.
                You may wish to speak with the class teacher.
            </div>
        <?php elseif ($acad['complete']): ?>
            <?php // only say this once every subject is marked - otherwise it
                  // reads like a pass when most papers are not even graded ?>
            <div class="note note-ok">
                ✅ <?php echo htmlspecialchars($child['name'] ?? ''); ?> has passed every subject. Well done!
            </div>
        <?php endif; ?>

        <?php if (!$acad['complete'] && count($acad['subjects']) > 0): ?>
            <div class="note" style="background:#fff8e1;border-left:4px solid #ffb300;color:#7a5c00;">
                ⏳ <strong><?php echo (int)$acad['missing_count']; ?></strong>
                subject<?php echo $acad['missing_count'] === 1 ? '' : 's'; ?>
                <?php echo $acad['missing_count'] === 1 ? 'has' : 'have'; ?> not been marked yet:
                <strong><?php echo htmlspecialchars(implode(', ', $acad['missing'])); ?></strong>.<br>
                The total, average, rank and PASS / FAIL stay blank until every subject has a mark,
                so you never see a result that is only half true.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <p style="color:#999;font-size:12px;margin-top:18px;">
        Grading: A+ 90–100 · A 80–89 · B 70–79 · C 60–69 · D 50–59 · F below 50 &nbsp;|&nbsp; Pass mark 50%
    </p>
</div>

<?php endif; ?>

<?php include 'footer.php'; ?>
