<?php
// ============================================================
// teacher/lesson_plans.php - Write a lesson plan and send it to the admin.
//
// Who can open it: teacher
//
// One page does three jobs, chosen by the query string:
//   (no query)   the teacher's list of plans
//   ?new=1       a blank form
//   ?edit=<id>   edit one of their own plans
//   ?view=<id>   read one of their own plans
//
// A plan can only be edited while it is a draft or has been sent back
// for changes. Once submitted it is locked until the admin answers.
// ============================================================
include '../config.php';

require_role('teacher');

include_once '../subjects_helper.php';
include_once '../lesson_plans_helper.php';

$user_id = $_SESSION['user_id'];
$teacher = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT t.* FROM teachers t WHERE t.user_id = " . (int)$user_id));

if (!$teacher) {
    include 'sidebar.php';
    echo '<div style="background:white;padding:30px;border-radius:15px;">
            <h2>No teacher record</h2>
            <p>Your login is not linked to a teacher. Please tell the school office.</p>
          </div></div></div></body></html>';
    exit();
}

if (!lp_installed($conn)) {
    include 'sidebar.php';
    echo '<div style="background:white;padding:30px;border-radius:15px;">
            <h2>Lesson plans are not set up yet</h2>
            <p>Ask the admin to import <code>lesson_plan_schema.sql</code> in phpMyAdmin.</p>
          </div></div></div></body></html>';
    exit();
}

$teacher_id = (int)$teacher['id'];
$message = '';
$message_type = '';

