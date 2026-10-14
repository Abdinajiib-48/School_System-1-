<?php
// ============================================================
// admin/teachers.php - Add, edit and remove teachers; their subjects, classes and logins.
//
// Who can open it: admin
//
// A teacher may take several subjects and several classes - those links
// live in teacher_subjects and class_teachers, not in the teachers row.
// ============================================================
include '../config.php';
include_once '../subjects_helper.php';

// Save which classes this teacher will teach.
// Stored in class_teachers - the same table the Classes page uses.
function sync_teacher_classes($conn, $teacher_id, $class_ids) {
    if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'class_teachers'")) == 0) return;
    $teacher_id = (int)$teacher_id;
    mysqli_query($conn, "DELETE FROM class_teachers WHERE teacher_id = $teacher_id");
    foreach ($class_ids as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) {
            mysqli_query($conn, "INSERT IGNORE INTO class_teachers (class_id, teacher_id) VALUES ($cid, $teacher_id)");
        }
    }
}

require_role('admin');

// Messages shown at the top of the page
$message = "";
$message_type = "";

// ==================================================================
// Delete a teacher
//
// The database refuses a plain DELETE while the teacher is still
// attached to other records:
//   classes.homeroom_teacher_id  -> ON DELETE RESTRICT
//   marks.entered_by             -> ON DELETE RESTRICT
// So we detach those first. Nothing important is thrown away:
// the class stays (it just loses its homeroom teacher) and the
// students' marks stay (only the "entered by" note is cleared).
// ==================================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $teacher = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, user_id FROM teachers WHERE id = $id"));

    if (!$teacher) {
        $message = "❌ That teacher no longer exists.";
        $message_type = "error";
    } else {
        $tname = htmlspecialchars($teacher['name']);
        mysqli_begin_transaction($conn);
        try {
            $did = [];

            // 1. Classes they lead keep existing, but lose their homeroom teacher
            $freed = [];
            $fr = mysqli_query($conn, "SELECT grade FROM classes WHERE homeroom_teacher_id = $id");
            while ($r = mysqli_fetch_assoc($fr)) $freed[] = $r['grade'];
            if ($freed) {
                mysqli_query($conn, "UPDATE classes SET homeroom_teacher_id = NULL WHERE homeroom_teacher_id = $id");
                $did[] = "class <strong>" . htmlspecialchars(implode(', ', $freed)) . "</strong> now has no homeroom teacher";
            }

            // 2. Marks they entered are KEPT - only the "entered by" note is cleared
            $mn = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM marks WHERE entered_by = $id"));
            if ((int)$mn['n'] > 0) {
                mysqli_query($conn, "UPDATE marks SET entered_by = NULL WHERE entered_by = $id");
                $did[] = "<strong>" . (int)$mn['n'] . "</strong> student mark(s) kept";
            }

            // 3. Attendance they took is kept the same way
            if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'attendance'")) > 0) {
                $an = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM attendance WHERE marked_by = $id"));
                if ((int)$an['n'] > 0) {
                    mysqli_query($conn, "UPDATE attendance SET marked_by = NULL WHERE marked_by = $id");
                    $did[] = "<strong>" . (int)$an['n'] . "</strong> attendance record(s) kept";
                }
            }

            // 4. Their lessons come off the timetable (a lesson needs a teacher)
            if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'timetable'")) > 0) {
                $tn = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM timetable WHERE teacher_id = $id"));
                if ((int)$tn['n'] > 0) {
                    mysqli_query($conn, "DELETE FROM timetable WHERE teacher_id = $id");
                    $did[] = "<strong>" . (int)$tn['n'] . "</strong> timetable lesson(s) removed";
                }
            }

            // 5. Class links
            if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'class_teachers'")) > 0) {
                mysqli_query($conn, "DELETE FROM class_teachers WHERE teacher_id = $id");
            }

            // 6. Finally the teacher and their login
            if ($teacher['user_id']) {
                mysqli_query($conn, "DELETE FROM users WHERE id = " . (int)$teacher['user_id']);
            }
            mysqli_query($conn, "DELETE FROM teachers WHERE id = $id");

            mysqli_commit($conn);

            $message = "✅ Teacher <strong>$tname</strong> deleted."
                     . ($did ? "<br><small>" . implode(' · ', $did) . "</small>" : "");
            if ($freed) {
                $message .= "<br><small>👉 Remember to give class <strong>"
                          . htmlspecialchars(implode(', ', $freed))
                          . "</strong> a new homeroom teacher in <strong>Classes</strong>.</small>";
            }
            $message_type = "success";

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = "❌ Could not delete <strong>$tname</strong>.<br>"
                     . "<small>The database is still using this teacher somewhere: "
                     . htmlspecialchars($e->getMessage()) . "</small>";
            $message_type = "error";
        }
    }
}

