<?php
// ============================================================
// teacher/report.php - Printable report card for the selected class.
//
// Who can open it: teacher
//
// Shows EVERY subject, not only this teacher's, because the TOTAL and
// RANK are the student's overall standing.
// ============================================================
include '../config.php';
include_once '../settings_helper.php';

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

// EVERY subject, not only the ones this teacher takes.
//
// This is a report CARD: the TOTAL, AVERAGE, RANK and PASS / FAIL are the
// student's overall standing in the class. If the page only listed the
// teacher's own subject, a student with 76 in Maths and nothing else would
// look like a 76.0 average and a PASS - which is exactly the wrong result.
include_once '../subjects_helper.php';
$subjects_result = mysqli_query($conn, "SELECT * FROM subjects ORDER BY id");
$subjects = [];
while ($sub = mysqli_fetch_assoc($subjects_result)) {
    $subjects[] = $sub;
}

// Which of those columns does this teacher actually mark? Used to tint
// their own columns so they can see at a glance what is theirs to enter.
$my_subject_ids = teacher_subject_ids($conn, $teacher['id']);

// Get all students with their marks
$students_sql = "SELECT 
                    s.id, 
                    s.student_id, 
                    s.name, 
                    s.gender
                 FROM students s 
                 WHERE s.class_id = '{$class['id']}'
                 ORDER BY s.name";
$students_result = mysqli_query($conn, $students_sql);
$students = [];

include_once '../results_helper.php';
include_once '../assessments_helper.php';

// The assessment columns (Assignment / Mid Exam / Final Exam) and every
// mark in this class, fetched once instead of once per student.
$types    = assessment_types($conn);
$all_raw  = class_marks_raw($conn, $class['id']);

while ($student = mysqli_fetch_assoc($students_result)) {
    // Every component mark this student holds: $raw[subject_id][type_id]
    $raw = isset($all_raw[(int)$student['id']]) ? $all_raw[(int)$student['id']] : [];

    // A subject only produces a score once all its assessments are in,
    // so a half-marked subject is simply absent from $marks.
    $marks = subject_scores_from_raw($raw, $types);

    $student['raw']   = $raw;
    $student['marks'] = $marks;

    // Total / average / PASS / FAIL - but ONLY when every subject
    // has a mark. One missing subject and nothing is calculated.
    $student = array_merge($student, calc_result($marks, $subjects));

    $students[] = $student;
}

// Ranks. Students still missing a subject are not ranked.
$students = rank_students($students);

// How many are still waiting for marks?
$incomplete_count = 0;
foreach ($students as $st) { if (!$st['complete']) $incomplete_count++; }

$print = isset($_GET['print']) ? $_GET['print'] : false;

// Include sidebar
include 'sidebar.php';
?>

<style>
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
    
    .print-btn {
        background: #4CAF50;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: bold;
    }
    
    .print-btn:hover {
        background: #45a049;
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
        .sidebar, .top-bar, .print-btn, .back-link {
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
</style>

<?php result_styles(); ?>
<style>
    /* a subject with no mark yet */
    td.cell-missing { background: #fff6e5; color: #c98a00; font-weight: bold; }
    /* the columns this teacher is responsible for */
    th.my-subject { background: #2e7d32; }
    th.my-subject::after { content: " ★"; font-size: 10px; }
</style>

<?php render_class_selector($all_classes, $selected_class_id, 'report.php'); ?>

<?php if ($incomplete_count > 0): ?>
    <div class="missing-note no-print">
        ⚠️ <strong><?php echo $incomplete_count; ?></strong>
        student<?php echo $incomplete_count === 1 ? '' : 's'; ?>
        <?php echo $incomplete_count === 1 ? 'is' : 'are'; ?> still missing marks.<br>
        Their total, average, rank and PASS / FAIL are left blank on purpose -
        a result is only worked out once <strong>all <?php echo count($subjects); ?> subjects</strong>
        have been entered. Hover over “INCOMPLETE” to see which subjects are missing.
    </div>
<?php endif; ?>

<div class="report-container">
    <button class="print-btn" onclick="window.print()">🖨️ Print Report / Save as PDF</button>

    <div class="school-header">
        <h1><?php echo htmlspecialchars(school_name($conn)); ?></h1>
        <h3>Student Academic Report</h3>
    </div>

    <div class="class-info">
        <strong>Grade:</strong> <?php echo $class['grade']; ?> &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Academic Year:</strong> <?php echo $class['academic_year']; ?> &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Semester:</strong> <?php echo $class['semester']; ?> &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Teacher:</strong> <?php echo $teacher['name']; ?> (<?php echo $class['teacher_role']; ?>)
    </div>

    <?php if (count($students) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student Name</th>
                    <th>Gender</th>
                    <th>ID</th>
                    <?php foreach ($subjects as $subject): ?>
                        <th class="<?php echo in_array((int)$subject['id'], $my_subject_ids) ? 'my-subject' : ''; ?>">
                            <?php echo $subject['name']; ?>
                        </th>
                    <?php endforeach; ?>
                    <th>TOTAL</th>
                    <th>AVG</th>
                    <th>RANK</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php $count = 1;
                foreach ($students as $student): ?>
                    <tr class="<?php echo $student['complete'] ? '' : 'row-incomplete'; ?>">
                        <td><?php echo $count++; ?></td>
                        <td><strong><?php echo $student['name']; ?></strong></td>
                        <td><?php echo $student['gender']; ?></td>
                        <td><?php echo $student['student_id']; ?></td>

                        <?php foreach ($subjects as $subject): ?>
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
        <p style="text-align: center; padding: 50px;">No students found in this class.</p>
    <?php endif; ?>
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