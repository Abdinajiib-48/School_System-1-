<?php
// ============================================================
// admin/finance_reports.php - Printable income and expense report for a chosen period.
//
// Who can open it: admin, cashier
// ============================================================
include '../config.php';
include_once '../settings_helper.php';
include 'finance_common.php';

// ---- Work out the date range ----
// A cashier sees income only - no expenses, no profit/loss
$show_expenses = $has_finance_v2 && empty($IS_CASHIER);

$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$today  = date('Y-m-d');

switch ($period) {
    case 'today':  $from = $today;                            $to = $today;                          $label = 'Today – ' . date('d M Y'); break;
    case 'week':   $from = date('Y-m-d', strtotime('monday this week')); $to = $today;               $label = 'This Week'; break;
    case 'year':   $from = date('Y-01-01');                   $to = date('Y-12-31');                 $label = 'Year ' . date('Y'); break;
    case 'custom':
        $from  = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-01');
        $to    = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : $today;
        $label = date('d M Y', strtotime($from)) . ' → ' . date('d M Y', strtotime($to));
        break;
    case 'month':
    default:
        $period = 'month';
        $from = date('Y-m-01'); $to = date('Y-m-t'); $label = date('F Y');
        break;
}
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }

// ---- Gather the numbers ----
$income = 0; $expense = 0; $income_rows = []; $expense_rows = []; $exp_by_cat = []; $inc_by_class = [];

