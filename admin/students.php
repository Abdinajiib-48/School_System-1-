<?php
// ============================================================
// admin/students.php - Add, edit and remove students; see and reset their logins.
//
// Who can open it: admin
//
// Adding a row here also creates the student's user account (see the
// before_student_insert trigger in the database).
// ============================================================
include '../config.php';

require_role('admin');

// ============================================
// FUNCTIONS
// ============================================

function getStudentStatus($avg_marks) {
    if ($avg_marks >= 50) {
        return ['text' => 'Active', 'color' => 'green', 'icon' => '●'];
    } else {
        return ['text' => 'Inactive', 'color' => 'red', 'icon' => '○'];
    }
}

function getGradeLetter($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 75) return 'B+';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    return 'F';
}

// ============================================
// HANDLE DELETE STUDENT
// ============================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get the student info including user_id before deleting
    $stu_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, student_id, name FROM students WHERE id = $id"));
    
    if ($stu_info) {
        // Delete marks first (foreign key constraint)
        mysqli_query($conn, "DELETE FROM marks WHERE student_id = $id");
        
        // Delete attendance records
        mysqli_query($conn, "DELETE FROM attendance WHERE student_id = $id");
        
        // Delete the student record (trigger will handle user deletion)
        $delete_result = mysqli_query($conn, "DELETE FROM students WHERE id = $id");
        
        if ($delete_result) {
            // The trigger `before_student_delete` should have deleted the user account
            // But as a fallback, also delete the user account manually
            if ($stu_info['user_id']) {
                mysqli_query($conn, "DELETE FROM users WHERE id = {$stu_info['user_id']}");
            }
            
            $message = "✅ Student '{$stu_info['name']}' (ID: {$stu_info['student_id']}) deleted successfully!";
            $message_type = "success";
        } else {
            $message = "❌ Error deleting student: " . mysqli_error($conn);
            $message_type = "error";
        }
    } else {
        $message = "❌ Student not found!";
        $message_type = "error";
    }
}

