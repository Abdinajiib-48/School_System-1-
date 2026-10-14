<?php
// ============================================================
// admin/reports.php - Class report card for every student, ready to print.
//
// Who can open it: admin
//
// Totals and PASS / FAIL come from results_helper.php, which only
// calculates a result once every subject has a mark.
// ============================================================
include '../config.php';
include_once '../settings_helper.php';

require_role('admin');

// Get all classes for filter (show with semester info)
$classes_sql = "SELECT c.*, t.name as teacher_name 
                FROM classes c 
                LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id 
                ORDER BY c.grade, c.semester";
$classes_result = mysqli_query($conn, $classes_sql);

// Get selected class and semester
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$selected_semester = isset($_GET['semester']) ? $_GET['semester'] : '';

$class_info = null;
if ($selected_class && $selected_semester) {
    $class_info_sql = "SELECT c.*, t.name as teacher_name 
                       FROM classes c 
                       LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id 
                       WHERE c.id = $selected_class AND c.semester = '$selected_semester'";
    $class_info_result = mysqli_query($conn, $class_info_sql);
    $class_info = mysqli_fetch_assoc($class_info_result);
}

// Get subjects
$subjects_sql = "SELECT * FROM subjects ORDER BY id";
$subjects_result = mysqli_query($conn, $subjects_sql);
$subjects = [];
while($sub = mysqli_fetch_assoc($subjects_result)) {
    $subjects[] = $sub;
}

include_once '../results_helper.php';
include_once '../assessments_helper.php';

// Get students and their marks for selected class and semester
$students_data = [];
if ($selected_class && $selected_semester) {
    // Get all students in the selected class (ONCE per student)
    $students_sql = "SELECT s.id, s.student_id, s.name, s.gender
                     FROM students s
                     WHERE s.class_id = $selected_class
                     ORDER BY s.name";
    $students_result = mysqli_query($conn, $students_sql);

    // The assessment columns (Assignment / Mid Exam / Final Exam), and
    // every mark in this class fetched once instead of per student.
    $types   = assessment_types($conn);
    $all_raw = class_marks_raw($conn, $selected_class);
    
    while($student = mysqli_fetch_assoc($students_result)) {
        // Component marks -> one score per subject. A subject that still
        // has an assessment missing does not appear here, so it counts as
        // unmarked - exactly as a missing subject always has.
        $raw   = isset($all_raw[(int)$student['id']]) ? $all_raw[(int)$student['id']] : [];
        $marks = subject_scores_from_raw($raw, $types);

        $student['raw']   = $raw;
        $student['marks'] = $marks;
        
        // Total / average / PASS / FAIL - but ONLY when every subject
        // has a mark. One missing subject and nothing is calculated.
        $student = array_merge($student, calc_result($marks, $subjects));
        
        // Add to students array (ONCE per student)
        $students_data[] = $student;
    }
    
    // Ranks. Students still missing a subject are not ranked.
    $students_data = rank_students($students_data);
}

// How many are still waiting for marks?
$incomplete_count = 0;
foreach ($students_data as $st) { if (empty($st['complete'])) $incomplete_count++; }

// Get overall stats
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM students"))['total'];
$total_teachers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM teachers"))['total'];
$total_subjects = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM subjects"))['total'];
$total_classes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM classes"))['total'];

$print = isset($_GET['print']) ? $_GET['print'] : false;

