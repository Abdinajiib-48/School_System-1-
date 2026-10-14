<?php
// ============================================================
// parent/account.php - The parent's own details and password.
//
// Who can open it: parent
// ============================================================
include '../config.php';
include 'parent_common.php';

$message = ""; $message_type = "";

// Current login row
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = " . (int)$_SESSION['user_id']));

if (isset($_POST['change_password'])) {
    $current = trim($_POST['current_password']);
    $new     = trim($_POST['new_password']);
    $confirm = trim($_POST['confirm_password']);
    $errors  = [];

    if ($current === '')            $errors[] = "❌ Please enter your current password!";
    elseif ($current !== $me['password']) $errors[] = "❌ Your current password is not correct!";
    if (strlen($new) < 4)           $errors[] = "❌ New password must be at least 4 characters!";
    if ($new !== $confirm)          $errors[] = "❌ The two new passwords do not match!";
    if ($new !== '' && $new === $me['password']) $errors[] = "❌ The new password is the same as the old one!";

    if (count($errors) == 0) {
        $esc = mysqli_real_escape_string($conn, $new);
        if (mysqli_query($conn, "UPDATE users SET password = '$esc' WHERE id = " . (int)$me['id'])) {
            $message = "✅ Your password has been changed. Please use it next time you log in.";
            $message_type = "success";
            $me['password'] = $new;
        } else {
            $message = "❌ Error: " . mysqli_error($conn); $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

include 'sidebar.php';
?>

<style>
    .msg { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; }
    .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .msg.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .form-group { margin-bottom: 18px; }
    label { display: block; margin-bottom: 7px; font-weight: bold; color: #333; font-size: 15px; }
    input { width: 100%; padding: 11px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
    input:focus { outline: none; border-color: #4CAF50; }
    .hint { font-size: 13px; color: #777; margin-top: 5px; }
    .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
    .info-row span:first-child { color: #666; }
    .info-row span:last-child  { font-weight: 600; color: #333; }
    .narrow { max-width: 460px; }
</style>

<?php if ($message): ?>
    <div class="msg <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="section-title">👤 My Account</h2>
    <div class="info-row"><span>Name</span><span><?php echo htmlspecialchars($parent['name'] ?? ''); ?></span></div>
    <div class="info-row"><span>Login username</span><span><?php echo htmlspecialchars($me['username'] ?? ''); ?></span></div>
    <?php if (!empty($parent['phone'])): ?>
        <div class="info-row"><span>Phone</span><span><?php echo htmlspecialchars($parent['phone'] ?? ''); ?></span></div>
    <?php endif; ?>
    <?php if (!empty($parent['email'])): ?>
        <div class="info-row"><span>Email</span><span><?php echo htmlspecialchars($parent['email'] ?? ''); ?></span></div>
    <?php endif; ?>
    <div class="info-row">
        <span>My children</span>
        <span>
            <?php
            if (count($children) == 0) echo '—';
            else {
                $names = [];
                foreach ($children as $c) $names[] = htmlspecialchars($c['name'] ?? '') . ' (' . htmlspecialchars($c['grade'] ?: '-') . ')';
                echo implode(', ', $names);
            }
            ?>
        </span>
    </div>
    <p class="hint" style="margin-top:14px;">
        To change your name, phone or email, please contact the school office.
    </p>
</div>

<div class="card">
    <h2 class="section-title">🔒 Change My Password</h2>
    <form method="POST" action="account.php" class="narrow" onsubmit="return checkPw()">
        <div class="form-group">
            <label>Current Password:</label>
            <input type="password" name="current_password" id="cur" required>
        </div>
        <div class="form-group">
            <label>New Password:</label>
            <input type="password" name="new_password" id="np" required minlength="4">
            <div class="hint">At least 4 characters.</div>
        </div>
        <div class="form-group">
            <label>Confirm New Password:</label>
            <input type="password" name="confirm_password" id="cp" required minlength="4">
        </div>
        <button type="submit" name="change_password" class="btn">💾 Change Password</button>
    </form>
</div>

<script>
    function checkPw() {
        var np = document.getElementById('np').value;
        var cp = document.getElementById('cp').value;
        if (np.length < 4) { alert('New password must be at least 4 characters!'); return false; }
        if (np !== cp)     { alert('The two new passwords do not match!'); return false; }
        return true;
    }
</script>

<?php include 'footer.php'; ?>
