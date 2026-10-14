<?php
// ============================================================
// teacher/enter_marks.php - Enter and change marks for the selected class.
//
// Who can open it: teacher
//
// A subject is made of several assessments - Assignment, Mid Exam,
// Final Exam - each worth a share of the subject's 100%. The teacher
// picks one subject, then fills a grid: one row per student, one
// column per assessment. The whole class is saved in one click.
//
// The assessments and their weights are set by the admin in
// admin/assessments.php.
// ============================================================
include '../config.php';

require_role('teacher');

include_once '../subjects_helper.php';
include_once '../assessments_helper.php';

// Get teacher info
$user_id = $_SESSION['user_id'];
$teacher = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT t.* FROM teachers t WHERE t.user_id = '" . (int)$user_id . "'"));

// Every class assigned to this teacher, and which one is selected
include 'teacher_classes.php';

if (!$class) {
    include 'sidebar.php';
    render_no_class_box();
    echo '</div></div></body></html>';
    exit();
}

// The subjects this teacher takes
$my_subjects = teacher_subjects($conn, $teacher['id']);

// The assessment columns (Assignment / Mid Exam / Final Exam)
$types = assessment_types($conn);

// Which subject is being marked?
$requested_id = isset($_REQUEST['subject_id']) ? (int)$_REQUEST['subject_id'] : 0;

// Is that one of theirs?
$subject = null;
foreach ($my_subjects as $s) {
    if ((int)$s['id'] === $requested_id) { $subject = $s; break; }
}

// Falling back to the teacher's first subject is a friendly default for
// SHOWING the page (no subject chosen yet, or a stale link).
//
// It must never happen on a save. Quietly saving to a different subject
// than the one asked for would write marks into the wrong column - that
// bug overwrote a real mark during testing.
$is_save = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grid']));

if (!$subject && !$is_save && count($my_subjects) > 0) {
    $subject = $my_subjects[0];
}

$subject_id = $subject ? (int)$subject['id'] : 0;

$message = '';
$message_type = '';
$errors = [];

