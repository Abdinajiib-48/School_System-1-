<?php
// ============================================================
// admin/payments.php - Record a fee payment and issue a receipt.
//
// Who can open it: admin, cashier
// ============================================================
include '../config.php';
include 'finance_common.php';

$message = "";
$message_type = "";

// ---- Delete payment ----
if ($has_finance && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM payments WHERE id = $id");
    $message = "✅ Payment deleted.";
    $message_type = "success";
}

// ---- Record a payment ----
if ($has_finance && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id   = (int)$_POST['student_id'];
    $amount       = $_POST['amount'];
    $payment_date = trim($_POST['payment_date']);
    $method       = $_POST['method'];
    $notes        = mysqli_real_escape_string($conn, trim($_POST['notes']));
    $errors = [];

    if ($student_id <= 0)                       $errors[] = "❌ Please select a student!";
    if (!is_numeric($amount) || $amount <= 0)   $errors[] = "❌ Amount must be greater than 0!";
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) $errors[] = "❌ Please enter a valid date!";
    if (!in_array($method, ['Cash','Bank Transfer','Mobile Money','Cheque'])) $errors[] = "❌ Invalid payment method!";

    // Student must exist
    if ($student_id > 0 && mysqli_num_rows(mysqli_query($conn, "SELECT id FROM students WHERE id = $student_id")) == 0) {
        $errors[] = "❌ Student not found!";
    }

    // Warn if paying more than the balance
    if (count($errors) == 0) {
        $sum = student_fee_summary($conn, $student_id);
        if ($sum['payable'] > 0 && $amount > $sum['balance'] + 0.001) {
            $errors[] = "❌ Amount (" . money($amount) . ") is more than the remaining balance (" . money($sum['balance']) . ")!";
        }
    }

    if (count($errors) == 0) {
        $amount     = (float)$amount;
        $receipt_no = next_receipt_no($conn);
        $admin_id   = (int)$_SESSION['user_id'];
        $sql = "INSERT INTO payments (student_id, amount, payment_date, method, receipt_no, notes, received_by)
                VALUES ($student_id, $amount, '$payment_date', '$method', '$receipt_no', '$notes', $admin_id)";
        if (mysqli_query($conn, $sql)) {
            $new_id = mysqli_insert_id($conn);
            $message = "✅ Payment recorded! Receipt <strong>$receipt_no</strong> &nbsp;
                        <a href='receipt.php?id=$new_id' target='_blank' class='btn btn-sm btn-blue'>🖨️ Print Receipt</a>";
            $message_type = "success";
        } else {
            $message = "❌ Error: " . mysqli_error($conn);
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

// ---- Data for the page ----
// Students with their fee / discount / paid / balance (for the dropdown)
$students = [];
if ($has_finance) {
    if ($has_finance_v2) {
        $s_res = mysqli_query($conn, fee_select_sql("ORDER BY c.grade, s.name"));
    } else {
        $s_res = mysqli_query($conn,
            "SELECT s.id, s.student_id, s.name, s.class_id, c.grade,
                    COALESCE(f.amount, 0) AS fee, NULL AS discount_type, 0 AS discount_value,
                    COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS paid
             FROM students s
             LEFT JOIN classes c ON s.class_id = c.id
             LEFT JOIN fee_structure f ON f.class_id = c.id AND f.academic_year = c.academic_year
             ORDER BY c.grade, s.name");
    }
    while ($row = mysqli_fetch_assoc($s_res)) $students[] = fee_row_calc($row);
}

// Pre-select a student if we came from the dashboard (?student_id=)
$preselect = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Payment history (with optional search)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$payments = [];
if ($has_finance) {
    $where = '';
    if ($search !== '') {
        $q = mysqli_real_escape_string($conn, $search);
        $where = "WHERE s.name LIKE '%$q%' OR s.student_id LIKE '%$q%' OR p.receipt_no LIKE '%$q%'";
    }
    $p_res = mysqli_query($conn,
        "SELECT p.*, s.name AS student_name, s.student_id AS student_code, c.grade, u.username AS received_by_name
         FROM payments p
         JOIN students s ON p.student_id = s.id
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN users u ON p.received_by = u.id
         $where
         ORDER BY p.payment_date DESC, p.id DESC
         LIMIT 200");
    while ($row = mysqli_fetch_assoc($p_res)) $payments[] = $row;
}

include 'sidebar.php';
render_finance_styles();
render_finance_tabs('payments.php');
?>

<style>
    .balance-box { background: #f0f2f5; border-left: 4px solid #4CAF50; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px; display: none; }
    .balance-box span { margin-right: 20px; }
    .search-row { display: flex; gap: 10px; margin-bottom: 10px; }
    .search-row input { flex: 1; }
</style>

<?php if (!$has_finance): render_finance_missing(); else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2>💵 Record a Payment</h2>
    <form method="POST" action="payments.php" onsubmit="return validatePayment()">
        <div class="form-group">
            <label>Student: <span style="color:red;">*</span></label>
            <select name="student_id" id="student_id" required>
                <option value="">-- Select Student --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?php echo $s['id']; ?>"
                        data-fee="<?php echo $s['fee']; ?>"
                        data-discount="<?php echo $s['discount']; ?>"
                        data-payable="<?php echo $s['payable']; ?>"
                        data-paid="<?php echo $s['paid']; ?>"
                        data-balance="<?php echo $s['balance']; ?>"
                        <?php echo ($preselect == $s['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['student_id'] ?? ''); ?> - <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                        (<?php echo htmlspecialchars($s['grade'] ?: 'No class'); ?>)
                        <?php if ($s['balance'] > 0): ?> — owes <?php echo money($s['balance']); ?><?php else: ?> — ✓ paid<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="balance-box" id="balanceBox">
            <span>🏷️ Fee: <strong id="bFee">-</strong></span>
            <span id="bDiscWrap" style="display:none;">🎓 Discount: <strong id="bDisc">-</strong></span>
            <span>💰 Payable: <strong id="bPay">-</strong></span>
            <span>✅ Paid: <strong id="bPaid">-</strong></span>
            <span>🔴 Balance: <strong id="bBal">-</strong></span>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Amount: <span style="color:red;">*</span></label>
                <input type="number" name="amount" id="amount" min="0.01" step="0.01" required placeholder="e.g. 500">
            </div>
            <div class="form-group">
                <label>Payment Date: <span style="color:red;">*</span></label>
                <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Payment Method: <span style="color:red;">*</span></label>
                <select name="method" required>
                    <option value="Cash">💵 Cash</option>
                    <option value="Bank Transfer">🏦 Bank Transfer</option>
                    <option value="Mobile Money">📱 Mobile Money</option>
                    <option value="Cheque">📝 Cheque</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes:</label>
                <input type="text" name="notes" placeholder="Optional, e.g. Term 1 installment">
            </div>
        </div>

        <button type="submit">💾 Save Payment</button>
    </form>
</div>

<div class="card">
    <h2>📋 Payment History</h2>
    <form method="GET" action="payments.php" class="search-row">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Search by student name, ID or receipt no...">
        <button type="submit">🔍 Search</button>
        <?php if ($search !== ''): ?><a href="payments.php" class="btn btn-grey">Clear</a><?php endif; ?>
    </form>

    <?php if (count($payments) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th class="num">Amount</th>
                    <th>Method</th>
                    <th>Notes</th>
                    <th>Received By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['receipt_no'] ?? ''); ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($p['student_name'] ?? ''); ?><br><small style="color:#888;"><?php echo htmlspecialchars($p['student_code'] ?? ''); ?></small></td>
                        <td><?php echo htmlspecialchars($p['grade'] ?: '-'); ?></td>
                        <td class="num"><strong><?php echo money($p['amount']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['method'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($p['notes'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($p['received_by_name'] ?: '-'); ?></td>
                        <td>
                            <a href="receipt.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-blue">🖨️ Receipt</a>
                            <a href="invoice.php?student_id=<?php echo $p['student_id']; ?>" target="_blank" class="btn btn-sm btn-grey">🧾 Invoice</a>
                            <a href="payments.php?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-red"
                               onclick="return confirm('Delete this payment? This cannot be undone.')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color:#999;text-align:center;padding:30px;">
            <?php echo $search !== '' ? 'No payments match your search.' : 'No payments recorded yet.'; ?>
        </p>
    <?php endif; ?>
</div>

<?php endif; // has_finance ?>

<script>
    function fmt(n) { return '$' + Number(n).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    var stuSel = document.getElementById('student_id');
    var box = document.getElementById('balanceBox');

    function showBalance() {
        if (!stuSel) return;
        var opt = stuSel.options[stuSel.selectedIndex];
        if (opt && opt.value) {
            var disc = parseFloat(opt.dataset.discount) || 0;
            document.getElementById('bFee').textContent  = fmt(opt.dataset.fee);
            document.getElementById('bPay').textContent  = fmt(opt.dataset.payable);
            document.getElementById('bPaid').textContent = fmt(opt.dataset.paid);
            document.getElementById('bBal').textContent  = fmt(opt.dataset.balance);
            document.getElementById('bDisc').textContent = '-' + fmt(disc);
            document.getElementById('bDiscWrap').style.display = disc > 0 ? 'inline' : 'none';
            document.getElementById('amount').max = opt.dataset.balance > 0 ? opt.dataset.balance : '';
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    }
    if (stuSel) { stuSel.addEventListener('change', showBalance); showBalance(); }

    function validatePayment() {
        var opt = stuSel.options[stuSel.selectedIndex];
        var amt = parseFloat(document.getElementById('amount').value);
        if (!opt || !opt.value) { alert('Please select a student!'); return false; }
        if (isNaN(amt) || amt <= 0) { alert('Amount must be greater than 0!'); return false; }
        var bal = parseFloat(opt.dataset.balance);
        var fee = parseFloat(opt.dataset.payable);
        if (fee > 0 && amt > bal) {
            alert('Amount (' + fmt(amt) + ') is more than the remaining balance (' + fmt(bal) + ')!');
            return false;
        }
        return true;
    }
</script>

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