// ============================================
// HANDLE SINGLE ADD/EDIT
// ============================================
$message = "";
$message_type = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['bulk_import'])) {
    // Check if fields exist in POST
    $student_id = isset($_POST['student_id']) ? mysqli_real_escape_string($conn, $_POST['student_id']) : '';
    $name = isset($_POST['name']) ? mysqli_real_escape_string($conn, $_POST['name']) : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $parent_phone = isset($_POST['parent_phone']) ? mysqli_real_escape_string($conn, $_POST['parent_phone']) : '';
    $address = isset($_POST['address']) ? mysqli_real_escape_string($conn, $_POST['address']) : '';
    
    if (isset($_POST['edit_id']) && $_POST['edit_id'] != '') {
        // ============================================
        // UPDATE existing student
        // ============================================
        $id = (int)$_POST['edit_id'];
        $check_sql = "SELECT id FROM students WHERE student_id = '$student_id' AND id != $id";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $message = "❌ Error: Student ID '$student_id' already exists!";
            $message_type = "error";
        } else {
            // Get current student info
            $current_student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, name FROM students WHERE id = $id"));
            $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
            
            // Generate username and password
            $display_username = $student_id;  // Student ID as username
            $password_base = strtolower(preg_replace('/[^a-zA-Z]/', '', $name));
            $display_password = $password_base . '123';
            
            // If password base is empty, use 'student' as fallback
            if (empty($password_base)) {
                $display_password = 'student123';
            }
            
            // Update user account if exists
            if ($current_student && $current_student['user_id']) {
                // Check if username already taken by another user (not this student)
                $user_check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$display_username' AND id != {$current_student['user_id']}");
                if (mysqli_num_rows($user_check) > 0) {
                    $display_username = $student_id . rand(10, 99);
                }
                // Only touch the password when the admin actually typed one,
                // otherwise a student's own password would be wiped every time
                // their phone or address was edited.
                $set_sql = "username='$display_username'";
                if ($new_password !== '') {
                    $set_sql .= ", password='" . mysqli_real_escape_string($conn, $new_password) . "'";
                }
                mysqli_query($conn, "UPDATE users SET $set_sql WHERE id = {$current_student['user_id']}");
                $user_id = $current_student['user_id'];
            } else {
                // Create new user account (for existing students without user_id)
                $user_check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$display_username'");
                if (mysqli_num_rows($user_check) > 0) {
                    $display_username = $student_id . rand(10, 99);
                }
                $pw_to_use = $new_password !== '' ? $new_password : $display_password;
                mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('$display_username', '" .
                                    mysqli_real_escape_string($conn, $pw_to_use) . "', 'student')");
                $user_id = mysqli_insert_id($conn);
            }
            
            $sql = "UPDATE students SET student_id='$student_id', name='$name', gender='$gender', class_id=$class_id, parent_phone='$parent_phone', address='$address', user_id='$user_id' WHERE id=$id";
            if (mysqli_query($conn, $sql)) {
                $live = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id = " . (int)$user_id));
                $shown_pw = $live ? $live['password'] : $display_password;
                $message = "✅ Student updated successfully!<br>📝 Username: <strong>$display_username</strong><br>🔑 Password: <strong>$shown_pw</strong>";
                $message_type = "success";
            } else {
                $message = "❌ Error: " . mysqli_error($conn);
                $message_type = "error";
            }
        }
    } else {
        // ============================================
        // INSERT new student - TRIGGER handles user creation
        // ============================================
        // Check if required fields are empty
        if (empty($student_id) || empty($name) || empty($gender) || $class_id <= 0) {
            $message = "❌ Please fill all required fields (Student ID, Name, Gender, Class)!";
            $message_type = "error";
        } else {
            $check_sql = "SELECT id FROM students WHERE student_id = '$student_id'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $message = "❌ Error: Student ID '$student_id' already exists!";
                $message_type = "error";
            } else {
                // IMPORTANT: We will NOT insert user_id. 
                // The trigger `before_student_insert` will handle user creation automatically!
                $sql = "INSERT INTO students (student_id, name, gender, class_id, parent_phone, address) 
                        VALUES ('$student_id', '$name', '$gender', $class_id, '$parent_phone', '$address')";
                
                if (mysqli_query($conn, $sql)) {
                    // Get the newly inserted student ID
                    $new_student_id = mysqli_insert_id($conn);
                    
                    // Check if trigger created the user account
                    $check_user = mysqli_query($conn, "SELECT u.id, u.username, u.password 
                                                       FROM users u 
                                                       JOIN students s ON s.user_id = u.id 
                                                       WHERE s.id = $new_student_id");
                    
                    if (mysqli_num_rows($check_user) > 0) {
                        $user_info = mysqli_fetch_assoc($check_user);
                        $actual_username = $user_info['username'];
                        $actual_password = $user_info['password'];
                        
                        $message = "✅ Student added successfully!<br>📝 Username: <strong>$actual_username</strong><br>🔑 Password: <strong>$actual_password</strong>";
                    } else {
                        // If trigger didn't work, use fallback
                        $password_base = strtolower(preg_replace('/[^a-zA-Z]/', '', $name));
                        $fallback_password = empty($password_base) ? 'student123' : $password_base . '123';
                        $message = "✅ Student added successfully!<br>📝 Username: <strong>$student_id</strong><br>🔑 Password: <strong>$fallback_password</strong><br><small>⚠️ Note: User account was created by database trigger.</small>";
                    }
                    $message_type = "success";
                } else {
                    $message = "❌ Error: " . mysqli_error($conn);
                    $message_type = "error";
                }
            }
        }
    }
}

// ============================================
// BULK IMPORT (Multiple Students at Once)
// ============================================
$import_message = "";
$import_message_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_import'])) {
    $import_data = json_decode($_POST['import_data'], true);
    $imported_count = 0;
    $failed_count = 0;
    $failed_records = [];
    $duplicate_records = [];
    
    if (!$import_data || !is_array($import_data)) {
        $import_message = "❌ No valid data received. Please check your CSV format.";
        $import_message_type = "error";
    } else {
        foreach ($import_data as $index => $row) {
            // Get values safely with defaults
            $student_id = isset($row['student_id']) ? trim($row['student_id']) : '';
            $name = isset($row['name']) ? trim($row['name']) : '';
            $gender = isset($row['gender']) ? strtoupper(trim($row['gender'])) : '';
            $class_id = isset($row['class_id']) ? (int)$row['class_id'] : 0;
            $parent_phone = isset($row['parent_phone']) ? trim($row['parent_phone']) : '';
            $address = isset($row['address']) ? trim($row['address']) : '';
            
            // Escape for SQL
            $student_id_esc = mysqli_real_escape_string($conn, $student_id);
            $name_esc = mysqli_real_escape_string($conn, $name);
            $parent_phone_esc = mysqli_real_escape_string($conn, $parent_phone);
            $address_esc = mysqli_real_escape_string($conn, $address);
            
            // Validate required fields
            if (empty($student_id) || empty($name) || empty($gender)) {
                $failed_count++;
                $failed_records[] = "Row " . ($index + 1) . ": Missing required fields";
                continue;
            }
            
            // Validate gender
            if ($gender != 'M' && $gender != 'F') {
                $failed_count++;
                $failed_records[] = $student_id . " (Invalid gender: $gender)";
                continue;
            }
            
            // Validate class_id exists in classes table
            $class_check = mysqli_query($conn, "SELECT id FROM classes WHERE id = $class_id");
            if (!$class_check || mysqli_num_rows($class_check) == 0) {
                $failed_count++;
                $failed_records[] = $student_id . " (Invalid class_id: $class_id)";
                continue;
            }
            
            // Check if student already exists
            $check_sql = "SELECT id FROM students WHERE student_id = '$student_id_esc'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $failed_count++;
                $duplicate_records[] = $student_id;
            } else {
                // Insert without user_id - trigger will handle it
                $sql = "INSERT INTO students (student_id, name, gender, class_id, parent_phone, address) 
                        VALUES ('$student_id_esc', '$name_esc', '$gender', $class_id, '$parent_phone_esc', '$address_esc')";
                if (mysqli_query($conn, $sql)) {
                    $imported_count++;
                } else {
                    $failed_count++;
                    $failed_records[] = $student_id . " (DB Error: " . mysqli_error($conn) . ")";
                }
            }
        }
    }
    
    if ($imported_count > 0) {
        $import_message = "✅ Successfully imported $imported_count students!";
        if ($failed_count > 0) {
            $import_message .= " Failed: $failed_count students.";
            if (count($duplicate_records) > 0) {
                $import_message .= " Duplicates: " . implode(', ', array_slice($duplicate_records, 0, 5));
                if (count($duplicate_records) > 5) $import_message .= "...";
            }
        }
        $import_message_type = "success";
        
        // // Refresh page to show new students
        // echo "<script>setTimeout(function() { window.location.href = 'students.php'; }, 2000);</script>";
    } else {
        $import_message = "❌ No students imported. " . ($failed_records ? "Errors: " . implode('; ', array_slice($failed_records, 0, 3)) : "Check your CSV format.");
        $import_message_type = "error";
    }
}

