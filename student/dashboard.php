<?php
// ============================================================
// student/dashboard.php - The whole student portal on one page.
//
// Who can open it: student
//
// Sections are switched with JavaScript, not separate files:
// results, attendance, timetable, announcements and profile.
//
// NOTE: this file must NOT start with a byte order mark. A BOM is
// output, and once anything is output header('Location: ...') can no
// longer run - which would stop the login guard below from working.
// ============================================================
include '../config.php';

require_role('student');

// Get student information from session
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Get full student details
$student_sql = "SELECT s.*, c.grade, c.academic_year, c.semester, t.name as teacher_name 
                FROM students s 
                LEFT JOIN classes c ON s.class_id = c.id 
                LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id 
                WHERE s.id = $student_id";
$student_result = mysqli_query($conn, $student_sql);
$student = mysqli_fetch_assoc($student_result);

// Get user account info
$user_sql = "SELECT * FROM users WHERE id = '{$_SESSION['user_id']}'";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

// Get all subjects
$subjects_sql = "SELECT * FROM subjects ORDER BY id";
$subjects_result = mysqli_query($conn, $subjects_sql);
$subjects = [];
while($sub = mysqli_fetch_assoc($subjects_result)) {
    $subjects[] = $sub;
}

// Get marks for this student
include_once '../assessments_helper.php';

// A subject is built from its assessments (Assignment / Mid / Final).
// $marks holds one score per FINISHED subject - a subject still waiting
// on an assessment is left out, so it counts as unmarked.
// $raw keeps the individual parts so the page can show what is missing.
$types = assessment_types($conn);
$raw   = student_marks_raw($conn, $student_id);
$marks = subject_scores_from_raw($raw, $types);

// ---- Fee status (finance module, optional) ----
$fee_info = null;
$fee_payments = [];
if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'payments'")) > 0
    && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'fee_structure'")) > 0) {
    // Discount / scholarship (finance v2, optional)
    $has_disc = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'student_discounts'")) > 0;
    $disc_join = $has_disc ? "LEFT JOIN student_discounts d ON d.student_id = s.id" : "";
    $disc_cols = $has_disc ? "d.discount_type, COALESCE(d.value,0) AS discount_value, d.reason AS discount_reason"
                           : "NULL AS discount_type, 0 AS discount_value, NULL AS discount_reason";
    $fee_row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(f.amount, 0) AS fee, $disc_cols,
                COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS paid
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN fee_structure f ON f.class_id = c.id AND f.academic_year = c.academic_year
         $disc_join
         WHERE s.id = $student_id"));
    if ($fee_row && $fee_row['fee'] > 0) {
        $f_fee  = (float)$fee_row['fee'];
        $f_val  = (float)$fee_row['discount_value'];
        $f_disc = 0;
        if ($f_val > 0) {
            $f_disc = ($fee_row['discount_type'] === 'Percent') ? $f_fee * $f_val / 100 : $f_val;
            if ($f_disc > $f_fee) $f_disc = $f_fee;
        }
        $f_payable = $f_fee - $f_disc;
        $f_paid    = (float)$fee_row['paid'];
        $fee_info = [
            'fee'      => $f_fee,
            'discount' => $f_disc,
            'reason'   => $fee_row['discount_reason'],
            'payable'  => $f_payable,
            'paid'     => $f_paid,
            'balance'  => $f_payable - $f_paid,
        ];
        $fee_info['pct'] = $f_payable > 0 ? round($f_paid / $f_payable * 100) : 100;

        // This student's own payment history (read only)
        $fp = mysqli_query($conn,
            "SELECT receipt_no, amount, payment_date, method
             FROM payments WHERE student_id = $student_id
             ORDER BY payment_date DESC, id DESC");
        while ($row = mysqli_fetch_assoc($fp)) $fee_payments[] = $row;
    }
}

// ---- Class timetable for this student ----
include_once '../timetable_helper.php';
$tt_grid  = tt_installed($conn) ? tt_class_grid($conn, $student['class_id']) : [];
$tt_count = 0;
foreach ($tt_grid as $tt_row) $tt_count += count($tt_row);

// ---- School announcements for this student ----
include_once '../announcements_helper.php';
$school_news = get_announcements($conn, 'Students', 50);

// Calculate totals.
// The result is only worked out when EVERY subject has a mark -
// otherwise one good mark would look like a high average.
include_once '../results_helper.php';
$res = calc_result($marks, $subjects);

