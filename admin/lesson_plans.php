<?php
// ============================================================
// admin/lesson_plans.php - Lesson plans teachers have sent in.
//
// Who can open it: admin
//
//   (no query)    every plan, newest waiting one first
//   ?view=<id>    read one plan and answer it
//   ?teacher=<id> only that teacher's plans
//
// The admin never sees a teacher's DRAFT - only what has actually been
// sent. Answering a plan either approves it or sends it back with a
// comment, which the teacher reads on their own page.
// ============================================================
include '../config.php';

require_role('admin');

include_once '../lesson_plans_helper.php';

if (!lp_installed($conn)) {
    die('<h3 style="font-family:sans-serif;padding:40px;">
         The <code>lesson_plans</code> table is missing.<br>
         Import <code>lesson_plan_schema.sql</code> in phpMyAdmin, then reload this page.
         </h3>');
}

$message = '';
$message_type = '';

// ------------------------------------------------------------
// ANSWER a plan: approve it, or send it back for changes
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review'])) {
    $id      = (int)$_POST['plan_id'];
    $comment = trim($_POST['admin_comment'] ?? '');
    $action  = $_POST['review'];        // 'approve' or 'changes'

    $p = lp_get($conn, $id);

    if (!$p) {
        $message = '❌ That plan no longer exists.';
        $message_type = 'error';
    } elseif ($p['status'] === 'draft') {
        // a draft has not been sent - there is nothing to answer
        $message = '❌ That plan is still a draft. Wait until the teacher sends it.';
        $message_type = 'error';
    } elseif ($action === 'changes' && $comment === '') {
        $message = '❌ Write a short comment so the teacher knows what to change.';
        $message_type = 'error';
    } else {
        $status = ($action === 'approve') ? 'approved' : 'needs_changes';
        mysqli_query($conn,
            "UPDATE lesson_plans
                SET status        = '$status',
                    admin_comment = '" . mysqli_real_escape_string($conn, $comment) . "',
                    reviewed_by   = " . (int)$_SESSION['user_id'] . ",
                    reviewed_at   = NOW()
              WHERE id = $id");

        $message = ($action === 'approve')
            ? '✅ Approved. <strong>' . htmlspecialchars($p['teacher_name']) . '</strong> will see this on their page.'
            : '✏️ Sent back to <strong>' . htmlspecialchars($p['teacher_name']) . '</strong> with your comment.';
        $message_type = 'success';
    }
}

// which view?
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$plan    = $view_id > 0 ? lp_get($conn, $view_id) : null;

// the admin has no business reading a teacher's unfinished draft
if ($plan && $plan['status'] === 'draft') {
    $plan = null;
    $message = 'That plan is still a draft — the teacher has not sent it yet.';
    $message_type = 'error';
}

// filters for the list
$filter_teacher = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;
$filter_status  = isset($_GET['status'])  ? $_GET['status'] : '';

$where = ['not_draft' => true];
if ($filter_teacher > 0) $where['teacher_id'] = $filter_teacher;
if (in_array($filter_status, ['submitted', 'approved', 'needs_changes'], true)) {
    $where['status'] = $filter_status;
}

$plans = $plan ? [] : lp_list($conn, $where);

// teachers who have actually sent something, for the filter bar
$teachers = [];
$r = mysqli_query($conn,
    "SELECT t.id, t.name, COUNT(lp.id) AS n,
            SUM(lp.status = 'submitted') AS waiting
       FROM teachers t
       JOIN lesson_plans lp ON lp.teacher_id = t.id AND lp.status <> 'draft'
      GROUP BY t.id ORDER BY t.name");
while ($row = mysqli_fetch_assoc($r)) $teachers[] = $row;

$pending = lp_pending_count($conn);

include 'sidebar.php';
?>

<style>
    .card {
        background: white; padding: 28px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 22px;
    }

    .page-head {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 15px; flex-wrap: wrap; margin-bottom: 20px;
    }
    .page-head h2 { font-size: 22px; color: #333; }
    .page-head p  { color: #777; font-size: 13.5px; margin-top: 4px; }

    .count-pill {
        background: #e3f0ff; color: #1b5fa8; padding: 8px 16px;
        border-radius: 20px; font-weight: 700; font-size: 14px; white-space: nowrap;
    }
    .count-pill.none { background: #e8f5e9; color: #2e7d32; }

    .filter-bar { display: flex; gap: 9px; flex-wrap: wrap; margin-bottom: 18px; }
    .filter-bar a {
        padding: 8px 15px; border-radius: 8px; border: 2px solid #e2e8f0;
        background: white; color: #5a6172; text-decoration: none;
        font-size: 13px; font-weight: 600; white-space: nowrap;
    }
    .filter-bar a:hover  { border-color: #667eea; }
    .filter-bar a.active { background: #667eea; border-color: #667eea; color: white; }
    .filter-bar a .n { opacity: .8; font-weight: normal; }

    table.plans { width: 100%; border-collapse: collapse; font-size: 14px; }
    table.plans th, table.plans td { border: 1px solid #e3e6ec; padding: 11px 12px; text-align: left; }
    table.plans thead th { background: #667eea; color: white; font-size: 13px; }
    table.plans tbody tr:nth-child(even) { background: #fafbfc; }
    table.plans tr.waiting td { background: #f5f9ff; }
    table.plans a.topic { color: #2d3748; font-weight: 600; text-decoration: none; }
    table.plans a.topic:hover { color: #667eea; }

    .btn {
        display: inline-flex; align-items: center; gap: 7px;
        background: #667eea; color: white; border: none;
        padding: 12px 22px; border-radius: 9px; text-decoration: none;
        font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer;
    }
    .btn:hover { background: #5a6fd6; }
    .btn-green  { background: #4CAF50; } .btn-green:hover  { background: #43a047; }
    .btn-amber  { background: #ff9800; } .btn-amber:hover  { background: #f57c00; }
    .btn-grey   { background: #8a91a0; } .btn-grey:hover   { background: #78808f; }
    .btn-outline { background: white; color: #5a6172; border: 2px solid #e2e8f0; }
    .btn-outline:hover { border-color: #667eea; color: #4c51bf; }
    .btn-sm { padding: 7px 13px; font-size: 12.5px; }

    .empty-box { text-align: center; padding: 45px 20px; color: #8a91a0; }
    .empty-box .big { font-size: 46px; display: block; margin-bottom: 12px; }

    .review-box {
        background: #f7f9fc; border: 2px solid #e8ecf3;
        border-radius: 12px; padding: 22px; margin-top: 22px;
    }
    .review-box h3 { font-size: 16px; color: #333; margin-bottom: 6px; }
    .review-box p.sub { color: #8a91a0; font-size: 13px; margin-bottom: 15px; line-height: 1.7; }
    .review-box textarea {
        width: 100%; min-height: 95px; padding: 12px;
        border: 2px solid #e2e8f0; border-radius: 9px;
        font-size: 14px; font-family: inherit; background: white; line-height: 1.7;
        resize: vertical;
    }
    .review-box textarea:focus { outline: none; border-color: #667eea; }
    .review-actions { display: flex; gap: 12px; margin-top: 15px; flex-wrap: wrap; align-items: center; }

    .message { padding: 15px; border-radius: 9px; margin-bottom: 20px; font-size: 14px; line-height: 1.7; }
    .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
    .message.error   { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
</style>
<?php lp_styles(); ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>


<?php if ($plan): ?>
<!-- ============================================================
     READ AND ANSWER ONE PLAN
     ============================================================ -->
    <div class="page-head no-print">
        <a class="btn btn-outline" href="lesson_plans.php">← All lesson plans</a>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <?php echo lp_status_badge($plan['status']); ?>
            <button class="btn btn-grey" onclick="window.print()">🖨️ Print</button>
        </div>
    </div>

    <p class="no-print" style="color:#8a91a0;font-size:13px;margin-bottom:18px;">
        Sent by <strong><?php echo htmlspecialchars($plan['teacher_name']); ?></strong>
        <?php if ($plan['submitted_at']): ?>
            on <?php echo date('j M Y \a\t H:i', strtotime($plan['submitted_at'])); ?>
        <?php endif; ?>
        <?php if ($plan['reviewed_at']): ?>
            &nbsp;·&nbsp; last answered <?php echo date('j M Y', strtotime($plan['reviewed_at'])); ?>
            <?php if (!empty($plan['reviewer_name'])): ?>
                by <?php echo htmlspecialchars($plan['reviewer_name']); ?>
            <?php endif; ?>
        <?php endif; ?>
    </p>

    <?php if (!empty($plan['admin_comment'])): ?>
        <div class="lp-comment">
            <strong>Your last comment:</strong>
            <?php echo nl2br(htmlspecialchars($plan['admin_comment'])); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php lp_render_plan($plan); ?>

        <div class="review-box no-print">
            <h3>Your answer</h3>
            <p class="sub">
                The teacher sees whatever you write here on their own page.
                A comment is optional when approving, and required when sending it back.
            </p>
            <form method="POST">
                <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
                <textarea name="admin_comment" id="adminComment"
                          placeholder="e.g. Good plan. Please add one more activity for the slower group."><?php
                    echo htmlspecialchars($plan['admin_comment'] ?? ''); ?></textarea>
                <div class="review-actions">
                    <button type="submit" name="review" value="approve" class="btn btn-green">✅ Approve</button>
                    <button type="submit" name="review" value="changes" class="btn btn-amber"
                            onclick="var c=document.getElementById('adminComment');
                                     if(!c.value.trim()){alert('Please write a short comment so the teacher knows what to change.');c.focus();return false;}">
                        ✏️ Send back for changes
                    </button>
                </div>
            </form>
        </div>
    </div>


<?php else: ?>
<!-- ============================================================
     THE LIST
     ============================================================ -->
    <div class="page-head">
        <div>
            <h2>📋 Lesson Plans</h2>
            <p>Plans your teachers have sent in. Drafts they are still writing do not appear here.</p>
        </div>
        <span class="count-pill <?php echo $pending === 0 ? 'none' : ''; ?>">
            <?php echo $pending === 0 ? '✅ Nothing waiting' : '📤 ' . $pending . ' waiting for you'; ?>
        </span>
    </div>

    <div class="card">
        <div class="filter-bar">
            <a href="lesson_plans.php" class="<?php echo ($filter_teacher === 0 && $filter_status === '') ? 'active' : ''; ?>">All</a>
            <a href="lesson_plans.php?status=submitted"     class="<?php echo $filter_status === 'submitted' ? 'active' : ''; ?>">📤 Waiting</a>
            <a href="lesson_plans.php?status=approved"      class="<?php echo $filter_status === 'approved' ? 'active' : ''; ?>">✅ Approved</a>
            <a href="lesson_plans.php?status=needs_changes" class="<?php echo $filter_status === 'needs_changes' ? 'active' : ''; ?>">✏️ Sent back</a>

            <?php foreach ($teachers as $t): ?>
                <a href="lesson_plans.php?teacher=<?php echo (int)$t['id']; ?>"
                   class="<?php echo $filter_teacher === (int)$t['id'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($t['name'] ?? ''); ?>
                    <span class="n">(<?php echo (int)$t['n']; ?><?php
                        echo (int)$t['waiting'] > 0 ? ' · ' . (int)$t['waiting'] . ' new' : ''; ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (count($plans) === 0): ?>
            <div class="empty-box">
                <span class="big">📋</span>
                <p>
                    <?php if ($filter_teacher || $filter_status): ?>
                        Nothing matches that filter.
                    <?php else: ?>
                        No teacher has sent a lesson plan yet.<br>
                        They write one under <strong>Lesson Plans</strong> in their own panel.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-scroll">
            <table class="plans">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Topic</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Lesson date</th>
                        <th>Sent</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $p): ?>
                        <tr class="<?php echo $p['status'] === 'submitted' ? 'waiting' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($p['teacher_name'] ?? ''); ?></strong></td>
                            <td>
                                <a class="topic" href="lesson_plans.php?view=<?php echo (int)$p['id']; ?>">
                                    <?php echo htmlspecialchars($p['topic'] ?? ''); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($p['subject_name'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($p['class_name'] ?: '-'); ?></td>
                            <td><?php echo lp_date($p['lesson_date']); ?></td>
                            <td style="color:#8a91a0;font-size:13px;white-space:nowrap;">
                                <?php echo $p['submitted_at'] ? date('j M, H:i', strtotime($p['submitted_at'])) : '-'; ?>
                            </td>
                            <td><?php echo lp_status_badge($p['status']); ?></td>
                            <td style="white-space:nowrap;">
                                <a class="btn <?php echo $p['status'] === 'submitted' ? '' : 'btn-outline'; ?> btn-sm"
                                   href="lesson_plans.php?view=<?php echo (int)$p['id']; ?>">
                                    <?php echo $p['status'] === 'submitted' ? 'Review' : 'Open'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

</div></div>
</body>
</html>
