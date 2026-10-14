<?php
// ============================================================
// teacher/announcements.php - Announcements aimed at teachers.
//
// Who can open it: teacher
// ============================================================
include '../config.php';

require_role('teacher');

// Get teacher info
$user_id = $_SESSION['user_id'];
$teacher = mysqli_fetch_assoc(mysqli_query($conn, "SELECT t.* FROM teachers t WHERE t.user_id = '$user_id'"));

include_once '../announcements_helper.php';

$filter = isset($_GET['type']) ? $_GET['type'] : 'All';
$valid  = ['All', 'News', 'Event', 'Holiday', 'Exam'];
if (!in_array($filter, $valid)) $filter = 'All';

$news = get_announcements($conn, 'Teachers', 100, $filter);

include 'sidebar.php';
?>

<style>
    .ann-head { background: white; border-radius: 15px; padding: 25px; margin-bottom: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .ann-head h2 { font-size: 20px; color: #333; margin-bottom: 8px; padding-left: 12px; border-left: 4px solid #4CAF50; }
    .ann-head p { color: #666; font-size: 14px; }

    .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
    .f-btn { padding: 8px 17px; border-radius: 8px; background: white; color: #333; text-decoration: none;
             font-size: 14px; font-weight: 500; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 2px solid transparent; }
    .f-btn:hover  { border-color: #4CAF50; }
    .f-btn.active { background: #4CAF50; color: white; }

    .ann { background: white; border-radius: 14px; padding: 22px; margin-bottom: 18px;
           box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #4CAF50; }
    .ann.pinned  { border-left-color: #ff9800; background: #fffdf7; }
    .ann.Event   { border-left-color: #9c27b0; }
    .ann.Holiday { border-left-color: #2196F3; }
    .ann.Exam    { border-left-color: #f44336; }
    .ann h3 { font-size: 18px; color: #333; margin-bottom: 8px; display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
    .ann .body { color: #555; font-size: 14px; line-height: 1.7; margin: 12px 0; }
    .ann .meta { color: #999; font-size: 12px; display: flex; gap: 15px; flex-wrap: wrap;
                 border-top: 1px solid #f0f0f0; padding-top: 11px; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; background: #e3f2fd; color: #1565c0; }
    .badge-new { background: #d4edda; color: #155724; }
    .empty { color: #999; text-align: center; padding: 40px; background: white; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
</style>

<div class="ann-head">
    <h2>📢 School Announcements</h2>
    <p>News, events, holidays and exam schedules from the school office.</p>
</div>

<div class="filter-bar">
    <?php foreach ($valid as $v): ?>
        <a href="announcements.php?type=<?php echo $v; ?>" class="f-btn <?php echo $filter === $v ? 'active' : ''; ?>">
            <?php echo $v === 'All' ? '📋 All' : (announcement_icon($v) . ' ' . ($v === 'News' ? 'News' : $v . 's')); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (count($news) == 0): ?>
    <div class="empty">No announcements<?php echo $filter !== 'All' ? ' of this type' : ''; ?> yet.</div>
<?php else: foreach ($news as $n):
    $is_new = strtotime($n['created_at']) >= strtotime('-7 days'); ?>
    <div class="ann <?php echo $n['is_pinned'] ? 'pinned' : htmlspecialchars($n['type'] ?? ''); ?>">
        <h3>
            <?php if ($n['is_pinned']): ?><span title="Pinned">📌</span><?php endif; ?>
            <?php echo announcement_icon($n['type']); ?>
            <?php echo htmlspecialchars($n['title'] ?? ''); ?>
            <span class="badge"><?php echo htmlspecialchars($n['type'] ?? ''); ?></span>
            <?php if ($is_new): ?><span class="badge badge-new">NEW</span><?php endif; ?>
        </h3>

        <div class="body"><?php echo nl2br(htmlspecialchars($n['body'] ?? '')); ?></div>

        <div class="meta">
            <span>🗓️ Posted <?php echo date('d M Y', strtotime($n['created_at'])); ?></span>
            <?php if ($n['event_date']): ?>
                <span>📅 <strong>Date: <?php echo date('d M Y', strtotime($n['event_date'])); ?></strong></span>
            <?php endif; ?>
            <?php if ($n['posted_by_name']): ?>
                <span>✍️ <?php echo htmlspecialchars($n['posted_by_name'] ?? ''); ?></span>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; endif; ?>

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