// Get initial students
// ------------------------------------------------------------------
// Reset a student's password back to the default: name + 123
// ------------------------------------------------------------------
if (isset($_GET['reset_pw'])) {
    $sid = (int)$_GET['reset_pw'];
    $st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, user_id FROM students WHERE id = $sid"));
    if ($st && $st['user_id']) {
        $base = strtolower(preg_replace('/[^a-zA-Z]/', '', $st['name']));
        $def  = ($base === '' ? 'student' : $base) . '123';
        mysqli_query($conn, "UPDATE users SET password='" . mysqli_real_escape_string($conn, $def) .
                            "' WHERE id = " . (int)$st['user_id']);
        $message = "🔑 Password for <strong>" . htmlspecialchars($st['name']) .
                   "</strong> reset to <strong>" . htmlspecialchars($def) . "</strong>";
        $message_type = "success";
    }
}

// ------------------------------------------------------------------
// Create a login for a student who does not have one yet
// ------------------------------------------------------------------
if (isset($_GET['make_login'])) {
    $sid = (int)$_GET['make_login'];
    $st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT student_id, name, user_id FROM students WHERE id = $sid"));
    if ($st && !$st['user_id']) {
        $uname = $st['student_id'];
        while (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE username = '" .
               mysqli_real_escape_string($conn, $uname) . "'")) > 0) {
            $uname = $st['student_id'] . rand(10, 99);
        }
        $base = strtolower(preg_replace('/[^a-zA-Z]/', '', $st['name']));
        $pw   = ($base === '' ? 'student' : $base) . '123';
        mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('" .
                            mysqli_real_escape_string($conn, $uname) . "', '" .
                            mysqli_real_escape_string($conn, $pw) . "', 'student')");
        $uid = mysqli_insert_id($conn);
        if ($uid) {
            mysqli_query($conn, "UPDATE students SET user_id = $uid WHERE id = $sid");
            $message = "✅ Login created for <strong>" . htmlspecialchars($st['name']) .
                       "</strong><br>📝 Username: <strong>" . htmlspecialchars($uname) .
                       "</strong> &nbsp; 🔑 Password: <strong>" . htmlspecialchars($pw) . "</strong>";
            $message_type = "success";
        }
    }
}

$students_sql = "SELECT s.*, c.grade as class_name,
                u.username AS login_username, u.password AS login_password,
                (SELECT AVG(marks_obtained) FROM marks WHERE student_id = s.id) as avg_marks
                FROM students s 
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
                ORDER BY s.name";
$students_result = mysqli_query($conn, $students_sql);
$total_students = mysqli_num_rows($students_result);

// Calculate active/inactive counts
$active_count = 0;
$inactive_count = 0;
mysqli_data_seek($students_result, 0);
while($student = mysqli_fetch_assoc($students_result)) {
    $avg = round($student['avg_marks'] ?? 0, 1);
    if ($avg >= 50) {
        $active_count++;
    } else {
        $inactive_count++;
    }
}
mysqli_data_seek($students_result, 0);

