<?php
// ============================================================
// teacher/my_class.php - The selected class at a glance - every student with their marks.
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

// Get ALL classes assigned to this teacher (homeroom + subject teacher) and the selected one
include 'teacher_classes.php';

if (!$class) {
    include 'sidebar.php';
    render_no_class_box();
    echo '</div></div></body></html>';
    exit();
}

// Get all subjects
include_once '../subjects_helper.php';
$subjects_result = mysqli_query($conn, teacher_subject_sql($conn, $teacher['id']));
$subjects = [];
while ($sub = mysqli_fetch_assoc($subjects_result)) {
    $subjects[] = $sub;
}

include_once '../results_helper.php';
include_once '../assessments_helper.php';

$types   = assessment_types($conn);
$all_raw = class_marks_raw($conn, $class['id']);

// Get all students - ONE PER STUDENT
$students_sql = "SELECT s.id, s.student_id, s.name, s.gender 
                 FROM students s 
                 WHERE s.class_id = '{$class['id']}'
                 ORDER BY s.name";
$students_result = mysqli_query($conn, $students_sql);
$students = [];

while ($student = mysqli_fetch_assoc($students_result)) {
    // Component marks -> one score per subject (only for finished subjects)
    $raw   = isset($all_raw[(int)$student['id']]) ? $all_raw[(int)$student['id']] : [];
    $marks = subject_scores_from_raw($raw, $types);

    $student['raw']   = $raw;
    $student['marks'] = $marks;

    // Total / average / PASS / FAIL - but ONLY when every subject
    // has a mark. One missing subject and nothing is calculated.
    $student = array_merge($student, calc_result($marks, $subjects));

    $students[] = $student;
}

// How many are still waiting for marks?
$incomplete_count = 0;
foreach ($students as $st) { if (!$st['complete']) $incomplete_count++; }

// Include sidebar
include 'sidebar.php';
?>

<style>
    .card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    
    h2 {
        margin-bottom: 20px;
        color: #333;
        font-size: 22px;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    th, td {
        border: 1px solid #ddd;
        padding: 12px;
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
    
    .no-marks {
        color: #999;
        font-style: italic;
    }
    
    .pass {
        color: green;
        font-weight: bold;
    }
    
    .fail {
        color: red;
        font-weight: bold;
    }
    
    .back-link {
        display: inline-block;
        margin-top: 20px;
        color: #4CAF50;
        text-decoration: none;
    }
    
    .stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        background: #e8f5e9;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
    }
    
    .stat-number {
        font-size: 28px;
        font-weight: bold;
        color: #4CAF50;
    }
    
    .class-info {
        margin-bottom: 15px;
        padding: 15px;
        background: #f0f2f5;
        border-radius: 10px;
        border-left: 4px solid #4CAF50;
    }
</style>
<?php result_styles(); ?>

<?php render_class_selector($all_classes, $selected_class_id, 'my_class.php'); ?>

<div class="card">
    <h2>📊 Class Information - <?php echo $class['grade']; ?></h2>
    
    <div class="stats">
        <div class="stat-box">
            <div class="stat-number"><?php echo count($students); ?></div>
            <div>Total Students</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo count($subjects); ?></div>
            <div>Subjects</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php
                                        // only students with every subject marked can be counted
                                        $pass_count = 0;
                                        foreach ($students as $s) {
                                            if ($s['complete'] && $s['status'] == 'PASS') $pass_count++;
                                        }
                                        echo $pass_count;
                                        ?></div>
            <div>Passing Students</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $incomplete_count; ?></div>
            <div>Waiting for Marks</div>
        </div>
    </div>

    <?php if ($incomplete_count > 0): ?>
        <div class="missing-note">
            ⚠️ <strong><?php echo $incomplete_count; ?></strong>
            student<?php echo $incomplete_count === 1 ? '' : 's'; ?>
            <?php echo $incomplete_count === 1 ? 'does' : 'do'; ?> not have a mark in every subject yet,
            so no total, average or PASS / FAIL is shown for them.
            Enter the missing marks and the result appears by itself.
        </div>
    <?php endif; ?>

    <div class="class-info">
        <strong>📅 Academic Year:</strong> <?php echo $class['academic_year']; ?> &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>📆 Semester:</strong> <?php echo $class['semester']; ?> &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>👨‍🏫 Teacher:</strong> <?php echo $teacher['name']; ?> (<?php echo $class['teacher_role']; ?>)
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Name</th>
                <th>Gender</th>
                <?php foreach ($subjects as $subject): ?>
                    <th><?php echo $subject['name']; ?></th>
                <?php endforeach; ?>
                <th>Total</th>
                <th>Average</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $count = 1;
            foreach ($students as $student): ?>
                <tr class="<?php echo $student['complete'] ? '' : 'row-incomplete'; ?>">
                    <td><?php echo $count++; ?></td>
                    <td><?php echo $student['student_id']; ?></td>
                    <td><strong><?php echo $student['name']; ?></strong></td>
                    <td><?php echo $student['gender'] == 'M' ? '👨 Male' : '👩 Female'; ?></td>

                    <?php foreach ($subjects as $subject):
                        $mark = isset($student['marks'][$subject['id']]) ? $student['marks'][$subject['id']] : null;
                    ?>
                        <td>
                            <?php if ($mark !== null): ?>
                                <?php echo $mark; ?>
                                <?php if ($mark < 50): ?>
                                    <span style="color: red;">⚠️</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="no-marks">Not entered</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>

                    <td><strong><?php echo result_cell($student['total']); ?></strong></td>
                    <td><?php echo result_cell($student['average'], 1); ?></td>
                    <td><?php echo result_status_badge($student); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
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