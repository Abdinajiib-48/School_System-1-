<?php
// ============================================================
// admin/parents.php - Add, edit and remove parent accounts, and link each one to a child.
//
// Who can open it: admin
// ============================================================
include '../config.php';

require_role('admin');

$has_parents = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'parents'")) > 0
            && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'parent_students'")) > 0;

// Was parent_schema_v2.sql imported?
$has_occupation = false;
if ($has_parents) {
    $has_occupation = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM parents LIKE 'occupation'")) > 0;
}

$DEFAULT_PASSWORD = 'parent123';
$message = ""; $message_type = "";

// ---- Delete a parent (removes their login too) ----
if ($has_parents && isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM parents WHERE id = $id"));
    mysqli_query($conn, "DELETE FROM parents WHERE id = $id");
    if ($row && $row['user_id']) mysqli_query($conn, "DELETE FROM users WHERE id = " . (int)$row['user_id']);
    $message = "✅ Parent account deleted."; $message_type = "success";
}

// ---- Unlink one child ----
if ($has_parents && isset($_GET['unlink'])) {
    $lid = (int)$_GET['unlink'];
    mysqli_query($conn, "DELETE FROM parent_students WHERE id = $lid");
    $message = "✅ Child unlinked. That student can now be assigned to another parent."; $message_type = "success";
}

