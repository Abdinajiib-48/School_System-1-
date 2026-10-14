<?php
// ============================================================
// admin/subjects.php - Add, edit and remove subjects.
//
// Who can open it: admin
// ============================================================
include '../config.php';

require_role('admin');

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Check if subject has marks
    $check = mysqli_query($conn, "SELECT * FROM marks WHERE subject_id = $id");
    if (mysqli_num_rows($check) > 0) {
        $message = "⚠️ Cannot delete! This subject has marks recorded.";
        $message_type = "error";
    } else {
        mysqli_query($conn, "DELETE FROM subjects WHERE id = $id");
        $message = "✅ Subject deleted successfully!";
        $message_type = "success";
    }
}

// Handle add/edit
$message = "";
$message_type = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $max_marks = $_POST['max_marks'];
    $department_id = $_POST['department_id'];
    $pass_mark = $_POST['pass_mark'];
    
    if (isset($_POST['edit_id']) && $_POST['edit_id'] != '') {
        // Update
        $id = $_POST['edit_id'];
        $sql = "UPDATE subjects SET name='$name', code='$code', max_marks='$max_marks', department_id='$department_id', pass_mark='$pass_mark' WHERE id=$id";
        if (mysqli_query($conn, $sql)) {
            $message = "✅ Subject updated successfully!";
            $message_type = "success";
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = "error";
        }
    } else {
        // Insert new
        $sql = "INSERT INTO subjects (name, code, max_marks, department_id, pass_mark) VALUES ('$name', '$code', '$max_marks', '$department_id', '$pass_mark')";
        if (mysqli_query($conn, $sql)) {
            $message = "✅ Subject added successfully!";
            $message_type = "success";
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = "error";
        }
    }
}

// Get all subjects with department names
$subjects_sql = "SELECT s.*, d.name as department_name 
                 FROM subjects s 
                 LEFT JOIN departments d ON s.department_id = d.id 
                 ORDER BY s.name";
$subjects_result = mysqli_query($conn, $subjects_sql);

// Get departments for dropdown
$depts_sql = "SELECT * FROM departments ORDER BY name";
$depts_result = mysqli_query($conn, $depts_sql);

// Get subject for editing
$edit_subject = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_sql = "SELECT * FROM subjects WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_sql);
    $edit_subject = mysqli_fetch_assoc($edit_result);
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
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $edit_subject ? '✏️ Edit Subject' : '➕ Add New Subject'; ?></h2>
    <form method="POST" action="">
        <?php if ($edit_subject): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_subject['id']; ?>">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Subject Name:</label>
                <input type="text" name="name" value="<?php echo $edit_subject ? $edit_subject['name'] : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Subject Code:</label>
                <input type="text" name="code" value="<?php echo $edit_subject ? $edit_subject['code'] : ''; ?>" placeholder="e.g., MATH101">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Department:</label>
                <select name="department_id" required>
                    <option value="">Select Department</option>
                    <?php mysqli_data_seek($depts_result, 0); ?>
                    <?php while($dept = mysqli_fetch_assoc($depts_result)): ?>
                        <option value="<?php echo $dept['id']; ?>" 
                            <?php echo ($edit_subject && $edit_subject['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                            <?php echo $dept['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Max Marks:</label>
                <input type="number" name="max_marks" value="<?php echo $edit_subject ? $edit_subject['max_marks'] : '100'; ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Pass Mark (in %):</label>
            <input type="number" name="pass_mark" value="<?php echo $edit_subject ? $edit_subject['pass_mark'] : '50'; ?>" required>
        </div>
        
        <button type="submit"><?php echo $edit_subject ? 'Update Subject' : 'Add Subject'; ?></button>
        <?php if ($edit_subject): ?>
            <a href="subjects.php" style="margin-left: 10px; text-decoration: none; color: #666;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>📋 All Subjects</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Subject Name</th>
                <th>Department</th>
                <th>Max Marks</th>
                <th>Pass Mark</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php mysqli_data_seek($subjects_result, 0); ?>
            <?php while($subject = mysqli_fetch_assoc($subjects_result)): ?>
            <tr>
                <td><?php echo $subject['id']; ?></td>
                <td><?php echo $subject['code'] ?: '-'; ?></td>
                <td><strong><?php echo $subject['name']; ?></strong></td>
                <td><?php echo $subject['department_name'] ?: 'Not Assigned'; ?></td>
                <td><?php echo $subject['max_marks']; ?></td>
                <td><?php echo $subject['pass_mark']; ?>%</td>
                <td class="action-buttons">
                    <a href="subjects.php?edit=<?php echo $subject['id']; ?>" class="edit-btn">Edit</a>
                    <a href="subjects.php?delete=<?php echo $subject['id']; ?>" class="delete-btn" onclick="return confirm('Delete this subject?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

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