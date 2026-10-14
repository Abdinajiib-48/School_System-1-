<?php
// ============================================================
// admin/payroll.php - Staff salaries, one run per month.
//
// Who can open it: admin ONLY - blocked for the cashier
// ============================================================
include '../config.php';
include 'finance_common.php';

$message = ""; $message_type = "";

// The month we are working on (YYYY-MM)
$pay_month = isset($_GET['month']) ? trim($_GET['month']) : (isset($_POST['pay_month']) ? trim($_POST['pay_month']) : date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $pay_month)) $pay_month = date('Y-m');

// ---- Update a teacher's monthly salary ----
if ($has_finance_v2 && isset($_POST['save_salary'])) {
    $tid = (int)$_POST['teacher_id'];
    $sal = $_POST['monthly_salary'];
    if (!is_numeric($sal) || $sal < 0) {
        $message = "❌ Salary must be a positive number!"; $message_type = "error";
    } else {
        $sal = (float)$sal;
        mysqli_query($conn, "UPDATE teachers SET monthly_salary = $sal WHERE id = $tid");
        $message = "✅ Monthly salary updated."; $message_type = "success";
    }
}

// ---- Delete a salary payment (and its expense row) ----
if ($has_finance_v2 && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM expenses WHERE salary_payment_id = $id");
    mysqli_query($conn, "DELETE FROM salary_payments WHERE id = $id");
    $message = "✅ Salary payment deleted."; $message_type = "success";
}

