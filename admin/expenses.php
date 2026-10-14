<?php
// ============================================================
// admin/expenses.php - What the school spends, grouped by category.
//
// Who can open it: admin ONLY - blocked for the cashier
// ============================================================
include '../config.php';
include 'finance_common.php';

$message = ""; $message_type = "";

// ---- Delete expense ----
if ($has_finance_v2 && isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT source FROM expenses WHERE id = $id"));
    if ($row && $row['source'] === 'Payroll') {
        $message = "❌ This is a salary payment. Delete it from the Payroll page instead.";
        $message_type = "error";
    } else {
        mysqli_query($conn, "DELETE FROM expenses WHERE id = $id");
        $message = "✅ Expense deleted.";
        $message_type = "success";
    }
}

// ---- Add / Edit expense ----
if ($has_finance_v2 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_id  = (int)$_POST['category_id'];
    $title        = mysqli_real_escape_string($conn, trim($_POST['title']));
    $amount       = $_POST['amount'];
    $expense_date = trim($_POST['expense_date']);
    $method       = $_POST['method'];
    $vendor       = mysqli_real_escape_string($conn, trim($_POST['vendor']));
    $notes        = mysqli_real_escape_string($conn, trim($_POST['notes']));
    $edit_id      = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $errors = [];

    if ($category_id <= 0)                      $errors[] = "❌ Please choose a category!";
    if ($title === '')                          $errors[] = "❌ Title is required!";
    if (!is_numeric($amount) || $amount <= 0)   $errors[] = "❌ Amount must be greater than 0!";
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) $errors[] = "❌ Please enter a valid date!";
    if (!in_array($method, ['Cash','Bank Transfer','Mobile Money','Cheque'])) $errors[] = "❌ Invalid payment method!";

    // A payroll row can't be edited here
    if ($edit_id > 0) {
        $chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT source FROM expenses WHERE id = $edit_id"));
        if ($chk && $chk['source'] === 'Payroll') $errors[] = "❌ Salary expenses are managed on the Payroll page.";
    }

    if (count($errors) == 0) {
        $amount   = (float)$amount;
        $admin_id = (int)$_SESSION['user_id'];
        if ($edit_id > 0) {
            $sql = "UPDATE expenses SET category_id=$category_id, title='$title', amount=$amount,
                    expense_date='$expense_date', method='$method', vendor='$vendor', notes='$notes'
                    WHERE id=$edit_id";
            $ok_msg = "✅ Expense updated!";
        } else {
            $sql = "INSERT INTO expenses (category_id, title, amount, expense_date, method, vendor, notes, source, recorded_by)
                    VALUES ($category_id, '$title', $amount, '$expense_date', '$method', '$vendor', '$notes', 'Manual', $admin_id)";
            $ok_msg = "✅ Expense recorded!";
        }
        if (mysqli_query($conn, $sql)) { $message = $ok_msg; $message_type = "success"; }
        else { $message = "❌ Error: " . mysqli_error($conn); $message_type = "error"; }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

// ---- Data ----
$categories = []; $expenses = []; $by_cat = []; $month_total = 0; $year_total = 0; $grand_total = 0;
$filter_cat   = isset($_GET['cat'])   ? (int)$_GET['cat'] : 0;
$filter_from  = isset($_GET['from'])  ? trim($_GET['from']) : '';
$filter_to    = isset($_GET['to'])    ? trim($_GET['to'])   : '';
$edit_expense = null;

if ($has_finance_v2) {
    $c_res = mysqli_query($conn, "SELECT * FROM expense_categories ORDER BY name");
    while ($r = mysqli_fetch_assoc($c_res)) $categories[] = $r;

    // filters
    $w = [];
    if ($filter_cat > 0) $w[] = "e.category_id = $filter_cat";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_from)) $w[] = "e.expense_date >= '$filter_from'";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_to))   $w[] = "e.expense_date <= '$filter_to'";
    $where = count($w) ? 'WHERE ' . implode(' AND ', $w) : '';

    $e_res = mysqli_query($conn,
        "SELECT e.*, ec.name AS category_name, ec.icon, u.username AS recorded_by_name
         FROM expenses e
         JOIN expense_categories ec ON e.category_id = ec.id
         LEFT JOIN users u ON e.recorded_by = u.id
         $where
         ORDER BY e.expense_date DESC, e.id DESC LIMIT 300");
    while ($r = mysqli_fetch_assoc($e_res)) $expenses[] = $r;

    // totals by category (respecting the filter)
    $bc = mysqli_query($conn,
        "SELECT ec.name, ec.icon, COALESCE(SUM(e.amount),0) AS total, COUNT(e.id) AS cnt
         FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id
         $where GROUP BY ec.id ORDER BY total DESC");
    while ($r = mysqli_fetch_assoc($bc)) $by_cat[] = $r;

    $month_total = total_expenses($conn, date('Y-m-01'), date('Y-m-t'));
    $year_total  = total_expenses($conn, date('Y-01-01'), date('Y-12-31'));
    $grand_total = total_expenses($conn);

    if (isset($_GET['edit'])) {
        $eid = (int)$_GET['edit'];
        $edit_expense = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM expenses WHERE id = $eid AND source = 'Manual'"));
    }
}

$filtered_total = 0;
foreach ($expenses as $e) $filtered_total += (float)$e['amount'];
$max_cat = 0;
foreach ($by_cat as $b) $max_cat = max($max_cat, (float)$b['total']);

include 'sidebar.php';
render_finance_styles();
render_finance_tabs('expenses.php');
?>

<style>
    .mini-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px,1fr)); gap: 18px; margin-bottom: 25px; }
    .mini { background: white; padding: 18px; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #f44336; }
    .mini .lbl { color: #666; font-size: 13px; }
    .mini .val { font-size: 25px; font-weight: bold; color: #333; margin-top: 5px; }
    .cat-row { margin-bottom: 12px; }
    .cat-row .top { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 4px; }
    .cat-row .progress > div { background: linear-gradient(90deg, #f44336, #ff7961); }
    .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
    .filter-row > div { flex: 1; min-width: 140px; }
</style>

<?php if (!$has_finance_v2): render_finance_v2_missing(); else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="mini-stats">
    <div class="mini"><div class="lbl">📅 This Month</div><div class="val"><?php echo money($month_total); ?></div></div>
    <div class="mini"><div class="lbl">🗓️ This Year</div><div class="val"><?php echo money($year_total); ?></div></div>
    <div class="mini"><div class="lbl">💸 All Time</div><div class="val"><?php echo money($grand_total); ?></div></div>
    <div class="mini"><div class="lbl">🧾 Records Shown</div><div class="val"><?php echo count($expenses); ?></div></div>
</div>

<div class="two-col">
    <div class="card">
        <h2><?php echo $edit_expense ? '✏️ Edit Expense' : '➕ Record an Expense'; ?></h2>
        <form method="POST" action="expenses.php">
            <?php if ($edit_expense): ?>
                <input type="hidden" name="edit_id" value="<?php echo $edit_expense['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Title: <span style="color:red;">*</span></label>
                <input type="text" name="title" required placeholder="e.g. Electricity bill – September"
                       value="<?php echo $edit_expense ? htmlspecialchars($edit_expense['title'] ?? '') : ''; ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Category: <span style="color:red;">*</span></label>
                    <select name="category_id" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($edit_expense && $edit_expense['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo $c['icon'] . ' ' . htmlspecialchars($c['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount: <span style="color:red;">*</span></label>
                    <input type="number" name="amount" min="0.01" step="0.01" required placeholder="e.g. 250"
                           value="<?php echo $edit_expense ? $edit_expense['amount'] : ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date: <span style="color:red;">*</span></label>
                    <input type="date" name="expense_date" required max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo $edit_expense ? $edit_expense['expense_date'] : date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Paid By: <span style="color:red;">*</span></label>
                    <select name="method" required>
                        <?php foreach (['Cash','Bank Transfer','Mobile Money','Cheque'] as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($edit_expense && $edit_expense['method'] == $m) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Vendor / Paid To:</label>
                    <input type="text" name="vendor" placeholder="e.g. City Power Co."
                           value="<?php echo $edit_expense ? htmlspecialchars($edit_expense['vendor'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Notes:</label>
                    <input type="text" name="notes" placeholder="Optional"
                           value="<?php echo $edit_expense ? htmlspecialchars($edit_expense['notes'] ?? '') : ''; ?>">
                </div>
            </div>

            <button type="submit"><?php echo $edit_expense ? 'Update Expense' : '💾 Save Expense'; ?></button>
            <?php if ($edit_expense): ?>
                <a href="expenses.php" style="margin-left:10px;color:#666;text-decoration:none;">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2>📊 Spending by Category</h2>
        <?php if (count($by_cat) == 0): ?>
            <p class="empty">No expenses recorded yet.</p>
        <?php else: foreach ($by_cat as $b):
            $pct = $max_cat > 0 ? round($b['total'] / $max_cat * 100) : 0; ?>
            <div class="cat-row">
                <div class="top">
                    <span><?php echo $b['icon'] . ' ' . htmlspecialchars($b['name'] ?? ''); ?> <small style="color:#888;">(<?php echo $b['cnt']; ?>)</small></span>
                    <span><strong><?php echo money($b['total']); ?></strong></span>
                </div>
                <div class="progress"><div style="width:<?php echo $pct; ?>%"></div></div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="card">
    <h2>🧾 Expense History</h2>

    <form method="GET" action="expenses.php" class="filter-row no-print">
        <div>
            <label>Category</label>
            <select name="cat">
                <option value="0">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $filter_cat == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['name'] ?? ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label>From</label><input type="date" name="from" value="<?php echo htmlspecialchars($filter_from ?? ''); ?>"></div>
        <div><label>To</label><input type="date" name="to" value="<?php echo htmlspecialchars($filter_to ?? ''); ?>"></div>
        <div style="flex:0;">
            <button type="submit">🔍 Filter</button>
        </div>
        <div style="flex:0;"><a href="expenses.php" class="btn btn-grey">Clear</a></div>
    </form>

    <?php if (count($expenses) > 0): ?>
        <p style="margin-top:14px;color:#666;">Total shown: <strong style="color:#f44336;"><?php echo money($filtered_total); ?></strong></p>
        <table>
            <thead>
                <tr>
                    <th>Date</th><th>Title</th><th>Category</th><th class="num">Amount</th>
                    <th>Method</th><th>Vendor</th><th>By</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars($e['title'] ?? ''); ?>
                            <?php if ($e['source'] === 'Payroll'): ?>
                                <span class="badge badge-info">Payroll</span>
                            <?php endif; ?>
                            <?php if ($e['notes']): ?><br><small style="color:#888;"><?php echo htmlspecialchars($e['notes'] ?? ''); ?></small><?php endif; ?>
                        </td>
                        <td><?php echo $e['icon'] . ' ' . htmlspecialchars($e['category_name'] ?? ''); ?></td>
                        <td class="num" style="color:#f44336;"><strong><?php echo money($e['amount']); ?></strong></td>
                        <td><?php echo htmlspecialchars($e['method'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($e['vendor'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($e['recorded_by_name'] ?: '-'); ?></td>
                        <td>
                            <?php if ($e['source'] === 'Payroll'): ?>
                                <a href="payroll.php" class="btn btn-sm btn-grey">Payroll</a>
                            <?php else: ?>
                                <a href="expenses.php?edit=<?php echo $e['id']; ?>" class="btn btn-sm btn-orange">Edit</a>
                                <a href="expenses.php?delete=<?php echo $e['id']; ?>" class="btn btn-sm btn-red"
                                   onclick="return confirm('Delete this expense?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="empty">No expenses found.</p>
    <?php endif; ?>
</div>

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
