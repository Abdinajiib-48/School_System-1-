<?php
// ============================================================
// admin/discounts.php - Discounts and scholarships taken off a student's fee.
//
// Who can open it: admin, cashier
// ============================================================
include '../config.php';
include 'finance_common.php';

$message = ""; $message_type = "";

// ---- Remove a discount ----
if ($has_finance_v2 && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM student_discounts WHERE id = $id");
    $message = "✅ Discount removed.";
    $message_type = "success";
}

// ---- Add / update a discount ----
if ($has_finance_v2 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = (int)$_POST['student_id'];
    $type       = $_POST['discount_type'];
    $value      = $_POST['value'];
    $reason     = mysqli_real_escape_string($conn, trim($_POST['reason']));
    $errors = [];

    if ($student_id <= 0)                        $errors[] = "❌ Please select a student!";
    if (!in_array($type, ['Percent','Amount']))  $errors[] = "❌ Choose Percent or Amount!";
    if (!is_numeric($value) || $value <= 0)      $errors[] = "❌ Value must be greater than 0!";
    if ($type === 'Percent' && $value > 100)     $errors[] = "❌ A percentage cannot be more than 100!";
    if ($reason === '')                          $reason = 'Scholarship';

    // Fixed amount must not exceed the student's fee
    if (count($errors) == 0 && $type === 'Amount') {
        $sum = student_fee_summary($conn, $student_id);
        if ($sum['fee'] > 0 && $value > $sum['fee']) {
            $errors[] = "❌ Discount (" . money($value) . ") is more than the student's fee (" . money($sum['fee']) . ")!";
        }
    }

    // Don't let a discount drop the payable below what was already paid
    if (count($errors) == 0) {
        $sum  = student_fee_summary($conn, $student_id);
        $off  = discount_amount($sum['fee'], $type, (float)$value);
        if ($sum['fee'] - $off < $sum['paid']) {
            $errors[] = "❌ That discount would make the payable amount (" . money($sum['fee'] - $off) .
                        ") less than what the student already paid (" . money($sum['paid']) . ").";
        }
    }

    if (count($errors) == 0) {
        $value = (float)$value;
        $sql = "INSERT INTO student_discounts (student_id, discount_type, value, reason)
                VALUES ($student_id, '$type', $value, '$reason')
                ON DUPLICATE KEY UPDATE discount_type='$type', value=$value, reason='$reason'";
        if (mysqli_query($conn, $sql)) {
            $message = "✅ Discount saved!"; $message_type = "success";
        } else {
            $message = "❌ Error: " . mysqli_error($conn); $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

// ---- Data ----
$students = []; $with_discount = []; $total_given = 0;
if ($has_finance_v2) {
    $res = mysqli_query($conn, fee_select_sql("ORDER BY c.grade, s.name"));
    while ($r = mysqli_fetch_assoc($res)) {
        $r = fee_row_calc($r);
        $students[] = $r;
        if ($r['discount'] > 0) {
            $d = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM student_discounts WHERE student_id = {$r['id']}"));
            $r['discount_id'] = $d ? $d['id'] : 0;
            $with_discount[] = $r;
            $total_given += $r['discount'];
        }
    }
}

$edit_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

include 'sidebar.php';
render_finance_styles();
render_finance_tabs('discounts.php');
?>

<style>
    .mini-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px,1fr)); gap: 18px; margin-bottom: 25px; }
    .mini { background: white; padding: 18px; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #9c27b0; }
    .mini .lbl { color: #666; font-size: 13px; }
    .mini .val { font-size: 25px; font-weight: bold; color: #333; margin-top: 5px; }
</style>

<?php if (!$has_finance_v2): render_finance_v2_missing(); else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="mini-stats">
    <div class="mini"><div class="lbl">🎓 Students with Discount</div><div class="val"><?php echo count($with_discount); ?> / <?php echo count($students); ?></div></div>
    <div class="mini"><div class="lbl">💸 Total Discount Given</div><div class="val"><?php echo money($total_given); ?></div></div>
</div>

<div class="card">
    <h2>🎓 Give a Discount or Scholarship</h2>
    <form method="POST" action="discounts.php" onsubmit="return validateDiscount()">
        <div class="form-group">
            <label>Student: <span style="color:red;">*</span></label>
            <select name="student_id" id="student_id" required>
                <option value="">-- Select Student --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?php echo $s['id']; ?>" data-fee="<?php echo $s['fee']; ?>"
                        <?php echo ($edit_student == $s['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['student_id'] ?? ''); ?> - <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                        (<?php echo htmlspecialchars($s['grade'] ?: 'No class'); ?>) — fee <?php echo money($s['fee']); ?>
                        <?php if ($s['discount'] > 0): ?> · has <?php echo money($s['discount']); ?> off<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="hint">A student can have one discount. Saving again replaces the old one.</div>
        </div>

        <div class="form-row-3">
            <div class="form-group">
                <label>Discount Type: <span style="color:red;">*</span></label>
                <select name="discount_type" id="discount_type" required>
                    <option value="Percent">Percent (%) off the fee</option>
                    <option value="Amount">Fixed amount off</option>
                </select>
            </div>
            <div class="form-group">
                <label>Value: <span style="color:red;">*</span></label>
                <input type="number" name="value" id="value" min="0.01" step="0.01" required placeholder="e.g. 50">
                <div class="hint" id="valueHint">50 = 50% off</div>
            </div>
            <div class="form-group">
                <label>Reason:</label>
                <input type="text" name="reason" placeholder="e.g. Merit Scholarship" value="Scholarship">
            </div>
        </div>

        <div class="message success" id="preview" style="display:none;"></div>

        <button type="submit">💾 Save Discount</button>
    </form>
</div>

<div class="card">
    <h2>📋 Students with a Discount</h2>
    <?php if (count($with_discount) == 0): ?>
        <p class="empty">No discounts given yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Student</th><th>Class</th><th>Type</th><th>Reason</th>
                    <th class="num">Full Fee</th><th class="num">Discount</th><th class="num">Payable</th>
                    <th class="num">Paid</th><th class="num">Balance</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($with_discount as $s): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($s['name'] ?? ''); ?></strong><br><small style="color:#888;"><?php echo htmlspecialchars($s['student_id'] ?? ''); ?></small></td>
                        <td><?php echo htmlspecialchars($s['grade'] ?: '-'); ?></td>
                        <td><span class="badge badge-info">
                            <?php echo $s['discount_type'] === 'Percent' ? rtrim(rtrim(number_format($s['discount_value'],2),'0'),'.') . '%' : money($s['discount_value']); ?>
                        </span></td>
                        <td><?php echo htmlspecialchars($s['discount_reason'] ?? ''); ?></td>
                        <td class="num"><?php echo money($s['fee']); ?></td>
                        <td class="num" style="color:#9c27b0;"><strong>-<?php echo money($s['discount']); ?></strong></td>
                        <td class="num"><strong><?php echo money($s['payable']); ?></strong></td>
                        <td class="num"><?php echo money($s['paid']); ?></td>
                        <td class="num" style="color:<?php echo $s['balance'] > 0 ? '#f44336' : '#4CAF50'; ?>;">
                            <strong><?php echo money(max($s['balance'], 0)); ?></strong>
                        </td>
                        <td>
                            <a href="discounts.php?student_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-orange">Edit</a>
                            <a href="discounts.php?delete=<?php echo $s['discount_id']; ?>" class="btn btn-sm btn-red"
                               onclick="return confirm('Remove this discount?')">Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php endif; ?>

<script>
    function fmt(n) { return '$' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

    var stu  = document.getElementById('student_id');
    var type = document.getElementById('discount_type');
    var val  = document.getElementById('value');
    var prev = document.getElementById('preview');
    var hint = document.getElementById('valueHint');

    function updatePreview() {
        if (!stu || !stu.value || !val.value) { if (prev) prev.style.display = 'none'; return; }
        var fee = parseFloat(stu.options[stu.selectedIndex].dataset.fee) || 0;
        var v   = parseFloat(val.value) || 0;
        var off = type.value === 'Percent' ? fee * v / 100 : v;
        if (off > fee) off = fee;
        prev.innerHTML = 'Full fee ' + fmt(fee) + ' &minus; discount ' + fmt(off) +
                         ' = <strong>student pays ' + fmt(fee - off) + '</strong>';
        prev.style.display = 'block';
    }

    function updateHint() {
        if (!hint) return;
        hint.textContent = type.value === 'Percent' ? '50 = 50% off the fee' : 'e.g. 500 = $500 off the fee';
        val.max = type.value === 'Percent' ? 100 : '';
    }

    if (stu)  { stu.addEventListener('change', updatePreview); }
    if (type) { type.addEventListener('change', function(){ updateHint(); updatePreview(); }); }
    if (val)  { val.addEventListener('input', updatePreview); }
    updateHint();

    function validateDiscount() {
        if (!stu.value) { alert('Please select a student!'); return false; }
        var v = parseFloat(val.value);
        if (isNaN(v) || v <= 0) { alert('Value must be greater than 0!'); return false; }
        if (type.value === 'Percent' && v > 100) { alert('A percentage cannot be more than 100!'); return false; }
        return true;
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