// ---- Pay a salary ----
if ($has_finance_v2 && isset($_POST['pay_salary'])) {
    $teacher_id   = (int)$_POST['teacher_id'];
    $basic        = $_POST['basic'];
    $bonus        = $_POST['bonus'] !== '' ? $_POST['bonus'] : 0;
    $deduction    = $_POST['deduction'] !== '' ? $_POST['deduction'] : 0;
    $payment_date = trim($_POST['payment_date']);
    $method       = $_POST['method'];
    $notes        = mysqli_real_escape_string($conn, trim($_POST['notes']));
    $errors = [];

    if ($teacher_id <= 0)                          $errors[] = "❌ Please select a teacher!";
    if (!is_numeric($basic) || $basic < 0)         $errors[] = "❌ Basic salary must be a positive number!";
    if (!is_numeric($bonus) || $bonus < 0)         $errors[] = "❌ Bonus must be a positive number!";
    if (!is_numeric($deduction) || $deduction < 0) $errors[] = "❌ Deduction must be a positive number!";
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) $errors[] = "❌ Please enter a valid payment date!";
    if (!in_array($method, ['Cash','Bank Transfer','Mobile Money','Cheque'])) $errors[] = "❌ Invalid payment method!";

    $net = (float)$basic + (float)$bonus - (float)$deduction;
    if (count($errors) == 0 && $net <= 0) $errors[] = "❌ Net salary must be greater than 0!";

    // Already paid this month?
    if (count($errors) == 0) {
        $dup = mysqli_query($conn, "SELECT id FROM salary_payments WHERE teacher_id = $teacher_id AND pay_month = '$pay_month'");
        if (mysqli_num_rows($dup) > 0) $errors[] = "❌ This teacher has already been paid for " . date('F Y', strtotime($pay_month . '-01')) . "!";
    }

    if (count($errors) == 0) {
        $basic = (float)$basic; $bonus = (float)$bonus; $deduction = (float)$deduction;
        $payslip_no = next_payslip_no($conn);
        $admin_id   = (int)$_SESSION['user_id'];

        $sql = "INSERT INTO salary_payments (teacher_id, pay_month, basic, bonus, deduction, net, payment_date, method, payslip_no, notes, paid_by)
                VALUES ($teacher_id, '$pay_month', $basic, $bonus, $deduction, $net, '$payment_date', '$method', '$payslip_no', '$notes', $admin_id)";
        if (mysqli_query($conn, $sql)) {
            $sal_id  = mysqli_insert_id($conn);
            // Mirror it into expenses so Profit/Loss is correct
            $t       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM teachers WHERE id = $teacher_id"));
            $tname   = mysqli_real_escape_string($conn, $t['name']);
            $cat     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM expense_categories WHERE name = 'Salaries'"));
            $cat_id  = $cat ? (int)$cat['id'] : 1;
            $label   = "Salary – $tname – " . date('F Y', strtotime($pay_month . '-01'));
            mysqli_query($conn,
                "INSERT INTO expenses (category_id, title, amount, expense_date, method, vendor, notes, source, salary_payment_id, recorded_by)
                 VALUES ($cat_id, '$label', $net, '$payment_date', '$method', '$tname', 'Payslip $payslip_no', 'Payroll', $sal_id, $admin_id)");

            $message = "✅ Salary paid! Payslip <strong>$payslip_no</strong> &nbsp;
                        <a href='payslip.php?id=$sal_id' target='_blank' class='btn btn-sm btn-blue'>🖨️ Print Payslip</a>";
            $message_type = "success";
        } else {
            $message = "❌ Error: " . mysqli_error($conn); $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

// ---- Data ----
$staff = []; $history = [];
$month_total = 0; $month_paid_count = 0; $month_pending_count = 0; $payroll_budget = 0;

if ($has_finance_v2) {
    // Every teacher + whether they were paid this month
    $t_res = mysqli_query($conn,
        "SELECT t.id, t.name, t.email, t.phone, t.monthly_salary, d.name AS dept,
                sp.id AS paid_id, sp.net AS paid_net, sp.payslip_no, sp.payment_date
         FROM teachers t
         LEFT JOIN departments d ON t.department_id = d.id
         LEFT JOIN salary_payments sp ON sp.teacher_id = t.id AND sp.pay_month = '$pay_month'
         ORDER BY t.name");
    while ($r = mysqli_fetch_assoc($t_res)) {
        $staff[] = $r;
        $payroll_budget += (float)$r['monthly_salary'];
        if ($r['paid_id']) { $month_total += (float)$r['paid_net']; $month_paid_count++; }
        else               { $month_pending_count++; }
    }

    // Full payment history
    $h_res = mysqli_query($conn,
        "SELECT sp.*, t.name AS teacher_name, u.username AS paid_by_name
         FROM salary_payments sp
         JOIN teachers t ON sp.teacher_id = t.id
         LEFT JOIN users u ON sp.paid_by = u.id
         ORDER BY sp.pay_month DESC, sp.id DESC LIMIT 100");
    while ($r = mysqli_fetch_assoc($h_res)) $history[] = $r;
}

$month_label = date('F Y', strtotime($pay_month . '-01'));

include 'sidebar.php';
render_finance_styles();
render_finance_tabs('payroll.php');
?>

<style>
    .mini-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px,1fr)); gap: 18px; margin-bottom: 25px; }
    .mini { background: white; padding: 18px; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #2196F3; }
    .mini .lbl { color: #666; font-size: 13px; }
    .mini .val { font-size: 25px; font-weight: bold; color: #333; margin-top: 5px; }
    .month-bar { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
    .pay-form { display: none; background: #f9f9f9; border-radius: 10px; padding: 15px; margin-top: 10px; }
    .pay-form.open { display: block; }
    .net-preview { font-size: 18px; font-weight: bold; color: #4CAF50; margin: 10px 0; }
    .inline-form { display: flex; gap: 6px; align-items: center; }
    .inline-form input { width: 100px; padding: 6px; }
</style>

<?php if (!$has_finance_v2): render_finance_v2_missing(); else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card no-print">
    <h2>📅 Payroll Month</h2>
    <form method="GET" action="payroll.php" class="month-bar">
        <div style="flex:1;max-width:250px;">
            <label>Choose month</label>
            <input type="month" name="month" value="<?php echo htmlspecialchars($pay_month ?? ''); ?>">
        </div>
        <button type="submit">Show</button>
    </form>
    <p style="color:#666;">Showing payroll for <strong><?php echo $month_label; ?></strong></p>
</div>

<div class="mini-stats">
    <div class="mini"><div class="lbl">💰 Monthly Payroll Budget</div><div class="val"><?php echo money($payroll_budget); ?></div></div>
    <div class="mini"><div class="lbl">✅ Paid in <?php echo $month_label; ?></div><div class="val"><?php echo money($month_total); ?></div></div>
    <div class="mini"><div class="lbl">👨‍🏫 Staff Paid</div><div class="val"><?php echo $month_paid_count; ?> / <?php echo count($staff); ?></div></div>
    <div class="mini" style="border-color:#ff9800;"><div class="lbl">⏳ Still Pending</div><div class="val"><?php echo $month_pending_count; ?></div></div>
</div>

<div class="card">
    <h2>👨‍🏫 Staff Salaries – <?php echo $month_label; ?></h2>
    <?php if (count($staff) == 0): ?>
        <p class="empty">No teachers found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Teacher</th><th>Department</th>
                    <th class="num">Monthly Salary</th>
                    <th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff as $t): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($t['name'] ?? ''); ?></strong>
                            <?php if ($t['email']): ?><br><small style="color:#888;"><?php echo htmlspecialchars($t['email'] ?? ''); ?></small><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($t['dept'] ?: '-'); ?></td>
                        <td class="num">
                            <form method="POST" action="payroll.php?month=<?php echo urlencode($pay_month); ?>" class="inline-form" style="justify-content:flex-end;">
                                <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                                <input type="hidden" name="pay_month" value="<?php echo htmlspecialchars($pay_month ?? ''); ?>">
                                <input type="number" name="monthly_salary" min="0" step="0.01" value="<?php echo $t['monthly_salary']; ?>">
                                <button type="submit" name="save_salary" class="btn btn-sm btn-grey">Set</button>
                            </form>
                        </td>
                        <td>
                            <?php if ($t['paid_id']): ?>
                                <span class="badge badge-paid">✓ Paid <?php echo money($t['paid_net']); ?></span><br>
                                <small style="color:#888;"><?php echo htmlspecialchars($t['payslip_no'] ?? ''); ?> · <?php echo date('d M Y', strtotime($t['payment_date'])); ?></small>
                            <?php else: ?>
                                <span class="badge badge-unpaid">Not paid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['paid_id']): ?>
                                <a href="payslip.php?id=<?php echo $t['paid_id']; ?>" target="_blank" class="btn btn-sm btn-blue">🖨️ Payslip</a>
                                <a href="payroll.php?month=<?php echo urlencode($pay_month); ?>&delete=<?php echo $t['paid_id']; ?>"
                                   class="btn btn-sm btn-red" onclick="return confirm('Delete this salary payment? The matching expense will be removed too.')">Delete</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm" onclick="togglePay(<?php echo $t['id']; ?>)">💵 Pay</button>
                            <?php endif; ?>

                            <?php if (!$t['paid_id']): ?>
                            <div class="pay-form" id="pay<?php echo $t['id']; ?>">
                                <form method="POST" action="payroll.php?month=<?php echo urlencode($pay_month); ?>">
                                    <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                                    <input type="hidden" name="pay_month" value="<?php echo htmlspecialchars($pay_month ?? ''); ?>">

                                    <div class="form-row-3">
                                        <div class="form-group">
                                            <label>Basic</label>
                                            <input type="number" name="basic" min="0" step="0.01" required
                                                   value="<?php echo $t['monthly_salary']; ?>" oninput="calcNet(<?php echo $t['id']; ?>)" id="b<?php echo $t['id']; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Bonus</label>
                                            <input type="number" name="bonus" min="0" step="0.01" value="0"
                                                   oninput="calcNet(<?php echo $t['id']; ?>)" id="bo<?php echo $t['id']; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Deduction</label>
                                            <input type="number" name="deduction" min="0" step="0.01" value="0"
                                                   oninput="calcNet(<?php echo $t['id']; ?>)" id="de<?php echo $t['id']; ?>">
                                        </div>
                                    </div>

                                    <div class="net-preview">Net Pay: <span id="net<?php echo $t['id']; ?>"><?php echo money($t['monthly_salary']); ?></span></div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Payment Date</label>
                                            <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Method</label>
                                            <select name="method">
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Mobile Money">Mobile Money</option>
                                                <option value="Cheque">Cheque</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Notes</label>
                                        <input type="text" name="notes" placeholder="Optional">
                                    </div>

                                    <button type="submit" name="pay_salary">💾 Pay Salary</button>
                                    <a href="#" onclick="togglePay(<?php echo $t['id']; ?>);return false;" style="margin-left:10px;color:#666;text-decoration:none;">Cancel</a>
                                </form>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:12px;color:#888;font-size:13px;">
            💡 Paying a salary also records it automatically as an expense under <strong>Salaries</strong>, so the Profit/Loss report stays correct.
            Non-teaching staff can be paid from the <a href="expenses.php">Expenses</a> page.
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>📜 Salary Payment History</h2>
    <?php if (count($history) == 0): ?>
        <p class="empty">No salary payments yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Payslip</th><th>Month</th><th>Teacher</th>
                    <th class="num">Basic</th><th class="num">Bonus</th><th class="num">Deduction</th><th class="num">Net Paid</th>
                    <th>Date</th><th>Method</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($h['payslip_no'] ?? ''); ?></strong></td>
                        <td><?php echo date('M Y', strtotime($h['pay_month'] . '-01')); ?></td>
                        <td><?php echo htmlspecialchars($h['teacher_name'] ?? ''); ?></td>
                        <td class="num"><?php echo money($h['basic']); ?></td>
                        <td class="num" style="color:#4CAF50;"><?php echo money($h['bonus']); ?></td>
                        <td class="num" style="color:#f44336;"><?php echo money($h['deduction']); ?></td>
                        <td class="num"><strong><?php echo money($h['net']); ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($h['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($h['method'] ?? ''); ?></td>
                        <td>
                            <a href="payslip.php?id=<?php echo $h['id']; ?>" target="_blank" class="btn btn-sm btn-blue">🖨️</a>
                            <a href="payroll.php?month=<?php echo urlencode($pay_month); ?>&delete=<?php echo $h['id']; ?>"
                               class="btn btn-sm btn-red" onclick="return confirm('Delete this salary payment?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php endif; ?>

<script>
    function togglePay(id) {
        var box = document.getElementById('pay' + id);
        if (box) box.classList.toggle('open');
    }
    function calcNet(id) {
        var b  = parseFloat(document.getElementById('b'  + id).value) || 0;
        var bo = parseFloat(document.getElementById('bo' + id).value) || 0;
        var de = parseFloat(document.getElementById('de' + id).value) || 0;
        var net = b + bo - de;
        document.getElementById('net' + id).textContent =
            '$' + net.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
</script>

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
