<?php
// ============================================================
// parent/receipt_view.php - Read-only receipt. A parent may only open their own child's.
//
// Who can open it: parent
// ============================================================
// Read-only receipt for a parent. Only receipts belonging to THIS parent's children can be opened.
include '../config.php';
include_once '../settings_helper.php';
include 'parent_common.php';

$pay_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$p = null;

if ($child && $pay_id > 0 && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'payments'")) > 0) {
    // Only allow payments that belong to a child of this parent
    $allowed = implode(',', array_map('intval', array_keys($children)));
    if ($allowed !== '') {
        $p = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT p.*, s.name AS student_name, s.student_id AS student_code, s.parent_phone,
                    c.grade, c.academic_year
             FROM payments p
             JOIN students s ON p.student_id = s.id
             LEFT JOIN classes c ON s.class_id = c.id
             WHERE p.id = $pay_id AND p.student_id IN ($allowed)"));
    }
}

if (!$p) {
    echo "<div style='padding:40px;text-align:center;font-family:Arial;'>
            <h2>Receipt not found</h2>
            <p style='color:#666;'>This receipt does not belong to your child.</p>
            <a href='fees.php?child=" . (int)$child_id . "'>← Back to Fees</a>
          </div>";
    exit();
}

$sum = child_fees($conn, (int)$p['student_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo htmlspecialchars($p['receipt_no'] ?? ''); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f0f2f5; padding: 30px; }
        .receipt { background: white; max-width: 600px; margin: 0 auto; padding: 35px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .head { text-align: center; border-bottom: 2px dashed #ddd; padding-bottom: 18px; margin-bottom: 20px; }
        .head h1 { font-size: 24px; color: #333; }
        .head p  { color: #666; font-size: 13px; margin-top: 4px; }
        .title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .title h2 { font-size: 20px; color: #4CAF50; }
        .title .no { font-weight: bold; font-size: 16px; }
        .row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .row span:first-child { color: #666; }
        .row span:last-child  { font-weight: 600; color: #333; }
        .amount-box { background: #e8f5e9; border: 2px solid #4CAF50; border-radius: 10px; padding: 18px; text-align: center; margin: 22px 0; }
        .amount-box .lbl { color: #388e3c; font-size: 13px; }
        .amount-box .amt { font-size: 34px; font-weight: bold; color: #2e7d32; }
        .summary { background: #f9f9f9; border-radius: 8px; padding: 14px; margin-top: 10px; }
        .summary .row { border: none; padding: 5px 0; }
        .bal { color: #f44336 !important; }
        .foot { text-align: center; margin-top: 25px; font-size: 12px; color: #999; border-top: 2px dashed #ddd; padding-top: 15px; }
        .actions { text-align: center; margin-bottom: 20px; }
        .btn { background: #4CAF50; color: white; padding: 10px 22px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; margin: 0 5px; }
        .btn-grey { background: #9e9e9e; }
        @media print { body { background: white; padding: 0; } .receipt { box-shadow: none; max-width: 100%; } .actions { display: none; } }
    </style>
    <link rel="stylesheet" href="../responsive.css?v=<?php echo @filemtime(__DIR__ . '/../responsive.css'); ?>">
</head>
<body>
    <div class="actions">
        <button class="btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <a href="fees.php?child=<?php echo (int)$child_id; ?>" class="btn btn-grey">← Back to Fees</a>
    </div>

    <div class="receipt">
        <div class="head">
            <h1><?php echo htmlspecialchars(school_name($conn)); ?></h1>
            <p>Official Fee Payment Receipt</p>
        </div>

        <div class="title">
            <h2>✅ PAYMENT RECEIPT</h2>
            <span class="no">No: <?php echo htmlspecialchars($p['receipt_no'] ?? ''); ?></span>
        </div>

        <div class="row"><span>Date</span><span><?php echo date('d F Y', strtotime($p['payment_date'])); ?></span></div>
        <div class="row"><span>Student Name</span><span><?php echo htmlspecialchars($p['student_name'] ?? ''); ?></span></div>
        <div class="row"><span>Student ID</span><span><?php echo htmlspecialchars($p['student_code'] ?? ''); ?></span></div>
        <div class="row"><span>Class</span><span><?php echo htmlspecialchars($p['grade'] ?: '-'); ?> (<?php echo htmlspecialchars($p['academic_year'] ?: '-'); ?>)</span></div>
        <div class="row"><span>Payment Method</span><span><?php echo htmlspecialchars($p['method'] ?? ''); ?></span></div>
        <?php if ($p['notes']): ?>
        <div class="row"><span>Notes</span><span><?php echo htmlspecialchars($p['notes'] ?? ''); ?></span></div>
        <?php endif; ?>

        <div class="amount-box">
            <div class="lbl">AMOUNT PAID</div>
            <div class="amt"><?php echo money_p($p['amount']); ?></div>
        </div>

        <div class="summary">
            <div class="row"><span>Total Fee</span><span><?php echo money_p($sum['fee']); ?></span></div>
            <?php if ($sum['discount'] > 0): ?>
                <div class="row"><span>Discount / Scholarship</span><span style="color:#9c27b0;">-<?php echo money_p($sum['discount']); ?></span></div>
                <div class="row"><span>Total Payable</span><span><?php echo money_p($sum['payable']); ?></span></div>
            <?php endif; ?>
            <div class="row"><span>Total Paid to Date</span><span><?php echo money_p($sum['paid']); ?></span></div>
            <div class="row"><span>Remaining Balance</span><span class="<?php echo $sum['balance'] > 0 ? 'bal' : ''; ?>"><?php echo money_p(max($sum['balance'], 0)); ?></span></div>
        </div>

        <div class="foot">
            Thank you for your payment. Please keep this receipt for your records.<br>
            Printed on <?php echo date('d M Y, H:i'); ?>
        </div>
    </div>
</body>
</html>