// ============================================================
// SAVE the whole grid
//
// $_POST['marks'][student_id][type_id] = '' or a number.
// An empty box means "not marked yet" and deletes any old mark,
// so a teacher can undo a mistake by clearing the box.
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grid'])) {

    if (!$subject) {
        // $subject is only null on a save when the requested subject is not
        // one of this teacher's - see the note above the fallback.
        $errors[] = $requested_id > 0
            ? '❌ You can only enter marks for the subjects you teach.'
            : '❌ Choose a subject first.';
    } elseif (!teacher_teaches_subject($conn, $teacher['id'], $subject_id)) {
        $errors[] = '❌ You can only enter marks for the subjects you teach.';
    } else {
        // The students of this class - nobody else may be marked
        $valid_students = [];
        $r = mysqli_query($conn, "SELECT id, name FROM students WHERE class_id = " . (int)$class['id']);
        while ($row = mysqli_fetch_assoc($r)) $valid_students[(int)$row['id']] = $row['name'];

        // The valid assessment columns
        $valid_types = [];
        foreach ($types as $t) $valid_types[(int)$t['id']] = $t;

        $posted  = isset($_POST['marks']) && is_array($_POST['marks']) ? $_POST['marks'] : [];
        $saved   = 0;
        $cleared = 0;

        mysqli_begin_transaction($conn);
        try {
            foreach ($posted as $sid => $by_type) {
                $sid = (int)$sid;
                if (!isset($valid_students[$sid]) || !is_array($by_type)) continue;

                foreach ($by_type as $tid => $value) {
                    $tid = (int)$tid;
                    if (!isset($valid_types[$tid])) continue;

                    $value = trim((string)$value);
                    // a comma is what many keyboards produce for a decimal point
                    $value = str_replace(',', '.', $value);
                    $max   = (float)$valid_types[$tid]['max_marks'];

                    // Empty box = no mark yet. Remove whatever was there.
                    if ($value === '') {
                        mysqli_query($conn,
                            "DELETE FROM marks
                              WHERE student_id = $sid AND subject_id = $subject_id
                                AND assessment_type_id = $tid");
                        $cleared++;
                        continue;
                    }

                    if (!is_numeric($value)) {
                        $errors[] = '❌ ' . htmlspecialchars($valid_students[$sid]) . ' - ' .
                                    htmlspecialchars($valid_types[$tid]['name']) .
                                    ': "' . htmlspecialchars($value) . '" is not a number.';
                        continue;
                    }
                    $num = (float)$value;
                    if ($num < 0 || $num > $max) {
                        $errors[] = '❌ ' . htmlspecialchars($valid_students[$sid]) . ' - ' .
                                    htmlspecialchars($valid_types[$tid]['name']) .
                                    ": $num is outside 0 - $max.";
                        continue;
                    }

                    // One row per student + subject + assessment. The unique key
                    // makes this a clean insert-or-update.
                    //
                    // Half marks are kept: out of 20 or 30, 17.5 is a normal
                    // mark. Rounding to a whole number here would quietly
                    // change what the teacher typed.
                    $num_sql = number_format(round($num, 2), 2, '.', '');
                    mysqli_query($conn,
                        "INSERT INTO marks (student_id, subject_id, assessment_type_id, marks_obtained, entered_by)
                         VALUES ($sid, $subject_id, $tid, $num_sql, " . (int)$teacher['id'] . ")
                         ON DUPLICATE KEY UPDATE
                            marks_obtained = $num_sql,
                            entered_by     = " . (int)$teacher['id'] . ",
                            entered_date   = NOW()");
                    $saved++;
                }
            }

            if (count($errors) > 0) {
                mysqli_rollback($conn);          // all or nothing - no half-saved class
            } else {
                mysqli_commit($conn);
                $message = "✅ Saved. <strong>$saved</strong> mark" . ($saved === 1 ? '' : 's') .
                           " stored for <strong>" . htmlspecialchars($subject['name']) . "</strong>" .
                           ($cleared > 0 ? ", $cleared box" . ($cleared === 1 ? '' : 'es') . " left empty." : '.');
                $message_type = 'success';
            }
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $errors[] = '❌ Could not save: ' . htmlspecialchars($e->getMessage());
        }
    }

    if (count($errors) > 0) {
        $message = 'Nothing was saved - please fix these and try again:<br>' . implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ============================================================
// Load the grid
// ============================================================
$students = [];
$r = mysqli_query($conn,
    "SELECT id, student_id, name, gender FROM students
      WHERE class_id = " . (int)$class['id'] . " ORDER BY name");
while ($row = mysqli_fetch_assoc($r)) $students[] = $row;

// Existing marks for THIS subject: $grid[student_id][type_id] = mark
$grid = [];
if ($subject_id > 0) {
    $r = mysqli_query($conn,
        "SELECT m.student_id, m.assessment_type_id, m.marks_obtained
           FROM marks m JOIN students s ON s.id = m.student_id
          WHERE s.class_id = " . (int)$class['id'] . " AND m.subject_id = $subject_id");
    while ($row = mysqli_fetch_assoc($r)) {
        $grid[(int)$row['student_id']][(int)$row['assessment_type_id']] = $row['marks_obtained'];
    }
}

$total_weight = at_total_weight($conn);

include 'sidebar.php';
?>

<style>
    .card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .card h2 { margin-bottom: 8px; color: #333; font-size: 20px; }
    .card .sub { color: #777; font-size: 13px; margin-bottom: 18px; line-height: 1.6; }

    .class-info {
        background: #f9f9f9;
        border-left: 4px solid #4CAF50;
        padding: 14px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    /* ---- the subject chooser ---- */
    .subject-bar { display: flex; gap: 9px; flex-wrap: wrap; }
    .subject-btn {
        padding: 10px 17px;
        border-radius: 9px;
        border: 2px solid #e2e8f0;
        background: white;
        color: #4a5568;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        white-space: nowrap;
    }
    .subject-btn:hover  { border-color: #4CAF50; }
    .subject-btn.active { background: #4CAF50; border-color: #4CAF50; color: white; }
    .subject-btn .done  { font-size: 11px; font-weight: normal; opacity: .85; display: block; }

    /* ---- the grid ---- */
    table.grid { width: 100%; border-collapse: collapse; font-size: 14px; }
    table.grid th, table.grid td {
        border: 1px solid #e3e6ec;
        padding: 9px 10px;
        text-align: center;
    }
    table.grid thead th { background: #4CAF50; color: white; font-size: 13px; }
    table.grid th.namecol, table.grid td.namecol { text-align: left; white-space: nowrap; }
    table.grid tbody tr:nth-child(even) { background: #fafbfc; }

    table.grid input.mark {
        width: 74px;
        padding: 8px 6px;
        border: 2px solid #e2e8f0;
        border-radius: 7px;
        text-align: center;
        font-size: 14px;
        font-family: inherit;
    }
    table.grid input.mark:focus { outline: none; border-color: #4CAF50; background: #f3fbf4; }
    table.grid input.mark.filled { border-color: #c9e6cb; background: #f6fcf6; }
    table.grid input.mark.bad    { border-color: #f44336; background: #fff5f5; }

    td.subtotal { background: #eef2ff; font-weight: 700; color: #3c366b; }
    td.subtotal .part { display: block; font-size: 11px; font-weight: normal; color: #8a91a0; }

    .save-bar {
        position: sticky;
        bottom: 0;
        background: white;
        border-top: 1px solid #eceef3;
        padding: 15px 0 5px;
        margin-top: 18px;
        display: flex;
        gap: 14px;
        align-items: center;
        flex-wrap: wrap;
    }
    .btn-save {
        background: #4CAF50; color: white; border: none;
        padding: 13px 28px; border-radius: 9px;
        font-size: 15px; font-weight: 600; font-family: inherit; cursor: pointer;
    }
    .btn-save:hover { background: #43a047; }
    .btn-save[disabled] { opacity: .6; cursor: default; }
    .save-bar .note { color: #8a91a0; font-size: 13px; }

    .message { padding: 15px; border-radius: 9px; margin-bottom: 20px; font-size: 14px; line-height: 1.7; }
    .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
    .message.error   { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }

    .weight-warn {
        background: #fff8e1; border-left: 4px solid #ffb300; color: #7a5c00;
        padding: 12px 15px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; line-height: 1.7;
    }

    .legend { color: #8a91a0; font-size: 12.5px; margin-top: 12px; line-height: 1.8; }
</style>
<?php assessment_styles(); ?>

<?php render_class_selector($all_classes, $selected_class_id, 'enter_marks.php'); ?>

<div class="class-info">
    <strong>🏫 Class:</strong> <?php echo htmlspecialchars($class['grade'] ?? ''); ?>
    (<?php echo htmlspecialchars($class['academic_year'] ?? ''); ?>)
    &nbsp;|&nbsp;
    <strong>👨‍🏫 Teacher:</strong> <?php echo htmlspecialchars($teacher['name'] ?? ''); ?>
    (<?php echo htmlspecialchars($class['teacher_role'] ?? ''); ?>)
</div>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (count($my_subjects) === 0): ?>
    <div class="card">
        <h2>No subject assigned</h2>
        <p class="sub">You are not linked to any subject yet, so there is nothing to mark.
           Please ask the admin to add your subject on the Teachers page.</p>
    </div>

<?php else: ?>

    <!-- ============ which subject ============ -->
    <div class="card">
        <h2>📚 Which subject?</h2>
        <p class="sub">You take <?php echo count($my_subjects); ?>
           subject<?php echo count($my_subjects) === 1 ? '' : 's'; ?> in this class.</p>
        <div class="subject-bar">
            <?php foreach ($my_subjects as $s):
                // how far along is this subject for the class?
                $sid  = (int)$s['id'];
                $need = count($students) * max(count($types), 1);
                $have = (int)mysqli_fetch_assoc(mysqli_query($conn,
                          "SELECT COUNT(*) n FROM marks m JOIN students st ON st.id = m.student_id
                            WHERE st.class_id = " . (int)$class['id'] . " AND m.subject_id = $sid"))['n'];
            ?>
                <a class="subject-btn <?php echo $sid === $subject_id ? 'active' : ''; ?>"
                   href="enter_marks.php?class_id=<?php echo (int)$selected_class_id; ?>&subject_id=<?php echo $sid; ?>">
                    <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                    <span class="done"><?php echo $have; ?> of <?php echo $need; ?> marks in</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============ the grid ============ -->
    <div class="card">
        <h2>📝 <?php echo htmlspecialchars($subject['name'] ?? ''); ?> — <?php echo htmlspecialchars($class['grade'] ?? ''); ?></h2>
        <p class="sub">
            Fill in what you have and press Save. You do not have to do it all at once -
            come back and finish the rest later. <strong>Leave a box empty</strong> if that
            assessment has not happened yet.
        </p>

        <?php if (count($types) === 0): ?>
            <div class="weight-warn">
                ⚠️ No assessment types are set up. Ask the admin to add
                Assignment, Mid Exam and Final Exam on the Assessments page.
            </div>
        <?php elseif ($total_weight !== 100): ?>
            <div class="weight-warn">
                ⚠️ The assessment weights add up to <strong><?php echo $total_weight; ?>%</strong>, not 100%.
                Subject scores will be wrong until the admin fixes this on the Assessments page.
            </div>
        <?php endif; ?>

        <?php if (count($students) === 0): ?>
            <p class="sub">There are no students in this class yet.</p>
        <?php else: ?>

        <form method="POST" action="enter_marks.php?class_id=<?php echo (int)$selected_class_id; ?>&subject_id=<?php echo $subject_id; ?>"
              id="gridForm">
            <input type="hidden" name="save_grid" value="1">

            <table class="grid">
                <thead>
                    <tr>
                        <th class="namecol">#</th>
                        <th class="namecol">Student</th>
                        <th>ID</th>
                        <?php foreach ($types as $t): ?>
                            <th class="at-head">
                                <?php echo htmlspecialchars($t['name'] ?? ''); ?>
                                <small>out of <?php echo at_show($t['max_marks']); ?> &nbsp;·&nbsp; worth <?php echo at_show($t['weight']); ?>%</small>
                            </th>
                        <?php endforeach; ?>
                        <th>Subject Total<br><small style="font-weight:normal;font-size:10px;">out of 100</small></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $n = 1; foreach ($students as $st):
                        $sid  = (int)$st['id'];
                        $comp = isset($grid[$sid]) ? $grid[$sid] : [];
                        $calc = subject_score($comp, $types);
                    ?>
                        <tr>
                            <td class="namecol"><?php echo $n++; ?></td>
                            <td class="namecol"><strong><?php echo htmlspecialchars($st['name'] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($st['student_id'] ?? ''); ?></td>

                            <?php foreach ($types as $t):
                                $tid = (int)$t['id'];
                                // The database stores 18.00; show it as 18, and
                                // 19.50 as 19.5 - trailing zeros just add noise.
                                $val = isset($comp[$tid]) ? at_show($comp[$tid]) : '';
                            ?>
                                <td>
                                    <input type="number"
                                           class="mark <?php echo $val !== '' ? 'filled' : ''; ?>"
                                           name="marks[<?php echo $sid; ?>][<?php echo $tid; ?>]"
                                           value="<?php echo htmlspecialchars((string)$val); ?>"
                                           min="0" max="<?php echo at_show($t['max_marks']); ?>" step="0.5"
                                           data-max="<?php echo at_show($t['max_marks']); ?>"
                                           data-weight="<?php echo at_show($t['weight']); ?>"
                                           data-student="<?php echo $sid; ?>"
                                           placeholder="—">
                                </td>
                            <?php endforeach; ?>

                            <td class="subtotal" id="total-<?php echo $sid; ?>">
                                <?php if ($calc['complete']): ?>
                                    <?php echo number_format($calc['score'], 1); ?>
                                <?php else: ?>
                                    —
                                    <span class="part"><?php echo $calc['entered']; ?> of <?php echo $calc['expected']; ?> in</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="save-bar">
                <button type="submit" class="btn-save" id="saveBtn">💾 Save All Marks</button>
                <span class="note" id="changeNote">No changes yet</span>
            </div>
        </form>

        <div class="legend">
            <strong>How the Subject Total works:</strong>
            <?php
            // When each paper is marked out of exactly what it is worth
            // (20, 30, 50) the total is simply the three marks added up.
            // Say that plainly instead of showing percentage sums nobody needs.
            $straight = true;
            foreach ($types as $t) {
                if ((float)$t['max_marks'] !== (float)$t['weight']) { $straight = false; break; }
            }

            $bits = [];
            foreach ($types as $t) {
                $bits[] = $straight
                    ? htmlspecialchars($t['name']) . ' (out of ' . at_show($t['max_marks']) . ')'
                    : htmlspecialchars($t['name']) . ' × ' . at_show($t['weight']) . '%';
            }
            echo implode(' &nbsp;+&nbsp; ', $bits);
            ?>
            &nbsp;=&nbsp; a score out of 100.<br>
            <?php if ($straight): ?>
                You just add the three marks together — half marks like
                <strong>17.5</strong> are fine.<br>
            <?php endif; ?>
            A subject total only appears once <strong>every</strong> assessment has a mark -
            two marks out of three is not a subject score.
        </div>

        <?php endif; ?>
    </div>

<?php endif; ?>

</div></div>

<script>
// ------------------------------------------------------------
// Live subject total, so the teacher sees the result as they type
// ------------------------------------------------------------
(function () {
    var form = document.getElementById('gridForm');
    if (!form) return;

    var boxes   = form.querySelectorAll('input.mark');
    var changed = false;

    function recalcStudent(studentId) {
        var mine  = form.querySelectorAll('input.mark[data-student="' + studentId + '"]');
        var score = 0, filled = 0;

        mine.forEach(function (b) {
            var v = b.value.trim();
            if (v === '') return;
            var max = parseFloat(b.dataset.max)    || 100;
            var w   = parseFloat(b.dataset.weight) || 0;
            score += (parseFloat(v) / max) * w;
            filled++;
        });

        var cell = document.getElementById('total-' + studentId);
        if (!cell) return;

        if (filled === mine.length && mine.length > 0) {
            cell.textContent = score.toFixed(1);
        } else {
            cell.innerHTML = '—<span class="part">' + filled + ' of ' + mine.length + ' in</span>';
        }
    }

    boxes.forEach(function (b) {
        b.addEventListener('input', function () {
            var v   = this.value.trim();
            var max = parseFloat(this.dataset.max) || 100;
            var num = parseFloat(v);

            this.classList.toggle('filled', v !== '');
            this.classList.toggle('bad', v !== '' && (isNaN(num) || num < 0 || num > max));

            recalcStudent(this.dataset.student);

            if (!changed) {
                changed = true;
                document.getElementById('changeNote').textContent =
                    'You have unsaved changes';
            }
        });

        // Enter moves down the column instead of submitting the form
        b.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var col = Array.prototype.indexOf.call(
                this.closest('tr').querySelectorAll('input.mark'), this);
            var row = this.closest('tr').nextElementSibling;
            if (row) {
                var next = row.querySelectorAll('input.mark')[col];
                if (next) { next.focus(); next.select(); }
            }
        });
    });

    // Don't let a half-finished page be lost by accident
    window.addEventListener('beforeunload', function (e) {
        if (!changed) return;
        e.preventDefault();
        e.returnValue = '';
    });

    form.addEventListener('submit', function () {
        changed = false;                       // saving is not "losing" the changes
        var b = document.getElementById('saveBtn');
        b.disabled = true;
        b.textContent = 'Saving...';
    });
})();

// sidebar toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
if (menuToggle) {
    menuToggle.addEventListener('click', function () { sidebar.classList.toggle('active'); });
}
</script>
</body>
</html>
