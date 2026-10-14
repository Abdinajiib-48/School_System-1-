<?php
// ============================================================
// teacher/attendance.php - Mark who is present today in the selected class.
//
// Who can open it: teacher
// ============================================================
include '../config.php';

require_role('teacher');

$user_id = (int)$_SESSION['user_id'];
$teacher_row = mysqli_query($conn, "SELECT t.* FROM teachers t WHERE t.user_id = $user_id");
$teacher = mysqli_fetch_assoc($teacher_row);

if (!$teacher) {
    header('Location: ../index.php');
    exit();
}

$tid = (int)$teacher['id'];

// Check if optional tables exist
$has_ct  = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'class_teachers'")) > 0;
$has_att = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'attendance'"))     > 0;

// Get all classes for this teacher
if ($has_ct) {
    $cls_res = mysqli_query($conn,
        "SELECT DISTINCT c.*,
         CASE WHEN c.homeroom_teacher_id=$tid THEN 'Homeroom Teacher'
              ELSE 'Subject Teacher' END AS teacher_role
         FROM classes c
         LEFT JOIN class_teachers ct ON c.id=ct.class_id AND ct.teacher_id=$tid
         WHERE c.homeroom_teacher_id=$tid OR ct.teacher_id=$tid
         ORDER BY c.grade");
} else {
    $cls_res = mysqli_query($conn,
        "SELECT c.*, 'Homeroom Teacher' AS teacher_role
         FROM classes c WHERE c.homeroom_teacher_id=$tid ORDER BY c.grade");
}

$all_classes = [];
if ($cls_res) {
    while ($row = mysqli_fetch_assoc($cls_res)) {
        $all_classes[] = $row;
    }
}

// Get selected class id — auto-pick first if not specified
$selected_class_id = 0;
if (isset($_GET['class_id']) && (int)$_GET['class_id'] > 0) {
    $selected_class_id = (int)$_GET['class_id'];
} elseif (isset($_POST['class_id']) && (int)$_POST['class_id'] > 0) {
    $selected_class_id = (int)$_POST['class_id'];
} elseif (count($all_classes) > 0) {
    $selected_class_id = (int)$all_classes[0]['id'];
}

// Load selected class details
$selected_class = null;
if ($selected_class_id > 0) {
    $sc_res = mysqli_query($conn,
        "SELECT c.*,
         CASE WHEN c.homeroom_teacher_id=$tid THEN 'Homeroom Teacher'
              ELSE 'Subject Teacher' END AS teacher_role
         FROM classes c WHERE c.id=$selected_class_id");
    if ($sc_res) {
        $selected_class = mysqli_fetch_assoc($sc_res);
    }
}

// Selected date
$selected_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date) || $selected_date > date('Y-m-d')) {
    $selected_date = date('Y-m-d');
}

$message = '';
$message_type = '';

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!$has_att) {
        $message = "Attendance table missing. Import School_db1.sql in phpMyAdmin first.";
        $message_type = 'error';
    } else {
        $post_date     = mysqli_real_escape_string($conn, $_POST['date']);
        $post_class_id = (int)$_POST['class_id'];
        $att_input     = isset($_POST['attendance']) ? $_POST['attendance'] : [];
        $rem_input     = isset($_POST['remarks'])    ? $_POST['remarks']    : [];
        $saved = 0; $updated = 0; $errors = 0;

        foreach ($att_input as $stu_id => $status) {
            $stu_id = (int)$stu_id;
            $status = in_array($status, array('Present','Absent','Late')) ? $status : 'Present';
            $remark = mysqli_real_escape_string($conn, trim(isset($rem_input[$stu_id]) ? $rem_input[$stu_id] : ''));

            $chk = mysqli_query($conn,
                "SELECT id FROM attendance WHERE student_id=$stu_id AND date='$post_date'");
            if ($chk && mysqli_num_rows($chk) > 0) {
                $rid = mysqli_fetch_assoc($chk)['id'];
                $ok = mysqli_query($conn,
                    "UPDATE attendance SET status='$status', remarks='$remark',
                     marked_by=$tid, marked_date=NOW() WHERE id=$rid");
                $ok ? $updated++ : $errors++;
            } else {
                $ok = mysqli_query($conn,
                    "INSERT INTO attendance (student_id,class_id,date,status,remarks,marked_by)
                     VALUES ($stu_id,$post_class_id,'$post_date','$status','$remark',$tid)");
                $ok ? $saved++ : $errors++;
            }
        }

        if ($errors === 0) {
            $message = "Attendance saved! New: $saved | Updated: $updated";
            $message_type = 'success';
        } else {
            $message = "Saved: ".($saved+$updated).", Failed: $errors";
            $message_type = 'error';
        }

        $selected_date = $post_date;
        $selected_class_id = $post_class_id;

        $sc2 = mysqli_query($conn,
            "SELECT c.*,
             CASE WHEN c.homeroom_teacher_id=$tid THEN 'Homeroom Teacher'
                  ELSE 'Subject Teacher' END AS teacher_role
             FROM classes c WHERE c.id=$selected_class_id");
        if ($sc2) {
            $selected_class = mysqli_fetch_assoc($sc2);
        }
    }
}

// Load students
$students = [];
$total_students = 0;
if ($selected_class && $selected_class_id > 0) {
    $s_res = mysqli_query($conn,
        "SELECT * FROM students WHERE class_id=$selected_class_id ORDER BY name");
    if ($s_res) {
        while ($s = mysqli_fetch_assoc($s_res)) {
            $students[] = $s;
        }
    }
    $total_students = count($students);
}

// Load existing attendance for selected date
$existing_att = [];
if ($has_att && $selected_class_id > 0 && $selected_date) {
    $a_res = mysqli_query($conn,
        "SELECT student_id, status, remarks FROM attendance
         WHERE class_id=$selected_class_id AND date='$selected_date'");
    if ($a_res) {
        while ($a = mysqli_fetch_assoc($a_res)) {
            $existing_att[$a['student_id']] = array(
                'status'  => $a['status'],
                'remarks' => $a['remarks']
            );
        }
    }
}

// Summary counts
$present_count = 0; $absent_count = 0; $late_count = 0;
foreach ($existing_att as $a) {
    if ($a['status'] === 'Present')     $present_count++;
    elseif ($a['status'] === 'Absent')  $absent_count++;
    elseif ($a['status'] === 'Late')    $late_count++;
}
$not_marked = $total_students - count($existing_att);

// History
$history = [];
if ($has_att && $selected_class_id > 0) {
    $h_res = mysqli_query($conn,
        "SELECT date,
         SUM(status='Present') AS present,
         SUM(status='Absent')  AS absent,
         SUM(status='Late')    AS late,
         COUNT(*) AS total
         FROM attendance
         WHERE class_id=$selected_class_id
         GROUP BY date ORDER BY date DESC LIMIT 10");
    if ($h_res) {
        while ($h = mysqli_fetch_assoc($h_res)) {
            $history[] = $h;
        }
    }
}

include 'sidebar.php';
?>

<style>
.card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.card-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 20px; padding-left: 12px; border-left: 4px solid #4CAF50; }
.class-selector { display: flex; gap: 10px; flex-wrap: wrap; }
.class-btn { padding: 9px 18px; border-radius: 8px; text-decoration: none; background: #f0f2f5; color: #333; font-size: 14px; font-weight: 500; border: 2px solid transparent; }
.class-btn:hover { background: #e0f2e9; border-color: #4CAF50; }
.class-btn.active { background: #4CAF50; color: white; }
.role-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 6px; }
.role-homeroom { background: #e3f2fd; color: #1565c0; }
.role-subject  { background: #fff3e0; color: #e65100; }
.class-btn.active .role-badge { background: rgba(255,255,255,0.3); color: white; }
.summary-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 15px; margin-bottom: 25px; }
.summary-box { background: white; border-radius: 12px; padding: 18px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.summary-box .num { font-size: 34px; font-weight: bold; margin-bottom: 4px; }
.summary-box .lbl { color: #666; font-size: 13px; }
.c-green { color: #4CAF50; } .c-red { color: #f44336; } .c-orange { color: #ff9800; } .c-blue { color: #2196F3; }
.date-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
.date-row input[type=date] { padding: 9px 13px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; }
.date-row input[type=date]:focus { outline: none; border-color: #4CAF50; }
.btn { padding: 9px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; }
.btn-green  { background: #4CAF50; color: white; }
.btn-blue   { background: #2196F3; color: white; }
.btn-red    { background: #f44336; color: white; }
.btn-orange { background: #ff9800; color: white; }
.btn-save   { padding: 12px 32px; font-size: 15px; }
.quick-row  { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
.quick-row span { font-size: 14px; color: #555; font-weight: 600; }
.att-table { width: 100%; border-collapse: collapse; }
.att-table th { background: #4CAF50; color: white; padding: 11px 14px; text-align: left; font-weight: 600; }
.att-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; font-size: 14px; }
.att-table tr:nth-child(even) td { background: #fafafa; }
.att-table tr:hover td { background: #f0f7f0; }
.status-select { padding: 7px 10px; border: 2px solid #ddd; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; width: 120px; }
.remark-input { width: 100%; padding: 7px 10px; border: 1px solid #ddd; border-radius: 7px; font-size: 13px; }
.remark-input:focus { outline: none; border-color: #4CAF50; }
.hist-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.hist-table th { background: #f0f2f5; color: #555; padding: 10px 12px; text-align: center; font-weight: 600; border-bottom: 2px solid #ddd; }
.hist-table td { padding: 10px 12px; text-align: center; border-bottom: 1px solid #f0f0f0; }
.hist-table tr:hover td { background: #f9f9f9; }
.badge { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.bg-green { background: #e8f5e9; color: #2e7d32; } .bg-red { background: #ffebee; color: #c62828; } .bg-orange { background: #fff3e0; color: #e65100; }
.message { padding: 13px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.info-bar { background: #f0f7f0; border-left: 4px solid #4CAF50; border-radius: 8px; padding: 11px 16px; margin-bottom: 20px; font-size: 14px; }
.today-tag { background: #4CAF50; color: white; padding: 2px 9px; border-radius: 10px; font-size: 11px; margin-left: 6px; }
.no-class-box { background: white; border-radius: 15px; padding: 50px 30px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
@media(max-width:768px) { .summary-grid { grid-template-columns: repeat(2,1fr); } .status-select { width: 100px; } }
</style>

<?php if ($message): ?>
<div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message ?? ''); ?></div>
<?php endif; ?>

<?php if (!$has_att): ?>
<div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:10px;padding:15px 18px;margin-bottom:20px;">
    <strong>Attendance table not found.</strong>
    Import <strong>School_db1.sql</strong> in phpMyAdmin first.
</div>
<?php endif; ?>

<?php if (count($all_classes) === 0): ?>
<!-- No class assigned -->
<div class="no-class-box">
    <div style="font-size:60px;margin-bottom:20px;">&#9888;</div>
    <h2 style="color:#333;margin-bottom:15px;">No Class Assigned Yet</h2>
    <p style="color:#666;margin-bottom:10px;">You are not assigned as homeroom teacher to any class.</p>
    <p style="color:#888;font-size:14px;margin-bottom:25px;">
        Ask Admin to go to <strong>Classes &rarr; Edit</strong> and assign you as homeroom teacher.
    </p>
    <a href="dashboard.php" class="btn btn-green">&#8592; Back to Dashboard</a>
</div>

<?php else: ?>

<!-- Class selector -->
<div class="card">
    <div class="card-title">&#127979; Select Class</div>
    <div class="class-selector">
    <?php foreach ($all_classes as $cls): ?>
        <a href="attendance.php?class_id=<?php echo $cls['id']; ?>&date=<?php echo $selected_date; ?>"
           class="class-btn <?php echo ($selected_class_id == $cls['id']) ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($cls['grade'] ?? ''); ?>
            (<?php echo htmlspecialchars($cls['academic_year'] ?? ''); ?>)
            <span class="role-badge <?php echo $cls['teacher_role'] === 'Homeroom Teacher' ? 'role-homeroom' : 'role-subject'; ?>">
                <?php echo $cls['teacher_role'] === 'Homeroom Teacher' ? 'Homeroom' : 'Subject'; ?>
            </span>
        </a>
    <?php endforeach; ?>
    </div>
</div>

<?php if ($selected_class): ?>

<div class="info-bar">
    <strong>Class:</strong> <?php echo htmlspecialchars($selected_class['grade'] ?? ''); ?> &nbsp;|&nbsp;
    <strong>Year:</strong> <?php echo htmlspecialchars($selected_class['academic_year'] ?? ''); ?> &nbsp;|&nbsp;
    <strong>Semester:</strong> <?php echo htmlspecialchars($selected_class['semester'] ?? ''); ?> &nbsp;|&nbsp;
    <strong>Role:</strong> <?php echo $selected_class['teacher_role']; ?> &nbsp;|&nbsp;
    <strong>Total Students:</strong> <?php echo $total_students; ?>
</div>

<div class="summary-grid">
    <div class="summary-box"><div class="num c-green"><?php echo $present_count; ?></div><div class="lbl">Present</div></div>
    <div class="summary-box"><div class="num c-red"><?php echo $absent_count; ?></div><div class="lbl">Absent</div></div>
    <div class="summary-box"><div class="num c-orange"><?php echo $late_count; ?></div><div class="lbl">Late</div></div>
    <div class="summary-box"><div class="num c-blue"><?php echo $not_marked; ?></div><div class="lbl">Not Marked</div></div>
</div>

<div class="card">
    <div class="card-title">
        &#128203; Mark Attendance
        <?php if ($selected_date === date('Y-m-d')): ?><span class="today-tag">Today</span><?php endif; ?>
    </div>

    <form method="GET" action="">
        <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
        <div class="date-row">
            <label style="font-weight:600;color:#555;">Date:</label>
            <input type="date" name="date"
                   value="<?php echo htmlspecialchars($selected_date ?? ''); ?>"
                   max="<?php echo date('Y-m-d'); ?>">
            <button type="submit" class="btn btn-blue">Load</button>
            <a href="attendance.php?class_id=<?php echo $selected_class_id; ?>&date=<?php echo date('Y-m-d'); ?>"
               class="btn btn-green">Today</a>
        </div>
    </form>

    <?php if ($total_students === 0): ?>
        <p style="text-align:center;padding:30px;color:#888;">No students found in this class.</p>
    <?php else: ?>

    <div class="quick-row">
        <span>Mark all:</span>
        <button type="button" class="btn btn-green"  onclick="markAll('Present')">All Present</button>
        <button type="button" class="btn btn-orange" onclick="markAll('Late')">All Late</button>
        <button type="button" class="btn btn-red"    onclick="markAll('Absent')">All Absent</button>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="save_attendance" value="1">
        <input type="hidden" name="date"     value="<?php echo htmlspecialchars($selected_date ?? ''); ?>">
        <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

        <div style="overflow-x:auto;">
        <table class="att-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Gender</th>
                    <th>Status</th>
                    <th>Remarks (optional)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $i => $stu):
                $sid        = $stu['id'];
                $cur_status = isset($existing_att[$sid]) ? $existing_att[$sid]['status']  : 'Present';
                $cur_remark = isset($existing_att[$sid]) ? $existing_att[$sid]['remarks'] : '';
            ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($stu['student_id'] ?? ''); ?></strong></td>
                <td><?php echo htmlspecialchars($stu['name'] ?? ''); ?></td>
                <td><?php echo $stu['gender'] === 'M' ? 'Male' : 'Female'; ?></td>
                <td>
                    <select name="attendance[<?php echo $sid; ?>]"
                            class="status-select" onchange="colorSelect(this)">
                        <option value="Present" <?php echo $cur_status==='Present'?'selected':''; ?>>Present</option>
                        <option value="Absent"  <?php echo $cur_status==='Absent' ?'selected':''; ?>>Absent</option>
                        <option value="Late"    <?php echo $cur_status==='Late'   ?'selected':''; ?>>Late</option>
                    </select>
                </td>
                <td>
                    <input type="text"
                           name="remarks[<?php echo $sid; ?>]"
                           class="remark-input"
                           placeholder="e.g. Sick, holiday..."
                           value="<?php echo htmlspecialchars($cur_remark ?? ''); ?>">
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div style="margin-top:20px;display:flex;gap:15px;align-items:center;flex-wrap:wrap;">
            <button type="submit" class="btn btn-green btn-save"
                    <?php echo !$has_att ? 'disabled' : ''; ?>>
                Save &mdash; <?php echo date('d M Y', strtotime($selected_date)); ?>
            </button>
            <?php if (!empty($existing_att)): ?>
            <span style="color:#888;font-size:13px;">Records for this date will be updated.</span>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($history)): ?>
<div class="card">
    <div class="card-title">&#128202; Attendance History (Last 10 Days)</div>
    <div style="overflow-x:auto;">
    <table class="hist-table">
        <thead>
            <tr>
                <th>Date</th><th>Marked/Total</th>
                <th>Present</th><th>Absent</th><th>Late</th>
                <th>Rate</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($history as $h):
            $rate  = $h['total'] > 0 ? round(($h['present'] / $h['total']) * 100) : 0;
            $color = $rate >= 80 ? '#4CAF50' : ($rate >= 60 ? '#ff9800' : '#f44336');
        ?>
        <tr>
            <td>
                <strong><?php echo date('d M Y', strtotime($h['date'])); ?></strong>
                <?php if ($h['date'] === date('Y-m-d')): ?><span class="today-tag">Today</span><?php endif; ?>
            </td>
            <td><?php echo $h['total']; ?> / <?php echo $total_students; ?></td>
            <td><span class="badge bg-green"><?php echo $h['present']; ?></span></td>
            <td><span class="badge bg-red"><?php echo $h['absent']; ?></span></td>
            <td><span class="badge bg-orange"><?php echo $h['late']; ?></span></td>
            <td><strong style="color:<?php echo $color; ?>"><?php echo $rate; ?>%</strong></td>
            <td>
                <a href="attendance.php?class_id=<?php echo $selected_class_id; ?>&date=<?php echo $h['date']; ?>"
                   class="btn btn-blue" style="padding:5px 12px;font-size:12px;">Edit</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php endif; // end if selected_class ?>
<?php endif; // end if all_classes ?>

</div></div>

<script>
function colorSelect(s) {
    s.style.borderColor = s.value === 'Present' ? '#4CAF50' : s.value === 'Absent' ? '#f44336' : '#ff9800';
    s.style.color       = s.value === 'Present' ? '#2e7d32' : s.value === 'Absent' ? '#b71c1c' : '#e65100';
}
function markAll(v) {
    var selects = document.querySelectorAll('.status-select');
    for (var i = 0; i < selects.length; i++) {
        selects[i].value = v;
        colorSelect(selects[i]);
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var selects = document.querySelectorAll('.status-select');
    for (var i = 0; i < selects.length; i++) colorSelect(selects[i]);
});
var menuToggle = document.getElementById('menuToggle');
var sidebar    = document.getElementById('sidebar');
if (menuToggle) {
    menuToggle.addEventListener('click', function() { sidebar.classList.toggle('active'); });
}
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768 && sidebar && menuToggle
        && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
        sidebar.classList.remove('active');
    }
});
</script>
</body>
</html>