if ($has_finance) {
    $income = total_income($conn, $from, $to);

    $ir = mysqli_query($conn,
        "SELECT p.payment_date AS d, p.receipt_no AS ref, s.name AS who, c.grade AS extra, p.method, p.amount
         FROM payments p
         JOIN students s ON p.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE p.payment_date BETWEEN '$from' AND '$to'
         ORDER BY p.payment_date DESC, p.id DESC");
    while ($r = mysqli_fetch_assoc($ir)) $income_rows[] = $r;

    $ic = mysqli_query($conn,
        "SELECT COALESCE(c.grade,'No class') AS grade, SUM(p.amount) AS total, COUNT(*) AS cnt
         FROM payments p
         JOIN students s ON p.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         WHERE p.payment_date BETWEEN '$from' AND '$to'
         GROUP BY c.grade ORDER BY total DESC");
    while ($r = mysqli_fetch_assoc($ic)) $inc_by_class[] = $r;
}

if ($show_expenses) {
    $expense = total_expenses($conn, $from, $to);

    $er = mysqli_query($conn,
        "SELECT e.expense_date AS d, ec.name AS cat, e.title AS who, e.vendor AS extra, e.method, e.amount, e.source
         FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id
         WHERE e.expense_date BETWEEN '$from' AND '$to'
         ORDER BY e.expense_date DESC, e.id DESC");
    while ($r = mysqli_fetch_assoc($er)) $expense_rows[] = $r;

    $ec = mysqli_query($conn,
        "SELECT ec.name, ec.icon, SUM(e.amount) AS total, COUNT(*) AS cnt
         FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id
         WHERE e.expense_date BETWEEN '$from' AND '$to'
         GROUP BY ec.id ORDER BY total DESC");
    while ($r = mysqli_fetch_assoc($ec)) $exp_by_cat[] = $r;
}

$profit    = $income - $expense;
$max_side  = max($income, $expense, 1);
$qs        = 'period=' . urlencode($period) . '&from=' . urlencode($from) . '&to=' . urlencode($to);

// ---- CSV export (must run before any HTML is sent) ----
if (isset($_GET['export'])) {
    $what = $_GET['export'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $what . '_report_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));   // BOM so Excel shows accents correctly

    if ($what === 'income') {
        fputcsv($out, ['Date', 'Receipt', 'Student', 'Class', 'Method', 'Amount']);
        foreach ($income_rows as $r) fputcsv($out, [$r['d'], $r['ref'], $r['who'], $r['extra'], $r['method'], $r['amount']]);
        fputcsv($out, []); fputcsv($out, ['TOTAL INCOME', '', '', '', '', $income]);
    } elseif ($what === 'expense') {
        fputcsv($out, ['Date', 'Category', 'Title', 'Vendor', 'Method', 'Amount']);
        foreach ($expense_rows as $r) fputcsv($out, [$r['d'], $r['cat'], $r['who'], $r['extra'], $r['method'], $r['amount']]);
        fputcsv($out, []); fputcsv($out, ['TOTAL EXPENSES', '', '', '', '', $expense]);
    } else { // summary
        fputcsv($out, ['Financial Summary', $label]);
        fputcsv($out, ['Period', $from . ' to ' . $to]);
        fputcsv($out, []);
        fputcsv($out, ['Total Income', $income]);
        fputcsv($out, ['Total Expenses', $expense]);
        fputcsv($out, ['Net Profit/Loss', $profit]);
        fputcsv($out, []);
        fputcsv($out, ['Income by Class']);
        fputcsv($out, ['Class', 'Payments', 'Amount']);
        foreach ($inc_by_class as $r) fputcsv($out, [$r['grade'], $r['cnt'], $r['total']]);
        fputcsv($out, []);
        fputcsv($out, ['Expenses by Category']);
        fputcsv($out, ['Category', 'Records', 'Amount']);
        foreach ($exp_by_cat as $r) fputcsv($out, [$r['name'], $r['cnt'], $r['total']]);
    }
    fclose($out);
    exit();
}

include 'sidebar.php';
render_finance_styles();
render_finance_tabs('finance_reports.php');
?>

<style>
    .period-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
    .period-btn { padding: 9px 18px; border-radius: 8px; background: #f0f2f5; color: #333; text-decoration: none; font-size: 14px; font-weight: 500; }
    .period-btn:hover { background: #e0f2e9; }
    .period-btn.active { background: #4CAF50; color: white; }

    .pl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px,1fr)); gap: 20px; margin-bottom: 25px; }
    .pl { background: white; padding: 22px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #ccc; }
    .pl.in   { border-color: #4CAF50; }
    .pl.out  { border-color: #f44336; }
    .pl.net  { border-color: #2196F3; }
    .pl .lbl { color: #666; font-size: 14px; }
    .pl .val { font-size: 29px; font-weight: bold; margin-top: 6px; }
    .pl .sub { color: #999; font-size: 12px; margin-top: 5px; }

    .cmp { margin-bottom: 14px; }
    .cmp .top { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 5px; }
    .cmp .bar { height: 22px; border-radius: 6px; }
    .cmp .bar.in  { background: linear-gradient(90deg, #4CAF50, #8BC34A); }
    .cmp .bar.out { background: linear-gradient(90deg, #f44336, #ff7961); }

    .report-head { text-align: center; margin-bottom: 20px; display: none; }
    @media print { .report-head { display: block; } }
</style>

<?php if (!$has_finance): render_finance_missing(); else: ?>

<div class="card no-print">
    <h2>📅 Report Period</h2>
    <div class="period-bar">
        <a href="finance_reports.php?period=today" class="period-btn <?php echo $period=='today'?'active':''; ?>">Daily</a>
        <a href="finance_reports.php?period=week"  class="period-btn <?php echo $period=='week' ?'active':''; ?>">Weekly</a>
        <a href="finance_reports.php?period=month" class="period-btn <?php echo $period=='month'?'active':''; ?>">Monthly</a>
        <a href="finance_reports.php?period=year"  class="period-btn <?php echo $period=='year' ?'active':''; ?>">Annual</a>
    </div>

    <form method="GET" action="finance_reports.php" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="period" value="custom">
        <div style="flex:1;min-width:150px;"><label>From</label><input type="date" name="from" value="<?php echo htmlspecialchars($from ?? ''); ?>"></div>
        <div style="flex:1;min-width:150px;"><label>To</label><input type="date" name="to" value="<?php echo htmlspecialchars($to ?? ''); ?>"></div>
        <div><button type="submit">Custom Range</button></div>
    </form>

    <div style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="window.print()">🖨️ Print / Save as PDF</button>
        <a href="finance_reports.php?<?php echo $qs; ?>&export=summary" class="btn btn-blue">📊 Export Summary (Excel)</a>
        <a href="finance_reports.php?<?php echo $qs; ?>&export=income"  class="btn btn-blue">💵 Export Income</a>
        <a href="finance_reports.php?<?php echo $qs; ?>&export=expense" class="btn btn-blue">🧾 Export Expenses</a>
    </div>
</div>

<div class="report-head">
    <h1><?php echo htmlspecialchars(school_name($conn)); ?></h1>
    <h3>Financial Report – <?php echo htmlspecialchars($label ?? ''); ?></h3>
</div>

<div class="card">
    <h2>📈 <?php echo htmlspecialchars($label ?? ''); ?>
        <small style="font-weight:normal;color:#888;font-size:14px;">
            (<?php echo date('d M Y', strtotime($from)); ?> – <?php echo date('d M Y', strtotime($to)); ?>)
        </small>
    </h2>

    <div class="pl-grid">
        <div class="pl in">
            <div class="lbl">💵 Total Income</div>
            <div class="val" style="color:#4CAF50;"><?php echo money($income); ?></div>
            <div class="sub"><?php echo count($income_rows); ?> payments received</div>
        </div>
        <div class="pl out">
            <div class="lbl">🧾 Total Expenses</div>
            <div class="val" style="color:#f44336;"><?php echo money($expense); ?></div>
            <div class="sub"><?php echo count($expense_rows); ?> expense records</div>
        </div>
        <div class="pl net">
            <div class="lbl"><?php echo $profit >= 0 ? '✅ Net Profit' : '⚠️ Net Loss'; ?></div>
            <div class="val" style="color:<?php echo $profit >= 0 ? '#2196F3' : '#f44336'; ?>;"><?php echo money(abs($profit)); ?></div>
            <div class="sub"><?php echo $income > 0 ? round($profit / $income * 100) : 0; ?>% margin on income</div>
        </div>
    </div>

    <h2 style="font-size:16px;">Income vs Expenses</h2>
    <div class="cmp">
        <div class="top"><span>💵 Income</span><span><strong><?php echo money($income); ?></strong></span></div>
        <div class="bar in" style="width:<?php echo round($income / $max_side * 100); ?>%;min-width:2px;"></div>
    </div>
    <div class="cmp">
        <div class="top"><span>🧾 Expenses</span><span><strong><?php echo money($expense); ?></strong></span></div>
        <div class="bar out" style="width:<?php echo round($expense / $max_side * 100); ?>%;min-width:2px;"></div>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <h2>💵 Income by Class</h2>
        <?php if (count($inc_by_class) == 0): ?>
            <p class="empty">No income in this period.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Class</th><th class="num">Payments</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($inc_by_class as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['grade'] ?? ''); ?></td>
                            <td class="num"><?php echo $r['cnt']; ?></td>
                            <td class="num" style="color:#4CAF50;"><strong><?php echo money($r['total']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background:#f1f8f2;"><td><strong>TOTAL</strong></td><td></td><td class="num"><strong><?php echo money($income); ?></strong></td></tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>🧾 Expenses by Category</h2>
        <?php if (!$show_expenses): ?>
            <p class="empty">Import <strong>finance_schema_v2.sql</strong> to track expenses.</p>
        <?php elseif (count($exp_by_cat) == 0): ?>
            <p class="empty">No expenses in this period.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Category</th><th class="num">Records</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($exp_by_cat as $r): ?>
                        <tr>
                            <td><?php echo $r['icon'] . ' ' . htmlspecialchars($r['name'] ?? ''); ?></td>
                            <td class="num"><?php echo $r['cnt']; ?></td>
                            <td class="num" style="color:#f44336;"><strong><?php echo money($r['total']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background:#fdf1f1;"><td><strong>TOTAL</strong></td><td></td><td class="num"><strong><?php echo money($expense); ?></strong></td></tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>💵 Income Transactions</h2>
    <?php if (count($income_rows) == 0): ?>
        <p class="empty">No payments in this period.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Date</th><th>Receipt</th><th>Student</th><th>Class</th><th>Method</th><th class="num">Amount</th></tr></thead>
            <tbody>
                <?php foreach ($income_rows as $r): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($r['d'])); ?></td>
                        <td><?php echo htmlspecialchars($r['ref'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['who'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['extra'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['method'] ?? ''); ?></td>
                        <td class="num" style="color:#4CAF50;"><strong><?php echo money($r['amount']); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($show_expenses): ?>
<div class="card">
    <h2>🧾 Expense Transactions</h2>
    <?php if (count($expense_rows) == 0): ?>
        <p class="empty">No expenses in this period.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Date</th><th>Category</th><th>Title</th><th>Vendor</th><th>Method</th><th class="num">Amount</th></tr></thead>
            <tbody>
                <?php foreach ($expense_rows as $r): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($r['d'])); ?></td>
                        <td><?php echo htmlspecialchars($r['cat'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['who'] ?? ''); ?>
                            <?php if ($r['source'] === 'Payroll'): ?><span class="badge badge-info">Payroll</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($r['extra'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['method'] ?? ''); ?></td>
                        <td class="num" style="color:#f44336;"><strong><?php echo money($r['amount']); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php // Close the divs opened in sidebar.php ?>
</div></div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (menuToggle) { menuToggle.addEventListener('click', function() { sidebar.classList.toggle('active'); }); }
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) sidebar.classList.remove('active');
        }
    });
</script>
</body>
</html>
