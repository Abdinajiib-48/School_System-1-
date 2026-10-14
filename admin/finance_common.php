<?php
include_once __DIR__ . '/../auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// Shared helper for all finance pages (finance.php, fees.php,
// payments.php, receipt.php). Include AFTER config.php.
// ============================================================

require_role(['admin', 'cashier']);

$IS_CASHIER = ($_SESSION['role'] === 'cashier');

// A cashier whose account was disabled is signed out
if ($IS_CASHIER && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'cashiers'")) > 0) {
    $me = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT is_active FROM cashiers WHERE user_id = " . (int)$_SESSION['user_id']));
    if (!$me || !$me['is_active']) {
        header('Location: ../logout.php');
        exit();
    }
}

// Pages a cashier is NOT allowed to open (money going OUT of the school)
$CASHIER_BLOCKED = ['expenses.php', 'payroll.php', 'payslip.php'];
if ($IS_CASHIER && in_array(basename($_SERVER['PHP_SELF']), $CASHIER_BLOCKED)) {
    header('Location: finance.php?denied=1');
    exit();
}

// Are the finance tables installed?
$has_finance = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'payments'")) > 0
            && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'fee_structure'")) > 0;

// Are the v2 tables (expenses / payroll / discounts) installed?
$has_finance_v2 = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'expenses'")) > 0
               && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'salary_payments'")) > 0
               && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'student_discounts'")) > 0;

// Format a number as money, e.g. 1500 -> $1,500.00
function money($n) {
    return '$' . number_format((float)$n, 2);
}

// Next receipt number, e.g. R-0001, R-0002 ...
function next_receipt_no($conn) {
    $res = mysqli_query($conn, "SELECT MAX(id) AS max_id FROM payments");
    $row = mysqli_fetch_assoc($res);
    return 'R-' . str_pad(((int)$row['max_id']) + 1, 4, '0', STR_PAD_LEFT);
}

// Work out the discount amount off a fee
function discount_amount($fee, $type, $value) {
    if ($fee <= 0 || $value <= 0) return 0;
    $off = ($type === 'Percent') ? $fee * $value / 100 : $value;
    return min($off, $fee);   // never more than the fee itself
}

// SQL used by every page that needs fee / discount / paid / balance.
// Gives columns: fee, discount_type, discount_value, paid
function fee_select_sql($extra_where = '') {
    return "SELECT s.id, s.student_id, s.name, s.class_id, c.grade, c.academic_year,
                   COALESCE(f.amount, 0) AS fee,
                   d.discount_type, COALESCE(d.value, 0) AS discount_value, d.reason AS discount_reason,
                   COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS paid
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN fee_structure f ON f.class_id = c.id AND f.academic_year = c.academic_year
            LEFT JOIN student_discounts d ON d.student_id = s.id
            $extra_where";
}

// Fill in discount / payable / balance on a row from fee_select_sql()
function fee_row_calc($row) {
    $fee  = (float)$row['fee'];
    $disc = discount_amount($fee, $row['discount_type'], (float)$row['discount_value']);
    $paid = (float)$row['paid'];
    $row['fee']      = $fee;
    $row['discount'] = $disc;
    $row['payable']  = $fee - $disc;
    $row['paid']     = $paid;
    $row['balance']  = ($fee - $disc) - $paid;
    return $row;
}

// Fee / discount / payable / paid / balance for ONE student
function student_fee_summary($conn, $student_id) {
    $student_id = (int)$student_id;
    $has_disc = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'student_discounts'")) > 0;
    if (!$has_disc) {
        $row = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(f.amount, 0) AS fee, NULL AS discount_type, 0 AS discount_value,
                    COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id),0) AS paid
             FROM students s
             LEFT JOIN classes c ON s.class_id = c.id
             WHERE s.id = $student_id"));
    } else {
        $row = mysqli_fetch_assoc(mysqli_query($conn, fee_select_sql("WHERE s.id = $student_id")));
    }
    if (!$row) return ['fee'=>0,'discount'=>0,'payable'=>0,'paid'=>0,'balance'=>0];
    return fee_row_calc($row);
}

// ---- Totals used by the dashboard and reports ----
// Income (fees collected). Pass null for "all time".
function total_income($conn, $from = null, $to = null) {
    $w = ($from && $to) ? "WHERE payment_date BETWEEN '$from' AND '$to'" : '';
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments $w"));
    return (float)$r['t'];
}