// Handle add/edit teacher

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $department_id = isset($_POST['department_id']) ? $_POST['department_id'] : '';
    $subject_ids = isset($_POST['subjects']) && is_array($_POST['subjects'])
                 ? array_values(array_filter(array_unique(array_map('intval', $_POST['subjects']))))
                 : [];
    // the first ticked subject stays in teachers.subject_id as the MAIN one
    $subject_id = count($subject_ids) ? $subject_ids[0] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $is_homeroom = isset($_POST['is_homeroom']) ? 1 : 0;
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $class_ids   = isset($_POST['classes']) && is_array($_POST['classes'])
                 ? array_values(array_unique(array_map('intval', $_POST['classes'])))
                 : [];
    
    // Generate username and password for DISPLAY only (trigger will create actual account)
    $display_username = $name;  // Username is the name as typed
    $password_base = strtolower(str_replace(' ', '', $name));  // Remove spaces, lowercase
    $display_password = $password_base . '123';  // Add 123 at the end
    
    if (empty($name)) {
        $message = "❌ Teacher name is required!";
        $message_type = "error";
    } elseif (empty($department_id)) {
        $message = "❌ Please select a department!";
        $message_type = "error";
    } elseif (empty($subject_id)) {
        $message = "❌ Please tick at least one subject!";
        $message_type = "error";
    } else {
        // A subject can be taught by SEVERAL teachers - the same subject
        // is often given to different classes, years or semesters.
        // Which class each teacher takes is set in Classes -> Subject Teachers.
        if (isset($_POST['edit_id']) && $_POST['edit_id'] != '') {
            // ============================================
            // UPDATE existing teacher
            // ============================================
            $id = $_POST['edit_id'];
            
            // Get old user_id
            $old_teacher = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM teachers WHERE id = $id"));
            
            // Update user account (PHP handles this because trigger only runs on INSERT)
            if ($old_teacher && $old_teacher['user_id']) {
                // Only change the password when the admin typed a new one,
                // otherwise a teacher's own password would be wiped every
                // time their phone or email was edited.
                $set_sql = "username='$display_username'";
                if ($new_password !== '') {
                    $set_sql .= ", password='" . mysqli_real_escape_string($conn, $new_password) . "'";
                }
                mysqli_query($conn, "UPDATE users SET $set_sql WHERE id = {$old_teacher['user_id']}");
                $user_id = $old_teacher['user_id'];
            } else {
                // Create new user account (for existing teachers without user_id)
                mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('$display_username', '$display_password', 'teacher')");
                $user_id = mysqli_insert_id($conn);
            }
            
            // Update teacher
            $sql = "UPDATE teachers SET name='$name', department_id='$department_id', subject_id='$subject_id', email='$email', phone='$phone', is_homeroom='$is_homeroom', user_id='$user_id' WHERE id=$id";
            if (mysqli_query($conn, $sql)) {
                save_teacher_subjects($conn, $id, $subject_ids);
                sync_teacher_classes($conn, $id, $class_ids);
                $cls_note = count($class_ids) . ' class(es) assigned';
                $live = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT password FROM users WHERE id = " . (int)$user_id));
                $shown_pw = $live ? $live['password'] : $display_password;
                $message = "✅ Teacher updated successfully!<br>📝 Username: <strong>$display_username</strong><br>🔑 Password: <strong>$shown_pw</strong><br>🏫 $cls_note";
                $message_type = "success";
            } else {
                $message = "❌ Error: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            // ============================================
            // INSERT new teacher - LET TRIGGER HANDLE USER CREATION
            // ============================================
            // IMPORTANT: Do NOT insert user_id. Trigger will handle it!
            $sql = "INSERT INTO teachers (name, department_id, subject_id, email, phone, is_homeroom) 
                    VALUES ('$name', '$department_id', '$subject_id', '$email', '$phone', '$is_homeroom')";
            
            if (mysqli_query($conn, $sql)) {
                // Get the newly inserted teacher ID
                $new_teacher_id = mysqli_insert_id($conn);
                save_teacher_subjects($conn, $new_teacher_id, $subject_ids);
                sync_teacher_classes($conn, $new_teacher_id, $class_ids);

                // the trigger set the default password - override it if the
                // admin typed one
                if ($new_password !== '') {
                    $nt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM teachers WHERE id = $new_teacher_id"));
                    if ($nt && $nt['user_id']) {
                        mysqli_query($conn, "UPDATE users SET password='" . mysqli_real_escape_string($conn, $new_password) .
                                            "' WHERE id = " . (int)$nt['user_id']);
                    }
                }
                
                // Check if trigger created the user account
                $check_user = mysqli_query($conn, "SELECT u.id, u.username, u.password 
                                                   FROM users u 
                                                   JOIN teachers t ON t.user_id = u.id 
                                                   WHERE t.id = $new_teacher_id");
                
                if (mysqli_num_rows($check_user) > 0) {
                    $user_info = mysqli_fetch_assoc($check_user);
                    $actual_username = $user_info['username'];
                    $actual_password = $user_info['password'];
                    
                    $message = "✅ Teacher added successfully!<br>📝 Username: <strong>$actual_username</strong><br>🔑 Password: <strong>$actual_password</strong><br>🏫 " . count($class_ids) . " class(es) assigned";
                } else {
                    // If trigger didn't work, use display values (fallback)
                    $message = "✅ Teacher added successfully!<br>📝 Username: <strong>$display_username</strong><br>🔑 Password: <strong>$display_password</strong><br><small>⚠️ Note: User account was created by database trigger.</small>";
                }
                $message_type = "success";
            } else {
                $message = "❌ Error: " . mysqli_error($conn);
                $message_type = "error";
            }
        }
    }
}

// Get all teachers with department and subject names
// Reset a teacher's password back to the default: name + 123
if (isset($_GET['reset_pw'])) {
    $tid = (int)$_GET['reset_pw'];
    $t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, user_id FROM teachers WHERE id = $tid"));
    if ($t && $t['user_id']) {
        $def = strtolower(str_replace(' ', '', $t['name'])) . '123';
        mysqli_query($conn, "UPDATE users SET password='" . mysqli_real_escape_string($conn, $def) .
                            "' WHERE id = " . (int)$t['user_id']);
        $message = "🔑 Password for <strong>" . htmlspecialchars($t['name']) .
                   "</strong> reset to <strong>" . htmlspecialchars($def) . "</strong>";
        $message_type = "success";
    }
}

$has_ct = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'class_teachers'")) > 0;
$classes_col = $has_ct
    ? "(SELECT GROUP_CONCAT(CONCAT(c2.grade, ' (', c2.academic_year, ' S', c2.semester, ')')
                            ORDER BY c2.academic_year, c2.grade SEPARATOR ', ')
        FROM class_teachers ct JOIN classes c2 ON ct.class_id = c2.id
        WHERE ct.teacher_id = t.id) AS taught_classes"
    : "NULL AS taught_classes";
