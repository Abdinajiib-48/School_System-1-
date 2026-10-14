<?php
// ============================================================
// admin/dashboard.php - Admin home - counts of students, teachers, classes and quick links.
//
// Who can open it: admin
// ============================================================
include '../config.php';

require_role('admin');

// Get admin info
$username = $_SESSION['username'];

// Get counts for dashboard
$teachers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM teachers"))['total'];
$students_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM students"))['total'];
$subjects_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM subjects"))['total'];
$classes_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM classes"))['total'];

// Include sidebar
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

<!-- WELCOME CARD - NOW WHITE BACKGROUND -->
<div class="welcome-card">
    <h2>Welcome, <?php echo $username; ?>! 👋</h2>
    <p>You have full access to manage the school system.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-number"><?php echo $teachers_count; ?></div>
        <div class="stat-label">Total Teachers</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👨‍🎓</div>
        <div class="stat-number"><?php echo $students_count; ?></div>
        <div class="stat-label">Total Students</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-number"><?php echo $subjects_count; ?></div>
        <div class="stat-label">Total Subjects</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">🏫</div>
        <div class="stat-number"><?php echo $classes_count; ?></div>
        <div class="stat-label">Total Classes</div>
    </div>
</div>

<div class="quick-actions">
    <h2>⚡ Quick Actions</h2>
    <div class="action-grid">
        <a href="teachers.php" class="action-btn">➕ Add New Teacher</a>
        <a href="subjects.php" class="action-btn">📖 Add New Subject</a>
        <a href="students.php" class="action-btn">👨‍🎓 View All Students</a>
        <a href="classes.php" class="action-btn">🏫 Manage Classes</a>
        <a href="reports.php" class="action-btn">📊 View All Reports</a>
    </div>
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