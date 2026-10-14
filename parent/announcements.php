<?php
// ============================================================
// parent/announcements.php - Announcements aimed at parents.
//
// Who can open it: parent
// ============================================================
include '../config.php';
include 'parent_common.php';

$filter = isset($_GET['type']) ? $_GET['type'] : 'All';
$valid  = ['All', 'News', 'Event', 'Holiday', 'Exam'];
if (!in_array($filter, $valid)) $filter = 'All';

$news = parent_announcements($conn, 100);
if ($filter !== 'All') {
    $news = array_values(array_filter($news, function ($n) use ($filter) { return $n['type'] === $filter; }));
}

$icons = ['News' => '📰', 'Event' => '🎉', 'Holiday' => '🏖️', 'Exam' => '📝'];

include 'sidebar.php';
?>

<style>
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
</style>

<div class="card">
    <h2 class="section-title">📢 School Announcements</h2>
    <p style="color:#666;font-size:14px;">News, events, holidays and exam schedules from the school.</p>
</div>

<div class="filter-bar no-print">
    <?php foreach ($valid as $v): ?>
        <a href="announcements.php?child=<?php echo (int)$child_id; ?>&type=<?php echo $v; ?>"
           class="f-btn <?php echo $filter === $v ? 'active' : ''; ?>">
            <?php echo $v === 'All' ? '📋 All' : ($icons[$v] . ' ' . ($v === 'News' ? 'News' : $v . 's')); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (count($news) == 0): ?>
    <div class="card"><p class="empty">No announcements<?php echo $filter !== 'All' ? ' of this type' : ''; ?> yet.</p></div>
<?php else: foreach ($news as $n): ?>
    <div class="ann <?php echo $n['is_pinned'] ? 'pinned' : htmlspecialchars($n['type'] ?? ''); ?>">
        <h3>
            <?php if ($n['is_pinned']): ?><span title="Pinned">📌</span><?php endif; ?>
            <?php echo isset($icons[$n['type']]) ? $icons[$n['type']] : '📰'; ?>
            <?php echo htmlspecialchars($n['title'] ?? ''); ?>
            <span class="badge b-blue"><?php echo htmlspecialchars($n['type'] ?? ''); ?></span>
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

<?php include 'footer.php'; ?>
