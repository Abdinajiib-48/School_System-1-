<?php
// ============================================================
// teacher/dashboard.php - Teacher home - their classes, today's lessons and notices.
//
// Who can open it: teacher
// ============================================================
include '../config.php';

require_role('teacher');

// Get teacher info
$user_id = $_SESSION['user_id'];
$sql = "SELECT t.* FROM teachers t WHERE t.user_id = '$user_id'";
$result = mysqli_query($conn, $sql);
$teacher = mysqli_fetch_assoc($result);

// Get department name for this teacher
$dept_sql = "SELECT d.name FROM departments d 
             JOIN teachers t ON t.department_id = d.id 
             WHERE t.id = '{$teacher['id']}'";
$dept_result = mysqli_query($conn, $dept_sql);
$dept = mysqli_fetch_assoc($dept_result);
$dept_name = $dept ? $dept['name'] : 'Not Assigned';

// Get ALL classes assigned to this teacher (homeroom + subject teacher)
include 'teacher_classes.php';

// Count students across all assigned classes
$students_count = 0;
if (count($all_classes) > 0) {
    $ids = implode(',', array_keys($all_classes));
    $count_sql = "SELECT COUNT(*) as total FROM students WHERE class_id IN ($ids)";
    $count_result = mysqli_query($conn, $count_sql);
    $students_count = mysqli_fetch_assoc($count_result)['total'];
}

// Include teacher sidebar
include 'sidebar.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-number {
        font-size: 48px;
        font-weight: bold;
        color: #4CAF50;
        margin: 10px 0;
    }
    
    .stat-label {
        color: #666;
        font-size: 16px;
    }
    
    .stat-icon {
        font-size: 40px;
    }
    
    /* Welcome Card - WHITE BACKGROUND */
    .welcome-card {
        background: white;
        color: #333;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border-left: 5px solid #4CAF50;
    }
    
    .welcome-card h2 {
        font-size: 24px;
        margin-bottom: 10px;
        color: #333;
    }
    
    .welcome-card p {
        color: #666;
    }
    
    .quick-actions {
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .quick-actions h2 {
        margin-bottom: 20px;
        color: #333;
    }
    
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .action-btn {
        display: block;
        padding: 15px;
        background: #f0f2f5;
        color: #333;
        text-decoration: none;
        text-align: center;
        border-radius: 10px;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .action-btn:hover {
        background: #4CAF50;
        color: white;
        transform: translateY(-3px);
    }
</style>

<div class="welcome-card">
    <h2>Welcome, <?php echo $teacher['name']; ?>! 👋</h2>
    <p>Department: <?php echo $dept_name; ?></p>
    <?php if (count($all_classes) > 0): ?>
        <p>My Classes:
        <?php $parts = [];
        foreach ($all_classes as $cls) {
            $parts[] = htmlspecialchars($cls['grade'] ?? '') . ' (' . htmlspecialchars($cls['academic_year'] ?? '') . ') – ' . $cls['teacher_role'];
        }
        echo implode(' &nbsp;|&nbsp; ', $parts); ?>
        </p>
    <?php else: ?>
        <p>No class assigned yet.</p>
    <?php endif; ?>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">👨‍🎓</div>
        <div class="stat-number"><?php echo $students_count; ?></div>
        <div class="stat-label">Students in My Classes</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">🏫</div>
        <div class="stat-number"><?php echo count($all_classes); ?></div>
        <div class="stat-label">My Classes</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-number" id="subjectsCount">-</div>
        <div class="stat-label">My Subjects</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📄</div>
        <div class="stat-number">📊</div>
        <div class="stat-label">Reports</div>
    </div>
</div>

<div class="quick-actions">
    <h2>⚡ Quick Actions</h2>
    <div class="action-grid">
        <a href="enter_marks.php" class="action-btn">📝 Enter Marks</a>
        <a href="attendance.php" class="action-btn">📋 Take Attendance</a>
        <a href="my_class.php" class="action-btn">👥 My Class</a>
        <a href="report.php" class="action-btn">📊 Generate Report</a>
    </div>
</div>

<script>
    // Get teacher's subjects count
    fetch('get_teacher_subjects.php')
        .then(response => response.json())
        .then(data => {
            if (data.count) {
                document.getElementById('subjectsCount').textContent = data.count;
            } else {
                document.getElementById('subjectsCount').textContent = '0';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('subjectsCount').textContent = '?';
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