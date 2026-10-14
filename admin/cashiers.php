<?php
// ============================================================
// admin/cashiers.php - Add, edit and remove cashier accounts.
//
// Who can open it: admin
//
// A cashier can reach the finance pages but nothing else.
// ============================================================
include '../config.php';

require_role('admin');

$has_cashiers = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'cashiers'")) > 0;
$DEFAULT_PASSWORD = 'cashier123';
$message = ""; $message_type = "";

// ---- Delete ----
if ($has_cashiers && isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM cashiers WHERE id = $id"));
    mysqli_query($conn, "DELETE FROM cashiers WHERE id = $id");
    if ($row && $row['user_id']) mysqli_query($conn, "DELETE FROM users WHERE id = " . (int)$row['user_id']);
    $message = "✅ Cashier account deleted."; $message_type = "success";
}

// ---- Activate / deactivate ----
if ($has_cashiers && isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    mysqli_query($conn, "UPDATE cashiers SET is_active = 1 - is_active WHERE id = $id");
    $message = "✅ Status updated."; $message_type = "success";
}

// ---- Add / edit ----
if ($has_cashiers && isset($_POST['save_cashier'])) {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address  = mysqli_real_escape_string($conn, trim($_POST['address']));
    $edit_id  = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $errors = [];

    if ($name === '')      $errors[] = "❌ Full name is required!";
    if ($username === '')  $errors[] = "❌ Username is required!";
    elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username))
        $errors[] = "❌ Username can only contain letters, numbers, dot, dash and underscore (3–50 characters)!";
    if ($edit_id === 0 && strlen($password) < 4)                   $errors[] = "❌ Password must be at least 4 characters!";
    if ($edit_id > 0 && $password !== '' && strlen($password) < 4) $errors[] = "❌ Password must be at least 4 characters!";
    if ($email !== '' && !filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL))
        $errors[] = "❌ Enter a valid email address!";
    if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', trim($_POST['phone'])))
        $errors[] = "❌ Enter a valid phone number, or leave it empty!";

    $username_esc = mysqli_real_escape_string($conn, $username);

    if (count($errors) == 0) {
        $dup = "SELECT id FROM users WHERE username = '$username_esc'";
        if ($edit_id) {
            $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM cashiers WHERE id = $edit_id"));
            if ($cur && $cur['user_id']) $dup .= " AND id != " . (int)$cur['user_id'];
        }
        if (mysqli_num_rows(mysqli_query($conn, $dup)) > 0)
            $errors[] = "❌ That username is already taken. Please choose another one.";
    }

    if (count($errors) == 0) {
        if ($edit_id > 0) {
            mysqli_query($conn, "UPDATE cashiers SET name='$name', phone='$phone', email='$email', address='$address' WHERE id=$edit_id");
            $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM cashiers WHERE id = $edit_id"));
            if ($cur && $cur['user_id']) {
                $uid = (int)$cur['user_id'];
                $set = "username='$username_esc'";
                if ($password !== '') $set .= ", password='" . mysqli_real_escape_string($conn, $password) . "'";
                mysqli_query($conn, "UPDATE users SET $set WHERE id = $uid");
            }
            $message = "✅ Cashier updated! Login username: <strong>" . htmlspecialchars($username ?? '') . "</strong>";
            $message_type = "success";
        } else {
            $pass_esc = mysqli_real_escape_string($conn, $password);
            mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('$username_esc', '$pass_esc', 'cashier')");
            $uid = mysqli_insert_id($conn);
            if ($uid) {
                mysqli_query($conn, "INSERT INTO cashiers (user_id, name, phone, email, address)
                                     VALUES ($uid, '$name', '$phone', '$email', '$address')");
                $message = "✅ Cashier account created! They log in with username <strong>" . htmlspecialchars($username ?? '') .
                           "</strong> and password <strong>" . htmlspecialchars($password ?? '') . "</strong>";
                $message_type = "success";
            } else {
                $message = "❌ Error: " . mysqli_error($conn); $message_type = "error";
            }
        }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

// ---- Data ----
$cashiers = [];
if ($has_cashiers) {
    $res = mysqli_query($conn,
        "SELECT c.*, u.username, u.password,
                (SELECT COUNT(*) FROM payments p WHERE p.received_by = c.user_id) AS payments_taken,
                (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.received_by = c.user_id) AS amount_taken
         FROM cashiers c LEFT JOIN users u ON c.user_id = u.id
         ORDER BY c.name");
    while ($r = mysqli_fetch_assoc($res)) $cashiers[] = $r;
}

$edit_cashier = null;
if ($has_cashiers && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_cashier = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT c.*, u.username FROM cashiers c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = $eid"));
}

include 'sidebar.php';
?>

<style>
    .card { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card h2 { margin-bottom: 18px; color: #333; font-size: 22px; }
    .howto {
        background: #e7f3fe; border-left: 5px solid #2196F3; color: #0d47a1;
        border-radius: 8px; padding: 18px 22px; margin-bottom: 25px; font-size: 15px; line-height: 1.9;
    }
    .howto .t { font-weight: bold; margin-bottom: 4px; }
    .howto ul { list-style: none; padding: 0; margin: 0; }
    .howto li::before { content: "• "; }
    .form-group { margin-bottom: 18px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
    label { display: block; margin-bottom: 7px; font-weight: bold; color: #333; font-size: 15px; }
    .req { color: red; }
    input, textarea { width: 100%; padding: 11px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; font-family: inherit; }
    textarea { min-height: 80px; resize: vertical; }
    input:focus, textarea:focus { outline: none; border-color: #4CAF50; }
    .hint { font-size: 13px; color: #777; margin-top: 5px; }
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
    .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .b-on  { background: #d4edda; color: #155724; }
    .b-off { background: #f8d7da; color: #721c24; }
    .empty { color: #999; text-align: center; padding: 30px; }
    @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; gap: 0; } }
</style>

<?php if (!$has_cashiers): ?>
    <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:10px;padding:20px;">
        <strong>⚠️ Cashier table not found.</strong><br>
        Import <strong>cashier_schema.sql</strong> in phpMyAdmin (database <strong>School_db1</strong>) first.
    </div>
<?php else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $edit_cashier ? '✏️ Edit Cashier' : '💵 Add New Cashier'; ?></h2>

    <div class="howto">
        <div class="t">📌 What a cashier can do:</div>
        <ul>
            <li>A login account is created <strong>AUTOMATICALLY</strong> when you add a cashier</li>
            <li>Username: <strong>What you enter below</strong></li>
            <li>Password: <strong>What you enter below (default: <?php echo $DEFAULT_PASSWORD; ?>)</strong></li>
            <li>✅ They CAN: collect fees, record payments, print receipts and invoices, set fees and discounts, view finance reports, and send fee reminders</li>
            <li>🚫 They CANNOT: see Expenses or Payroll (staff salaries), or manage Teachers, Students, Classes, Subjects or Parents</li>
        </ul>
    </div>

    <form method="POST" action="cashiers.php">
        <?php if ($edit_cashier): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_cashier['id']; ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label>Full Name: <span class="req">*</span></label>
                <input type="text" name="name" required
                       value="<?php echo $edit_cashier ? htmlspecialchars($edit_cashier['name'] ?? '') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Username (for login): <span class="req">*</span></label>
                <input type="text" name="username" required minlength="3" maxlength="50"
                       value="<?php echo $edit_cashier ? htmlspecialchars($edit_cashier['username'] ?? '') : ''; ?>">
                <div class="hint">Cashier will use this username to login</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Password: <?php if (!$edit_cashier): ?><span class="req">*</span><?php endif; ?></label>
                <input type="text" name="password" <?php echo $edit_cashier ? '' : 'required'; ?> minlength="4"
                       value="<?php echo $edit_cashier ? '' : $DEFAULT_PASSWORD; ?>"
                       placeholder="<?php echo $edit_cashier ? 'Leave empty to keep current password' : ''; ?>">
                <div class="hint">
                    <?php echo $edit_cashier ? 'Leave empty to keep the current password.' : 'Default password (give this to the cashier)'; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email"
                       value="<?php echo $edit_cashier ? htmlspecialchars($edit_cashier['email'] ?? '') : ''; ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Phone:</label>
                <input type="text" name="phone"
                       value="<?php echo $edit_cashier ? htmlspecialchars($edit_cashier['phone'] ?? '') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Address:</label>
                <textarea name="address"><?php echo $edit_cashier ? htmlspecialchars($edit_cashier['address'] ?? '') : ''; ?></textarea>
            </div>
        </div>

        <button type="submit" name="save_cashier"><?php echo $edit_cashier ? 'Update Cashier' : '💾 Add Cashier'; ?></button>
        <?php if ($edit_cashier): ?>
            <a href="cashiers.php" style="margin-left:10px;color:#666;text-decoration:none;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>💵 Cashier Accounts</h2>
    <?php if (count($cashiers) == 0): ?>
        <p class="empty">No cashier accounts yet. Add one above.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Cashier</th><th>Username</th><th>Password</th><th>Phone</th>
                    <th class="num">Payments Taken</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cashiers as $c): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($c['name'] ?? ''); ?></strong>
                            <?php if ($c['email']): ?><br><small style="color:#888;"><?php echo htmlspecialchars($c['email'] ?? ''); ?></small><?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($c['username'] ?: '-'); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($c['password'] ?: '-'); ?></code></td>
                        <td><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></td>
                        <td><?php echo (int)$c['payments_taken']; ?> payment(s)<br>
                            <small style="color:#2e7d32;">$<?php echo number_format((float)$c['amount_taken'], 2); ?></small></td>
                        <td>
                            <span class="badge <?php echo $c['is_active'] ? 'b-on' : 'b-off'; ?>">
                                <?php echo $c['is_active'] ? '✅ Active' : '🚫 Disabled'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="cashiers.php?toggle=<?php echo $c['id']; ?>" class="btn btn-sm btn-grey">
                                <?php echo $c['is_active'] ? 'Disable' : 'Enable'; ?>
                            </a>
                            <a href="cashiers.php?edit=<?php echo $c['id']; ?>" class="btn btn-sm btn-orange">Edit</a>
                            <a href="cashiers.php?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-red"
                               onclick="return confirm('Delete this cashier account and their login?')">Delete</a>
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
