<?php
// ============================================================
// parent/fees.php - What the child owes and what has been paid.
//
// Who can open it: parent
// ============================================================
include '../config.php';
include 'parent_common.php';

$fees = $child ? child_fees($conn, $child_id) : null;

include 'sidebar.php';
?>

<?php if (!$child): ?>
    <div class="card"><p class="empty">No child linked to your account. Please contact the school admin.</p></div>
<?php elseif (!$fees['has_fees']): ?>
    <div class="card">
        <h2 class="section-title">💰 School Fees — <?php echo htmlspecialchars($child['name'] ?? ''); ?></h2>
        <p class="empty">No fee has been set for this class yet. Please check back later or contact the school office.</p>
    </div>
<?php else: ?>

<div class="card">
    <h2 class="section-title">💰 School Fees — <?php echo htmlspecialchars($child['name'] ?? ''); ?></h2>

    <div class="stats-container">
        <div class="stat-card">
            <h3><?php echo $fees['discount'] > 0 ? 'Payable (after discount)' : 'Total Fee'; ?></h3>
            <div class="stat-value" style="color:#2196F3;"><?php echo money_p($fees['payable']); ?></div>
            <?php if ($fees['discount'] > 0): ?>
                <div style="font-size:11px;color:#9c27b0;margin-top:6px;">
                    <?php echo money_p($fees['fee']); ?> − <?php echo money_p($fees['discount']); ?>
                    <?php echo htmlspecialchars($fees['reason'] ?: 'discount'); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="stat-card"><h3>Paid</h3><div class="stat-value"><?php echo money_p($fees['paid']); ?></div></div>
        <div class="stat-card">
            <h3>Balance</h3>
            <div class="stat-value" style="color:<?php echo $fees['balance'] > 0 ? '#c62828' : '#2e7d32'; ?>;"><?php echo money_p(max($fees['balance'], 0)); ?></div>
        </div>
        <div class="stat-card">
            <h3>Status</h3>
            <div class="stat-value" style="font-size:19px;color:<?php echo $fees['balance'] <= 0 ? '#2e7d32' : ($fees['paid'] > 0 ? '#ef6c00' : '#c62828'); ?>;">
                <?php echo $fees['balance'] <= 0 ? '✅ Fully Paid' : ($fees['paid'] > 0 ? '⚠️ Partial' : '❌ Unpaid'); ?>
            </div>
        </div>
    </div>

    <div class="progress" style="margin-top:20px;"><div style="width:<?php echo min($fees['pct'], 100); ?>%"></div></div>
    <p style="color:#666;font-size:13px;margin-top:8px;"><?php echo min($fees['pct'], 100); ?>% of the fee has been paid.</p>

    <?php if ($fees['balance'] > 0.001): ?>
        <div class="note note-warn">
            ⚠️ There is an outstanding balance of <strong><?php echo money_p($fees['balance']); ?></strong>.
            Please visit the school finance office to settle it.
        </div>
    <?php else: ?>
        <div class="note note-ok">✅ The school fee is fully paid. Thank you!</div>
    <?php endif; ?>

    <?php if ($fees['discount'] > 0): ?>
        <div class="note note-info">
            🎓 A discount of <strong><?php echo money_p($fees['discount']); ?></strong>
            (<?php echo htmlspecialchars($fees['reason'] ?: 'discount'); ?>) has been applied to this account.
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="section-title">🧾 Payment History</h2>
    <?php if (count($fees['payments']) == 0): ?>
        <p class="empty">No payments have been recorded yet.</p>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr><th>Receipt No</th><th>Date</th><th>Method</th><th>Note</th><th>Amount</th><th class="no-print">Receipt</th></tr>
            </thead>
            <tbody>
                <?php foreach ($fees['payments'] as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['receipt_no'] ?? ''); ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($p['method'] ?? ''); ?></td>
                        <td><?php echo $p['notes'] ? htmlspecialchars($p['notes'] ?? '') : '<span style="color:#bbb;">—</span>'; ?></td>
                        <td style="color:#2e7d32;font-weight:bold;"><?php echo money_p($p['amount']); ?></td>
                        <td class="no-print">
                            <a href="receipt_view.php?child=<?php echo $child_id; ?>&id=<?php echo $p['id']; ?>"
                               target="_blank" class="btn btn-sm">🖨️ View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:#f1f8f2;">
                    <td colspan="4"><strong>TOTAL PAID</strong></td>
                    <td style="color:#2e7d32;font-weight:bold;"><?php echo money_p($fees['paid']); ?></td>
                    <td class="no-print"></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top:18px;" class="no-print">
        <a href="statement.php?child=<?php echo $child_id; ?>" target="_blank" class="btn">🧾 Print Fee Statement</a>
    </div>
</div>

<?php endif; ?>

<?php include 'footer.php'; ?>