$has_ts_tbl = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'teacher_subjects'")) > 0;
$subjects_col = $has_ts_tbl
    ? "(SELECT GROUP_CONCAT(s2.name ORDER BY s2.name SEPARATOR ', ')
        FROM teacher_subjects ts JOIN subjects s2 ON ts.subject_id = s2.id
        WHERE ts.teacher_id = t.id) AS all_subjects"
    : "s.name AS all_subjects";
$teachers_sql = "SELECT t.*, d.name as department_name, s.name as subject_name, $classes_col, $subjects_col,
                        u.username AS login_username, u.password AS login_password,
                        (SELECT GROUP_CONCAT(c3.grade) FROM classes c3 WHERE c3.homeroom_teacher_id = t.id) AS homeroom_classes
                 FROM teachers t
                 LEFT JOIN departments d ON t.department_id = d.id
                 LEFT JOIN subjects s ON t.subject_id = s.id
                 LEFT JOIN users u ON t.user_id = u.id
                 ORDER BY t.name";
$teachers_result = mysqli_query($conn, $teachers_sql);

// Get all departments for dropdown
$depts_sql = "SELECT * FROM departments ORDER BY name";
$depts_result = mysqli_query($conn, $depts_sql);

// Get all subjects with their department_id
$subjects_sql = "SELECT s.*, d.name as dept_name,
                        (SELECT COUNT(*) FROM teachers t WHERE t.subject_id = s.id) AS teacher_count
                 FROM subjects s
                 LEFT JOIN departments d ON s.department_id = d.id
                 ORDER BY d.name, s.name";