// Expenses. Pass null for "all time".
function total_expenses($conn, $from = null, $to = null) {
    if (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'expenses'")) == 0) return 0;
    $w = ($from && $to) ? "WHERE expense_date BETWEEN '$from' AND '$to'" : '';
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM expenses $w"));
    return (float)$r['t'];
}

// Next payslip number, e.g. PS-0001
function next_payslip_no($conn) {
    $res = mysqli_query($conn, "SELECT MAX(id) AS max_id FROM salary_payments");
    $row = mysqli_fetch_assoc($res);
    return 'PS-' . str_pad(((int)$row['max_id']) + 1, 4, '0', STR_PAD_LEFT);
}

// Tab bar shown at the top of every finance page
function render_finance_tabs($active) {
    global $IS_CASHIER;
    $tabs = [
        'finance.php'         => '📊 Dashboard',
        'fees.php'            => '🏷️ Fees',
        'discounts.php'       => '🎓 Discounts',
        'payments.php'        => '💵 Payments',
        'expenses.php'        => '🧾 Expenses',
        'payroll.php'         => '💰 Payroll',
        'finance_reports.php' => '📈 Reports',
    ];
    if (!empty($IS_CASHIER)) {
        unset($tabs['expenses.php'], $tabs['payroll.php']);
        $tabs['fee_reminders.php'] = '🔔 Fee Reminders';
    }
    echo '<div class="finance-tabs">';
    foreach ($tabs as $file => $label) {
        $cls = ($file === $active) ? 'finance-tab active' : 'finance-tab';
        echo "<a href=\"$file\" class=\"$cls\">$label</a>";
    }
    echo '</div>';
}

// Warning shown if tables are missing
function render_finance_missing() {
    ?>
    <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:10px;padding:20px;margin-bottom:20px;">
        <strong>⚠️ Finance tables not found.</strong><br>
        Import <strong>finance_schema.sql</strong> in phpMyAdmin (database <strong>School_db1</strong>) first.
    </div>
    <?php
}

// Warning shown if the v2 tables are missing
function render_finance_v2_missing() {
    ?>
    <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:10px;padding:20px;margin-bottom:20px;">
        <strong>⚠️ This feature needs the new finance tables.</strong><br>
        Import <strong>finance_schema_v2.sql</strong> in phpMyAdmin (database <strong>School_db1</strong>) first.
    </div>
    <?php
}

// Shared CSS - call this AFTER including sidebar.php
function render_finance_styles() {
?>
<style>
    /* ---- shared finance styles ---- */
    .finance-tabs { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px; }
    .finance-tab { padding: 10px 20px; border-radius: 10px; background: white; color: #333; text-decoration: none; font-weight: 500; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 2px solid transparent; }
    .finance-tab:hover { border-color: #4CAF50; }
    .finance-tab.active { background: #4CAF50; color: white; }

    .card { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card h2 { margin-bottom: 20px; color: #333; font-size: 20px; }

    .form-group { margin-bottom: 15px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
    input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; }
    input:focus, select:focus, textarea:focus { outline: none; border-color: #4CAF50; }
    .hint { font-size: 12px; color: #666; margin-top: 4px; }

    button, .btn { background: #4CAF50; color: white; padding: 11px 22px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; }
    button:hover, .btn:hover { background: #45a049; }
    .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 5px; }
    .btn-orange { background: #ff9800; } .btn-orange:hover { background: #f57c00; }
    .btn-red { background: #f44336; } .btn-red:hover { background: #d32f2f; }
    .btn-blue { background: #2196F3; } .btn-blue:hover { background: #1976D2; }
    .btn-grey { background: #9e9e9e; } .btn-grey:hover { background: #757575; }

    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
    th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
    th { background: #4CAF50; color: white; font-weight: bold; }
    tr:nth-child(even) { background: #f9f9f9; }
    td.num, th.num { text-align: right; }

    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .badge-paid { background: #d4edda; color: #155724; }
    .badge-partial { background: #fff3cd; color: #856404; }
    .badge-unpaid { background: #f8d7da; color: #721c24; }

    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
    .progress { background: #eee; border-radius: 10px; height: 14px; overflow: hidden; }
    .progress > div { height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); border-radius: 10px; }
    .badge-info { background: #e3f2fd; color: #1565c0; }
    .badge-grey { background: #eee; color: #666; }
    .empty { color: #999; text-align: center; padding: 30px; }

    @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .form-row, .form-row-3 { grid-template-columns: 1fr; } }
    @media print { .sidebar, .top-bar, .finance-tabs, .no-print { display: none !important; } .main-content { margin-left: 0 !important; } .page-content { padding: 0 !important; } .card { box-shadow: none; } }
</style>
<?php
}
?>
