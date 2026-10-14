<?php
// ============================================================
// admin/announcements.php - Write announcements aimed at students, parents, teachers or everyone.
//
// Who can open it: admin, cashier
// ============================================================
include '../config.php';

require_role(['admin', 'cashier']);
$is_cashier = ($_SESSION['role'] === 'cashier');

$has_ann = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'announcements'")) > 0;
$message = ""; $message_type = "";

// ---- Delete ----
if ($has_ann && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM announcements WHERE id = $id");
    $message = "✅ Announcement deleted."; $message_type = "success";
}

// ---- Pin / unpin ----
if ($has_ann && isset($_GET['pin'])) {
    $id = (int)$_GET['pin'];
    mysqli_query($conn, "UPDATE announcements SET is_pinned = 1 - is_pinned WHERE id = $id");
    $message = "✅ Pin updated."; $message_type = "success";
}

// ---- Add / edit ----
if ($has_ann && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $title      = mysqli_real_escape_string($conn, trim($_POST['title']));
    $body       = mysqli_real_escape_string($conn, trim($_POST['body']));
    $type       = $_POST['type'];
    $audience   = $_POST['audience'];
    $event_date = trim($_POST['event_date']);
    $is_pinned  = isset($_POST['is_pinned']) ? 1 : 0;
    $edit_id    = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $errors = [];

    if ($title === '') $errors[] = "❌ Title is required!";
    if ($body === '')  $errors[] = "❌ Message is required!";
    if (!in_array($type, ['News','Event','Holiday','Exam']))            $errors[] = "❌ Invalid type!";
    if (!in_array($audience, ['All','Parents','Students','Teachers']))  $errors[] = "❌ Invalid audience!";
    if ($event_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) $errors[] = "❌ Invalid date!";

    $date_sql = $event_date === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $event_date) . "'";

    if (count($errors) == 0) {
        $admin_id = (int)$_SESSION['user_id'];
        if ($edit_id > 0) {
            $sql = "UPDATE announcements SET title='$title', body='$body', type='$type',
                    audience='$audience', event_date=$date_sql, is_pinned=$is_pinned WHERE id=$edit_id";
            $ok = "✅ Announcement updated!";
        } else {
            $sql = "INSERT INTO announcements (title, body, type, audience, event_date, is_pinned, posted_by)
                    VALUES ('$title', '$body', '$type', '$audience', $date_sql, $is_pinned, $admin_id)";
            $ok = "✅ Announcement posted!";
        }
        if (mysqli_query($conn, $sql)) { $message = $ok; $message_type = "success"; }
        else { $message = "❌ Error: " . mysqli_error($conn); $message_type = "error"; }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

// ---- Data ----
$items = [];
if ($has_ann) {
    $res = mysqli_query($conn,
        "SELECT a.*, u.username AS posted_by_name
         FROM announcements a LEFT JOIN users u ON a.posted_by = u.id
         ORDER BY a.is_pinned DESC, a.created_at DESC LIMIT 200");
    while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
}

$edit_item = null;
if ($has_ann && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_item = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM announcements WHERE id = $eid"));
}

$icons = ['News' => '📰', 'Event' => '🎉', 'Holiday' => '🏖️', 'Exam' => '📝'];

include 'sidebar.php';
?>

<style>
    .card { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card h2 { margin-bottom: 18px; color: #333; font-size: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
    input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; }
    textarea { min-height: 110px; resize: vertical; }
    input:focus, select:focus, textarea:focus { outline: none; border-color: #4CAF50; }
    .hint { font-size: 12px; color: #666; margin-top: 4px; }
    .check-row { display: flex; align-items: center; gap: 9px; }
    .check-row input { width: auto; }
    .check-row label { margin: 0; }
    button, .btn { background: #4CAF50; color: white; padding: 11px 22px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; }
    button:hover, .btn:hover { background: #45a049; }
    .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 5px; }
    .btn-orange { background: #ff9800; } .btn-red { background: #f44336; } .btn-grey { background: #9e9e9e; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
    th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; vertical-align: top; }
    th { background: #4CAF50; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; background: #e3f2fd; color: #1565c0; }
    .empty { color: #999; text-align: center; padding: 30px; }
    @media (max-width: 768px) { .form-row-3 { grid-template-columns: 1fr; } }
</style>

<?php if (!$has_ann): ?>
    <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:10px;padding:20px;">
        <strong>⚠️ Announcements table not found.</strong><br>
        Import <strong>parent_schema.sql</strong> in phpMyAdmin (database <strong>School_db1</strong>) first.
    </div>
<?php else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $edit_item ? '✏️ Edit Announcement' : '📢 Post an Announcement'; ?></h2>
    <form method="POST" action="announcements.php">
        <?php if ($edit_item): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_item['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Title: <span style="color:red;">*</span></label>
            <input type="text" name="title" required placeholder="e.g. Mid-term exams start next Monday"
                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['title'] ?? '') : ''; ?>">
        </div>

        <div class="form-group">
            <label>Message: <span style="color:red;">*</span></label>
            <textarea name="body" required placeholder="Write the full announcement here..."><?php echo $edit_item ? htmlspecialchars($edit_item['body'] ?? '') : ''; ?></textarea>
        </div>

        <div class="form-row-3">
            <div class="form-group">
                <label>Type: <span style="color:red;">*</span></label>
                <select name="type" required>
                    <?php foreach (['News','Event','Holiday','Exam'] as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo ($edit_item && $edit_item['type'] == $t) ? 'selected' : ''; ?>>
                            <?php echo $icons[$t] . ' ' . $t; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Who can see it: <span style="color:red;">*</span></label>
                <select name="audience" required>
                    <?php foreach (['All','Parents','Students','Teachers'] as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo ($edit_item && $edit_item['audience'] == $a) ? 'selected' : ''; ?>>
                            <?php echo $a === 'All' ? 'Everyone' : $a . ' only'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Parents see "Everyone" and "Parents only".</div>
            </div>
            <div class="form-group">
                <label>Event / Exam Date: <span style="color:#666;">(optional)</span></label>
                <input type="date" name="event_date" value="<?php echo $edit_item ? htmlspecialchars($edit_item['event_date'] ?? '') : ''; ?>">
                <div class="hint">Leave empty for general news.</div>
            </div>
        </div>

        <div class="form-group check-row">
            <input type="checkbox" name="is_pinned" id="is_pinned" value="1" <?php echo ($edit_item && $edit_item['is_pinned']) ? 'checked' : ''; ?>>
            <label for="is_pinned">📌 Pin to the top</label>
        </div>

        <button type="submit"><?php echo $edit_item ? 'Update Announcement' : '📢 Post Announcement'; ?></button>
        <?php if ($edit_item): ?>
            <a href="announcements.php" style="margin-left:10px;color:#666;text-decoration:none;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>📋 All Announcements</h2>
    <?php if (count($items) == 0): ?>
        <p class="empty">No announcements yet. Post the first one above.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Title</th><th>Type</th><th>Audience</th><th>Date</th><th>Posted</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i): ?>
                    <tr>
                        <td>
                            <?php if ($i['is_pinned']): ?>📌 <?php endif; ?>
                            <strong><?php echo htmlspecialchars($i['title'] ?? ''); ?></strong><br>
                            <small style="color:#888;"><?php echo htmlspecialchars(mb_strimwidth($i['body'], 0, 90, '…')); ?></small>
                        </td>
                        <td><span class="badge"><?php echo (isset($icons[$i['type']]) ? $icons[$i['type']] : '') . ' ' . htmlspecialchars($i['type'] ?? ''); ?></span></td>
                        <td><?php echo $i['audience'] === 'All' ? 'Everyone' : htmlspecialchars($i['audience'] ?? ''); ?></td>
                        <td><?php echo $i['event_date'] ? date('d M Y', strtotime($i['event_date'])) : '<span style="color:#bbb;">—</span>'; ?></td>
                        <td><?php echo date('d M Y', strtotime($i['created_at'])); ?><br><small style="color:#888;"><?php echo htmlspecialchars($i['posted_by_name'] ?: '-'); ?></small></td>
                        <td>
                            <a href="announcements.php?pin=<?php echo $i['id']; ?>" class="btn btn-sm btn-grey"><?php echo $i['is_pinned'] ? 'Unpin' : 'Pin'; ?></a>
                            <a href="announcements.php?edit=<?php echo $i['id']; ?>" class="btn btn-sm btn-orange">Edit</a>
                            <a href="announcements.php?delete=<?php echo $i['id']; ?>" class="btn btn-sm btn-red"
                               onclick="return confirm('Delete this announcement?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