// The teacher's own classes and subjects, to fill the two dropdowns
$my_subjects = teacher_subjects($conn, $teacher_id);
$my_classes  = [];
$r = mysqli_query($conn,
    "SELECT DISTINCT c.id, c.grade, c.academic_year
       FROM classes c
      WHERE c.homeroom_teacher_id = $teacher_id
         OR c.id IN (SELECT class_id FROM class_teachers WHERE teacher_id = $teacher_id)
      ORDER BY c.grade");
while ($row = mysqli_fetch_assoc($r)) $my_classes[] = $row;

// ------------------------------------------------------------
// Load the plan being worked on, and prove it belongs to this teacher.
// Every read and write below goes through this check.
// ------------------------------------------------------------
$plan_id = 0;
$plan    = null;
foreach (['edit', 'view'] as $k) {
    if (isset($_GET[$k])) { $plan_id = (int)$_GET[$k]; break; }
}
if ($plan_id === 0 && isset($_POST['plan_id'])) $plan_id = (int)$_POST['plan_id'];

if ($plan_id > 0) {
    $plan = lp_get($conn, $plan_id);
    if (!$plan || (int)$plan['teacher_id'] !== $teacher_id) {
        // someone else's plan, or none - behave as if it does not exist
        header('Location: lesson_plans.php');
        exit();
    }
}

// ------------------------------------------------------------
// SAVE - as a draft, or submit to the admin
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {

    $submit = isset($_POST['submit_to_admin']);

    $topic = trim($_POST['topic'] ?? '');
    if ($topic === '') {
        $message = '❌ Give the lesson a topic before saving.';
        $message_type = 'error';
    } elseif ($plan && !lp_is_editable($plan['status'])) {
        $message = '❌ This plan is with the admin and cannot be edited right now.';
        $message_type = 'error';
    } else {
        // the short fields
        $vals = [
            'topic'           => $topic,
            'duration'        => trim($_POST['duration'] ?? ''),
            'teaching_method' => trim($_POST['teaching_method'] ?? ''),
            'curriculum'      => trim($_POST['curriculum'] ?? ''),
            'region'          => trim($_POST['region'] ?? ''),
        ];
        // the long sections, straight from lp_sections() so the form and
        // the database can never disagree about which fields exist
        foreach (lp_sections() as $key => $meta) {
            $vals[$key] = trim($_POST[$key] ?? '');
        }

        $class_id   = (int)($_POST['class_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);

        // a teacher may only file a plan against their own class and subject
        $ok_class = $class_id === 0;
        foreach ($my_classes as $c) if ((int)$c['id'] === $class_id) $ok_class = true;
        $ok_subject = $subject_id === 0;
        foreach ($my_subjects as $s) if ((int)$s['id'] === $subject_id) $ok_subject = true;

        if (!$ok_class || !$ok_subject) {
            $message = '❌ Choose one of your own classes and subjects.';
            $message_type = 'error';
        } else {
            // build the SET clause
            $sets = [];
            foreach ($vals as $k => $v) {
                $sets[] = "$k = '" . mysqli_real_escape_string($conn, $v) . "'";
            }
            $sets[] = 'class_id = '   . ($class_id   > 0 ? $class_id   : 'NULL');
            $sets[] = 'subject_id = ' . ($subject_id > 0 ? $subject_id : 'NULL');

            // "No date yet" must be stored as NULL. Putting an empty string
            // into a DATE column makes MySQL save 0000-00-00, which then
            // prints as "30 Nov -0001" on every screen.
            $date = trim($_POST['lesson_date'] ?? '');
            $sets[] = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date !== '0000-00-00')
                    ? "lesson_date = '" . mysqli_real_escape_string($conn, $date) . "'"
                    : 'lesson_date = NULL';

            if ($submit) {
                // sending it (or sending it again after changes) clears the
                // old review, so the admin sees a clean "waiting" plan
                $sets[] = "status = 'submitted'";
                $sets[] = 'submitted_at = NOW()';
                $sets[] = 'reviewed_by = NULL';
                $sets[] = 'reviewed_at = NULL';
            } elseif (!$plan || $plan['status'] === 'needs_changes') {
                // saving a draft of a sent-back plan puts it back to draft
                $sets[] = "status = 'draft'";
            }

            $set_sql = implode(', ', $sets);

            if ($plan) {
                mysqli_query($conn, "UPDATE lesson_plans SET $set_sql WHERE id = {$plan['id']} AND teacher_id = $teacher_id");
                $saved_id = (int)$plan['id'];
            } else {
                mysqli_query($conn, "INSERT INTO lesson_plans SET teacher_id = $teacher_id, $set_sql");
                $saved_id = mysqli_insert_id($conn);
            }

            // Redirect after a successful POST, so a refresh does not save twice
            $flash = $submit ? 'sent' : 'saved';
            header("Location: lesson_plans.php?view=$saved_id&done=$flash");
            exit();
        }
    }
}

// ------------------------------------------------------------
// DELETE - a draft only
// ------------------------------------------------------------
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $p   = lp_get($conn, $did);
    if ($p && (int)$p['teacher_id'] === $teacher_id && $p['status'] === 'draft') {
        mysqli_query($conn, "DELETE FROM lesson_plans WHERE id = $did AND teacher_id = $teacher_id");
        header('Location: lesson_plans.php?done=deleted');
        exit();
    }
    header('Location: lesson_plans.php');
    exit();
}

// messages after a redirect
if (isset($_GET['done'])) {
    $done = $_GET['done'];
    if ($done === 'sent') {
        $message = '📤 Sent to the admin. You will see their answer here.';
        $message_type = 'success';
    } elseif ($done === 'saved') {
        $message = '💾 Saved as a draft. It has not been sent yet - press <strong>Send to Admin</strong> when it is ready.';
        $message_type = 'success';
    } elseif ($done === 'deleted') {
        $message = '🗑 Draft deleted.';
        $message_type = 'success';
    }
}

// which view?
$mode = 'list';
if (isset($_GET['new']))                       $mode = 'form';
elseif (isset($_GET['edit']) && $plan)         $mode = 'form';
elseif (isset($_GET['view']) && $plan)         $mode = 'view';

$my_plans = ($mode === 'list') ? lp_list($conn, ['teacher_id' => $teacher_id]) : [];

include 'sidebar.php';
?>

<style>
    .card {
        background: white; padding: 28px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 22px;
    }
    .card h2 { font-size: 20px; color: #333; margin-bottom: 7px; }
    .card .sub { color: #777; font-size: 13.5px; margin-bottom: 20px; line-height: 1.7; }

    .page-head {
        display: flex; justify-content: space-between; align-items: center;
        gap: 15px; flex-wrap: wrap; margin-bottom: 20px;
    }
    .page-head h2 { font-size: 22px; color: #333; }

    .btn {
        display: inline-flex; align-items: center; gap: 7px;
        background: #4CAF50; color: white; border: none;
        padding: 12px 22px; border-radius: 9px; text-decoration: none;
        font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer;
    }
    .btn:hover      { background: #43a047; }
    .btn-grey       { background: #8a91a0; } .btn-grey:hover  { background: #78808f; }
    .btn-blue       { background: #4a7cf7; } .btn-blue:hover  { background: #3a6ae0; }
    .btn-outline    { background: white; color: #5a6172; border: 2px solid #e2e8f0; }
    .btn-outline:hover { border-color: #4CAF50; color: #2e7d32; }

    table.plans { width: 100%; border-collapse: collapse; font-size: 14px; }
    table.plans th, table.plans td { border: 1px solid #e3e6ec; padding: 11px 12px; text-align: left; }
    table.plans thead th { background: #4CAF50; color: white; font-size: 13px; }
    table.plans tbody tr:nth-child(even) { background: #fafbfc; }
    table.plans a.topic { color: #2d3748; font-weight: 600; text-decoration: none; }
    table.plans a.topic:hover { color: #4CAF50; }

    .empty-box { text-align: center; padding: 45px 20px; color: #8a91a0; }
    .empty-box .big { font-size: 46px; display: block; margin-bottom: 12px; }

    /* ---- the form ---- */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 7px; }
    .form-group .hint { font-size: 12px; color: #a0aec0; margin-top: 6px; }
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group select,
    .form-group textarea {
        width: 100%; padding: 11px 12px;
        border: 2px solid #e2e8f0; border-radius: 9px;
        font-size: 14px; font-family: inherit; background: #f8fafc;
    }
    .form-group textarea { min-height: 110px; resize: vertical; line-height: 1.7; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none; border-color: #4CAF50; background: white;
    }

    .section-divider {
        border-top: 1px solid #eceef3; margin: 26px 0 20px;
        padding-top: 18px; font-size: 13px; font-weight: 700;
        color: #667eea; text-transform: uppercase; letter-spacing: .5px;
    }

    .form-actions {
        position: sticky; bottom: 0; background: white;
        border-top: 1px solid #eceef3; padding: 16px 0 6px; margin-top: 22px;
        display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
    }
    .form-actions .note { color: #8a91a0; font-size: 12.5px; }

    .message { padding: 15px; border-radius: 9px; margin-bottom: 20px; font-size: 14px; line-height: 1.7; }
    .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
    .message.error   { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
</style>
<?php lp_styles(); ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>


<?php if ($mode === 'list'): ?>
<!-- ============================================================
     THE LIST
     ============================================================ -->
    <div class="page-head">
        <div>
            <h2>📋 My Lesson Plans</h2>
            <p style="color:#777;font-size:13.5px;margin-top:4px;">
                Write your plan, then send it to the admin to be checked.
            </p>
        </div>
        <a class="btn" href="lesson_plans.php?new=1">➕ New Lesson Plan</a>
    </div>

    <div class="card">
        <?php if (count($my_plans) === 0): ?>
            <div class="empty-box">
                <span class="big">📋</span>
                <p>You have not written a lesson plan yet.</p>
                <p style="margin-top:16px;">
                    <a class="btn" href="lesson_plans.php?new=1">➕ Write your first one</a>
                </p>
            </div>
        <?php else: ?>
            <div class="table-scroll">
            <table class="plans">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Lesson date</th>
                        <th>Status</th>
                        <th>Last change</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_plans as $p): ?>
                        <tr>
                            <td>
                                <a class="topic" href="lesson_plans.php?view=<?php echo (int)$p['id']; ?>">
                                    <?php echo htmlspecialchars($p['topic'] ?? ''); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($p['subject_name'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($p['class_name'] ?: '-'); ?></td>
                            <td><?php echo lp_date($p['lesson_date']); ?></td>
                            <td><?php echo lp_status_badge($p['status']); ?></td>
                            <td style="color:#8a91a0;font-size:13px;white-space:nowrap;">
                                <?php echo date('j M, H:i', strtotime($p['updated_at'])); ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-outline" style="padding:7px 13px;font-size:12.5px;"
                                   href="lesson_plans.php?view=<?php echo (int)$p['id']; ?>">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>


<?php elseif ($mode === 'view'): ?>
<!-- ============================================================
     READ ONE PLAN
     ============================================================ -->
    <div class="page-head no-print">
        <a class="btn btn-outline" href="lesson_plans.php">← All my plans</a>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <?php echo lp_status_badge($plan['status']); ?>
            <?php if (lp_is_editable($plan['status'])): ?>
                <a class="btn btn-blue" href="lesson_plans.php?edit=<?php echo (int)$plan['id']; ?>">✏️ Edit</a>
            <?php endif; ?>
            <button class="btn btn-grey" onclick="window.print()">🖨️ Print</button>
        </div>
    </div>

    <?php list(, , $status_help) = lp_status_label($plan['status']); ?>
    <p class="no-print" style="color:#8a91a0;font-size:13px;margin-bottom:18px;">
        <?php echo htmlspecialchars($status_help); ?>
    </p>

    <?php if (!empty($plan['admin_comment'])): ?>
        <div class="lp-comment">
            <strong>Message from the admin<?php
                echo $plan['reviewed_at'] ? ' — ' . date('j M Y', strtotime($plan['reviewed_at'])) : ''; ?>:</strong>
            <?php echo nl2br(htmlspecialchars($plan['admin_comment'])); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php lp_render_plan($plan); ?>
    </div>


<?php else: ?>
<!-- ============================================================
     THE FORM
     ============================================================ -->
    <?php
    // a blank plan when writing a new one, so every field below has a value
    if (!$plan) {
        $plan = ['id' => 0, 'topic' => '', 'lesson_date' => '', 'duration' => '50 Minutes',
                 'teaching_method' => 'Lecture', 'curriculum' => '', 'region' => '',
                 'class_id' => 0, 'subject_id' => 0, 'status' => 'draft'];
        foreach (lp_sections() as $k => $m) $plan[$k] = '';
    }
    ?>

    <div class="page-head">
        <h2><?php echo $plan['id'] ? '✏️ Edit Lesson Plan' : '➕ New Lesson Plan'; ?></h2>
        <a class="btn btn-outline" href="lesson_plans.php">← All my plans</a>
    </div>

    <?php if (!empty($plan['admin_comment']) && $plan['status'] === 'needs_changes'): ?>
        <div class="lp-comment">
            <strong>The admin asked for changes:</strong>
            <?php echo nl2br(htmlspecialchars($plan['admin_comment'])); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="lesson_plans.php" id="planForm">
        <input type="hidden" name="save_plan" value="1">
        <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">

        <div class="card">
            <h2>The lesson</h2>
            <p class="sub">The basics. Only the topic is required — you can fill in the rest as you go.</p>

            <div class="form-group">
                <label for="topic">Topic <span style="color:red;">*</span></label>
                <input type="text" name="topic" id="topic" required maxlength="200"
                       placeholder="e.g. Animal cell"
                       value="<?php echo htmlspecialchars($plan['topic'] ?? ''); ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="subject_id">Subject</label>
                    <select name="subject_id" id="subject_id">
                        <option value="0">— choose —</option>
                        <?php foreach ($my_subjects as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>"
                                <?php echo (int)$plan['subject_id'] === (int)$s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="class_id">Class</label>
                    <select name="class_id" id="class_id">
                        <option value="0">— choose —</option>
                        <?php foreach ($my_classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"
                                <?php echo (int)$plan['class_id'] === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['grade'] ?? ''); ?>
                                (<?php echo htmlspecialchars($c['academic_year'] ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="lesson_date">Lesson date</label>
                    <input type="date" name="lesson_date" id="lesson_date"
                           value="<?php echo htmlspecialchars(lp_date_input($plan['lesson_date'] ?? '')); ?>">
                </div>

                <div class="form-group">
                    <label for="duration">Duration</label>
                    <select name="duration" id="duration">
                        <?php foreach (lp_duration_options() as $d): ?>
                            <option <?php echo $plan['duration'] === $d ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="teaching_method">Teaching method</label>
                    <select name="teaching_method" id="teaching_method">
                        <?php foreach (lp_method_options() as $m): ?>
                            <option <?php echo $plan['teaching_method'] === $m ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="curriculum">Curriculum</label>
                    <input type="text" name="curriculum" id="curriculum" maxlength="100"
                           placeholder="e.g. Somali"
                           value="<?php echo htmlspecialchars($plan['curriculum'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="region">Region</label>
                    <input type="text" name="region" id="region" maxlength="100"
                           placeholder="e.g. Ethiopia"
                           value="<?php echo htmlspecialchars($plan['region'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="card">
            <h2>The plan</h2>
            <p class="sub">
                In the list boxes, put <strong>one item on each line</strong> — they become
                bullet points when the plan is read. No need to type dashes.
            </p>

            <?php foreach (lp_sections() as $key => $meta): ?>
                <div class="form-group">
                    <label for="<?php echo $key; ?>"><?php echo htmlspecialchars($meta[0]); ?></label>
                    <textarea name="<?php echo $key; ?>" id="<?php echo $key; ?>"
                              placeholder="<?php echo htmlspecialchars($meta[3]); ?>"><?php
                        echo htmlspecialchars($plan[$key] ?? ''); ?></textarea>
                    <div class="hint"><?php echo htmlspecialchars($meta[2]); ?></div>
                </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-grey" name="save_draft" value="1">💾 Save Draft</button>
                <button type="submit" class="btn" name="submit_to_admin" value="1"
                        onclick="return confirm('Send this plan to the admin?\n\nYou will not be able to edit it while they are checking it.')">
                    📤 Send to Admin
                </button>
                <span class="note">A draft is private — only you can see it until you send it.</span>
            </div>
        </div>
    </form>

<?php endif; ?>

</div></div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (menuToggle) {
        menuToggle.addEventListener('click', function () { sidebar.classList.toggle('active'); });
    }

    // Don't lose a half-written plan by accident
    (function () {
        var form = document.getElementById('planForm');
        if (!form) return;
        var dirty = false;
        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.addEventListener('input', function () { dirty = true; });
        });
        form.addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (e) {
            if (!dirty) return;
            e.preventDefault();
            e.returnValue = '';
        });
    })();
</script>
</body>
</html>