// Include sidebar
include 'sidebar.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: bold;
        color: #4CAF50;
    }
    
    .stat-label {
        color: #666;
        margin-top: 5px;
    }
    
    .filter-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .filter-card h3 {
        margin-bottom: 15px;
        color: #333;
    }
    
    .filter-row {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
        margin-top: 15px;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #555;
    }
    
    .filter-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .filter-group select:focus {
        outline: none;
        border-color: #4CAF50;
    }
    
    .report-container {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    
    .school-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .school-header h1 {
        font-size: 28px;
        margin-bottom: 5px;
        color: #333;
    }
    
    .school-header h3 {
        color: #666;
    }
    
    .class-info {
        margin-bottom: 20px;
        padding: 15px;
        background: #f9f9f9;
        border-left: 4px solid #4CAF50;
        border-radius: 8px;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 14px;
    }
    
    th, td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
    }
    
    th {
        background: #4CAF50;
        color: white;
        font-weight: bold;
    }
    
    tr:nth-child(even) {
        background: #f9f9f9;
    }
    
    .pass {
        color: green;
        font-weight: bold;
    }
    
    .fail {
        color: red;
        font-weight: bold;
    }
    
    .print-btn, .filter-btn {
        background: #4CAF50;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        margin-right: 10px;
        font-size: 14px;
        font-weight: bold;
    }
    
    .print-btn:hover, .filter-btn:hover {
        background: #45a049;
    }
    
    .filter-btn {
        background: #2196F3;
    }
    
    .filter-btn:hover {
        background: #0b7dda;
    }
    
    .back-link {
        display: inline-block;
        margin-top: 20px;
        color: #4CAF50;
        text-decoration: none;
    }
    
    .footer {
        margin-top: 30px;
        text-align: center;
        font-size: 12px;
        color: #999;
    }
    
    @media print {
        .sidebar, .top-bar, .stats-grid, .filter-card, .print-btn, .back-link {
            display: none;
        }
        body {
            background: white;
        }
        .report-container {
            box-shadow: none;
            padding: 0;
        }
        .main-content {
            margin-left: 0;
        }
        .page-content {
            padding: 0;
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    td.cell-missing { background: #fff6e5; color: #c98a00; font-weight: bold; }
</style>
<?php result_styles(); ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card"><div class="stat-number"><?php echo $total_students; ?></div><div class="stat-label">Total Students</div></div>
    <div class="stat-card"><div class="stat-number"><?php echo $total_teachers; ?></div><div class="stat-label">Total Teachers</div></div>
    <div class="stat-card"><div class="stat-number"><?php echo $total_subjects; ?></div><div class="stat-label">Total Subjects</div></div>
    <div class="stat-card"><div class="stat-number"><?php echo $total_classes; ?></div><div class="stat-label">Total Classes</div></div>
</div>

<!-- Filter by Class and Semester -->
<div class="filter-card">
    <h3>📋 Select Class and Semester to View Report</h3>
    <form method="GET" action="">
        <div class="filter-row">
            <div class="filter-group">
                <label>🏫 Select Class:</label>
                <select name="class_id" id="class_id" required>
                    <option value="">-- Select Class --</option>
                    <?php mysqli_data_seek($classes_result, 0); ?>
                    <?php while($class = mysqli_fetch_assoc($classes_result)): ?>
                        <option value="<?php echo $class['id']; ?>" 
                            data-semester="<?php echo $class['semester']; ?>"
                            <?php echo ($selected_class == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo $class['grade']; ?> (<?php echo $class['academic_year']; ?>) - Semester <?php echo $class['semester']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>📆 Semester:</label>
                <select name="semester" id="semester" required>
                    <option value="">-- Select Semester --</option>
                    <option value="I" <?php echo ($selected_semester == 'I') ? 'selected' : ''; ?>>Semester I</option>
                    <option value="II" <?php echo ($selected_semester == 'II') ? 'selected' : ''; ?>>Semester II</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="filter-btn">📊 Generate Report</button>
                <?php if ($selected_class && $selected_semester && count($students_data) > 0): ?>
                    <button type="button" class="print-btn" onclick="window.print()">🖨️ Print Report</button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if ($selected_class && $selected_semester && $class_info): ?>
    <div class="report-container">
        <div class="school-header">
            <h1><?php echo htmlspecialchars(school_name($conn)); ?></h1>
            <h3>Student Academic Report</h3>
        </div>
        
        <div class="class-info">
            <strong>Grade:</strong> <?php echo $class_info['grade']; ?> &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Academic Year:</strong> <?php echo $class_info['academic_year']; ?> &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Semester:</strong> <?php echo $class_info['semester']; ?> &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong>Homeroom Teacher:</strong> <?php echo $class_info['teacher_name'] ?: 'Not Assigned'; ?>
        </div>
        
        <?php if (count($students_data) > 0): ?>
            <?php if ($incomplete_count > 0): ?>
                <div class="missing-note no-print">
                    ⚠️ <strong><?php echo $incomplete_count; ?></strong>
                    student<?php echo $incomplete_count === 1 ? '' : 's'; ?>
                    <?php echo $incomplete_count === 1 ? 'is' : 'are'; ?> still missing marks.
                    Their total, average, rank and PASS / FAIL are left blank on purpose -
                    a result is only worked out once <strong>all <?php echo count($subjects); ?> subjects</strong>
                    have been entered.
                </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Gender</th>
                        <th>ID</th>
                        <?php foreach($subjects as $subject): ?>
                            <th><?php echo $subject['name']; ?></th>
                        <?php endforeach; ?>
                        <th>TOTAL</th>
                        <th>AVG</th>
                        <th>RANK</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 1; foreach($students_data as $student): ?>
                    <tr class="<?php echo $student['complete'] ? '' : 'row-incomplete'; ?>">
                        <td><?php echo $count++; ?></td>
                        <td><strong><?php echo $student['name']; ?></strong></td>
                        <td><?php echo $student['gender']; ?></td>
                        <td><?php echo $student['student_id']; ?></td>
                        <?php foreach($subjects as $subject): ?>
                            <?php $has = isset($student['marks'][$subject['id']]); ?>
                            <td class="<?php echo $has ? '' : 'cell-missing'; ?>">
                                <?php echo $has ? $student['marks'][$subject['id']] : '—'; ?>
                            </td>
                        <?php endforeach; ?>
                        <td><strong><?php echo result_cell($student['total']); ?></strong></td>
                        <td><?php echo result_cell($student['average'], 1); ?></td>
                        <td><strong><?php echo result_cell($student['rank']); ?></strong></td>
                        <td><?php echo result_status_badge($student); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="footer">
                <p>Subject Level Total = 100 | Overall Total = <?php echo count($subjects) * 100; ?> | Pass Mark = <?php echo PASS_MARK; ?>%</p>
                <p>A result is calculated only when all <?php echo count($subjects); ?> subjects have a mark.</p>
                <p>Report Generated on: <?php echo date('F j, Y'); ?></p>
            </div>
        <?php else: ?>
            <p style="text-align: center; padding: 50px;">No students found in this class for Semester <?php echo $selected_semester; ?>.</p>
        <?php endif; ?>
    </div>
<?php elseif ($selected_class && $selected_semester): ?>
    <div class="report-container">
        <p style="text-align: center; padding: 50px;">No class information found for the selected semester.</p>
    </div>
<?php else: ?>
    <div class="report-container">
        <p style="text-align: center; padding: 50px;">👈 Select a class and semester to view the academic report.</p>
    </div>
<?php endif; ?>

<script>
    // Auto-select semester when class is selected
    const classSelect = document.getElementById('class_id');
    const semesterSelect = document.getElementById('semester');
    
    if (classSelect) {
        classSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const semester = selectedOption.getAttribute('data-semester');
            if (semester && semesterSelect) {
                semesterSelect.value = semester;
            }
        });
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