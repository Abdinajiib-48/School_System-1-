<?php
// ============================================================
// teacher/timetable.php - This teacher's own weekly timetable.
//
// Who can open it: teacher
// ============================================================
include '../config.php';

require_role('teacher');

include_once '../timetable_helper.php';

$user_id = (int)$_SESSION['user_id'];
$teacher = mysqli_fetch_assoc(mysqli_query($conn, "SELECT t.* FROM teachers t WHERE t.user_id = $user_id"));

$installed = tt_installed($conn);
$grid      = ($installed && $teacher) ? tt_teacher_grid($conn, $teacher['id']) : [];
$periods   = $installed ? tt_periods($conn) : [];
$days      = tt_days();

// Counts
$total_lessons = 0; $per_day = array_fill_keys(array_keys($days), 0); $classes_taught = [];
foreach ($grid as $pid => $row) {
    foreach ($row as $d => $cell) {
        $total_lessons++;
        $per_day[$d]++;
        $classes_taught[$cell['grade']] = true;
    }
}

// Today's lessons
$today   = (int)date('N');
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
    .card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card h2 { font-size: 19px; color: #333; margin-bottom: 14px; padding-left: 12px; border-left: 4px solid #4CAF50; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px,1fr)); gap: 16px; margin-bottom: 22px; }
    .stat { background: white; padding: 18px; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #4CAF50; }
    .stat .l { color: #666; font-size: 13px; }
    .stat .v { font-size: 25px; font-weight: bold; color: #333; margin-top: 5px; }
    .today-list { list-style: none; padding: 0; }
    .today-list li { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .today-list li:last-child { border-bottom: none; }
    .today-list .tm { background: #e8f5e9; color: #2e7d32; font-weight: 700; padding: 6px 12px; border-radius: 8px; font-size: 13px; white-space: nowrap; }
    .today-list .sj { font-weight: 600; color: #333; }
    .today-list .cl { color: #666; font-size: 13px; }
    .empty { color: #999; text-align: center; padding: 30px; }
    .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
           font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; }
</style>

<?php if (!$installed): ?>
    <div class="card"><p class="empty">The timetable has not been set up yet. Please ask the admin.</p></div>
<?php elseif (!$teacher): ?>
    <div class="card"><p class="empty">Teacher record not found.</p></div>
<?php else: ?>

<div class="stats">
    <div class="stat"><div class="l">📚 Lessons per Week</div><div class="v"><?php echo $total_lessons; ?></div></div>
    <div class="stat"><div class="l">🏫 Classes I Teach</div><div class="v"><?php echo count($classes_taught); ?></div></div>
    <div class="stat" style="border-color:#2196F3;">
        <div class="l">📅 Lessons Today</div>
        <div class="v"><?php echo $today <= 5 ? count($today_list) : '—'; ?></div>
    </div>
    <div class="stat" style="border-color:#ff9800;">
        <div class="l">🔔 Busiest Day</div>
        <div class="v" style="font-size:19px;">
            <?php
            $max = 0; $busiest = '—';
            foreach ($per_day as $d => $n) if ($n > $max) { $max = $n; $busiest = $days[$d]; }
            echo $busiest . ($max ? " ($max)" : '');
            ?>
        </div>
    </div>
</div>

<div class="card">
    <h2>📅 Today — <?php echo date('l, d M Y'); ?></h2>
    <?php if ($today > 5): ?>
        <p class="empty">🎉 It's the weekend — no lessons today.</p>
    <?php elseif (count($today_list) === 0): ?>
        <p class="empty">You have no lessons today.</p>
    <?php else: ?>
        <ul class="today-list">
            <?php foreach ($today_list as $c): ?>
                <li>
                    <span class="tm"><?php echo htmlspecialchars($c['time'] ?? ''); ?></span>
                    <span>
                        <span class="sj"><?php echo htmlspecialchars($c['subject_name'] ?? ''); ?></span><br>
                        <span class="cl">Class <?php echo htmlspecialchars($c['grade'] ?? ''); ?>
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
    <h2>🗓️ My Weekly Timetable</h2>
    <p style="color:#666;font-size:14px;">Every class you teach, in one view.</p>
    <?php if ($total_lessons === 0): ?>
        <p class="empty">Your timetable is empty. The admin has not placed your lessons yet.</p>
    <?php else: ?>
        <?php tt_render_grid($conn, $grid, 'teacher'); ?>
        <div style="margin-top:16px;" class="no-print">
            <button class="btn" onclick="window.print()">🖨️ Print My Timetable</button>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php // Close the divs opened in sidebar.php ?>
</div></div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (menuToggle) { menuToggle.addEventListener('click', function() { sidebar.classList.toggle('active'); }); }
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) sidebar.classList.remove('active');
        }
    });
</script>
</body>
</html>
