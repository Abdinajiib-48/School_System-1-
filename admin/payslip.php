<?php
// ============================================================
// admin/payslip.php - Printable payslip for one staff member.
//
// Who can open it: admin ONLY - blocked for the cashier
// ============================================================
include '../config.php';
include_once '../settings_helper.php';
include 'finance_common.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$s = null;
if ($has_finance_v2 && $id > 0) {
    $s = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT sp.*, t.name AS teacher_name, t.email, t.phone, d.name AS dept, u.username AS paid_by_name
         FROM salary_payments sp
         JOIN teachers t ON sp.teacher_id = t.id
         LEFT JOIN departments d ON t.department_id = d.id
         LEFT JOIN users u ON sp.paid_by = u.id
         WHERE sp.id = $id"));
}

if (!$s) {
    echo "<div style='padding:40px;text-align:center;font-family:Arial;'><h2>Payslip not found</h2><a href='payroll.php'>← Back to Payroll</a></div>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip <?php echo htmlspecialchars($s['payslip_no'] ?? ''); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f0f2f5; padding: 30px; }
        .slip { background: white; max-width: 640px; margin: 0 auto; padding: 35px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .head { text-align: center; border-bottom: 2px dashed #ddd; padding-bottom: 18px; margin-bottom: 20px; }
        .head h1 { font-size: 24px; color: #333; }
        .head p { color: #666; font-size: 13px; margin-top: 4px; }
        .title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .title h2 { font-size: 20px; color: #2196F3; }
        .title .no { font-weight: bold; font-size: 15px; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .row span:first-child { color: #666; }
        .row span:last-child { font-weight: 600; color: #333; }
        .section-t { margin: 20px 0 8px; font-size: 14px; font-weight: bold; color: #555; text-transform: uppercase; letter-spacing: .5px; }
        .earn { background: #f1f8f2; border-radius: 8px; padding: 12px 15px; }
        .ded  { background: #fdf1f1; border-radius: 8px; padding: 12px 15px; }
        .earn .row, .ded .row { border-bottom: 1px dashed rgba(0,0,0,.07); }
        .earn .row:last-child, .ded .row:last-child { border-bottom: none; }
        .net-box { background: #e3f2fd; border: 2px solid #2196F3; border-radius: 10px; padding: 18px; text-align: center; margin: 22px 0; }
        .net-box .lbl { color: #1565c0; font-size: 13px; }
        .net-box .amt { font-size: 32px; font-weight: bold; color: #0d47a1; }
        .sig { display: flex; justify-content: space-between; margin-top: 45px; font-size: 13px; color: #666; }
        .sig div { border-top: 1px solid #999; padding-top: 6px; width: 40%; text-align: center; }
        .foot { text-align: center; margin-top: 25px; font-size: 12px; color: #999; border-top: 2px dashed #ddd; padding-top: 15px; }
        .actions { text-align: center; margin-bottom: 20px; }
        .btn { background: #2196F3; color: white; padding: 10px 22px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; margin: 0 5px; }
        .btn-grey { background: #9e9e9e; }
        @media print { body { background: white; padding: 0; } .slip { box-shadow: none; max-width: 100%; } .actions { display: none; } }
    </style>
    <link rel="stylesheet" href="../responsive.css?v=<?php echo @filemtime(__DIR__ . '/../responsive.css'); ?>">
</head>
<body>
    <div class="actions">
        <button class="btn" onclick="window.print()">🖨️ Print Payslip</button>
        <a href="payroll.php" class="btn btn-grey">← Back to Payroll</a>
    </div>

    <div class="slip">
        <div class="head">
            <h1><?php echo htmlspecialchars(school_name($conn)); ?></h1>
            <p>Staff Salary Payslip</p>
        </div>

        <div class="title">
            <h2>💰 PAYSLIP</h2>
            <span class="no">No: <?php echo htmlspecialchars($s['payslip_no'] ?? ''); ?></span>
        </div>

        <div class="row"><span>Pay Period</span><span><?php echo date('F Y', strtotime($s['pay_month'] . '-01')); ?></span></div>
        <div class="row"><span>Employee Name</span><span><?php echo htmlspecialchars($s['teacher_name'] ?? ''); ?></span></div>
        <div class="row"><span>Department</span><span><?php echo htmlspecialchars($s['dept'] ?: '-'); ?></span></div>
        <?php if ($s['email']): ?>
        <div class="row"><span>Email</span><span><?php echo htmlspecialchars($s['email'] ?? ''); ?></span></div>
        <?php endif; ?>
        <?php if ($s['phone']): ?>
        <div class="row"><span>Phone</span><span><?php echo htmlspecialchars($s['phone'] ?? ''); ?></span></div>
        <?php endif; ?>
        <div class="row"><span>Payment Date</span><span><?php echo date('d F Y', strtotime($s['payment_date'])); ?></span></div>
        <div class="row"><span>Payment Method</span><span><?php echo htmlspecialchars($s['method'] ?? ''); ?></span></div>

        <div class="section-t">Earnings</div>
        <div class="earn">
            <div class="row"><span>Basic Salary</span><span><?php echo money($s['basic']); ?></span></div>
            <div class="row"><span>Bonus / Allowance</span><span><?php echo money($s['bonus']); ?></span></div>
            <div class="row"><span><strong>Gross Pay</strong></span><span><strong><?php echo money($s['basic'] + $s['bonus']); ?></strong></span></div>
        </div>

        <div class="section-t">Deductions</div>
        <div class="ded">
            <div class="row"><span>Total Deductions</span><span><?php echo money($s['deduction']); ?></span></div>
        </div>

        <div class="net-box">
            <div class="lbl">NET PAY</div>
            <div class="amt"><?php echo money($s['net']); ?></div>
        </div>

        <?php if ($s['notes']): ?>
            <div class="row"><span>Notes</span><span><?php echo htmlspecialchars($s['notes'] ?? ''); ?></span></div>
        <?php endif; ?>

        <div class="sig">
            <div>Paid by: <?php echo htmlspecialchars($s['paid_by_name'] ?: 'Admin'); ?></div>
            <div>Employee Signature</div>
        </div>

        <div class="foot">
            This is a computer-generated payslip.<br>
            Printed on <?php echo date('d M Y, H:i'); ?>
        </div>
    </div>
</body>
</html>
