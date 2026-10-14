<?php
// ============================================================
// teacher/account.php - The teacher's own profile and password.
//
// Who can open it: teacher
// ============================================================
include '../config.php';

require_role('teacher');

$user_id = (int)$_SESSION['user_id'];
$teacher = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT t.*, d.name AS dept, s.name AS subject_name
     FROM teachers t
     LEFT JOIN departments d ON t.department_id = d.id
     LEFT JOIN subjects s ON t.subject_id = s.id
     WHERE t.user_id = $user_id"));

$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));

$message = ""; $message_type = "";

if (isset($_POST['change_password'])) {
    $current = trim($_POST['current_password']);
    $new     = trim($_POST['new_password']);
    $confirm = trim($_POST['confirm_password']);
    $errors  = [];

    if ($current === '')                          $errors[] = "❌ Please enter your current password!";
    elseif ($current !== $me['password'])         $errors[] = "❌ Your current password is not correct!";
    if (strlen($new) < 4)                         $errors[] = "❌ New password must be at least 4 characters!";
    if ($new !== $confirm)                        $errors[] = "❌ The two new passwords do not match!";
    if ($new !== '' && $new === $me['password'])  $errors[] = "❌ The new password is the same as the old one!";

    if (count($errors) == 0) {
        $esc = mysqli_real_escape_string($conn, $new);
        if (mysqli_query($conn, "UPDATE users SET password = '$esc' WHERE id = $user_id")) {
            $message = "✅ Your password has been changed. Use it the next time you log in.";
            $message_type = "success";
            $me['password'] = $new;
        } else {
            $message = "❌ Error: " . mysqli_error($conn); $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

// Classes this teacher takes
$my_classes = [];
$cr = mysqli_query($conn,
    "SELECT c.grade, c.academic_year, c.semester, 'Homeroom Teacher' AS role
     FROM classes c WHERE c.homeroom_teacher_id = " . (int)$teacher['id'] . "
     UNION ALL
     SELECT c.grade, c.academic_year, c.semester, 'Subject Teacher'
     FROM class_teachers ct JOIN classes c ON ct.class_id = c.id
     WHERE ct.teacher_id = " . (int)$teacher['id'] . "
     ORDER BY 2, 1");
if ($cr) { while ($r = mysqli_fetch_assoc($cr)) $my_classes[] = $r; }

include 'sidebar.php';
?>

<style>
    .card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card h2 { font-size: 19px; color: #333; margin-bottom: 15px; padding-left: 12px; border-left: 4px solid #4CAF50; }
    .msg { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; }
    .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .msg.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .info-row { display: flex; justify-content: space-between; gap: 12px; padding: 11px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
    .info-row:last-child { border-bottom: none; }
    .info-row span:first-child { color: #666; }
    .info-row span:last-child  { font-weight: 600; color: #333; text-align: right; }
    .form-group { margin-bottom: 18px; }
    label { display: block; margin-bottom: 7px; font-weight: bold; color: #333; font-size: 15px; }
    input { width: 100%; padding: 11px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
    input:focus { outline: none; border-color: #4CAF50; }
    .hint { font-size: 13px; color: #777; margin-top: 5px; }
    .narrow { max-width: 460px; }
    .btn { background: #4CAF50; color: white; padding: 11px 22px; border: none; border-radius: 8px;
           cursor: pointer; font-size: 14px; font-weight: 600; }
    .btn:hover { background: #45a049; }
    .note { background: #e7f3fe; border-left: 5px solid #2196F3; color: #0d47a1;
            border-radius: 8px; padding: 14px 18px; font-size: 14px; line-height: 1.7; margin-top: 15px; }
    .tag { display: inline-block; background: #e3f2fd; color: #1565c0; padding: 2px 9px;
           border-radius: 10px; font-size: 12px; margin: 2px 3px 2px 0; }
</style>

<?php if ($message): ?>
    <div class="msg <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2>👤 My Account</h2>
    <div class="info-row"><span>Name</span><span><?php echo htmlspecialchars($teacher['name'] ?? ''); ?></span></div>
    <div class="info-row"><span>Login username</span><span><?php echo htmlspecialchars($me['username'] ?? ''); ?></span></div>
    <div class="info-row"><span>Department</span><span><?php echo htmlspecialchars($teacher['dept'] ?: '—'); ?></span></div>
    <div class="info-row"><span>Subject I teach</span><span><?php echo htmlspecialchars($teacher['subject_name'] ?: '—'); ?></span></div>
    <?php if ($teacher['email']): ?>
        <div class="info-row"><span>Email</span><span><?php echo htmlspecialchars($teacher['email'] ?? ''); ?></span></div>
    <?php endif; ?>
    <?php if ($teacher['phone']): ?>
        <div class="info-row"><span>Phone</span><span><?php echo htmlspecialchars($teacher['phone'] ?? ''); ?></span></div>
    <?php endif; ?>
    <div class="info-row">
        <span>My classes</span>
        <span>
            <?php if (count($my_classes) === 0): ?>—<?php else: ?>
                <?php foreach ($my_classes as $c): ?>
                    <span class="tag"><?php echo htmlspecialchars($c['grade'] ?? ''); ?>
                        (<?php echo htmlspecialchars($c['academic_year'] ?? ''); ?> · S<?php echo htmlspecialchars($c['semester'] ?? ''); ?>)
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </span>
    </div>
    <p class="hint" style="margin-top:14px;">To change your name, subject or classes, please contact the admin.</p>
</div>

<div class="card">
    <h2>🔒 Change My Password</h2>
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

    <div class="note">
        ℹ️ If you forget your password, ask the school admin — they can see it and can reset it for you.
    </div>
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