// ---- Add / edit a parent ----
if ($has_parents && isset($_POST['save_parent'])) {
    $name       = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username   = trim($_POST['username']);
    $password   = trim($_POST['password']);
    $email      = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone      = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $occupation = mysqli_real_escape_string($conn, trim($_POST['occupation']));
    $address    = mysqli_real_escape_string($conn, trim($_POST['address']));
    $edit_id    = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $errors = [];

    if ($name === '')                                     $errors[] = "❌ Full name is required!";
    if ($username === '')                                 $errors[] = "❌ Username is required!";
    elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username))
        $errors[] = "❌ Username can only contain letters, numbers, dot, dash and underscore (3–50 characters)!";
    if ($edit_id === 0 && strlen($password) < 4)          $errors[] = "❌ Password must be at least 4 characters!";
    if ($edit_id > 0 && $password !== '' && strlen($password) < 4)
        $errors[] = "❌ Password must be at least 4 characters!";
    if ($email !== '' && !filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL))
        $errors[] = "❌ Enter a valid email address!";
    if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', trim($_POST['phone'])))
        $errors[] = "❌ Enter a valid phone number, or leave it empty!";

    $username_esc = mysqli_real_escape_string($conn, $username);

    // Username must be unique across ALL logins
    if (count($errors) == 0) {
        $dup_u = "SELECT id FROM users WHERE username = '$username_esc'";
        if ($edit_id) {
            $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM parents WHERE id = $edit_id"));
            if ($cur && $cur['user_id']) $dup_u .= " AND id != " . (int)$cur['user_id'];
        }
        if (mysqli_num_rows(mysqli_query($conn, $dup_u)) > 0)
            $errors[] = "❌ That username is already taken. Please choose another one.";
    }

    if (count($errors) == 0) {
        $occ_col = $has_occupation ? ", occupation='$occupation'" : "";
        if ($edit_id > 0) {
            mysqli_query($conn, "UPDATE parents SET name='$name', phone='$phone', email='$email', address='$address' $occ_col WHERE id=$edit_id");
            $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM parents WHERE id = $edit_id"));
            if ($cur && $cur['user_id']) {
                $uid = (int)$cur['user_id'];
                $set = "username='$username_esc'";
                if ($password !== '') $set .= ", password='" . mysqli_real_escape_string($conn, $password) . "'";
                mysqli_query($conn, "UPDATE users SET $set WHERE id = $uid");
            }
            $message = "✅ Parent updated! Login username: <strong>" . htmlspecialchars($username ?? '') . "</strong>";
            $message_type = "success";
        } else {
            $pass_esc = mysqli_real_escape_string($conn, $password);
            mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('$username_esc', '$pass_esc', 'parent')");
            $uid = mysqli_insert_id($conn);
            if ($uid) {
                $occ_field = $has_occupation ? ", occupation" : "";
                $occ_value = $has_occupation ? ", '$occupation'" : "";
                mysqli_query($conn, "INSERT INTO parents (user_id, name, phone, email, address $occ_field)
                                     VALUES ($uid, '$name', '$phone', '$email', '$address' $occ_value)");
                $message = "✅ Parent account created! They log in with username <strong>" . htmlspecialchars($username ?? '') .
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

// ---- Link a child to a parent ----
if ($has_parents && isset($_POST['link_child'])) {
    $pid = (int)$_POST['parent_id'];
    $sid = (int)$_POST['student_id'];
    $rel = in_array($_POST['relationship'], ['Father','Mother','Guardian']) ? $_POST['relationship'] : 'Guardian';

    if ($pid <= 0 || $sid <= 0) {
        $message = "❌ Please choose both a parent and a child!"; $message_type = "error";
    } else {
        // A student may belong to only ONE parent
        $taken = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT p.name FROM parent_students ps JOIN parents p ON ps.parent_id = p.id
             WHERE ps.student_id = $sid"));
        if ($taken) {
            $message = "❌ That student is already linked to <strong>" . htmlspecialchars($taken['name'] ?? '') .
                       "</strong>. Unlink them first if you want to move them.";
            $message_type = "error";
        } else {
            mysqli_query($conn, "INSERT INTO parent_students (parent_id, student_id, relationship) VALUES ($pid, $sid, '$rel')");
            $message = "✅ Child linked to parent!"; $message_type = "success";
        }
    }
}

// ---- Data ----
$parents = []; $students = []; $links = []; $taken_students = [];
if ($has_parents) {
    $occ_sel = $has_occupation ? "p.occupation," : "'' AS occupation,";
    $res = mysqli_query($conn,
        "SELECT p.*, $occ_sel u.username, u.password,
                (SELECT COUNT(*) FROM parent_students ps WHERE ps.parent_id = p.id) AS child_count
         FROM parents p LEFT JOIN users u ON p.user_id = u.id
         ORDER BY p.name");
    while ($r = mysqli_fetch_assoc($res)) $parents[] = $r;

    // Which students already have a parent?
    $t_res = mysqli_query($conn,
        "SELECT ps.student_id, p.name AS parent_name
         FROM parent_students ps JOIN parents p ON ps.parent_id = p.id");
    while ($r = mysqli_fetch_assoc($t_res)) $taken_students[(int)$r['student_id']] = $r['parent_name'];

    $s_res = mysqli_query($conn,
        "SELECT s.id, s.student_id, s.name, c.grade
         FROM students s LEFT JOIN classes c ON s.class_id = c.id
         ORDER BY c.grade, s.name");
    while ($r = mysqli_fetch_assoc($s_res)) $students[] = $r;

    $l_res = mysqli_query($conn,
        "SELECT ps.*, s.name AS student_name, s.student_id AS student_code, c.grade
         FROM parent_students ps
         JOIN students s ON ps.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         ORDER BY ps.parent_id, s.name");
    while ($r = mysqli_fetch_assoc($l_res)) $links[(int)$r['parent_id']][] = $r;
}

$edit_parent = null;
if ($has_parents && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $occ_sel = $has_occupation ? "p.occupation," : "'' AS occupation,";
    $edit_parent = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT p.*, $occ_sel u.username FROM parents p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = $eid"));
}

$free_students = 0;
foreach ($students as $s) if (!isset($taken_students[(int)$s['id']])) $free_students++;

include 'sidebar.php';
?>

<style>
    .card { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card h2 { margin-bottom: 18px; color: #333; font-size: 22px; }

    /* Blue "How it works" box */
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
    input, select, textarea {
        width: 100%; padding: 11px; border: 1px solid #ccc; border-radius: 6px;
        font-size: 14px; font-family: inherit;
    }
    textarea { min-height: 80px; resize: vertical; }
    input:focus, select:focus, textarea:focus { outline: none; border-color: #4CAF50; }
    .hint { font-size: 13px; color: #777; margin-top: 5px; }

    button, .btn { background: #4CAF50; color: white; padding: 11px 22px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; }
    button:hover, .btn:hover { background: #45a049; }
    .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 5px; }
    .btn-orange { background: #ff9800; } .btn-orange:hover { background: #f57c00; }
    .btn-red { background: #f44336; } .btn-red:hover { background: #d32f2f; }
    .btn-grey { background: #9e9e9e; } .btn-grey:hover { background: #757575; }

    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
    th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; vertical-align: top; }
    th { background: #4CAF50; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }

    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    .kid { display: inline-block; background: #e3f2fd; color: #1565c0; padding: 3px 9px; border-radius: 12px; font-size: 12px; margin: 2px 3px 2px 0; }
    .kid a { color: #c62828; text-decoration: none; font-weight: bold; margin-left: 5px; }
    .empty { color: #999; text-align: center; padding: 30px; }
    option:disabled { color: #bbb; }

    @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; gap: 0; } }
</style>

<?php if (!$has_parents): ?>
    <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:10px;padding:20px;">
        <strong>⚠️ Parent tables not found.</strong><br>
        Import <strong>parent_schema.sql</strong> in phpMyAdmin (database <strong>School_db1</strong>) first.
    </div>
<?php else: ?>

<?php if (!$has_occupation): ?>
    <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:10px;padding:15px;margin-bottom:20px;">
        <strong>⚠️ Import <code>parent_schema_v2.sql</code></strong> to enable the Occupation field and the
        "one parent per student" rule.
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $edit_parent ? '✏️ Edit Parent' : '👤 Add New Parent'; ?></h2>

    <div class="howto">
        <div class="t">📌 How it works:</div>
        <ul>
            <li>When you add a parent, a login account is created AUTOMATICALLY</li>
            <li>Username: <strong>What you enter below</strong></li>
            <li>Password: <strong>What you enter below (default: <?php echo $DEFAULT_PASSWORD; ?>)</strong></li>
            <li>The parent will use these credentials to login to their dashboard</li>
            <li>⚠️ <strong>Important:</strong> A student can only be linked to ONE parent. Already assigned students will appear disabled.</li>
        </ul>
    </div>

    <form method="POST" action="parents.php">
        <?php if ($edit_parent): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_parent['id']; ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label>Full Name: <span class="req">*</span></label>
                <input type="text" name="name" required
                       value="<?php echo $edit_parent ? htmlspecialchars($edit_parent['name'] ?? '') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Username (for login): <span class="req">*</span></label>
                <input type="text" name="username" required minlength="3" maxlength="50"
                       value="<?php echo $edit_parent ? htmlspecialchars($edit_parent['username'] ?? '') : ''; ?>">
                <div class="hint">Parent will use this username to login</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Password: <?php if (!$edit_parent): ?><span class="req">*</span><?php endif; ?></label>
                <input type="text" name="password" <?php echo $edit_parent ? '' : 'required'; ?> minlength="4"
                       value="<?php echo $edit_parent ? '' : $DEFAULT_PASSWORD; ?>"
                       placeholder="<?php echo $edit_parent ? 'Leave empty to keep current password' : ''; ?>">
                <div class="hint">
                    <?php echo $edit_parent
                        ? 'Leave empty to keep the current password.'
                        : 'Default password (parent can change after login)'; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email"
                       value="<?php echo $edit_parent ? htmlspecialchars($edit_parent['email'] ?? '') : ''; ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Phone:</label>
                <input type="text" name="phone"
                       value="<?php echo $edit_parent ? htmlspecialchars($edit_parent['phone'] ?? '') : ''; ?>">
            </div>
            <div class="form-group">
                <label>Occupation:</label>
                <input type="text" name="occupation" <?php echo $has_occupation ? '' : 'disabled'; ?>
                       value="<?php echo $edit_parent ? htmlspecialchars($edit_parent['occupation'] ?? '') : ''; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Address:</label>
            <textarea name="address"><?php echo $edit_parent ? htmlspecialchars($edit_parent['address'] ?? '') : ''; ?></textarea>
        </div>

        <button type="submit" name="save_parent"><?php echo $edit_parent ? 'Update Parent' : '💾 Add Parent'; ?></button>
        <?php if ($edit_parent): ?>
            <a href="parents.php" style="margin-left:10px;color:#666;text-decoration:none;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>🔗 Link a Child to a Parent</h2>
    <?php if (count($parents) == 0): ?>
        <p class="empty">Create a parent account first.</p>
    <?php else: ?>
        <form method="POST" action="parents.php">
            <div class="form-row">
                <div class="form-group">
                    <label>Parent: <span class="req">*</span></label>
                    <select name="parent_id" required>
                        <option value="">-- Select Parent --</option>
                        <?php foreach ($parents as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo htmlspecialchars($p['name'] ?? ''); ?> (<?php echo htmlspecialchars($p['username'] ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Child: <span class="req">*</span></label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $s):
                            $sid   = (int)$s['id'];
                            $taken = isset($taken_students[$sid]); ?>
                            <option value="<?php echo $sid; ?>" <?php echo $taken ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($s['student_id'] ?? ''); ?> - <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                                (<?php echo htmlspecialchars($s['grade'] ?: 'No class'); ?>)
                                <?php echo $taken ? ' — already with ' . htmlspecialchars($taken_students[$sid] ?? '') : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">
                        <?php echo $free_students; ?> of <?php echo count($students); ?> students still available.
                        Greyed-out students already have a parent.
                    </div>
                </div>
            </div>

            <div class="form-group" style="max-width:340px;">
                <label>Relationship:</label>
                <select name="relationship">
                    <option value="Father">Father</option>
                    <option value="Mother">Mother</option>
                    <option value="Guardian" selected>Guardian</option>
                </select>
            </div>

            <button type="submit" name="link_child" <?php echo $free_students == 0 ? 'disabled style="background:#bbb;cursor:not-allowed;"' : ''; ?>>🔗 Link Child</button>
            <?php if ($free_students == 0): ?>
                <span class="hint" style="margin-left:10px;">Every student already has a parent.</span>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>👨‍👩‍👧 Parent Accounts</h2>
    <?php if (count($parents) == 0): ?>
        <p class="empty">No parent accounts yet. Add one above.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Parent</th><th>Username</th><th>Password</th>
                    <th>Phone</th><th>Occupation</th><th>Children</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parents as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['name'] ?? ''); ?></strong>
                            <?php if ($p['email']): ?><br><small style="color:#888;"><?php echo htmlspecialchars($p['email'] ?? ''); ?></small><?php endif; ?>
                            <?php if ($p['address']): ?><br><small style="color:#888;"><?php echo htmlspecialchars($p['address'] ?? ''); ?></small><?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($p['username'] ?: '-'); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($p['password'] ?: '-'); ?></code></td>
                        <td><?php echo htmlspecialchars($p['phone'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($p['occupation'] ?: '-'); ?></td>
                        <td>
                            <?php if (!empty($links[(int)$p['id']])): ?>
                                <?php foreach ($links[(int)$p['id']] as $l): ?>
                                    <span class="kid">
                                        <?php echo htmlspecialchars($l['student_name'] ?? ''); ?>
                                        (<?php echo htmlspecialchars($l['grade'] ?: '-'); ?>, <?php echo htmlspecialchars($l['relationship'] ?? ''); ?>)
                                        <a href="parents.php?unlink=<?php echo $l['id']; ?>"
                                           onclick="return confirm('Unlink this child?')" title="Unlink">✕</a>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color:#999;">No child linked</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="parents.php?edit=<?php echo $p['id']; ?>" class="btn btn-sm btn-orange">Edit</a>
                            <a href="parents.php?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-red"
                               onclick="return confirm('Delete this parent account and their login?')">Delete</a>
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
