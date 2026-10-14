<?php
// ============================================================
// parent/timetable.php - The child's class timetable.
//
// Who can open it: parent
// ============================================================
include '../config.php';
include 'parent_common.php';
include_once '../timetable_helper.php';

$installed = tt_installed($conn);
$grid      = ($installed && $child) ? tt_class_grid($conn, $child['class_id']) : [];
$periods   = $installed ? tt_periods($conn) : [];
$days      = tt_days();

$total_lessons = 0;
foreach ($grid as $row) $total_lessons += count($row);

// Today
$today = (int)date('N');
$today_list = [];
if ($today <= 5) {
    foreach ($periods as $p) {
        if (isset($grid[(int)$p['id']][$today])) {
            $c = $grid[(int)$p['id']][$today];
            $c['period_name'] = $p['name'];
            $c['time']        = tt_time($p);
            $today_list[]     = $c;
        }
    }
}

include 'sidebar.php';
tt_styles();
?>

<style>
    .today-list { list-style: none; padding: 0; }
    .today-list li { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .today-list li:last-child { border-bottom: none; }
    .today-list .tm { background: #e8f5e9; color: #2e7d32; font-weight: 700; padding: 6px 12px; border-radius: 8px; font-size: 13px; white-space: nowrap; }
    .today-list .sj { font-weight: 600; color: #333; }
    .today-list .cl { color: #666; font-size: 13px; }
</style>

<?php if (!$child): ?>
    <div class="card"><p class="empty">No child linked to your account. Please contact the school admin.</p></div>
<?php elseif (!$installed): ?>
    <div class="card"><p class="empty">The timetable has not been set up yet. Please check back later.</p></div>
<?php else: ?>

<div class="card">
    <h2 class="section-title">🗓️ Timetable — <?php echo htmlspecialchars($child['name'] ?? ''); ?></h2>
    <p style="color:#666;font-size:14px;">
        Class <?php echo htmlspecialchars($child['grade'] ?: '-'); ?>
        · <?php echo $total_lessons; ?> lessons a week
    </p>
</div>

<div class="card">
    <h2 class="section-title">📅 Today — <?php echo date('l, d M Y'); ?></h2>
    <?php if ($today > 5): ?>
        <p class="empty">🎉 It's the weekend — no school today.</p>
    <?php elseif (count($today_list) === 0): ?>
        <p class="empty">No lessons are scheduled for today.</p>
    <?php else: ?>
        <ul class="today-list">
            <?php foreach ($today_list as $c): ?>
                <li>
                    <span class="tm"><?php echo htmlspecialchars($c['time'] ?? ''); ?></span>
                    <span>
                        <span class="sj"><?php echo htmlspecialchars($c['subject_name'] ?? ''); ?></span><br>
                        <span class="cl">
                            👨‍🏫 <?php echo htmlspecialchars($c['teacher_name'] ?: '—'); ?>
                            <?php if (!empty($c['room'])): ?> · 📍 <?php echo htmlspecialchars($c['room'] ?? ''); ?><?php endif; ?>
                            · <?php echo htmlspecialchars($c['period_name'] ?? ''); ?>
                        </span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="section-title">🗓️ Weekly Timetable</h2>
    <?php if ($total_lessons === 0): ?>
        <p class="empty">The timetable for this class has not been filled in yet.</p>
    <?php else: ?>
        <?php tt_render_grid($conn, $grid, 'class'); ?>
        <div style="margin-top:16px;" class="no-print">
            <button class="btn" onclick="window.print()">🖨️ Print Timetable</button>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include 'footer.php'; ?>
