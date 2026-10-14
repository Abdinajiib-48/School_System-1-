<?php
// ============================================================
// admin/classes.php - Add, edit and remove classes (grade, year, semester, homeroom teacher).
//
// Who can open it: admin
// ============================================================
include '../config.php';

require_role('admin');

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Check if class has students
    $check = mysqli_query($conn, "SELECT * FROM students WHERE class_id = $id");
    if (mysqli_num_rows($check) > 0) {
        $message = "⚠️ Cannot delete! This class has students enrolled.";
        $message_type = "error";
    } else {
        mysqli_query($conn, "DELETE FROM classes WHERE id = $id");
        $message = "✅ Class deleted successfully!";
        $message_type = "success";
    }
}

// Does the class_teachers table exist? (created by School_db1.sql)
$has_ct = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'class_teachers'")) > 0;

// Save the list of subject teachers for a class (replaces the old list)
function save_subject_teachers($conn, $class_id, $teacher_ids) {
    global $has_ct;
    if (!$has_ct) return;
    $class_id = (int)$class_id;
    mysqli_query($conn, "DELETE FROM class_teachers WHERE class_id = $class_id");
    foreach ($teacher_ids as $tid) {
        $tid = (int)$tid;
        if ($tid > 0) {
            mysqli_query($conn, "INSERT IGNORE INTO class_teachers (class_id, teacher_id) VALUES ($class_id, $tid)");
        }
    }
}

// Handle add/edit
$message = "";
$message_type = "";
$errors = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $grade = mysqli_real_escape_string($conn, $_POST['grade']);
    $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    
    // Check if homeroom_teacher_id is empty, set to NULL
    if (isset($_POST['homeroom_teacher_id']) && $_POST['homeroom_teacher_id'] != '') {
        $homeroom_teacher_id = $_POST['homeroom_teacher_id'];
    } else {
        $homeroom_teacher_id = 'NULL';
    }
    
    // VALIDATION: Check if class name already exists (for new class)
    if (!isset($_POST['edit_id']) || $_POST['edit_id'] == '') {
        $check_class_sql = "SELECT id FROM classes WHERE grade = '$grade' AND academic_year = '$academic_year'";
        $check_class_result = mysqli_query($conn, $check_class_sql);
        if (mysqli_num_rows($check_class_result) > 0) {
            $errors[] = "❌ Class '$grade' for year '$academic_year' already exists!";
        }
    }
    
    // VALIDATION: Check if teacher is already homeroom for another class
    if ($homeroom_teacher_id != 'NULL') {
        $check_teacher_sql = "SELECT id FROM classes WHERE homeroom_teacher_id = '$homeroom_teacher_id'";
        if (isset($_POST['edit_id']) && $_POST['edit_id'] != '') {
            $check_teacher_sql .= " AND id != " . $_POST['edit_id'];
        }
        $check_teacher_result = mysqli_query($conn, $check_teacher_sql);
        if (mysqli_num_rows($check_teacher_result) > 0) {
            $busy = mysqli_fetch_assoc(mysqli_query($conn, "SELECT grade FROM classes WHERE homeroom_teacher_id = '$homeroom_teacher_id'"));
            $busy_grade = $busy ? htmlspecialchars($busy['grade'] ?? '') : 'another class';
            $errors[] = "❌ This teacher is already the <strong>homeroom teacher of class $busy_grade</strong>. A teacher can be homeroom of only ONE class.<br>👉 To give them a second class, leave the homeroom box as it is and tick their name under <strong>Subject Teachers</strong> below instead.";
        }
    }
    
    // If no errors, proceed
    if (count($errors) == 0) {
        if (isset($_POST['edit_id']) && $_POST['edit_id'] != '') {
            // Update class
            $id = $_POST['edit_id'];
            if ($homeroom_teacher_id == 'NULL') {
                $sql = "UPDATE classes SET grade='$grade', academic_year='$academic_year', semester='$semester', homeroom_teacher_id = NULL WHERE id=$id";
            } else {
                $sql = "UPDATE classes SET grade='$grade', academic_year='$academic_year', semester='$semester', homeroom_teacher_id='$homeroom_teacher_id' WHERE id=$id";
            }
            if (mysqli_query($conn, $sql)) {
                save_subject_teachers($conn, $id, isset($_POST['subject_teachers']) ? $_POST['subject_teachers'] : []);
                if ($homeroom_teacher_id != 'NULL') {
                    mysqli_query($conn, "UPDATE teachers SET is_homeroom = 1 WHERE id = '$homeroom_teacher_id'");
                }
                $message = "✅ Class updated successfully!";
                $message_type = "success";
            } else {
                $message = "❌ Error: " . mysqli_error($conn);
                $message_type = "error";
            }
        } else {
            // Insert new class
            if ($homeroom_teacher_id == 'NULL') {
                $sql = "INSERT INTO classes (grade, academic_year, semester, homeroom_teacher_id) VALUES ('$grade', '$academic_year', '$semester', NULL)";
            } else {
                $sql = "INSERT INTO classes (grade, academic_year, semester, homeroom_teacher_id) VALUES ('$grade', '$academic_year', '$semester', '$homeroom_teacher_id')";
            }
            if (mysqli_query($conn, $sql)) {
                save_subject_teachers($conn, mysqli_insert_id($conn), isset($_POST['subject_teachers']) ? $_POST['subject_teachers'] : []);
                if ($homeroom_teacher_id != 'NULL') {
                    mysqli_query($conn, "UPDATE teachers SET is_homeroom = 1 WHERE id = '$homeroom_teacher_id'");
                }
                $message = "✅ Class added successfully!";
                $message_type = "success";
            } else {
                $message = "❌ Error: " . mysqli_error($conn);
                $message_type = "error";
            }
        }
    } else {
        // Build error message with HTML
        $message = "<ul style='margin:0; padding-left:20px;'>";
        foreach ($errors as $error) {
            $message .= "<li>$error</li>";
        }
        $message .= "</ul>";
        $message_type = "error";
    }
}

