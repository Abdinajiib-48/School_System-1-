<?php
// ============================================================
// parent/dashboard.php - Parent home - a summary for the child being viewed.
//
// Who can open it: parent
// ============================================================
include '../config.php';
include 'parent_common.php';

$alerts = [];
$acad = null; $att = null; $fees = null; $news = [];

if ($child) {
    $acad = child_academics($conn, $child_id, $child['class_id']);
    $att  = child_attendance($conn, $child_id);
    $fees = child_fees($conn, $child_id);
    $news = parent_announcements($conn, 3);

    // ---- Notifications ----
    // Absent or late today
    if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'attendance'")) > 0) {
        $today = date('Y-m-d');
        $t = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT status, remarks FROM attendance WHERE student_id = $child_id AND date = '$today'"));
        if ($t && $t['status'] === 'Absent') {
            $alerts[] = ['type' => 'bad', 'icon' => '🚫',
                'text' => '<strong>' . htmlspecialchars($child['name'] ?? '') . ' was marked ABSENT today</strong> (' . date('d M Y') . ').'
                          . ($t['remarks'] ? ' Note: ' . htmlspecialchars($t['remarks'] ?? '') : ''),
                'link' => child_link('attendance.php', $child_id), 'link_text' => 'View attendance'];
        } elseif ($t && $t['status'] === 'Late') {
            $alerts[] = ['type' => 'warn', 'icon' => '⏰',
                'text' => '<strong>' . htmlspecialchars($child['name'] ?? '') . ' arrived LATE today</strong> (' . date('d M Y') . ').',
                'link' => child_link('attendance.php', $child_id), 'link_text' => 'View attendance'];
        }
    }

    // Low attendance
    if ($att['total'] > 0 && $att['percent'] < 75) {
        $alerts[] = ['type' => 'warn', 'icon' => '📉',
            'text' => 'Attendance is <strong>' . $att['percent'] . '%</strong>, below the 75% the school expects.',
            'link' => child_link('attendance.php', $child_id), 'link_text' => 'See details'];
    }

    // Fee reminder
    if ($fees['has_fees'] && $fees['balance'] > 0.001) {
        $alerts[] = ['type' => 'warn', 'icon' => '💰',
            'text' => 'School fee balance of <strong>' . money_p($fees['balance']) . '</strong> is still outstanding.',
            'link' => child_link('fees.php', $child_id), 'link_text' => 'View fees'];
    }

    // Failing subjects
    $failing = 0;
    foreach ($acad['marks'] as $mk) if ($mk < 50) $failing++;
    if ($failing > 0) {
        $alerts[] = ['type' => 'bad', 'icon' => '📕',
            'text' => '<strong>' . $failing . ' subject' . ($failing > 1 ? 's are' : ' is') . ' below the pass mark</strong> of 50.',
            'link' => child_link('results.php', $child_id), 'link_text' => 'See results'];
    }

    // Newest announcement
    if (count($news) > 0) {
        $newest = $news[0];
        if (strtotime($newest['created_at']) >= strtotime('-7 days')) {
            $alerts[] = ['type' => 'info', 'icon' => '📢',
                'text' => 'New announcement: <strong>' . htmlspecialchars($newest['title'] ?? '') . '</strong>',
                'link' => 'announcements.php?child=' . $child_id, 'link_text' => 'Read'];
        }
    }
}

include 'sidebar.php';
?>