$subjects_result = mysqli_query($conn, $subjects_sql);

// Store subjects in array for JavaScript
$subjects_array = [];
while($sub = mysqli_fetch_assoc($subjects_result)) {
    $subjects_array[] = $sub;
}

// Subjects already ticked for the teacher being edited
$edit_subject_ids = [];
if (isset($_GET['edit'])) {
    $edit_subject_ids = teacher_subject_ids($conn, (int)$_GET['edit']);
}

// Every class, with its year and semester
$all_classes = [];
$cl_res = mysqli_query($conn,
    "SELECT c.*, t.name AS homeroom_name
     FROM classes c LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id
     ORDER BY c.academic_year DESC, c.semester, c.grade");
while ($row = mysqli_fetch_assoc($cl_res)) $all_classes[] = $row;

// Filter lists
$year_list = []; $sem_list = [];
foreach ($all_classes as $c) {
    if ($c['academic_year'] !== null && !in_array($c['academic_year'], $year_list)) $year_list[] = $c['academic_year'];
    if ($c['semester'] !== null && !in_array($c['semester'], $sem_list)) $sem_list[] = $c['semester'];
}
sort($sem_list);

// Classes already ticked for the teacher being edited
$edit_class_ids = [];
if (isset($_GET['edit']) && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'class_teachers'")) > 0) {
    $eid = (int)$_GET['edit'];
    $ec = mysqli_query($conn, "SELECT class_id FROM class_teachers WHERE teacher_id = $eid");
    while ($row = mysqli_fetch_assoc($ec)) $edit_class_ids[] = (int)$row['class_id'];
}

// Get teacher for editing
$edit_teacher = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_sql = "SELECT * FROM teachers WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_sql);
    $edit_teacher = mysqli_fetch_assoc($edit_result);
}

// Include sidebar
include 'sidebar.php';
?>