// Get all classes with teacher names
$classes_sql = "SELECT c.*, t.name as teacher_name 
                FROM classes c 
                LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id 
                ORDER BY c.grade";
$classes_result = mysqli_query($conn, $classes_sql);

// Get teachers for dropdown
// Show EVERY teacher. Teachers who already lead another class are marked,
// so it is obvious why they cannot be picked again.
$teachers_sql = "SELECT t.*, (SELECT c2.grade FROM classes c2 WHERE c2.homeroom_teacher_id = t.id LIMIT 1) AS homeroom_of
                 FROM teachers t ORDER BY t.name";
$teachers_result = mysqli_query($conn, $teachers_sql);

// All teachers (with their subject) for the "Subject Teachers" checkboxes
$all_teachers = [];
$at_res = mysqli_query($conn, "SELECT t.id, t.name, s.name AS subject_name
                               FROM teachers t LEFT JOIN subjects s ON t.subject_id = s.id
                               ORDER BY t.name");
while ($row = mysqli_fetch_assoc($at_res)) {
    $all_teachers[] = $row;
}

// Subject teachers currently assigned to each class: [class_id => [teacher names]]
$class_subject_teachers = [];
if ($has_ct) {
    $ct_res = mysqli_query($conn, "SELECT ct.class_id, t.name
                                   FROM class_teachers ct JOIN teachers t ON ct.teacher_id = t.id
                                   ORDER BY t.name");
    while ($row = mysqli_fetch_assoc($ct_res)) {
        $class_subject_teachers[$row['class_id']][] = $row['name'];
    }
}

// Get class for editing
$edit_class = null;
$edit_subject_teacher_ids = [];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_sql = "SELECT * FROM classes WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_sql);
    $edit_class = mysqli_fetch_assoc($edit_result);

    if ($edit_class && $has_ct) {
        $e_res = mysqli_query($conn, "SELECT teacher_id FROM class_teachers WHERE class_id = $edit_id");
        while ($row = mysqli_fetch_assoc($e_res)) {
            $edit_subject_teacher_ids[] = (int)$row['teacher_id'];
        }
    }
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
    
    .close-btn {
        float: right;
        font-size: 20px;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
        color: inherit;
        padding: 0;
        margin: -5px -5px 0 0;
        width: auto;
    }
    
    .close-btn:hover {
        opacity: 0.7;
        background: none;
    }
    
    .howto {
        background: #e7f3fe; border-left: 5px solid #2196F3; color: #0d47a1;
        border-radius: 8px; padding: 16px 20px; margin-bottom: 22px; font-size: 14px; line-height: 1.8;
    }
    .howto .t { font-weight: bold; margin-bottom: 4px; }
    .howto ul { list-style: none; padding: 0; margin: 0; }
    .howto li::before { content: "\2022  "; }

    .teacher-checks {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 8px;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: #fafafa;
    }

    .teacher-checks label {
        font-weight: normal;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        cursor: pointer;
    }

    .teacher-checks input[type="checkbox"] {
        width: auto;
    }

    .subject-teacher-tag {
        display: inline-block;
        background: #fff3e0;
        color: #e65100;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
        margin: 2px 2px 2px 0;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>" id="alertMessage">
        <button class="close-btn" onclick="closeAlert()">✕</button>
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $edit_class ? '✏️ Edit Class' : '➕ Add New Class'; ?></h2>

    <div class="howto">
        <div class="t">📌 How a teacher gets a class:</div>
        <ul>
            <li><strong>Homeroom Teacher</strong> — the class owner. A teacher can be homeroom of <strong>ONE class only</strong>.</li>
            <li><strong>Subject Teacher</strong> — ticked in the list below. A teacher can be subject teacher of <strong>as many classes as you like</strong>.</li>
            <li>👉 To give one teacher <strong>two classes</strong>: make them homeroom of the first class, then open the second class and tick their name under <strong>Subject Teachers</strong>.</li>
        </ul>
    </div>
    
    <form method="POST" action="">
        <?php if ($edit_class): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_class['id']; ?>">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Class Name: <span style="color:red;">*</span></label>
                <input type="text" name="grade" value="<?php echo $edit_class ? $edit_class['grade'] : ''; ?>" placeholder="e.g., 9A, 10B" required>
                <small style="color:#666;">Example: 9A, 9B, 10A, 11C</small>
            </div>
            
            <div class="form-group">
                <label>Academic Year: <span style="color:red;">*</span></label>
                <input type="text" name="academic_year" value="<?php echo $edit_class ? $edit_class['academic_year'] : '2026'; ?>" placeholder="e.g., 2026" required>
                <small style="color:#666;">Example: 2026, 2025, 2024</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Semester: <span style="color:red;">*</span></label>
                <select name="semester" required>
                    <option value="I" <?php echo ($edit_class && $edit_class['semester'] == 'I') ? 'selected' : ''; ?>>Semester I</option>
                    <option value="II" <?php echo ($edit_class && $edit_class['semester'] == 'II') ? 'selected' : ''; ?>>Semester II</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Homeroom Teacher: <span style="color:#666;">(Optional)</span></label>
                <select name="homeroom_teacher_id">
                    <option value="">-- No Teacher Assigned --</option>
                    <?php mysqli_data_seek($teachers_result, 0); ?>
                    <?php while($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                        <?php
                        $is_this_class  = $edit_class && $edit_class['homeroom_teacher_id'] == $teacher['id'];
                        $busy_elsewhere = !empty($teacher['homeroom_of']) && !$is_this_class;
                        ?>
                        <option value="<?php echo $teacher['id']; ?>"
                            <?php echo $is_this_class ? 'selected' : ''; ?>
                            <?php echo $busy_elsewhere ? 'disabled' : ''; ?>>
                            <?php echo htmlspecialchars($teacher['name'] ?? ''); ?>
                            <?php echo $busy_elsewhere ? ' — already homeroom of ' . htmlspecialchars($teacher['homeroom_of'] ?? '') : ''; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small style="color:#666;">One class = one homeroom teacher. Greyed-out teachers already lead another class.</small>
            </div>
        </div>
        
        <div class="form-group">
            <label>Subject Teachers: <span style="color:#666;">(Optional – teachers who also teach this class)</span></label>
            <?php if ($has_ct): ?>
                <div class="teacher-checks">
                    <?php foreach ($all_teachers as $t): ?>
                        <label>
                            <input type="checkbox" name="subject_teachers[]" value="<?php echo $t['id']; ?>"
                                <?php echo in_array((int)$t['id'], $edit_subject_teacher_ids) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($t['name'] ?? ''); ?>
                            <?php if ($t['subject_name']): ?>
                                <small style="color:#888;">(<?php echo htmlspecialchars($t['subject_name'] ?? ''); ?>)</small>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small style="color:#666;">Checked teachers can enter marks, take attendance and view reports for this class.</small>
            <?php else: ?>
                <small style="color:#c00;">Table <strong>class_teachers</strong> not found. Import <strong>School_db1.sql</strong> in phpMyAdmin first.</small>
            <?php endif; ?>
        </div>

        <button type="submit"><?php echo $edit_class ? 'Update Class' : 'Add Class'; ?></button>
        <?php if ($edit_class): ?>
            <a href="classes.php" style="margin-left: 10px; text-decoration: none; color: #666;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>📋 All Classes</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Class</th>
                <th>Academic Year</th>
                <th>Semester</th>
                <th>Homeroom Teacher</th>
                <th>Subject Teachers</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php mysqli_data_seek($classes_result, 0); ?>
            <?php while($class = mysqli_fetch_assoc($classes_result)): ?>
            <tr>
                <td><?php echo $class['id']; ?></td>
                <td><strong><?php echo $class['grade']; ?></strong></td>
                <td><?php echo $class['academic_year']; ?></td>
                <td><?php echo $class['semester']; ?></td>
                <td><?php echo $class['teacher_name'] ?: '<span style="color:#999;">Not Assigned</span>'; ?></td>
                <td>
                    <?php if (!empty($class_subject_teachers[$class['id']])): ?>
                        <?php foreach ($class_subject_teachers[$class['id']] as $tn): ?>
                            <span class="subject-teacher-tag"><?php echo htmlspecialchars($tn ?? ''); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#999;">None</span>
                    <?php endif; ?>
                </td>
                <td class="action-buttons">
                    <a href="classes.php?edit=<?php echo $class['id']; ?>" class="edit-btn">Edit</a>
                    <a href="classes.php?delete=<?php echo $class['id']; ?>" class="delete-btn" onclick="return confirm('Delete this class? Students will be affected!')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    function closeAlert() {
        var alert = document.getElementById('alertMessage');
        if (alert) {
            alert.style.animation = 'slideUp 0.3s ease';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }
    }
    
    // Add slideUp animation
    var style = document.createElement('style');
    style.innerHTML = `
        @keyframes slideUp {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(-20px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
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