// Get classes for dropdown
$classes_sql = "SELECT * FROM classes ORDER BY grade";
$classes_result = mysqli_query($conn, $classes_sql);
$classes_list = [];
while($class = mysqli_fetch_assoc($classes_result)) {
    $classes_list[] = $class;
}
mysqli_data_seek($classes_result, 0);

$edit_student = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_sql = "SELECT * FROM students WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_sql);
    $edit_student = mysqli_fetch_assoc($edit_result);
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
    
    input, select, textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    
    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: #4CAF50;
    }
    
    button, .btn {
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
    
    button:hover, .btn:hover {
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
    
    .edit-btn {
        background: #ff9800;
        color: white;
        padding: 5px 10px;
        text-decoration: none;
        border-radius: 5px;
        font-size: 12px;
        margin-right: 5px;
    }
    
    .view-btn {
        background: #2196F3;
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
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .warning-text {
        color: #856404;
        background: #fff3cd;
        padding: 10px;
        border-radius: 8px;
        margin-top: 10px;
        font-size: 13px;
    }
    
    /* Stats Cards */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 20px;
        margin-top: 20px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #4CAF50;
    }
    
    .stat-label {
        color: #666;
        margin-top: 5px;
    }
    
    /* Search Section */
    .search-section {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .section-title {
        font-size: 18px;
        margin-bottom: 15px;
        color: #333;
        border-left: 4px solid #4CAF50;
        padding-left: 15px;
    }
    
    .search-box {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 15px;
        align-items: center;
    }
    
    .search-input {
        flex: 2;
        position: relative;
    }
    
    .search-input input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-size: 16px;
    }
    
    .search-input input:focus {
        outline: none;
        border-color: #4CAF50;
    }
    
    .btn-reset {
        background: #f44336;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-export {
        background: #2196F3;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .btn-import {
        background: #ff9800;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .btn-import:hover {
        background: #e68900;
    }
    
    .search-info {
        padding: 10px;
        background: #e3f2fd;
        border-radius: 8px;
        color: #1565c0;
        font-size: 14px;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background: white;
        width: 90%;
        max-width: 1000px;
        border-radius: 15px;
        padding: 25px;
        animation: slideIn 0.3s;
        max-height: 85vh;
        overflow-y: auto;
    }
    
    @keyframes slideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #4CAF50;
    }
    
    .close {
        font-size: 28px;
        cursor: pointer;
        color: #999;
    }
    
    .detail-item {
        margin-bottom: 12px;
        padding: 8px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .detail-label {
        font-weight: bold;
        color: #4CAF50;
        width: 130px;
        display: inline-block;
    }
    
    .status-active { color: green; font-weight: bold; }
    .status-inactive { color: red; font-weight: bold; }
    
    .import-preview {
        max-height: 350px;
        overflow-y: auto;
        margin-top: 15px;
    }
    
    .preview-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    
    .preview-table th, .preview-table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    
    .preview-table th {
        background: #4CAF50;
        color: white;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        .stats-container {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    code.pw {
        background: #f3f4f6; border: 1px solid #e2e4e8; border-radius: 5px;
        padding: 2px 7px; font-size: 13px; color: #b7791f; font-weight: 600;
    }
    .reset-btn, .make-btn {
        display: inline-block; margin-left: 6px; font-size: 11px;
        text-decoration: none; white-space: nowrap;
    }
    .reset-btn { color: #ff9800; }
    .make-btn  { color: #4CAF50; font-weight: 600; }
    .reset-btn:hover, .make-btn:hover { text-decoration: underline; }
    .no-login { color: #c62828; font-size: 12px; font-style: italic; }
</style>

<?php if (isset($message) && $message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (isset($import_message) && $import_message): ?>
    <div class="message <?php echo $import_message_type; ?>"><?php echo $import_message; ?></div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SINGLE STUDENT ADD FORM -->
<!-- ============================================ -->
<div class="card">
    <h2><?php echo $edit_student ? '✏️ Edit Student' : '➕ Add New Student'; ?></h2>
    
    <?php if (!$edit_student): ?>
    <div class="warning-text">
        ⚠️ <strong>Important:</strong> Each Student ID must be UNIQUE.
        <br>💡 When you add a student, a login account is automatically created with:
        <br>• <strong>Username:</strong> Student ID (e.g., ABC010/26)
        <br>• <strong>Password:</strong> Student's name without spaces + "123" (e.g., ahmedhassan123)
        <br>💡 For multiple students, use the <strong>Bulk Import</strong> button below.
    </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <?php if ($edit_student): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_student['id']; ?>">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Student ID: <span style="color: red;">*</span></label>
                <input type="text" name="student_id" value="<?php echo $edit_student ? htmlspecialchars($edit_student['student_id'] ?? '') : ''; ?>" placeholder="e.g., ABC001/26" required>
                <small>Must be unique. This will be the student's username.</small>
            </div>
            
            <div class="form-group">
                <label>Full Name: <span style="color: red;">*</span></label>
                <input type="text" name="name" value="<?php echo $edit_student ? htmlspecialchars($edit_student['name'] ?? '') : ''; ?>" required>
                <small>Used to generate password (e.g., Ahmed Hassan → ahmedhassan123)</small>
            </div>

            <div class="form-group">
                <label>🔑 Password:</label>
                <input type="text" name="new_password"
                       placeholder="<?php echo !empty($edit_student) ? 'Leave empty to keep the current password' : 'Leave empty to use the default (name + 123)'; ?>">
                <small>
                    <?php if (!empty($edit_student)): ?>
                        Leave this empty and the student keeps the password they are using now.
                    <?php else: ?>
                        Leave empty and the system creates <strong>name + 123</strong> automatically.
                    <?php endif; ?>
                </small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Gender: <span style="color: red;">*</span></label>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="M" <?php echo ($edit_student && $edit_student['gender'] == 'M') ? 'selected' : ''; ?>>Male</option>
                    <option value="F" <?php echo ($edit_student && $edit_student['gender'] == 'F') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Class: <span style="color: red;">*</span></label>
                <select name="class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach($classes_list as $class): ?>
                        <option value="<?php echo $class['id']; ?>" 
                            <?php echo ($edit_student && $edit_student['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo $class['grade']; ?> (<?php echo $class['academic_year']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Parent Phone:</label>
            <input type="text" name="parent_phone" value="<?php echo $edit_student ? (isset($edit_student['parent_phone']) ? htmlspecialchars($edit_student['parent_phone'] ?? '') : '') : ''; ?>" placeholder="e.g., 1234567890">
        </div>
        
        <div class="form-group">
            <label>Address:</label>
            <textarea name="address" rows="2"><?php echo $edit_student ? (isset($edit_student['address']) ? htmlspecialchars($edit_student['address'] ?? '') : '') : ''; ?></textarea>
        </div>
        
        <button type="submit"><?php echo $edit_student ? 'Update Student' : 'Add Student'; ?></button>
        <?php if ($edit_student): ?>
            <a href="students.php" style="margin-left: 10px; text-decoration: none; color: #666;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<!-- ============================================ -->
<!-- STATISTICS CARDS -->
<!-- ============================================ -->
<div class="stats-container">
    <div class="stat-card">
        <div class="stat-number" id="totalStudents"><?php echo $total_students; ?></div>
        <div class="stat-label">Total Students</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" id="activeCount"><?php echo $active_count; ?></div>
        <div class="stat-label">Active (Passing)</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" id="inactiveCount"><?php echo $inactive_count; ?></div>
        <div class="stat-label">Inactive (Failing)</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" id="functionCalls">0</div>
        <div class="stat-label">Actions Performed</div>
    </div>
</div>

<!-- ============================================ -->
<!-- SEARCH + EXPORT + IMPORT BUTTONS -->
<!-- ============================================ -->
<div class="search-section">
    <h2 class="section-title">🔍 SEARCH STUDENTS</h2>
    <div class="search-info" style="margin-bottom: 15px;">
        💡 <strong>Live Search:</strong> Start typing and results will appear instantly.
    </div>
    <div class="search-box">
        <div class="search-input">
            <input type="text" id="searchInput" placeholder="🔍 Search by Student ID, Name, or Phone..." 
                   onkeyup="liveSearch()">
        </div>
        <a href="students.php" class="btn-reset" onclick="return resetSearch()">🔄 Reset All</a>
        <button class="btn-export" onclick="exportToCSV()">📥 Export to CSV</button>
        <button class="btn-import" onclick="openImportModal()">📥 Bulk Import (CSV)</button>
    </div>
    <div class="search-info" id="searchInfo">
        Showing all <?php echo $total_students; ?> students
        <span id="loadingIndicator" style="display: none; margin-left: 10px;">⏳ Searching...</span>
    </div>
</div>

<!-- ============================================ -->
<!-- STUDENTS TABLE -->
<!-- ============================================ -->
<div class="card">
    <h2>📋 All Students</h2>
    <p style="margin-bottom: 10px;">Total: <span id="resultCount"><?php echo $total_students; ?></span> students</p>
    
    <div style="overflow-x: auto;">
        <table id="studentTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Gender</th>
                    <th>Class</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Average</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="studentTableBody">
                <?php if ($total_students > 0): ?>
                    <?php while($student = mysqli_fetch_assoc($students_result)): 
                        $avg = round($student['avg_marks'] ?? 0, 1);
                        $status = getStudentStatus($avg);
                    ?>
                    <tr>
                        <td><?php echo $student['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($student['student_id'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($student['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($student['login_username'] ?: '-'); ?></td>
                        <td>
                            <?php if (!empty($student['login_username'])): ?>
                                <code class="pw"><?php echo htmlspecialchars($student['login_password'] ?? ''); ?></code>
                                <a href="students.php?reset_pw=<?php echo $student['id']; ?>" class="reset-btn"
                                   onclick="return confirm('Reset this password back to the default (name + 123)?')">🔑 Reset</a>
                            <?php else: ?>
                                <span class="no-login">no login</span>
                                <a href="students.php?make_login=<?php echo $student['id']; ?>" class="make-btn"
                                   onclick="return confirm('Create a login for this student?')">➕ Create</a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $student['gender'] == 'M' ? '👨 Male' : '👩 Female'; ?></td>
                        <td><?php echo htmlspecialchars($student['class_name'] ?: 'Not Assigned'); ?></td>
                        <td><?php echo isset($student['parent_phone']) && $student['parent_phone'] ? htmlspecialchars($student['parent_phone'] ?? '') : '-'; ?></td>
                        <td><?php echo isset($student['address']) && $student['address'] ? htmlspecialchars(substr($student['address'], 0, 30)) : '-'; ?></td>
                        <td><?php echo $avg; ?>%</td>
                        <td class="<?php echo $status['color'] == 'green' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $status['icon'] . ' ' . $status['text']; ?>
                         </td>
                        <td class="action-buttons">
                            <button class="view-btn" onclick="viewStudent(<?php echo $student['id']; ?>)">👁 VIEW</button>
                            <a href="students.php?edit=<?php echo $student['id']; ?>" class="edit-btn">✏️ EDIT</a>
                            <a href="students.php?delete=<?php echo $student['id']; ?>" class="delete-btn" onclick="return confirmDelete(<?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>', '<?php echo addslashes($student['student_id']); ?>')">🗑 DELETE</a>
                         </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align: center;">❌ No students found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================ -->
<!-- BULK IMPORT MODAL -->
<!-- ============================================ -->
<div id="importModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📥 Bulk Import Students (CSV)</h2>
            <span class="close" onclick="closeImportModal()">&times;</span>
        </div>
        
        <div class="search-info" style="margin-bottom: 15px;">
            💡 <strong>Instructions:</strong> Upload a CSV file with the following format:
            <br>• <strong>Headers:</strong> student_id,name,gender,class_id,parent_phone,address
            <br>• <strong>Example:</strong> ABC010/26,Ahmed Hassan,M,1,1234567890,123 Main St
            <br>• <strong>Note:</strong> Login accounts will be created automatically for each student.
        </div>
        
        <input type="file" id="csvFile" accept=".csv" style="margin: 15px 0; width: 100%; padding: 10px;">
        <button class="btn" onclick="previewCSV()">👁 Preview Data</button>
        
        <div id="previewContainer" class="import-preview" style="display: none;">
            <h4 style="margin-top: 15px;">Preview (First 10 rows):</h4>
            <div id="previewTable"></div>
            <button class="btn-import" onclick="confirmImport()" style="margin-top: 15px;">✅ Confirm Import</button>
        </div>
        
        <form id="importForm" method="POST" style="display: none;">
            <input type="hidden" name="bulk_import" value="1">
            <input type="hidden" name="import_data" id="importData">
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- VIEW STUDENT MODAL -->
<!-- ============================================ -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>👁 Student Details</h2>
            <span class="close" onclick="closeViewModal()">&times;</span>
        </div>
        <div id="viewDetails"></div>
    </div>
</div>

<script>
    let actionCount = 0;
    let searchTimeout = null;
    let importData = [];
    
    function updateActionCount() {
        actionCount++;
        document.getElementById('functionCalls').textContent = actionCount;
    }
    
    // ============================================
    // CONFIRM DELETE WITH DETAILS
    // ============================================
    function confirmDelete(id, name, studentId) {
        return confirm(
            '⚠️ Delete Student?\n\n' +
            'Student: ' + name + '\n' +
            'ID: ' + studentId + '\n\n' +
            'This will permanently delete:\n' +
            '• The student record\n' +
            '• All marks for this student\n' +
            '• All attendance records\n' +
            '• The student\'s login account\n\n' +
            'This action CANNOT be undone!\n\n' +
            'Click OK to delete or Cancel to abort.'
        );
    }
    
    // ============================================
    // LIVE SEARCH
    // ============================================
    function liveSearch() {
        const keyword = document.getElementById('searchInput').value;
        document.getElementById('loadingIndicator').style.display = 'inline-block';
        
        if (searchTimeout) clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(function() {
            fetch('search_students_ajax.php?q=' + encodeURIComponent(keyword))
                .then(response => response.json())
                .then(data => {
                    updateTable(data.students);
                    document.getElementById('resultCount').textContent = data.total;
                    document.getElementById('totalStudents').textContent = data.total;
                    if (keyword !== '') {
                        document.getElementById('searchInfo').innerHTML = `🔍 Found ${data.total} result(s) for "${escapeHtml(keyword)}"`;
                    } else {
                        document.getElementById('searchInfo').innerHTML = `Showing all ${data.total} students`;
                    }
                    document.getElementById('loadingIndicator').style.display = 'none';
                    updateActionCount();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loadingIndicator').style.display = 'none';
                });
        }, 300);
    }
    
    function updateTable(students) {
        const tbody = document.getElementById('studentTableBody');
        tbody.innerHTML = '';
        if (students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align: center;">❌ No students found</td></tr>';
            return;
        }
        for (let student of students) {
            const avg = parseFloat(student.avg_marks).toFixed(1) || 0;
            const status = avg >= 50 ? 'Active' : 'Inactive';
            const statusColor = avg >= 50 ? 'green' : 'red';
            const statusIcon = avg >= 50 ? '●' : '○';
            const genderIcon = student.gender === 'M' ? '👨 Male' : '👩 Female';
            const row = `
                <tr>
                    <td>${student.id}</td>
                    <td><strong>${escapeHtml(student.student_id)}</strong></td>
                    <td>${escapeHtml(student.name)}</td>
                    <td>${genderIcon}</td>
                    <td>${escapeHtml(student.class_name || 'Not Assigned')}</td>
                    <td>${escapeHtml(student.parent_phone || '-')}</td>
                    <td>${escapeHtml((student.address || '-').substring(0, 30))}</td>
                    <td>${avg}%</td>
                    <td style="color: ${statusColor}">${statusIcon} ${status}</td>
                    <td class="action-buttons">
                        <button class="view-btn" onclick="viewStudent(${student.id})">👁 VIEW</button>
                        <a href="students.php?edit=${student.id}" class="edit-btn">✏️ EDIT</a>
                        <a href="students.php?delete=${student.id}" class="delete-btn" onclick="return confirmDelete(${student.id}, '${escapeHtml(student.name)}', '${escapeHtml(student.student_id)}')">🗑 DELETE</a>
                    </td>
                </tr>
            `;
            tbody.innerHTML += row;
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
    
    function resetSearch() {
        document.getElementById('searchInput').value = '';
        liveSearch();
        return false;
    }
    
    // ============================================
    // EXPORT TO CSV
    // ============================================
    function exportToCSV() {
        const rows = document.querySelectorAll('#studentTableBody tr');
        let csv = "ID,Student ID,Name,Gender,Class,Phone,Address,Average,Status\n";
        rows.forEach(row => {
            if (row.cells && row.cells.length >= 9) {
                const rowData = [
                    row.cells[0]?.innerText || '',
                    row.cells[1]?.innerText || '',
                    row.cells[2]?.innerText || '',
                    row.cells[3]?.innerText || '',
                    row.cells[4]?.innerText || '',
                    row.cells[5]?.innerText || '',
                    row.cells[6]?.innerText || '',
                    row.cells[7]?.innerText || '',
                    row.cells[8]?.innerText || ''
                ];
                csv += rowData.join(',') + '\n';
            }
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'students_export_' + new Date().toISOString().split('T')[0] + '.csv';
        a.click();
        URL.revokeObjectURL(url);
        showTemporaryMessage('✅ Exported to CSV');
    }
    
    // ============================================
    // BULK IMPORT FUNCTIONS
    // ============================================
    function openImportModal() {
        document.getElementById('importModal').style.display = 'flex';
        document.getElementById('previewContainer').style.display = 'none';
        document.getElementById('csvFile').value = '';
        importData = [];
    }
    
    function closeImportModal() {
        document.getElementById('importModal').style.display = 'none';
    }
    
    function previewCSV() {
        const file = document.getElementById('csvFile').files[0];
        if (!file) {
            alert('Please select a CSV file first!');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            const lines = content.split('\n');
            
            importData = [];
            // Skip header row (first line)
            for (let i = 1; i < lines.length; i++) {
                if (lines[i].trim() === '') continue;
                const values = lines[i].split(',');
                if (values.length >= 3) {
                    importData.push({
                        student_id: values[0]?.trim() || '',
                        name: values[1]?.trim() || '',
                        gender: values[2]?.trim() || '',
                        class_id: parseInt(values[3]) || 1,
                        parent_phone: values[4]?.trim() || '',
                        address: values[5]?.trim() || ''
                    });
                }
            }
            
            // Display preview
            let previewHtml = '<table class="preview-table"><thead><tr>';
            previewHtml += '<th>Student ID</th><th>Name</th><th>Gender</th><th>Class ID</th><th>Phone</th><th>Address</th>';
            previewHtml += '</tr></thead><tbody>';
            
            for (let i = 0; i < Math.min(importData.length, 10); i++) {
                const row = importData[i];
                previewHtml += `<tr>
                    <td>${escapeHtml(row.student_id)}</td>
                    <td>${escapeHtml(row.name)}</td>
                    <td>${escapeHtml(row.gender)}</td>
                    <td>${escapeHtml(row.class_id)}</td>
                    <td>${escapeHtml(row.parent_phone)}</td>
                    <td>${escapeHtml(row.address)}</td>
                </tr>`;
            }
            previewHtml += '</tbody></table>';
            previewHtml += `<p style="margin-top:10px"><strong>Total records found:</strong> ${importData.length}</p>`;
            
            // Add warning if class_id is invalid
            const validClassIds = <?php echo json_encode(array_column($classes_list, 'id')); ?>;
            const invalidClasses = importData.filter(row => !validClassIds.includes(row.class_id));
            if (invalidClasses.length > 0) {
                previewHtml += `<p style="color: red;"><strong>⚠️ Warning:</strong> ${invalidClasses.length} record(s) have invalid class_id. Please check your class_id values.</p>`;
            }
            
            document.getElementById('previewTable').innerHTML = previewHtml;
            document.getElementById('previewContainer').style.display = 'block';
        };
        reader.readAsText(file);
    }
    
    function confirmImport() {
        if (importData.length === 0) {
            alert('No data to import!');
            return;
        }
        
        // Validate class_ids
        const validClassIds = <?php echo json_encode(array_column($classes_list, 'id')); ?>;
        const invalidClasses = importData.filter(row => !validClassIds.includes(row.class_id));
        if (invalidClasses.length > 0) {
            if (!confirm(`Warning: ${invalidClasses.length} record(s) have invalid class_id. These will be skipped. Continue?`)) {
                return;
            }
        }
        
        if (confirm(`Are you sure you want to import ${importData.length} students?\n\nLogin accounts will be created automatically for each student.`)) {
            document.getElementById('importData').value = JSON.stringify(importData);
            document.getElementById('importForm').submit();
        }
    }
    
    // ============================================
    // VIEW STUDENT DETAILS
    // ============================================
    function viewStudent(id) {
        updateActionCount();
        fetch('get_student_details.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const s = data.student;
                    const avg = s.avg_marks ? parseFloat(s.avg_marks).toFixed(1) : 0;
                    const status = avg >= 50 ? 'Active' : 'Inactive';
                    const statusColor = avg >= 50 ? 'green' : 'red';
                    const grade = getGradeLetter(avg);
                    const detailsHtml = `
                        <div class="detail-item"><span class="detail-label">Student ID:</span> ${escapeHtml(s.student_id)}</div>
                        <div class="detail-item"><span class="detail-label">Full Name:</span> ${escapeHtml(s.name)}</div>
                        <div class="detail-item"><span class="detail-label">Gender:</span> ${s.gender == 'M' ? 'Male' : 'Female'}</div>
                        <div class="detail-item"><span class="detail-label">Class:</span> ${escapeHtml(s.class_name || 'Not Assigned')}</div>
                        <div class="detail-item"><span class="detail-label">Parent Phone:</span> ${escapeHtml(s.parent_phone || '-')}</div>
                        <div class="detail-item"><span class="detail-label">Address:</span> ${escapeHtml(s.address || '-')}</div>
                        <div class="detail-item"><span class="detail-label">Average:</span> ${avg}%</div>
                        <div class="detail-item"><span class="detail-label">Grade:</span> ${grade}</div>
                        <div class="detail-item"><span class="detail-label">Status:</span> <span style="color: ${statusColor}">${status}</span></div>
                    `;
                    document.getElementById('viewDetails').innerHTML = detailsHtml;
                    document.getElementById('viewModal').style.display = 'flex';
                } else {
                    alert('Student not found');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading student details');
            });
    }
    
    function getGradeLetter(percentage) {
        if (percentage >= 90) return 'A+';
        if (percentage >= 80) return 'A';
        if (percentage >= 75) return 'B+';
        if (percentage >= 70) return 'B';
        if (percentage >= 60) return 'C';
        if (percentage >= 50) return 'D';
        return 'F';
    }
    
    function showTemporaryMessage(message) {
        const searchInfo = document.getElementById('searchInfo');
        const originalText = searchInfo.innerHTML;
        searchInfo.innerHTML = '✅ ' + message;
        setTimeout(() => {
            searchInfo.innerHTML = originalText;
        }, 2000);
    }
    
    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        const viewModal = document.getElementById('viewModal');
        const importModal = document.getElementById('importModal');
        if (event.target === viewModal) closeViewModal();
        if (event.target === importModal) closeImportModal();
    }
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