$result_complete  = $res['complete'];
$missing_subjects = $res['missing'];
$subject_count    = $res['counted'];
$total_marks      = $res['total'];      // null while marks are missing
$average          = $res['average'];    // null while marks are missing
$status           = $res['status'];     // PASS / FAIL / INCOMPLETE

// Calculate RANK among all students in same class
$rank = '-';
if ($student['class_id']) {
    $rank_sql = "SELECT s.id, s.name, SUM(m.marks_obtained) as total
                 FROM students s
                 LEFT JOIN marks m ON s.id = m.student_id
                 WHERE s.class_id = '{$student['class_id']}'
                 GROUP BY s.id
                 ORDER BY total DESC";
    $rank_result = mysqli_query($conn, $rank_sql);
    $rank_number = 1;
    $total_students = 0;
    while($rank_student = mysqli_fetch_assoc($rank_result)) {
        $total_students++;
        if ($rank_student['id'] == $student_id) {
            $rank = $rank_number;
        }
        $rank_number++;
    }
    if ($rank != '-') {
        $rank = $rank . " / " . $total_students;
    }
}

// ========== GET ATTENDANCE DATA ==========
// Get attendance for this student (last 30 days)
$attendance_sql = "SELECT date, status, remarks 
                   FROM attendance 
                   WHERE student_id = $student_id 
                   ORDER BY date DESC 
                   LIMIT 30";
$attendance_result = mysqli_query($conn, $attendance_sql);

// Calculate attendance statistics
$total_days = 0;
$present_days = 0;
$absent_days = 0;
$late_days = 0;
$attendance_data = [];

while($att = mysqli_fetch_assoc($attendance_result)) {
    $attendance_data[] = $att;
    $total_days++;
    if ($att['status'] == 'Present') $present_days++;
    elseif ($att['status'] == 'Absent') $absent_days++;
    elseif ($att['status'] == 'Late') $late_days++;
}

$attendance_percentage = $total_days > 0 ? ($present_days / $total_days) * 100 : 0;

// Get attendance summary by month (last 6 months)
$monthly_sql = "SELECT 
                    DATE_FORMAT(date, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
                FROM attendance 
                WHERE student_id = $student_id 
                AND date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(date, '%Y-%m')
                ORDER BY month DESC";
$monthly_result = mysqli_query($conn, $monthly_sql);
$monthly_data = [];
while($month = mysqli_fetch_assoc($monthly_result)) {
    $monthly_data[] = $month;
}

// Function to get grade based on percentage
function getGrade($percentage) {
    if ($percentage >= 90) {
        return ['grade' => 'A+', 'remark' => 'Excellent', 'color' => '#2ecc71'];
    }
    if ($percentage >= 80) {
        return ['grade' => 'A', 'remark' => 'Very Good', 'color' => '#27ae60'];
    }
    if ($percentage >= 70) {
        return ['grade' => 'B+', 'remark' => 'Good', 'color' => '#f39c12'];
    }
    if ($percentage >= 60) {
        return ['grade' => 'B', 'remark' => 'Above Average', 'color' => '#f1c40f'];
    }
    if ($percentage >= 50) {
        return ['grade' => 'C', 'remark' => 'Average', 'color' => '#e67e22'];
    }
    if ($percentage >= 40) {
        return ['grade' => 'D', 'remark' => 'Below Average', 'color' => '#e74c3c'];
    }
    return ['grade' => 'F', 'remark' => 'Need Improvement', 'color' => '#c0392b'];
}

// Handle profile photo upload
$upload_message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_photo'])) {
    $target_dir = "../uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $file_name = time() . "_" . basename($_FILES["profile_photo"]["name"]);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($imageFileType, $allowed_types)) {
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
            $update_sql = "UPDATE students SET profile_photo = '$file_name' WHERE id = $student_id";
            mysqli_query($conn, $update_sql);
            $upload_message = '<div class="message success">Profile photo updated successfully!</div>';
            // Refresh student data
            $student_result = mysqli_query($conn, $student_sql);
            $student = mysqli_fetch_assoc($student_result);
        } else {
            $upload_message = '<div class="message error">Error uploading file!</div>';
        }
    } else {
        $upload_message = '<div class="message error">Only JPG, JPEG, PNG, GIF files are allowed!</div>';
    }
}