<style>
    .welcome {
        background: white; color: #333; border-radius: 15px; padding: 28px; margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #4CAF50;
    }
    .welcome h1 { font-size: 24px; margin-bottom: 8px; color: #333; }
    .welcome p  { color: #666; font-size: 14px; }
    .chips { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
    .chip  { background: #f0f2f5; color: #555; padding: 6px 14px; border-radius: 20px; font-size: 13px; }

    .alert-box { display: flex; align-items: center; gap: 12px; padding: 13px 17px; border-radius: 10px; margin-bottom: 11px; font-size: 14px; }
    .alert-box .ico { font-size: 21px; }
    .alert-box .txt { flex: 1; }
    .alert-box a { color: inherit; font-weight: 700; white-space: nowrap; }
    .a-warn { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
    .a-bad  { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    .a-info { background: #e3f2fd; color: #0d47a1; border-left: 4px solid #2196F3; }
    .a-ok   { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }

    .quick { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px,1fr)); gap: 15px; }
    .quick a {
        display: block; padding: 18px; background: #f0f2f5; color: #333; text-decoration: none;
        text-align: center; border-radius: 12px; font-weight: 600; transition: all .3s;
    }
    .quick a:hover { background: #4CAF50; color: white; transform: translateY(-3px); }
    .quick a .ic { display: block; font-size: 26px; margin-bottom: 7px; }

    .news-item { padding: 13px 0; border-bottom: 1px solid #eee; }
    .news-item:last-child { border-bottom: none; }
    .news-item h4 { font-size: 15px; color: #333; margin-bottom: 4px; }
    .news-item p  { font-size: 13px; color: #666; }
</style>

<?php if (!$child): ?>

    <div class="card" style="text-align:center;padding:50px 30px;">
        <div style="font-size:60px;margin-bottom:18px;">👨‍👩‍👧</div>
        <h2 style="color:#333;margin-bottom:12px;">No child linked to your account</h2>
        <p style="color:#666;margin-bottom:8px;">Your parent account is active, but no student has been linked to it yet.</p>
        <p style="color:#888;font-size:14px;">Please ask the school admin to link your child from <strong>Admin → Parents</strong>.</p>
    </div>

<?php else: ?>

<div class="welcome">
    <h1>Welcome, <?php echo htmlspecialchars($parent['name'] ?? ''); ?>! 👋</h1>
    <p>Here is how <strong><?php echo htmlspecialchars($child['name'] ?? ''); ?></strong> is doing at school.</p>
    <div class="chips">
        <span class="chip">🆔 <?php echo htmlspecialchars($child['student_id'] ?? ''); ?></span>
        <span class="chip">🏫 Class <?php echo htmlspecialchars($child['grade'] ?: '-'); ?></span>
        <span class="chip">📅 <?php echo htmlspecialchars($child['academic_year'] ?: '-'); ?></span>
        <span class="chip">📆 Semester <?php echo htmlspecialchars($child['semester'] ?: '-'); ?></span>
        <?php if ($child['homeroom_teacher']): ?>
            <span class="chip">👨‍🏫 <?php echo htmlspecialchars($child['homeroom_teacher'] ?? ''); ?></span>
        <?php endif; ?>
        <span class="chip">👪 <?php echo htmlspecialchars($child['relationship'] ?? ''); ?></span>
    </div>
</div>

<!-- ===== NOTIFICATIONS ===== -->
<div class="card">
    <h2 class="section-title">🔔 Notifications</h2>
    <?php if (count($alerts) == 0): ?>
        <div class="alert-box a-ok">
            <span class="ico">✅</span>
            <span class="txt">Everything looks good — no absences, no failing subjects, no unpaid fees.</span>
        </div>
    <?php else: foreach ($alerts as $a): ?>
        <div class="alert-box a-<?php echo $a['type']; ?>">
            <span class="ico"><?php echo $a['icon']; ?></span>
            <span class="txt"><?php echo $a['text']; ?></span>
            <a href="<?php echo $a['link']; ?>"><?php echo $a['link_text']; ?> →</a>
        </div>
    <?php endforeach; endif; ?>
</div>

<!-- ===== AT A GLANCE ===== -->
<div class="card">
    <h2 class="section-title">📊 At a Glance</h2>
    <div class="stats-container">
        <div class="stat-card">
            <h3>Average Score</h3>
            <div class="stat-value"><?php echo $acad['complete'] ? number_format($acad['average'], 1) . '%' : '—'; ?></div>
        </div>
        <div class="stat-card">
            <h3>Class Rank</h3>
            <div class="stat-value"><?php echo $acad['rank']; ?><?php if ($acad['class_size']): ?><span style="font-size:15px;color:#999;"> / <?php echo $acad['class_size']; ?></span><?php endif; ?></div>
        </div>
        <div class="stat-card">
            <h3>Result</h3>
            <div class="stat-value" style="color:<?php echo $acad['status'] === 'PASS' ? '#2e7d32' : ($acad['status'] === 'FAIL' ? '#c62828' : '#b26a00'); ?>;font-size:<?php echo $acad['complete'] ? '22px' : '15px'; ?>;">
                <?php echo $acad['status'] === 'PASS' ? '✅ PASS' : ($acad['status'] === 'FAIL' ? '❌ FAIL' : '⏳ Waiting for marks'); ?>
            </div>
        </div>
        <div class="stat-card">
            <h3>Attendance</h3>
            <div class="stat-value" style="color:<?php echo $att['percent'] >= 75 ? '#2e7d32' : '#ef6c00'; ?>;">
                <?php echo $att['total'] > 0 ? $att['percent'] . '%' : '-'; ?>
            </div>
        </div>
        <div class="stat-card">
            <h3>Fee Balance</h3>
            <div class="stat-value" style="color:<?php echo ($fees['has_fees'] && $fees['balance'] > 0) ? '#c62828' : '#2e7d32'; ?>;font-size:22px;">
                <?php echo $fees['has_fees'] ? money_p(max($fees['balance'], 0)) : '-'; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== QUICK LINKS ===== -->
<div class="card">
    <h2 class="section-title">⚡ Go To</h2>
    <div class="quick">
        <a href="<?php echo child_link('results.php', $child_id); ?>"><span class="ic">📝</span>Results &amp; Report Card</a>
        <a href="<?php echo child_link('attendance.php', $child_id); ?>"><span class="ic">📋</span>Attendance</a>
        <a href="<?php echo child_link('fees.php', $child_id); ?>"><span class="ic">💰</span>School Fees</a>
        <a href="<?php echo child_link('announcements.php', $child_id); ?>"><span class="ic">📢</span>Announcements</a>
    </div>
</div>

<!-- ===== LATEST NEWS ===== -->
<div class="card">
    <h2 class="section-title">📢 Latest from the School</h2>
    <?php if (count($news) == 0): ?>
        <p class="empty">No announcements yet.</p>
    <?php else: foreach ($news as $n): ?>
        <div class="news-item">
            <h4>
                <?php if ($n['is_pinned']): ?>📌 <?php endif; ?>
                <?php echo htmlspecialchars($n['title'] ?? ''); ?>
                <span class="badge b-blue"><?php echo htmlspecialchars($n['type'] ?? ''); ?></span>
            </h4>
            <p><?php echo nl2br(htmlspecialchars(mb_strimwidth($n['body'], 0, 160, '…'))); ?></p>
            <p style="color:#999;font-size:12px;margin-top:5px;">
                <?php echo date('d M Y', strtotime($n['created_at'])); ?>
                <?php if ($n['event_date']): ?> · 📅 <?php echo date('d M Y', strtotime($n['event_date'])); ?><?php endif; ?>
            </p>
        </div>
    <?php endforeach; ?>
        <p style="margin-top:14px;"><a href="<?php echo child_link('announcements.php', $child_id); ?>" style="color:#4CAF50;text-decoration:none;font-weight:600;">See all announcements →</a></p>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include 'footer.php'; ?>
