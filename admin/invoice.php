<?php
// ============================================================
// admin/invoice.php - Printable fee invoice for one student. Needs ?student_id=<id>.
//
// Who can open it: admin, cashier
// ============================================================
include '../config.php';
include_once '../settings_helper.php';
include 'finance_common.php';

$sid = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student = null;
if ($has_finance && $sid > 0) {
    $student = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT s.*, c.grade, c.academic_year, c.semester
         FROM students s LEFT JOIN classes c ON s.class_id = c.id
         WHERE s.id = $sid"));
}

if (!$student) {
    echo "<div style='padding:40px;text-align:center;font-family:Arial;'><h2>Student not found</h2><a href='finance.php'>← Back to Finance</a></div>";
    exit();
}

$sum = student_fee_summary($conn, $sid);

// All payments by this student
$payments = [];
$pr = mysqli_query($conn, "SELECT * FROM payments WHERE student_id = $sid ORDER BY payment_date, id");
while ($r = mysqli_fetch_assoc($pr)) $payments[] = $r;

$invoice_no = 'INV-' . str_pad($sid, 4, '0', STR_PAD_LEFT) . '-' . date('Ym');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice_no ?? ''); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f0f2f5; padding: 30px; }
        .inv { background: white; max-width: 720px; margin: 0 auto; padding: 35px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #4CAF50; padding-bottom: 18px; margin-bottom: 22px; }
        .head h1 { font-size: 22px; color: #333; }
        .head p { color: #666; font-size: 13px; margin-top: 3px; }
        .head .right { text-align: right; }
        .head .right h2 { font-size: 22px; color: #4CAF50; }
        .head .right .no { font-size: 13px; color: #666; margin-top: 4px; }

        .who { display: flex; justify-content: space-between; margin-bottom: 25px; gap: 20px; }
        .who .box { flex: 1; background: #f9f9f9; border-radius: 8px; padding: 14px; font-size: 13px; }
        .who .box .t { color: #888; text-transform: uppercase; font-size: 11px; letter-spacing: .5px; margin-bottom: 6px; }
        .who .box div { margin: 3px 0; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #4CAF50; color: white; }
        td.num, th.num { text-align: right; }
        tr.sub td { background: #f9f9f9; font-weight: 600; }

        .totals { margin-left: auto; width: 300px; font-size: 14px; }
        .totals .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .totals .row.big { border-top: 2px solid #333; border-bottom: none; font-size: 18px; font-weight: bold; padding-top: 12px; }
        .status { text-align: center; padding: 14px; border-radius: 10px; margin: 22px 0; font-size: 17px; font-weight: bold; }
        .st-paid   { background: #d4edda; color: #155724; }
        .st-part   { background: #fff3cd; color: #856404; }
        .st-unpaid { background: #f8d7da; color: #721c24; }
        .foot { text-align: center; margin-top: 25px; font-size: 12px; color: #999; border-top: 2px dashed #ddd; padding-top: 15px; }
        .actions { text-align: center; margin-bottom: 20px; }
        .btn { background: #4CAF50; color: white; padding: 10px 22px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; margin: 0 5px; }
        .btn-grey { background: #9e9e9e; }
        @media print { body { background: white; padding: 0; } .inv { box-shadow: none; max-width: 100%; } .actions { display: none; } }
    </style>
    <link rel="stylesheet" href="../responsive.css?v=<?php echo @filemtime(__DIR__ . '/../responsive.css'); ?>">
</head>
<body>
    <div class="actions">
        <button class="btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <a href="finance.php" class="btn btn-grey">← Back to Finance</a>
    </div>

    <div class="inv">
        <div class="head">
            <div>
                <h1><?php echo htmlspecialchars(school_name($conn)); ?></h1>
                <p>Student Fee Statement</p>
            </div>
            <div class="right">
                <h2>INVOICE</h2>
                <div class="no"><?php echo htmlspecialchars($invoice_no ?? ''); ?></div>
                <div class="no">Date: <?php echo date('d M Y'); ?></div>
            </div>
        </div>

        <div class="who">
            <div class="box">
                <div class="t">Billed To</div>
                <div><strong><?php echo htmlspecialchars($student['name'] ?? ''); ?></strong></div>
                <div>ID: <?php echo htmlspecialchars($student['student_id'] ?? ''); ?></div>
                <?php if ($student['parent_phone']): ?><div>Parent: <?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?></div><?php endif; ?>
                <?php if ($student['address']): ?><div><?php echo htmlspecialchars($student['address'] ?? ''); ?></div><?php endif; ?>
            </div>
            <div class="box">
                <div class="t">Class Details</div>
                <div>Class: <strong><?php echo htmlspecialchars($student['grade'] ?: '-'); ?></strong></div>
                <div>Academic Year: <?php echo htmlspecialchars($student['academic_year'] ?: '-'); ?></div>
                <div>Semester: <?php echo htmlspecialchars($student['semester'] ?: '-'); ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Description</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Tuition Fee – Class <?php echo htmlspecialchars($student['grade'] ?: '-'); ?> (<?php echo htmlspecialchars($student['academic_year'] ?: '-'); ?>)</td>
                    <td class="num"><?php echo money($sum['fee']); ?></td>
                </tr>
                <?php if ($sum['discount'] > 0): ?>
                <tr>
                    <td>Discount / Scholarship</td>
                    <td class="num" style="color:#9c27b0;">-<?php echo money($sum['discount']); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="sub">
                    <td>Total Payable</td>
                    <td class="num"><?php echo money($sum['payable']); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (count($payments) > 0): ?>
            <h3 style="font-size:15px;margin-bottom:10px;color:#555;">Payments Received</h3>
            <table>
                <thead>
                    <tr><th>Date</th><th>Receipt</th><th>Method</th><th class="num">Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($p['receipt_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($p['method'] ?? ''); ?></td>
                            <td class="num" style="color:#4CAF50;"><?php echo money($p['amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="totals">
            <div class="row"><span>Total Payable</span><span><?php echo money($sum['payable']); ?></span></div>
            <div class="row"><span>Total Paid</span><span style="color:#4CAF50;">-<?php echo money($sum['paid']); ?></span></div>
            <div class="row big">
                <span>Balance Due</span>
                <span style="color:<?php echo $sum['balance'] > 0 ? '#f44336' : '#4CAF50'; ?>;"><?php echo money(max($sum['balance'], 0)); ?></span>
            </div>
        </div>

        <?php
        if ($sum['balance'] <= 0.001)    { $cls = 'st-paid';   $txt = '✅ PAID IN FULL — Thank you!'; }
        elseif ($sum['paid'] > 0)        { $cls = 'st-part';   $txt = '⚠️ PARTIALLY PAID — ' . money($sum['balance']) . ' still due'; }
        else                             { $cls = 'st-unpaid'; $txt = '❌ UNPAID — ' . money($sum['balance']) . ' due'; }
        ?>
        <div class="status <?php echo $cls; ?>"><?php echo $txt; ?></div>

        <div class="foot">
            Please settle any outstanding balance at the school finance office.<br>
            Printed on <?php echo date('d M Y, H:i'); ?>
        </div>
    </div>
</body>
</html>
