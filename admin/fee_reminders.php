<?php
// ============================================================
// admin/fee_reminders.php - Send fee reminders to the parents of students who still owe.
//
// Who can open it: admin, cashier
// ============================================================
include '../config.php';
include 'finance_common.php';

include_once '../announcements_helper.php';

$has_reminders = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'fee_reminders'")) > 0;
$has_ann       = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'announcements'")) > 0;

$message = ""; $message_type = "";

// ---- Who still owes? ----
$owing = []; $total_owed = 0; $students_total = 0;
if ($has_finance) {
    if ($has_finance_v2) {
        $res = mysqli_query($conn, fee_select_sql("ORDER BY c.grade, s.name"));
    } else {
        $res = mysqli_query($conn,
            "SELECT s.id, s.student_id, s.name, s.class_id, c.grade, s.parent_phone,
                    COALESCE(f.amount, 0) AS fee, NULL AS discount_type, 0 AS discount_value,
                    COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS paid
             FROM students s
             LEFT JOIN classes c ON s.class_id = c.id
             LEFT JOIN fee_structure f ON f.class_id = c.id AND f.academic_year = c.academic_year
             ORDER BY c.grade, s.name");
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $row = fee_row_calc($row);
        $students_total++;
        if ($row['balance'] > 0.001) {
            // who is the parent?
            $par = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT p.name, p.phone FROM parent_students ps
                 JOIN parents p ON ps.parent_id = p.id
                 WHERE ps.student_id = " . (int)$row['id']));
            $row['parent_name']  = $par ? $par['name']  : null;
            $row['parent_phone'] = $par ? $par['phone'] : null;
            $owing[] = $row;
            $total_owed += $row['balance'];
        }
    }
    usort($owing, function ($a, $b) { return $b['balance'] <=> $a['balance']; });
}

// ---- Send a reminder ----
if ($has_ann && isset($_POST['send_reminder'])) {
    $title    = mysqli_real_escape_string($conn, trim($_POST['title']));
    $body     = mysqli_real_escape_string($conn, trim($_POST['body']));
    $due_date = trim($_POST['due_date']);
    $to_par   = isset($_POST['to_parents']);
    $to_stu   = isset($_POST['to_students']);
    $pin      = isset($_POST['is_pinned']) ? 1 : 0;
    $errors = [];

    if ($title === '')          $errors[] = "❌ Title is required!";
    if ($body === '')           $errors[] = "❌ Message is required!";
    if (!$to_par && !$to_stu)   $errors[] = "❌ Choose at least one audience (parents or students)!";
    if ($due_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) $errors[] = "❌ Invalid due date!";
    if (count($owing) === 0)    $errors[] = "❌ Nobody has an outstanding balance right now.";

    if (count($errors) == 0) {
        // Both ticked -> one announcement for everyone, otherwise target one group
        $audience = ($to_par && $to_stu) ? 'All' : ($to_par ? 'Parents' : 'Students');
        $date_sql = $due_date === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $due_date) . "'";
        $uid      = (int)$_SESSION['user_id'];

        $ok = mysqli_query($conn,
            "INSERT INTO announcements (title, body, type, audience, event_date, is_pinned, posted_by)
             VALUES ('$title', '$body', 'News', '$audience', $date_sql, $pin, $uid)");

        if ($ok) {
            $ann_id = mysqli_insert_id($conn);
            if ($has_reminders) {
                $cnt = count($owing); $tot = (float)$total_owed;
                mysqli_query($conn,
                    "INSERT INTO fee_reminders (announcement_id, students_owing, total_owed, due_date, audience, sent_by)
                     VALUES ($ann_id, $cnt, $tot, $date_sql, '$audience', $uid)");
            }
            $who = ($to_par && $to_stu) ? 'parents and students' : ($to_par ? 'parents' : 'students');
            $message = "✅ Fee reminder sent to <strong>$who</strong>. It now appears in their portal.";
            $message_type = "success";
        } else {
            $message = "❌ Error: " . mysqli_error($conn); $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

// ---- History ----
$history = [];
if ($has_reminders) {
    $h = mysqli_query($conn,
        "SELECT r.*, a.title, a.audience AS ann_audience, u.username AS sent_by_name
         FROM fee_reminders r
         LEFT JOIN announcements a ON r.announcement_id = a.id
         LEFT JOIN users u ON r.sent_by = u.id
         ORDER BY r.created_at DESC LIMIT 50");
    while ($row = mysqli_fetch_assoc($h)) $history[] = $row;
}

// Default text for the form
$default_due   = date('Y-m-d', strtotime('+14 days'));
$default_title = 'School Fee Reminder';
$default_body  = "Dear parents and students,\n\n"
               . "This is a friendly reminder that some school fees are still outstanding.\n\n"
               . "Please check your fee status in your portal under \"School Fees\", and settle any "
               . "remaining balance at the school finance office before the date shown above.\n\n"
               . "If you have already paid in full, please ignore this message.\n\n"
               . "Thank you,\nSchool Finance Office";

include 'sidebar.php';
render_finance_styles();
render_finance_tabs('fee_reminders.php');
?>

<style>
    .mini-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px,1fr)); gap: 18px; margin-bottom: 25px; }
    .mini { background: white; padding: 18px; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #ff9800; }
    .mini .lbl { color: #666; font-size: 13px; }
    .mini .val { font-size: 25px; font-weight: bold; color: #333; margin-top: 5px; }
    .privacy { background: #e7f3fe; border-left: 5px solid #2196F3; color: #0d47a1;
               border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; font-size: 14px; line-height: 1.7; }
    .check-row { display: flex; align-items: center; gap: 9px; margin-bottom: 8px; }
    .check-row input { width: auto; }
    .check-row label { margin: 0; font-weight: normal; }
    textarea { min-height: 170px; }
</style>

<?php if (!$has_finance): render_finance_missing(); else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="mini-stats">
    <div class="mini">
        <div class="lbl">🔴 Students Who Still Owe</div>
        <div class="val"><?php echo count($owing); ?> <span style="font-size:15px;color:#999;">of <?php echo $students_total; ?></span></div>
    </div>
    <div class="mini">
        <div class="lbl">💸 Total Outstanding</div>
        <div class="val"><?php echo money($total_owed); ?></div>
    </div>
    <div class="mini" style="border-color:#4CAF50;">
        <div class="lbl">✅ Fully Paid</div>
        <div class="val"><?php echo $students_total - count($owing); ?></div>
    </div>
    <div class="mini" style="border-color:#2196F3;">
        <div class="lbl">🔔 Reminders Sent</div>
        <div class="val"><?php echo count($history); ?></div>
    </div>
</div>

<div class="two-col">
    <!-- ============ SEND A REMINDER ============ -->
    <div class="card">
        <h2>🔔 Send a Fee Reminder</h2>

        <div class="privacy">
            <strong>ℹ️ How this works:</strong><br>
            This posts one announcement to the portals of the people you tick.
            Each family then sees <strong>their own balance</strong> on their School Fees page.<br>
            <strong>No student names or amounts are published</strong> — everyone sees the same general message,
            so nobody is named in public.
        </div>

        <?php if (count($owing) === 0): ?>
            <p class="empty">🎉 Everyone has paid in full. There is nobody to remind.</p>
        <?php else: ?>
        <form method="POST" action="fee_reminders.php">
            <div class="form-group">
                <label>Title: <span style="color:red;">*</span></label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($default_title ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Message: <span style="color:red;">*</span></label>
                <textarea name="body" required><?php echo htmlspecialchars($default_body ?? ''); ?></textarea>
                <div class="hint">You can edit this text before sending.</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Pay Before (optional):</label>
                    <input type="date" name="due_date" value="<?php echo $default_due; ?>">
                    <div class="hint">Shown as the date on the announcement.</div>
                </div>
                <div class="form-group">
                    <label>Send To: <span style="color:red;">*</span></label>
                    <div class="check-row">
                        <input type="checkbox" name="to_parents" id="to_parents" value="1" checked>
                        <label for="to_parents">👪 Parents</label>
                    </div>
                    <div class="check-row">
                        <input type="checkbox" name="to_students" id="to_students" value="1" checked>
                        <label for="to_students">👨‍🎓 Students</label>
                    </div>
                    <div class="check-row">
                        <input type="checkbox" name="is_pinned" id="is_pinned" value="1" checked>
                        <label for="is_pinned">📌 Pin to the top</label>
                    </div>
                </div>
            </div>

            <button type="submit" name="send_reminder"
                    onclick="return confirm('Send this fee reminder now? It will appear in their portals straight away.')">
                🔔 Send Reminder
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- ============ HISTORY ============ -->
    <div class="card">
        <h2>📜 Reminders Already Sent</h2>
        <?php if (!$has_reminders): ?>
            <p class="empty">Import <strong>cashier_schema.sql</strong> to keep a history of reminders.</p>
        <?php elseif (count($history) === 0): ?>
            <p class="empty">No reminders sent yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Sent</th><th>Title</th><th>To</th><th class="num">Owing</th><th class="num">Amount</th><th>By</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($h['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($h['title'] ?? '(announcement deleted)'); ?></td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($h['audience'] ?? ''); ?></span></td>
                            <td class="num"><?php echo (int)$h['students_owing']; ?></td>
                            <td class="num"><?php echo money($h['total_owed']); ?></td>
                            <td><?php echo htmlspecialchars($h['sent_by_name'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- ============ WHO OWES (internal only) ============ -->
<div class="card">
    <h2>🔴 Students With an Outstanding Balance</h2>
    <p style="color:#888;font-size:13px;margin-bottom:6px;">
        🔒 This list is for staff only — it is never shown to students or parents.
    </p>
    <?php if (count($owing) === 0): ?>
        <p class="empty">🎉 Everyone has paid in full.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Student</th><th>Class</th><th>Parent</th><th>Contact</th>
                    <th class="num">Payable</th><th class="num">Paid</th><th class="num">Balance</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($owing as $o): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($o['name'] ?? ''); ?></strong><br>
                            <small style="color:#888;"><?php echo htmlspecialchars($o['student_id'] ?? ''); ?></small></td>
                        <td><?php echo htmlspecialchars($o['grade'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($o['parent_name'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($o['parent_phone'] ?: ($o['parent_phone'] ?: '—')); ?></td>
                        <td class="num"><?php echo money($o['payable']); ?>
                            <?php if ($o['discount'] > 0): ?><br><small style="color:#9c27b0;">-<?php echo money($o['discount']); ?> off</small><?php endif; ?>
                        </td>
                        <td class="num"><?php echo money($o['paid']); ?></td>
                        <td class="num" style="color:#f44336;"><strong><?php echo money($o['balance']); ?></strong></td>
                        <td>
                            <a href="payments.php?student_id=<?php echo $o['id']; ?>" class="btn btn-sm">+ Pay</a>
                            <a href="invoice.php?student_id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-sm btn-grey">🧾</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#fdf1f1;">
                    <td colspan="6"><strong>TOTAL OUTSTANDING</strong></td>
                    <td class="num" style="color:#f44336;"><strong><?php echo money($total_owed); ?></strong></td>
                    <td></td>
                </tr>
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