<style>
    .card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .card h2 {
        margin-bottom: 20px;
        color: #333;
        font-size: 22px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #555;
    }
    
    input, select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    
    input:focus, select:focus {
        outline: none;
        border-color: #4CAF50;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    button {
        background: #4CAF50;
        color: white;
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
        transition: background 0.3s;
    }
    
    button:hover {
        background: #45a049;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    th, td {
        border: 1px solid #ddd;
        padding: 12px;
        text-align: left;
    }
    
    th {
        background: #4CAF50;
        color: white;
        font-weight: bold;
    }
    
    tr:nth-child(even) {
        background: #f9f9f9;
    }
    
    .delete-btn {
        background: #f44336;
        color: white;
        padding: 5px 10px;
        text-decoration: none;
        border-radius: 5px;
        font-size: 12px;
    }
    
    /* the Plans link sits with Edit / Delete, so it must match their
       shape - responsive.css gives .view-btn/.edit-btn/.delete-btn
       their size, and this borrows the same look */
    .plans-btn {
        display: inline-flex; align-items: center; justify-content: center;
        background: #667eea; color: white; text-decoration: none;
        min-height: 30px; padding: 6px 12px; font-size: 12px;
        border-radius: 5px; margin: 2px 5px 2px 0; white-space: nowrap;
        line-height: 1.2; box-sizing: border-box;
    }
    .plans-btn:hover { background: #5a6fd6; }

    .edit-btn {
        background: #ff9800;
        color: white;
        padding: 5px 10px;
        text-decoration: none;
        border-radius: 5px;
        font-size: 12px;
        margin-right: 5px;
    }
    
    .message {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    
    .success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .checkbox {
        width: auto;
        margin-right: 10px;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    .class-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 9px; align-items: center; }
    .class-filters select { width: auto; min-width: 140px; padding: 8px 10px; }
    .link-btn {
        background: none; border: none; color: #4CAF50; cursor: pointer;
        font-size: 13px; font-weight: 600; padding: 4px 6px; text-decoration: underline;
    }
    .class-checks {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr));
        gap: 8px; padding: 12px; border: 1px solid #ddd; border-radius: 8px;
        background: #fafafa; max-height: 230px; overflow-y: auto;
    }
    .class-check {
        display: flex; align-items: flex-start; gap: 9px; margin: 0;
        font-weight: normal; cursor: pointer; font-size: 14px;
        background: white; border: 1px solid #eee; border-radius: 8px; padding: 9px 11px;
    }
    .class-check:hover { border-color: #4CAF50; }
    .class-check input[type="checkbox"] { width: auto; margin-top: 3px; }
    .class-check small { display: block; color: #888; font-size: 12px; }
    .class-check em { display: block; color: #bbb; font-size: 11px; font-style: normal; }
    .class-check.hidden { display: none; }
    .subject-checks {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));
        gap: 7px; padding: 12px; border: 1px solid #ddd; border-radius: 8px;
        background: #fafafa; max-height: 240px; overflow-y: auto;
    }
    .subject-checks .dept-head {
        grid-column: 1 / -1; font-size: 11px; font-weight: 700; color: #888;
        text-transform: uppercase; letter-spacing: .5px; margin-top: 4px;
    }
    .subject-check {
        display: flex; align-items: center; gap: 8px; margin: 0;
        font-weight: normal; cursor: pointer; font-size: 14px;
        background: white; border: 1px solid #eee; border-radius: 7px; padding: 8px 10px;
    }
    .subject-check:hover { border-color: #4CAF50; }
    .subject-check input[type="checkbox"] { width: auto; }
    .subj-tag {
        display: inline-block; background: #e8f5e9; color: #2e7d32;
        padding: 2px 9px; border-radius: 10px; font-size: 11px; margin: 2px 2px 2px 0;
    }

    code.pw {
        background: #f3f4f6; border: 1px solid #e2e4e8; border-radius: 5px;
        padding: 2px 7px; font-size: 13px; color: #b7791f; font-weight: 600;
    }
    .reset-btn {
        display: inline-block; margin-left: 6px; font-size: 11px;
        color: #ff9800; text-decoration: none; white-space: nowrap;
    }
    .reset-btn:hover { text-decoration: underline; }

    .cls-tag {
        display: inline-block; background: #e3f2fd; color: #1565c0;
        padding: 2px 8px; border-radius: 10px; font-size: 11px; margin: 2px 2px 2px 0;
    }
</style>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $edit_teacher ? '✏️ Edit Teacher' : '➕ Add New Teacher'; ?></h2>
    
    <form method="POST" action="" id="teacherForm">
        <?php if ($edit_teacher): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_teacher['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label>Full Name:</label>
            <input type="text" name="name" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['name'] ?? '') : ''; ?>" required>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>🏛️ Department:</label>
                <select name="department_id" id="department_id" required>
                    <option value="">Select Department</option>
                    <?php mysqli_data_seek($depts_result, 0); ?>
                    <?php while($dept = mysqli_fetch_assoc($depts_result)): ?>
                        <option value="<?php echo $dept['id']; ?>" 
                            <?php echo ($edit_teacher && $edit_teacher['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name'] ?? ''); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>📚 Subjects they teach: <span style="color:red;">*</span></label>
                <div class="subject-checks">
                    <?php
                    mysqli_data_seek($subjects_result, 0);
                    $main_sub = $edit_teacher ? (int)$edit_teacher['subject_id'] : 0;
                    $last_dept = null;
                    while ($sub = mysqli_fetch_assoc($subjects_result)):
                        $sid = (int)$sub['id'];
                        $checked = in_array($sid, $edit_subject_ids)
                                || (!$edit_subject_ids && $sid === $main_sub);
                        $dept = $sub['dept_name'] ?: 'Other';
                        if ($dept !== $last_dept): $last_dept = $dept; ?>
                            <div class="dept-head"><?php echo htmlspecialchars($dept ?? ''); ?></div>
                        <?php endif; ?>
                        <label class="subject-check">
                            <input type="checkbox" name="subjects[]" value="<?php echo $sid; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($sub['name'] ?? ''); ?></span>
                        </label>
                    <?php endwhile; ?>
                </div>
                <small style="color:#666;">
                    Tick every subject this teacher takes — for example <strong>Physics and Maths</strong>.
                    Subjects from different departments are fine.
                </small>
            </div>
        </div>
        
        <div class="form-group">
            <label>🏫 Classes this teacher will teach: <span style="color:#999;font-weight:normal;">(optional)</span></label>

            <div class="class-filters">
                <select id="filterYear" onchange="filterClasses()">
                    <option value="">All years</option>
                    <?php foreach ($year_list as $y): ?>
                        <option value="<?php echo htmlspecialchars($y ?? ''); ?>"><?php echo htmlspecialchars($y ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterSem" onchange="filterClasses()">
                    <option value="">All semesters</option>
                    <?php foreach ($sem_list as $sm): ?>
                        <option value="<?php echo htmlspecialchars($sm ?? ''); ?>">Semester <?php echo htmlspecialchars($sm ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="link-btn" onclick="pickAll(true)">Select all shown</button>
                <button type="button" class="link-btn" onclick="pickAll(false)">Clear</button>
            </div>

            <div class="class-checks" id="classChecks">
                <?php if (count($all_classes) === 0): ?>
                    <p style="color:#999;margin:0;">No classes yet. Add one in <strong>Classes</strong> first.</p>
                <?php else: foreach ($all_classes as $c):
                    $cid = (int)$c['id'];
                    $checked = in_array($cid, $edit_class_ids); ?>
                    <label class="class-check"
                           data-year="<?php echo htmlspecialchars($c['academic_year'] ?? ''); ?>"
                           data-sem="<?php echo htmlspecialchars($c['semester'] ?? ''); ?>">
                        <input type="checkbox" name="classes[]" value="<?php echo $cid; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                        <span>
                            <strong><?php echo htmlspecialchars($c['grade'] ?? ''); ?></strong>
                            <small><?php echo htmlspecialchars($c['academic_year'] ?? ''); ?> · Semester <?php echo htmlspecialchars($c['semester'] ?? ''); ?></small>
                            <?php if (!empty($c['homeroom_name'])): ?>
                                <em>homeroom: <?php echo htmlspecialchars($c['homeroom_name'] ?? ''); ?></em>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; endif; ?>
            </div>
            <small style="color:#666;">
                Use the filters to narrow by year or semester, then tick every class this teacher takes.
                Two teachers of the same subject can each take different classes.
            </small>
        </div>

        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['email'] ?? '') : ''; ?>">
        </div>
        
        <div class="form-group">
            <label>🔑 Password:</label>
            <input type="text" name="new_password"
                   placeholder="<?php echo $edit_teacher ? 'Leave empty to keep the current password' : 'Leave empty to use the default (name + 123)'; ?>">
            <small style="color:#666;">
                <?php if ($edit_teacher): ?>
                    Leave this empty and the teacher keeps the password they are using now.
                <?php else: ?>
                    Leave empty and the system creates <strong>name + 123</strong> automatically.
                <?php endif; ?>
            </small>
        </div>

        <div class="form-group">
            <label>Phone:</label>
            <input type="text" name="phone" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['phone'] ?? '') : ''; ?>">
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_homeroom" class="checkbox" 
                    <?php echo ($edit_teacher && $edit_teacher['is_homeroom']) ? 'checked' : ''; ?>>
                Is Homeroom Teacher
            </label>
        </div>
        
        <button type="submit"><?php echo $edit_teacher ? 'Update Teacher' : 'Add Teacher'; ?></button>
        <?php if ($edit_teacher): ?>
            <a href="teachers.php" style="margin-left: 10px; text-decoration: none; color: #666;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>📋 All Teachers</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Username</th>
                <th>Password</th>
                <th>Department</th>
                <th>Subjects</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Classes Taught</th>
                <th>Homeroom</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php mysqli_data_seek($teachers_result, 0); ?>
            <?php while($teacher = mysqli_fetch_assoc($teachers_result)): 
                // username and password come straight from the users table,
                // so whatever the teacher is using right now is what shows here
                $username = $teacher['login_username'] ?: '-';
                $password = $teacher['login_password'] ?: '-';
            ?>
            <tr>
                <td><?php echo $teacher['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($teacher['name'] ?? '-'); ?></strong></td>
                <td><?php echo htmlspecialchars($username ?? ''); ?></td>
                <td>
                    <code class="pw"><?php echo htmlspecialchars($password ?? ''); ?></code>
                    <a href="teachers.php?reset_pw=<?php echo $teacher['id']; ?>" class="reset-btn" title="Reset to default"
                       onclick="return confirm('Reset this teacher\'s password back to the default (name + 123)?')">🔑 Reset</a>
                </td>
                <td><?php echo $teacher['department_name'] ? htmlspecialchars($teacher['department_name'] ?? '') : 'Not Assigned'; ?></td>
                <td>
                    <?php if (!empty($teacher['all_subjects'])): ?>
                        <?php foreach (explode(', ', $teacher['all_subjects']) as $sn): ?>
                            <span class="subj-tag"><?php echo htmlspecialchars($sn ?? ''); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#999;">Not Assigned</span>
                    <?php endif; ?>
                </td>
                <td><?php echo isset($teacher['email']) && $teacher['email'] ? htmlspecialchars($teacher['email'] ?? '') : '-'; ?></td>
                <td><?php echo isset($teacher['phone']) && $teacher['phone'] ? htmlspecialchars($teacher['phone'] ?? '') : '-'; ?></td>
                <td>
                    <?php if (!empty($teacher['taught_classes'])): ?>
                        <?php foreach (explode(', ', $teacher['taught_classes']) as $tc): ?>
                            <span class="cls-tag"><?php echo htmlspecialchars($tc ?? ''); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#999;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($teacher['homeroom_classes'])): ?>
                        ✅ <?php echo htmlspecialchars($teacher['homeroom_classes'] ?? ''); ?>
                    <?php else: ?>
                        ❌ No
                    <?php endif; ?>
                </td>
                <td class="action-buttons">
                    <?php
                    // Straight to this teacher's lesson plans - this page is
                    // where an admin naturally looks for one teacher's work.
                    include_once __DIR__ . '/../lesson_plans_helper.php';
                    $lp_n = 0;
                    if (lp_installed($conn)) {
                        $lp_n = (int)mysqli_fetch_assoc(mysqli_query($conn,
                            "SELECT COUNT(*) n FROM lesson_plans
                              WHERE teacher_id = " . (int)$teacher['id'] . " AND status = 'submitted'"))['n'];
                    }
                    ?>
                    <a href="lesson_plans.php?teacher=<?php echo (int)$teacher['id']; ?>"
                       class="plans-btn" title="Lesson plans from this teacher">
                        📋 Plans<?php echo $lp_n > 0 ? ' (' . $lp_n . ')' : ''; ?>
                    </a>
                    <a href="teachers.php?edit=<?php echo $teacher['id']; ?>" class="edit-btn">Edit</a>
                    <?php
                    // what is this teacher still attached to?
                    $tid  = (int)$teacher['id'];
                    $warn = [];
                    if (!empty($teacher['homeroom_classes'])) {
                        $warn[] = 'class ' . $teacher['homeroom_classes'] . ' will be left without a homeroom teacher';
                    }
                    $wm = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM marks WHERE entered_by = $tid"));
                    if ((int)$wm['n'] > 0) $warn[] = (int)$wm['n'] . ' mark(s) they entered will be kept but unlinked';
                    $wt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM timetable WHERE teacher_id = $tid"));
                    if ((int)$wt['n'] > 0) $warn[] = (int)$wt['n'] . ' timetable lesson(s) will be removed';

                    $confirm_txt = 'Delete ' . $teacher['name'] . " and their login?";
                    if ($warn) $confirm_txt .= "\n\nAlso:\n• " . implode("\n• ", $warn);
                    ?>
                    <a href="teachers.php?delete=<?php echo $teacher['id']; ?>" class="delete-btn"
                       onclick="return confirm(<?php echo htmlspecialchars(json_encode($confirm_txt), ENT_QUOTES); ?>)">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    // Show only the classes that match the chosen year / semester
    function filterClasses() {
        var y = document.getElementById('filterYear').value;
        var m = document.getElementById('filterSem').value;
        var rows = document.querySelectorAll('#classChecks .class-check');
        var shown = 0;
        for (var i = 0; i < rows.length; i++) {
            var ok = (!y || rows[i].dataset.year === y) && (!m || rows[i].dataset.sem === m);
            rows[i].classList.toggle('hidden', !ok);
            if (ok) shown++;
        }
        var note = document.getElementById('noClassNote');
        if (note) note.style.display = shown ? 'none' : 'block';
    }

    // Tick / untick everything currently visible
    function pickAll(state) {
        var rows = document.querySelectorAll('#classChecks .class-check');
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].classList.contains('hidden')) continue;
            rows[i].querySelector('input[type=checkbox]').checked = state;
        }
    }

    const subjectsData = <?php echo json_encode($subjects_array); ?>;
    const departmentSelect = document.getElementById('department_id');
    const subjectSelect = document.getElementById('subject_id'); // replaced by checkboxes
    let originalSubjectId = <?php echo $edit_teacher ? $edit_teacher['subject_id'] : 'null'; ?>;
    
    function updateSubjects() {
        if (!subjectSelect) return;   // subjects are checkboxes now
        const selectedDeptId = departmentSelect.value;
        subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';
        
        if (!selectedDeptId) {
            subjectSelect.innerHTML = '<option value="">First select a department</option>';
            return;
        }
        
        const filteredSubjects = subjectsData.filter(subject => subject.department_id == selectedDeptId);
        
        if (filteredSubjects.length === 0) {
            subjectSelect.innerHTML = '<option value="">No subjects available for this department</option>';
            return;
        }
        
        filteredSubjects.forEach(subject => {
            const option = document.createElement('option');
            option.value = subject.id;
            var n = parseInt(subject.teacher_count) || 0;
            option.textContent = subject.name +
                (n === 0 ? '  (no teacher yet)' : '  (' + n + ' teacher' + (n > 1 ? 's' : '') + ' already)');
            subjectSelect.appendChild(option);
        });
        
        if (originalSubjectId) {
            const originalSubject = subjectsData.find(s => s.id == originalSubjectId);
            if (originalSubject && originalSubject.department_id == selectedDeptId) {
                subjectSelect.value = originalSubjectId;
            }
        }
    }
    
    departmentSelect.addEventListener('change', updateSubjects);
    
    document.addEventListener('DOMContentLoaded', function() {
        if (departmentSelect.value) {
            updateSubjects();
        }
    });
</script>

<?php
// Close the divs opened in sidebar.php
?>
</div></div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
</script>
</body>
</html>