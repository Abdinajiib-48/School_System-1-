<?php
// ============================================================
// admin/finance.php - Finance home - collected, outstanding and recent activity.
//
// Who can open it: admin, cashier
// ============================================================
include '../config.php';
include 'finance_common.php';

// A cashier collects money IN - they never see money going OUT
$show_expenses = $has_finance_v2 && empty($IS_CASHIER);

$total_expected = 0; $total_collected = 0; $total_outstanding = 0; $total_discount = 0;
$students_total = 0; $students_paid = 0; $students_partial = 0; $students_unpaid = 0;
$by_class = []; $recent = []; $owing = []; $by_month = []; $by_method = [];

// v2: expenses, profit/loss, payroll, alerts
$total_expense = 0; $expense_month = 0; $income_month = 0; $net_profit = 0;
$exp_by_cat = []; $recent_expenses = []; $expense_by_month = [];
$payroll_due = 0; $payroll_pending = 0; $alerts = [];

if ($has_finance) {
    // ---- Every student with fee / discount / paid / balance ----
    if ($has_finance_v2) {
        $res = mysqli_query($conn, fee_select_sql("ORDER BY c.grade, s.name"));
    } else {
        $res = mysqli_query($conn,
            "SELECT s.id, s.student_id, s.name, s.class_id, c.grade,
                    COALESCE(f.amount, 0) AS fee, NULL AS discount_type, 0 AS discount_value,
                    COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS paid
             FROM students s
             LEFT JOIN classes c ON s.class_id = c.id
             LEFT JOIN fee_structure f ON f.class_id = c.id AND f.academic_year = c.academic_year
             ORDER BY c.grade, s.name");
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $row  = fee_row_calc($row);
        $fee  = $row['payable'];          // what the student actually owes (after discount)
        $paid = $row['paid'];
        $bal  = $row['balance'];

        $students_total++;
        $total_expected  += $fee;
        $total_collected += $paid;
        $total_discount  += $row['discount'];

        if ($fee > 0 && $bal <= 0.001)      $students_paid++;
        elseif ($paid > 0 && $bal > 0)      $students_partial++;
        elseif ($fee > 0)                   $students_unpaid++;

        if ($bal > 0.001) $owing[] = $row;

        // group by class
        $g = $row['grade'] ?: 'No class';
        if (!isset($by_class[$g])) $by_class[$g] = ['expected' => 0, 'collected' => 0, 'students' => 0, 'class_id' => $row['class_id']];
        $by_class[$g]['expected']  += $fee;
        $by_class[$g]['collected'] += $paid;
        $by_class[$g]['students']++;
    }
    $total_outstanding = $total_expected - $total_collected;

    // biggest balance first
    usort($owing, function ($a, $b) { return $b['balance'] <=> $a['balance']; });

    // ---- Recent payments ----
    $r = mysqli_query($conn,
        "SELECT p.*, s.name AS student_name, s.student_id AS student_code, c.grade
         FROM payments p
         JOIN students s ON p.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         ORDER BY p.payment_date DESC, p.id DESC LIMIT 8");
    while ($row = mysqli_fetch_assoc($r)) $recent[] = $row;

    // ---- Collections per month (last 6 months) ----
    $m = mysqli_query($conn,
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total
         FROM payments
         WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym ORDER BY ym");
    while ($row = mysqli_fetch_assoc($m)) $by_month[$row['ym']] = (float)$row['total'];

    // ---- Collections by method ----
    $mm = mysqli_query($conn, "SELECT method, SUM(amount) AS total, COUNT(*) AS cnt FROM payments GROUP BY method ORDER BY total DESC");
    while ($row = mysqli_fetch_assoc($mm)) $by_method[] = $row;

    $income_month = total_income($conn, date('Y-m-01'), date('Y-m-t'));
}

// ---- v2: expenses, profit/loss, payroll, alerts ----
if ($show_expenses) {
    $total_expense = total_expenses($conn);
    $expense_month = total_expenses($conn, date('Y-m-01'), date('Y-m-t'));
    $net_profit    = $total_collected - $total_expense;

    $ec = mysqli_query($conn,
        "SELECT ec.name, ec.icon, SUM(e.amount) AS total, COUNT(*) AS cnt
         FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id
         GROUP BY ec.id ORDER BY total DESC");
    while ($row = mysqli_fetch_assoc($ec)) $exp_by_cat[] = $row;

    $re = mysqli_query($conn,
        "SELECT e.*, ec.name AS category_name, ec.icon
         FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id
         ORDER BY e.expense_date DESC, e.id DESC LIMIT 6");
    while ($row = mysqli_fetch_assoc($re)) $recent_expenses[] = $row;

    $em = mysqli_query($conn,
        "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total
         FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym ORDER BY ym");
    while ($row = mysqli_fetch_assoc($em)) $expense_by_month[$row['ym']] = (float)$row['total'];

    // Salaries still unpaid this month
    $this_month = date('Y-m');
    $pd = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(t.monthly_salary),0) AS due
         FROM teachers t
         WHERE t.monthly_salary > 0
           AND NOT EXISTS (SELECT 1 FROM salary_payments sp WHERE sp.teacher_id = t.id AND sp.pay_month = '$this_month')"));
    $payroll_pending = (int)$pd['cnt'];
    $payroll_due     = (float)$pd['due'];
}

// ---- Alerts ----
if ($has_finance) {
    if (count($owing) > 0) {
        $alerts[] = ['type' => 'warn', 'icon' => '🔴',
                     'text' => '<strong>' . count($owing) . ' students</strong> have overdue fees totalling <strong>' . money($total_outstanding) . '</strong>.',
                     'link' => '#owing', 'link_text' => 'View list'];
    }
    if ($show_expenses && $payroll_pending > 0) {
        $alerts[] = ['type' => 'info', 'icon' => '👨‍🏫',
                     'text' => '<strong>' . $payroll_pending . ' staff</strong> not yet paid for ' . date('F Y') . ' — about <strong>' . money($payroll_due) . '</strong> due.',
                     'link' => 'payroll.php', 'link_text' => 'Go to Payroll'];
    }
    if ($show_expenses && $net_profit < 0) {
        $alerts[] = ['type' => 'danger', 'icon' => '⚠️',
                     'text' => 'Expenses are <strong>' . money(abs($net_profit)) . '</strong> more than the money collected. The school is running at a loss.',
                     'link' => 'finance_reports.php', 'link_text' => 'See report'];
    }
    if ($show_expenses && $payroll_due > 0 && ($total_collected - $total_expense) < $payroll_due) {
        $alerts[] = ['type' => 'danger', 'icon' => '💸',
                     'text' => 'Low budget: remaining balance (<strong>' . money($total_collected - $total_expense) . '</strong>) may not cover this month\'s salaries (<strong>' . money($payroll_due) . '</strong>).',
                     'link' => 'payments.php', 'link_text' => 'Collect fees'];
    }
}

$collection_rate = $total_expected > 0 ? round($total_collected / $total_expected * 100) : 0;
$all_months = array_unique(array_merge(array_keys($by_month), array_keys($expense_by_month)));
sort($all_months);
$max_month = 0;
foreach ($all_months as $m) {
    $max_month = max($max_month, isset($by_month[$m]) ? $by_month[$m] : 0, isset($expense_by_month[$m]) ? $expense_by_month[$m] : 0);
}
$max_exp_cat = 0;
foreach ($exp_by_cat as $b) $max_exp_cat = max($max_exp_cat, (float)$b['total']);

include 'sidebar.php';
render_finance_styles();
render_finance_tabs('finance.php');
?>

<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .stat-card { background: white; padding: 22px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #4CAF50; }
    .stat-card.blue   { border-color: #2196F3; }
    .stat-card.green  { border-color: #4CAF50; }
    .stat-card.red    { border-color: #f44336; }
    .stat-card.orange { border-color: #ff9800; }
    .stat-label { color: #666; font-size: 14px; margin-bottom: 6px; }
    .stat-number { font-size: 30px; font-weight: bold; color: #333; }
    .stat-sub { color: #999; font-size: 12px; margin-top: 6px; }

    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
    @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

    .progress { background: #eee; border-radius: 10px; height: 14px; overflow: hidden; }
    .progress > div { height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); border-radius: 10px; }
    .progress.big { height: 22px; }

    .class-row { margin-bottom: 14px; }
    .class-row .top { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 5px; }

    .bars { display: flex; align-items: flex-end; gap: 12px; height: 160px; padding-top: 10px; }
    .bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; }
    .bar { width: 100%; max-width: 60px; background: linear-gradient(180deg, #667eea, #764ba2); border-radius: 6px 6px 0 0; min-height: 3px; }
    .bar-label { font-size: 12px; color: #666; margin-top: 6px; }
    .bar-value { font-size: 11px; color: #333; font-weight: 600; margin-bottom: 4px; }

    .pill-list { display: flex; gap: 10px; flex-wrap: wrap; }
    .pill { background: #f0f2f5; padding: 8px 14px; border-radius: 20px; font-size: 13px; }
    .pill strong { color: #4CAF50; }

    .status-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }

    .alert-box { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 10px; margin-bottom: 12px; font-size: 14px; }
    .alert-box .ico { font-size: 22px; }
    .alert-box .txt { flex: 1; }
    .alert-warn   { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
    .alert-info   { background: #e3f2fd; color: #0d47a1; border-left: 4px solid #2196F3; }
    .alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    .alert-ok     { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
    .alert-box a  { color: inherit; font-weight: 600; white-space: nowrap; }

    .bar-pair { display: flex; gap: 3px; align-items: flex-end; height: 100%; width: 100%; justify-content: center; }
    .bar-in  { background: linear-gradient(180deg, #4CAF50, #8BC34A); width: 45%; border-radius: 5px 5px 0 0; min-height: 2px; }
    .bar-out { background: linear-gradient(180deg, #f44336, #ff7961); width: 45%; border-radius: 5px 5px 0 0; min-height: 2px; }
    .legend { display: flex; gap: 16px; font-size: 12px; color: #666; margin-top: 10px; justify-content: center; }
    .legend i { display: inline-block; width: 11px; height: 11px; border-radius: 3px; margin-right: 5px; }
    .cat-row { margin-bottom: 12px; }
    .cat-row .top { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px; }
    .cat-row .progress > div { background: linear-gradient(90deg, #f44336, #ff7961); }
</style>

<?php if (isset($_GET['denied'])): ?>
    <div class="alert-box a-bad" style="margin-bottom:18px;">
        <span class="ico">🔒</span>
        <span class="txt">That page is for administrators only. Cashiers handle fee collection.</span>
    </div>
<?php endif; ?>

<?php if (!$has_finance): render_finance_missing(); else: ?>

<!-- ===== ALERTS ===== -->
<?php if (count($alerts) > 0): ?>
    <div class="card" style="padding:20px 22px;">
        <h2 style="font-size:17px;">🔔 Notifications</h2>
        <?php foreach ($alerts as $a): ?>
            <div class="alert-box alert-<?php echo $a['type']; ?>">
                <span class="ico"><?php echo $a['icon']; ?></span>
                <span class="txt"><?php echo $a['text']; ?></span>
                <a href="<?php echo $a['link']; ?>"><?php echo $a['link_text']; ?> →</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert-box alert-ok"><span class="ico">✅</span><span class="txt">All good — no overdue fees, no pending salaries.</span></div>
<?php endif; ?>

<!-- ===== MONEY IN / OUT ===== -->
<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-label">💵 Total Income (collected)</div>
        <div class="stat-number" style="color:#4CAF50;"><?php echo money($total_collected); ?></div>
        <div class="stat-sub"><?php echo money($income_month); ?> this month</div>
    </div>
    <?php if ($show_expenses): ?>
    <div class="stat-card red">
        <div class="stat-label">🧾 Total Expenses</div>
        <div class="stat-number" style="color:#f44336;"><?php echo $show_expenses ? money($total_expense) : '—'; ?></div>
        <div class="stat-sub"><?php echo $show_expenses ? money($expense_month) . ' this month' : 'Import finance_schema_v2.sql'; ?></div>
    </div>
    <?php endif; ?>
    <?php if ($show_expenses): ?>
    <div class="stat-card blue">
        <div class="stat-label"><?php echo $net_profit >= 0 ? '📈 Net Profit' : '📉 Net Loss'; ?></div>
        <div class="stat-number" style="color:<?php echo $net_profit >= 0 ? '#2196F3' : '#f44336'; ?>;"><?php echo $show_expenses ? money(abs($net_profit)) : '—'; ?></div>
        <div class="stat-sub">Income minus expenses (all time)</div>
    </div>
    <?php endif; ?>
    <div class="stat-card orange">
        <div class="stat-label">🔴 Outstanding Fees</div>
        <div class="stat-number" style="color:#ff9800;"><?php echo money($total_outstanding); ?></div>
        <div class="stat-sub"><?php echo count($owing); ?> students still owe</div>
    </div>
</div>

<!-- ===== FEE STATS ===== -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-label">🏷️ Total Fees Expected</div>
        <div class="stat-number"><?php echo money($total_expected); ?></div>
        <div class="stat-sub"><?php echo $students_total; ?> students<?php if ($total_discount > 0): ?> · <?php echo money($total_discount); ?> in discounts<?php endif; ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">✅ Collection Rate</div>
        <div class="stat-number"><?php echo $collection_rate; ?>%</div>
        <div class="stat-sub"><?php echo money($total_collected); ?> of <?php echo money($total_expected); ?></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-label">👨‍🎓 Payment Status</div>
        <div class="stat-number"><?php echo $students_paid; ?> <span style="font-size:16px;color:#999;">/ <?php echo $students_total; ?> fully paid</span></div>
        <div class="status-row">
            <span class="badge badge-paid">Paid: <?php echo $students_paid; ?></span>
            <span class="badge badge-partial">Partial: <?php echo $students_partial; ?></span>
            <span class="badge badge-unpaid">Unpaid: <?php echo $students_unpaid; ?></span>
        </div>
    </div>
    <?php if ($show_expenses): ?>
    <div class="stat-card red">
        <div class="stat-label">👨‍🏫 Payroll This Month</div>
        <div class="stat-number" style="font-size:24px;"><?php echo $show_expenses ? $payroll_pending . ' pending' : '—'; ?></div>
        <div class="stat-sub"><?php echo $show_expenses ? money($payroll_due) . ' still to pay' : 'Import finance_schema_v2.sql'; ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== OVERALL PROGRESS ===== -->
<div class="card">
    <h2>📈 Overall Collection Progress</h2>
    <div class="progress big"><div style="width:<?php echo $collection_rate; ?>%"></div></div>
    <p style="margin-top:10px;color:#666;">
        <strong><?php echo $collection_rate; ?>%</strong> collected —
        <?php echo money($total_collected); ?> of <?php echo money($total_expected); ?>
    </p>
    <div style="margin-top:15px;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="payments.php" class="btn">💵 Record Payment</a>
        <?php if ($show_expenses): ?>
            <a href="expenses.php" class="btn btn-red">🧾 Record Expense</a>
            <a href="payroll.php" class="btn btn-blue">👨‍🏫 Pay Salaries</a>
        <?php endif; ?>
        <?php if (!empty($IS_CASHIER)): ?>
            <a href="fee_reminders.php" class="btn btn-red">🔔 Send Fee Reminder</a>
        <?php endif; ?>
        <a href="finance_reports.php" class="btn btn-grey">📈 Reports</a>
        <a href="fees.php" class="btn btn-grey">🏷️ Set Fees</a>
    </div>
</div>

<div class="two-col">
    <!-- ===== BY CLASS ===== -->
    <div class="card">
        <h2>🏫 Collection by Class</h2>
        <?php if (count($by_class) == 0): ?>
            <p style="color:#999;">No data.</p>
        <?php else: foreach ($by_class as $grade => $d):
            $pct = $d['expected'] > 0 ? round($d['collected'] / $d['expected'] * 100) : 0; ?>
            <div class="class-row">
                <div class="top">
                    <span><strong><?php echo htmlspecialchars($grade ?? ''); ?></strong> <small style="color:#888;">(<?php echo $d['students']; ?> students)</small></span>
                    <span><?php echo money($d['collected']); ?> / <?php echo money($d['expected']); ?> — <strong><?php echo $pct; ?>%</strong></span>
                </div>
                <div class="progress"><div style="width:<?php echo $pct; ?>%"></div></div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- ===== BY MONTH ===== -->
    <div class="card">
        <h2>📅 Income vs Expenses – Last 6 Months</h2>
        <?php if (count($all_months) == 0): ?>
            <p style="color:#999;">No activity in the last 6 months.</p>
        <?php else: ?>
            <div class="bars">
                <?php foreach ($all_months as $ym):
                    $inc = isset($by_month[$ym]) ? $by_month[$ym] : 0;
                    $exp = isset($expense_by_month[$ym]) ? $expense_by_month[$ym] : 0;
                    $hi  = $max_month > 0 ? round($inc / $max_month * 100) : 0;
                    $he  = $max_month > 0 ? round($exp / $max_month * 100) : 0; ?>
                    <div class="bar-wrap">
                        <div class="bar-value" style="font-size:10px;">
                            <span style="color:#4CAF50;"><?php echo money($inc); ?></span>
                            <?php if ($show_expenses): ?><br><span style="color:#f44336;"><?php echo money($exp); ?></span><?php endif; ?>
                        </div>
                        <div class="bar-pair">
                            <div class="bar-in" style="height:<?php echo $hi; ?>%" title="Income"></div>
                            <?php if ($show_expenses): ?><div class="bar-out" style="height:<?php echo $he; ?>%" title="Expenses"></div><?php endif; ?>
                        </div>
                        <div class="bar-label"><?php echo date('M Y', strtotime($ym . '-01')); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="legend">
                <span><i style="background:#4CAF50;"></i>Income</span>
                <?php if ($show_expenses): ?><span><i style="background:#f44336;"></i>Expenses</span><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (count($by_method) > 0): ?>
            <h2 style="margin-top:25px;font-size:16px;">💳 By Payment Method</h2>
            <div class="pill-list">
                <?php foreach ($by_method as $bm): ?>
                    <span class="pill"><?php echo htmlspecialchars($bm['method'] ?? ''); ?>: <strong><?php echo money($bm['total']); ?></strong> (<?php echo $bm['cnt']; ?>)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($show_expenses): ?>
<div class="two-col">
    <!-- ===== EXPENSES BY CATEGORY ===== -->
    <div class="card">
        <h2>🧾 Expenses by Category</h2>
        <?php if (count($exp_by_cat) == 0): ?>
            <p style="color:#999;text-align:center;padding:20px;">No expenses yet. <a href="expenses.php">Record the first one →</a></p>
        <?php else: foreach ($exp_by_cat as $b):
            $pct = $max_exp_cat > 0 ? round($b['total'] / $max_exp_cat * 100) : 0; ?>
            <div class="cat-row">
                <div class="top">
                    <span><?php echo $b['icon'] . ' ' . htmlspecialchars($b['name'] ?? ''); ?> <small style="color:#888;">(<?php echo $b['cnt']; ?>)</small></span>
                    <span><strong><?php echo money($b['total']); ?></strong></span>
                </div>
                <div class="progress"><div style="width:<?php echo $pct; ?>%"></div></div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- ===== RECENT EXPENSES ===== -->
    <div class="card">
        <h2>🕒 Recent Expenses</h2>
        <?php if (count($recent_expenses) == 0): ?>
            <p style="color:#999;text-align:center;padding:20px;">No expenses recorded yet.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th>Title</th><th>Category</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($recent_expenses as $e): ?>
                        <tr>
                            <td><?php echo date('d M', strtotime($e['expense_date'])); ?></td>
                            <td><?php echo htmlspecialchars($e['title'] ?? ''); ?>
                                <?php if ($e['source'] === 'Payroll'): ?><span class="badge badge-info">Payroll</span><?php endif; ?>
                            </td>
                            <td><?php echo $e['icon'] . ' ' . htmlspecialchars($e['category_name'] ?? ''); ?></td>
                            <td class="num" style="color:#f44336;"><strong><?php echo money($e['amount']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:12px;"><a href="expenses.php" style="color:#4CAF50;text-decoration:none;">View all expenses →</a></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="two-col">
    <!-- ===== STUDENTS OWING ===== -->
    <div class="card" id="owing">
        <h2>🔴 Students with Outstanding Balance</h2>
        <?php if (count($owing) == 0): ?>
            <p style="color:#4CAF50;text-align:center;padding:20px;">🎉 Everyone has paid in full!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Student</th><th>Class</th><th class="num">Payable</th><th class="num">Paid</th><th class="num">Balance</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($owing as $o): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($o['name'] ?? ''); ?><br><small style="color:#888;"><?php echo htmlspecialchars($o['student_id'] ?? ''); ?></small></td>
                            <td><?php echo htmlspecialchars($o['grade'] ?: '-'); ?></td>
                            <td class="num"><?php echo money($o['payable']); ?>
                                <?php if ($o['discount'] > 0): ?><br><small style="color:#9c27b0;">-<?php echo money($o['discount']); ?> off</small><?php endif; ?>
                            </td>
                            <td class="num"><?php echo money($o['paid']); ?></td>
                            <td class="num" style="color:#f44336;"><strong><?php echo money($o['balance']); ?></strong></td>
                            <td>
                                <a href="payments.php?student_id=<?php echo $o['id']; ?>" class="btn btn-sm">+ Pay</a>
                                <a href="invoice.php?student_id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-sm btn-grey">🧾</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- ===== RECENT PAYMENTS ===== -->
    <div class="card">
        <h2>🕒 Recent Payments</h2>
        <?php if (count($recent) == 0): ?>
            <p style="color:#999;text-align:center;padding:20px;">No payments yet. <a href="payments.php">Record the first one →</a></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Receipt</th><th>Student</th><th>Date</th><th class="num">Amount</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['receipt_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($p['student_name'] ?? ''); ?> <small style="color:#888;">(<?php echo htmlspecialchars($p['grade'] ?: '-'); ?>)</small></td>
                            <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                            <td class="num"><strong><?php echo money($p['amount']); ?></strong></td>
                            <td><a href="receipt.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-blue">🖨️</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:12px;"><a href="payments.php" style="color:#4CAF50;text-decoration:none;">View all payments →</a></p>
        <?php endif; ?>
    </div>
</div>

<?php endif; // has_finance ?>

<?php // Close the divs opened in sidebar.php ?>
</div></div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() { sidebar.classList.toggle('active'); });
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