// Handle password change
$password_message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if ($current_password != $user['password']) {
        $errors[] = "Current password is incorrect!";
    }
    if (strlen($new_password) < 4) {
        $errors[] = "New password must be at least 4 characters long!";
    }
    if ($new_password != $confirm_password) {
        $errors[] = "New password and confirm password do not match!";
    }
    
    if (count($errors) == 0) {
        $update_sql = "UPDATE users SET password = '$new_password' WHERE id = '{$_SESSION['user_id']}'";
        if (mysqli_query($conn, $update_sql)) {
            $password_message = '<div class="message success">Password changed successfully! Please login again.</div>';
        } else {
            $password_message = '<div class="message error">Error updating password!</div>';
        }
    } else {
        $password_message = '<div class="message error"><ul>';
        foreach ($errors as $error) {
            $password_message .= "<li>$error</li>";
        }
        $password_message .= '</ul></div>';
    }
}

$profile_photo = isset($student['profile_photo']) && $student['profile_photo'] ? $student['profile_photo'] : 'default-avatar.png';
if (empty($profile_photo)) $profile_photo = 'default-avatar.png';

// Include the shared sidebar
include 'sidebar.php';
?>

<style>
    /* Content cards */
    .results-section {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }
    .section-title {
        font-size: 20px;
        margin-bottom: 20px;
        color: #333;
        border-left: 4px solid #4CAF50;
        padding-left: 15px;
    }

    /* Welcome card */
    .welcome-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .welcome-card h1 { font-size: 26px; margin-bottom: 10px; }
    .student-info { margin-top: 15px; display: flex; gap: 12px; flex-wrap: wrap; }
    .info-badge {
        background: rgba(255,255,255,0.2);
        padding: 7px 14px;
        border-radius: 20px;
        font-size: 13px;
    }
    .rank-badge { background: rgba(255,200,0,0.4); font-weight: bold; }

    /* Stats grid */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 20px;
    }
    .stat-card {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        border: 1px solid #e0e0e0;
    }
    .stat-card h3 { font-size: 13px; color: #666; margin-bottom: 10px; }
    .stat-value { font-size: 28px; font-weight: bold; color: #4CAF50; }

    /* Results table */
    .results-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .results-table th, .results-table td {
        padding: 12px; text-align: left; border-bottom: 1px solid #eee;
    }
    .results-table th { background: #f8f9fa; font-weight: 600; color: #555; }
    .score { font-weight: 600; color: #4CAF50; }

    /* Status badges */
    .status-pass { background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 20px; display: inline-block; }
    .status-fail { background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 20px; display: inline-block; }
    .status-late { background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 20px; display: inline-block; }

    /* Attendance */
    .attendance-summary {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px;
    }
    .attendance-card {
        background: #f8f9fa; padding: 15px; border-radius: 12px;
        text-align: center; border: 1px solid #e0e0e0;
    }
    .attendance-card h3 { font-size: 13px; color: #666; margin-bottom: 10px; }
    .attendance-number { font-size: 28px; font-weight: bold; }
    .monthly-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
    .monthly-table th, .monthly-table td {
        padding: 10px; text-align: center; border: 1px solid #eee;
    }
    .monthly-table th { background: #f8f9fa; font-weight: 600; }

    /* Profile */
    .profile-section { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .profile-photo-section { text-align: center; padding: 20px; background: #f8f9fa; border-radius: 15px; margin-bottom: 20px; }
    .profile-photo { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #4CAF50; margin-bottom: 15px; }
    .profile-info { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
    .profile-field { padding: 12px; background: #f8f9fa; border-radius: 8px; }
    .profile-field label { font-weight: 600; color: #666; display: block; margin-bottom: 5px; font-size: 13px; }
    .profile-field span { font-size: 16px; color: #333; }
    .upload-btn {
        background: #4CAF50; color: white; border: none;
        padding: 10px 20px; border-radius: 8px; cursor: pointer; margin-top: 10px;
    }
    .upload-btn:hover { background: #45a049; }
    .password-section { background: #f8f9fa; padding: 20px; border-radius: 15px; margin-top: 20px; }
    .password-section h3 { margin-bottom: 15px; color: #333; }
    .password-form-group { margin-bottom: 15px; }
    .password-form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
    .password-form-group input {
        width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px;
    }

    /* Messages */
    .message { padding: 12px; border-radius: 8px; margin-bottom: 20px; }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    @media (max-width: 768px) {
        .stats-container, .attendance-summary { grid-template-columns: repeat(2, 1fr); }
        .profile-info { grid-template-columns: 1fr; }
    }
    .status-wait {
        background: #fff3cd; color: #8a6100; padding: 3px 10px;
        border-radius: 12px; font-weight: bold; font-size: 12px; white-space: nowrap;
    }
    .waiting-box {
        background: #fff8e1; border-left: 5px solid #ffb300;
        border-radius: 12px; padding: 22px 25px; color: #6d5200;
    }
    .waiting-box h3 { margin-bottom: 10px; color: #8a6100; }
    .waiting-box p { margin-top: 8px; line-height: 1.7; }
    .waiting-box .missing-list span {
        display: inline-block; background: white; border: 1px solid #ffd77a;
        color: #8a6100; padding: 4px 12px; border-radius: 14px;
        margin: 4px 6px 0 0; font-size: 13px; font-weight: 600;
    }
    .waiting-box .why { color: #8a7a52; font-size: 13px; margin-top: 14px; }
</style>

<?php if ($upload_message): ?>
    <?php echo $upload_message; ?>
<?php endif; ?>

<?php if ($password_message): ?>
    <?php echo $password_message; ?>
<?php endif; ?>

<!-- ============================================ -->
<!-- DASHBOARD SECTION -->
<!-- ============================================ -->
<div id="dashboardSection">
    <div class="welcome-card">
        <h1>Welcome, <?php echo htmlspecialchars($student['name'] ?? ''); ?>!</h1>
        <p>Welcome to your academic dashboard. Track your performance and stay updated!</p>
        <div class="student-info">
            <div class="info-badge">Student ID: <?php echo htmlspecialchars($student['student_id'] ?? ''); ?></div>
            <div class="info-badge">Class: <?php echo htmlspecialchars($student['grade'] ?: 'Not Assigned'); ?></div>
            <div class="info-badge">Year: <?php echo htmlspecialchars($student['academic_year'] ?: '-'); ?></div>
            <div class="info-badge">Semester: <?php echo htmlspecialchars($student['semester'] ?: '-'); ?></div>
            <div class="info-badge rank-badge">Rank: <?php echo $result_complete ? $rank : '—'; ?></div>
            <div class="info-badge">
                Status:
                <?php if (!$result_complete): ?>
                    <span class="status-wait">⏳ WAITING FOR MARKS</span>
                <?php elseif ($status == 'PASS'): ?>
                    <span class="status-pass">PASS</span>
                <?php else: ?>
                    <span class="status-fail">FAIL</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="results-section">
        <h2 class="section-title">Academic Performance</h2>
        <div class="stats-container">
            <div class="stat-card"><h3>Total Subjects</h3><div class="stat-value"><?php echo count($subjects); ?></div></div>
            <div class="stat-card"><h3>Total Marks</h3><div class="stat-value"><?php echo $result_complete ? $total_marks : '—'; ?></div></div>
            <div class="stat-card"><h3>Average Score</h3><div class="stat-value"><?php echo $result_complete ? number_format($average, 1) . '%' : '—'; ?></div></div>
            <div class="stat-card"><h3>Class Rank</h3><div class="stat-value"><?php echo $rank; ?></div></div>
        </div>
    </div>

    <?php if ($fee_info): ?>
    <div class="results-section">
        <h2 class="section-title">💰 My Fee Status</h2>
        <div class="stats-container">
            <div class="stat-card"><h3><?php echo $fee_info['discount'] > 0 ? 'Payable (after discount)' : 'Total Fee'; ?></h3>
                <div class="stat-value" style="color:#2196F3;">$<?php echo number_format($fee_info['payable'], 2); ?></div>
                <?php if ($fee_info['discount'] > 0): ?>
                    <div style="font-size:11px;color:#9c27b0;margin-top:6px;">
                        $<?php echo number_format($fee_info['fee'], 2); ?> − $<?php echo number_format($fee_info['discount'], 2); ?>
                        <?php echo htmlspecialchars($fee_info['reason'] ?: 'discount'); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="stat-card"><h3>Paid</h3><div class="stat-value">$<?php echo number_format($fee_info['paid'], 2); ?></div></div>
            <div class="stat-card"><h3>Balance</h3><div class="stat-value" style="color:<?php echo $fee_info['balance'] > 0 ? '#f44336' : '#4CAF50'; ?>;">$<?php echo number_format(max($fee_info['balance'], 0), 2); ?></div></div>
            <div class="stat-card"><h3>Status</h3><div class="stat-value" style="font-size:20px;color:<?php echo $fee_info['balance'] <= 0 ? '#4CAF50' : ($fee_info['paid'] > 0 ? '#ff9800' : '#f44336'); ?>;">
                <?php echo $fee_info['balance'] <= 0 ? '✅ Fully Paid' : ($fee_info['paid'] > 0 ? '⚠️ Partial' : '❌ Unpaid'); ?>
            </div></div>
        </div>

        <div style="background:#eee;border-radius:10px;height:14px;overflow:hidden;margin-top:18px;">
            <div style="width:<?php echo min($fee_info['pct'], 100); ?>%;height:100%;background:linear-gradient(90deg,#4CAF50,#8BC34A);"></div>
        </div>
        <p style="color:#666;font-size:13px;margin-top:8px;"><?php echo min($fee_info['pct'], 100); ?>% of your fee has been paid.</p>

        <?php if ($fee_info['balance'] > 0): ?>
            <p style="background:#fff3cd;color:#856404;border-left:4px solid #ffc107;border-radius:8px;padding:12px 15px;margin-top:15px;font-size:14px;">
                ⚠️ You still have <strong>$<?php echo number_format($fee_info['balance'], 2); ?></strong> to pay.
                Please visit the school finance office to settle your balance.
            </p>
        <?php else: ?>
            <p style="background:#d4edda;color:#155724;border-left:4px solid #28a745;border-radius:8px;padding:12px 15px;margin-top:15px;font-size:14px;">
                ✅ Your fee is fully paid. Thank you!
            </p>
        <?php endif; ?>

        <?php if (count($fee_payments) > 0): ?>
            <h2 class="section-title" style="margin-top:25px;">🧾 My Payment History</h2>
            <table class="results-table">
                <thead>
                    <tr><th>Receipt No</th><th>Date</th><th>Method</th><th>Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($fee_payments as $fp): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($fp['receipt_no'] ?? ''); ?></strong></td>
                            <td><?php echo date('d M Y', strtotime($fp['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($fp['method'] ?? ''); ?></td>
                            <td style="color:#4CAF50;font-weight:bold;">$<?php echo number_format($fp['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#999;margin-top:20px;">No payments recorded yet.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="results-section">
        <h2 class="section-title">Subject Wise Marks</h2>
        <table class="results-table">
            <thead>
                <tr>
                    <th>Subject</th><th>Marks</th><th>Max</th>
                    <th>Percentage</th><th>Grade</th><th>Remark</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $subject):
                $mark = isset($marks[$subject['id']]) ? $marks[$subject['id']] : '-';
                if (is_numeric($mark)) {
                    $pct        = $mark;
                    $grade_info = getGrade($pct);
                } else {
                    $pct        = 0;
                    $grade_info = ['grade' => '-', 'remark' => 'Not Available', 'color' => '#999'];
                }
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($subject['name'] ?? ''); ?></strong></td>
                <td class="score"><?php echo $mark; ?></td>
                <td>100</td>
                <td><?php echo is_numeric($mark) ? $pct . '%' : '-'; ?></td>
                <td style="color:<?php echo $grade_info['color']; ?>;font-weight:bold;"><?php echo $grade_info['grade']; ?></td>
                <td style="color:<?php echo $grade_info['color']; ?>;"><?php echo $grade_info['remark']; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================ -->
<!-- ATTENDANCE SECTION -->
<!-- ============================================ -->
<div id="attendanceSection" style="display:none;">
    <div class="results-section">
        <h2 class="section-title">My Attendance Record</h2>
        <div class="attendance-summary">
            <div class="attendance-card"><h3>Total Days</h3><div class="attendance-number"><?php echo $total_days; ?></div></div>
            <div class="attendance-card"><h3>Present</h3><div class="attendance-number" style="color:#4CAF50;"><?php echo $present_days; ?></div></div>
            <div class="attendance-card"><h3>Absent</h3><div class="attendance-number" style="color:#f44336;"><?php echo $absent_days; ?></div></div>
            <div class="attendance-card"><h3>Attendance %</h3><div class="attendance-number" style="color:#2196F3;"><?php echo number_format($attendance_percentage, 1); ?>%</div></div>
        </div>

        <h3 style="margin:20px 0 10px 0;">Recent Attendance (Last 30 Days)</h3>
        <?php if (count($attendance_data) > 0): ?>
        <table class="results-table">
            <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($attendance_data as $att):
                if ($att['status'] == 'Present')     { $sc = 'status-pass'; }
                elseif ($att['status'] == 'Absent')  { $sc = 'status-fail'; }
                else                                 { $sc = 'status-late'; }
            ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($att['date'])); ?></td>
                <td><span class="<?php echo $sc; ?>"><?php echo $att['status']; ?></span></td>
                <td><?php echo $att['remarks'] ?: '-'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align:center;padding:30px;color:#666;">No attendance records yet. Your teacher will mark attendance soon.</p>
        <?php endif; ?>

        <?php if (count($monthly_data) > 0): ?>
        <h3 style="margin:25px 0 10px 0;">Monthly Summary</h3>
        <table class="monthly-table">
            <thead>
                <tr><th>Month</th><th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>%</th></tr>
            </thead>
            <tbody>
            <?php foreach ($monthly_data as $month):
                $mn  = date('F Y', strtotime($month['month'] . '-01'));
                $mpt = $month['total'] > 0 ? ($month['present'] / $month['total']) * 100 : 0;
            ?>
            <tr>
                <td><?php echo $mn; ?></td>
                <td><?php echo $month['total']; ?></td>
                <td style="color:#4CAF50;"><?php echo $month['present']; ?></td>
                <td style="color:#f44336;"><?php echo $month['absent']; ?></td>
                <td style="color:#ff9800;"><?php echo $month['late']; ?></td>
                <td><strong><?php echo number_format($mpt, 1); ?>%</strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- RESULTS SECTION -->
<!-- ============================================ -->
<div id="resultsSection" style="display:none;">
    <div class="results-section">
        <h2 class="section-title">My Academic Report</h2>
        <table class="results-table">
            <thead>
                <tr><th>Subject</th><th>Marks</th><th>Percentage</th><th>Grade</th><th>Remark</th></tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $subject):
                $mark = isset($marks[$subject['id']]) ? $marks[$subject['id']] : '-';
                if (is_numeric($mark)) { $pct = $mark; $gi = getGrade($pct); }
                else { $pct = 0; $gi = ['grade'=>'-','remark'=>'Not Available','color'=>'#999']; }
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($subject['name'] ?? ''); ?></strong></td>
                <td class="score"><?php echo $mark; ?></td>
                <td><?php echo is_numeric($mark) ? $pct . '%' : '-'; ?></td>
                <td style="color:<?php echo $gi['color']; ?>;font-weight:bold;"><?php echo $gi['grade']; ?></td>
                <td style="color:<?php echo $gi['color']; ?>;"><?php echo $gi['remark']; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$result_complete): ?>
            <div class="waiting-box" style="margin-top:25px;">
                <h3>⏳ Your result is not ready yet</h3>
                <p>
                    <strong><?php echo count($missing_subjects); ?></strong>
                    of your <strong><?php echo count($subjects); ?></strong> subjects
                    <?php echo count($missing_subjects) === 1 ? 'has' : 'have'; ?> not been marked yet:
                </p>
                <p class="missing-list">
                    <?php foreach ($missing_subjects as $ms): ?>
                        <span><?php echo htmlspecialchars($ms ?? ''); ?></span>
                    <?php endforeach; ?>
                </p>
                <p class="why">
                    Your total, average, grade and rank appear once every subject has a mark,
                    so the numbers you see are always the real ones.
                </p>
            </div>
        <?php else: ?>
            <?php $og = getGrade($average); ?>
            <div class="stats-container" style="margin-top:25px;">
                <div class="stat-card"><h3>Total Marks</h3><div class="stat-value"><?php echo $total_marks; ?></div></div>
                <div class="stat-card"><h3>Average</h3><div class="stat-value"><?php echo number_format($average,1); ?>%</div></div>
                <div class="stat-card"><h3>Grade</h3><div class="stat-value" style="color:<?php echo $og['color']; ?>;"><?php echo $og['grade']; ?></div></div>
                <div class="stat-card"><h3>Rank</h3><div class="stat-value"><?php echo $rank; ?></div></div>
            </div>
            <div style="margin-top:20px;padding:15px;background:<?php echo $og['color']; ?>20;border-radius:10px;text-align:center;">
                <strong style="color:<?php echo $og['color']; ?>;">Overall: <?php echo $og['remark']; ?></strong>
                <p style="margin-top:8px;color:#666;font-size:14px;">
                    <?php echo $average >= PASS_MARK ? 'Congratulations! You passed.' : 'Keep working hard! You can improve.'; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- PROFILE SECTION -->
<!-- ============================================ -->
<!-- ============================================ -->
<!-- TIMETABLE SECTION -->
<!-- ============================================ -->
<div id="timetableSection" style="display:none;">
    <div class="results-section">
        <h2 class="section-title">🗓️ My Class Timetable</h2>
        <p style="color:#666;font-size:14px;">
            Class <?php echo htmlspecialchars($student['grade'] ?: '-'); ?>
            <?php if ($tt_count > 0): ?> · <?php echo $tt_count; ?> lessons a week<?php endif; ?>
        </p>

        <?php if ($tt_count === 0): ?>
            <p style="color:#999;text-align:center;padding:35px;">
                Your timetable has not been filled in yet.
            </p>
        <?php else: ?>
            <?php tt_styles(); ?>
            <?php tt_render_grid($conn, $tt_grid, 'class'); ?>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- ANNOUNCEMENTS SECTION -->
<!-- ============================================ -->
<div id="announcementsSection" style="display:none;">
    <div class="results-section">
        <h2 class="section-title">📢 School Announcements</h2>
        <p style="color:#666;font-size:14px;">News, events, holidays and exam schedules from the school.</p>
    </div>

    <?php if (count($school_news) == 0): ?>
        <div class="results-section">
            <p style="color:#999;text-align:center;padding:35px;">No announcements yet.</p>
        </div>
    <?php else: foreach ($school_news as $n):
        $is_new = strtotime($n['created_at']) >= strtotime('-7 days');
        $accent = $n['is_pinned'] ? '#ff9800'
                : ($n['type'] === 'Event' ? '#9c27b0'
                : ($n['type'] === 'Holiday' ? '#2196F3'
                : ($n['type'] === 'Exam' ? '#f44336' : '#4CAF50')));
    ?>
        <div class="results-section" style="border-left:5px solid <?php echo $accent; ?>;<?php echo $n['is_pinned'] ? 'background:#fffdf7;' : ''; ?>">
            <h2 class="section-title" style="border-left:none;padding-left:0;display:flex;align-items:center;gap:9px;flex-wrap:wrap;">
                <?php if ($n['is_pinned']): ?><span title="Pinned">📌</span><?php endif; ?>
                <?php echo announcement_icon($n['type']); ?>
                <?php echo htmlspecialchars($n['title'] ?? ''); ?>
                <span style="background:#e3f2fd;color:#1565c0;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">
                    <?php echo htmlspecialchars($n['type'] ?? ''); ?>
                </span>
                <?php if ($is_new): ?>
                    <span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">NEW</span>
                <?php endif; ?>
            </h2>

            <p style="color:#555;font-size:14px;line-height:1.7;margin:12px 0;">
                <?php echo nl2br(htmlspecialchars($n['body'] ?? '')); ?>
            </p>

            <div style="color:#999;font-size:12px;border-top:1px solid #f0f0f0;padding-top:11px;display:flex;gap:15px;flex-wrap:wrap;">
                <span>🗓️ Posted <?php echo date('d M Y', strtotime($n['created_at'])); ?></span>
                <?php if ($n['event_date']): ?>
                    <span>📅 <strong>Date: <?php echo date('d M Y', strtotime($n['event_date'])); ?></strong></span>
                <?php endif; ?>
                <?php if ($n['posted_by_name']): ?>
                    <span>✍️ <?php echo htmlspecialchars($n['posted_by_name'] ?? ''); ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<div id="profileSection" style="display:none;">
    <div class="profile-section">
        <h2 class="section-title">My Profile</h2>

        <div class="profile-photo-section">
            <img src="<?php echo htmlspecialchars(avatar_url($profile_photo)); ?>"
                 alt="Profile Photo" class="profile-photo" id="profilePhotoPreview"
                 onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(avatar_url("default-avatar.png")); ?>'">
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_photo" id="photoInput"
                       accept="image/jpeg,image/png,image/gif" style="display:none;">
                <button type="button" class="upload-btn"
                        onclick="document.getElementById('photoInput').click()">Choose Photo</button>
                <button type="submit" class="upload-btn" style="background:#2196F3;">Save Photo</button>
            </form>
            <p style="font-size:12px;color:#666;margin-top:10px;">Allowed: JPG, PNG, GIF</p>
        </div>

        <div class="profile-info">
            <div class="profile-field"><label>Full Name</label><span><?php echo htmlspecialchars($student['name'] ?? ''); ?></span></div>
            <div class="profile-field"><label>Student ID</label><span><?php echo htmlspecialchars($student['student_id'] ?? ''); ?></span></div>
            <div class="profile-field"><label>Gender</label><span><?php echo $student['gender'] == 'M' ? 'Male' : 'Female'; ?></span></div>
            <div class="profile-field"><label>Class</label><span><?php echo htmlspecialchars($student['grade'] ?: 'Not Assigned'); ?></span></div>
            <div class="profile-field"><label>Academic Year</label><span><?php echo htmlspecialchars($student['academic_year'] ?: '-'); ?></span></div>
            <div class="profile-field"><label>Semester</label><span><?php echo htmlspecialchars($student['semester'] ?: '-'); ?></span></div>
            <div class="profile-field"><label>Homeroom Teacher</label><span><?php echo htmlspecialchars($student['teacher_name'] ?: 'Not Assigned'); ?></span></div>
            <div class="profile-field"><label>Parent Phone</label><span><?php echo htmlspecialchars($student['parent_phone'] ?: '-'); ?></span></div>
            <div class="profile-field"><label>Class Rank</label><span><?php echo $result_complete ? $rank : '— (waiting for marks)'; ?></span></div>
            <div class="profile-field"><label>Overall Status</label>
                <?php if (!$result_complete): ?>
                    <span class="status-wait">⏳ WAITING FOR MARKS</span>
                <?php else: ?>
                    <span class="<?php echo $status=='PASS'?'status-pass':'status-fail'; ?>"><?php echo $status; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="password-section">
            <h3>Change Password</h3>
            <form method="POST" action="">
                <div class="password-form-group">
                    <label>Current Password:</label>
                    <input type="password" name="current_password" required placeholder="Enter current password">
                </div>
                <div class="password-form-group">
                    <label>New Password:</label>
                    <input type="password" name="new_password" required placeholder="Min 4 characters">
                </div>
                <div class="password-form-group">
                    <label>Confirm New Password:</label>
                    <input type="password" name="confirm_password" required placeholder="Repeat new password">
                </div>
                <button type="submit" name="change_password" class="upload-btn" style="background:#ff9800;">
                    Update Password
                </button>
            </form>
        </div>
    </div>
</div>

</div></div>

<script>
    // Nav items in sidebar
    var navItems = document.querySelectorAll('.sidebar-menu span, .sidebar-menu a');

    function showSection(section) {
        // Hide all sections
        var dashboard = document.getElementById('dashboardSection');
        var attendance = document.getElementById('attendanceSection');
        var results = document.getElementById('resultsSection');
        var profile = document.getElementById('profileSection');
        var announcements = document.getElementById('announcementsSection');
        var timetable = document.getElementById('timetableSection');
        
        if (dashboard) dashboard.style.display = 'none';
        if (attendance) attendance.style.display = 'none';
        if (results) results.style.display = 'none';
        if (profile) profile.style.display = 'none';
        if (announcements) announcements.style.display = 'none';
        if (timetable) timetable.style.display = 'none';

        // Show selected
        var map = {
            'dashboard': 'dashboardSection',
            'attendance': 'attendanceSection',
            'results': 'resultsSection',
            'profile': 'profileSection',
            'announcements': 'announcementsSection',
            'timetable': 'timetableSection'
        };
        
        var target = document.getElementById(map[section]);
        if (target) target.style.display = 'block';

        // Update active state on sidebar nav
        navItems.forEach(function(item) { 
            item.classList.remove('active'); 
        });
        
        var idx = {'dashboard':0, 'attendance':1, 'results':2, 'profile':3};
        if (idx[section] !== undefined && navItems[idx[section]]) {
            navItems[idx[section]].classList.add('active');
        }
    }

    // Photo preview
    var photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(ev) {
                    var p = document.getElementById('profilePhotoPreview');
                    if (p) p.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Mobile sidebar toggle
    var menuToggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
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