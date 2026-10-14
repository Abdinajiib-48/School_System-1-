<?php
// ============================================================
// admin/assessments.php - The assessments a subject is made of.
//
// Who can open it: admin
//
// A subject mark is built from several assessments - by default
// Assignment 20%, Mid Exam 30%, Final Exam 50%. The weights must add
// up to 100, and this page is the only place they can be changed.
// ============================================================
include '../config.php';

require_role('admin');

include_once '../assessments_helper.php';

if (!at_installed($conn)) {
    die('<h3 style="font-family:sans-serif;padding:40px;">
         The <code>assessment_types</code> table is missing.<br>
         Import <code>assessment_schema.sql</code> in phpMyAdmin, then reload this page.
         </h3>');
}

$message = '';
$message_type = '';

// How many marks are stored against a type? Needed before deleting one.
function marks_using_type($conn, $id) {
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) n FROM marks WHERE assessment_type_id = " . (int)$id));
    return (int)$r['n'];
}

// ------------------------------------------------------------
// ADD a new assessment
// ------------------------------------------------------------
if (isset($_POST['add'])) {
    $name   = trim($_POST['name'] ?? '');
    $weight = (int)($_POST['weight'] ?? 0);
    $max    = (int)($_POST['max_marks'] ?? 100);

    if ($name === '') {
        $message = '❌ Give the assessment a name.';
        $message_type = 'error';
    } elseif ($max <= 0) {
        $message = '❌ "Out of" must be more than 0.';
        $message_type = 'error';
    } elseif ($weight < 0 || $weight > 100) {
        $message = '❌ Weight must be between 0 and 100.';
        $message_type = 'error';
    } else {
        $dup = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT id FROM assessment_types WHERE name = '" . mysqli_real_escape_string($conn, $name) . "'"));
        if ($dup) {
            $message = '❌ There is already an assessment called "' . htmlspecialchars($name) . '".';
            $message_type = 'error';
        } else {
            $next = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM assessment_types"))['n'];
            mysqli_query($conn,
                "INSERT INTO assessment_types (name, weight, max_marks, sort_order)
                 VALUES ('" . mysqli_real_escape_string($conn, $name) . "', $weight, $max, $next)");
            at_cache_clear();
            $message = '✅ Added <strong>' . htmlspecialchars($name) . '</strong>.';
            $message_type = 'success';
        }
    }
}

// ------------------------------------------------------------
// SAVE the table (names, weights, out-of, on/off)
// ------------------------------------------------------------
if (isset($_POST['save_all'])) {
    $rows   = isset($_POST['row']) && is_array($_POST['row']) ? $_POST['row'] : [];
    $active = isset($_POST['active']) && is_array($_POST['active']) ? $_POST['active'] : [];
    $errors = [];

    // check before writing anything
    $new_total = 0;
    foreach ($rows as $id => $r) {
        $id = (int)$id;
        $nm = trim($r['name'] ?? '');
        $w  = (int)($r['weight'] ?? 0);
        $mx = (int)($r['max_marks'] ?? 100);
        $on = isset($active[$id]);

        if ($nm === '')            $errors[] = "A name cannot be empty.";
        if ($mx <= 0)              $errors[] = "\"$nm\": out of must be more than 0.";
        if ($w < 0 || $w > 100)    $errors[] = "\"$nm\": weight must be between 0 and 100.";
        if ($on) $new_total += $w;
    }

    if ($new_total !== 100 && count($errors) === 0) {
        $errors[] = "The switched-on assessments add up to <strong>$new_total%</strong>. " .
                    "They must add up to exactly 100%.";
    }

    if (count($errors) > 0) {
        $message = 'Nothing was saved:<br>• ' . implode('<br>• ', $errors);
        $message_type = 'error';
    } else {
        mysqli_begin_transaction($conn);
        try {
            foreach ($rows as $id => $r) {
                $id = (int)$id;
                mysqli_query($conn,
                    "UPDATE assessment_types SET
                        name      = '" . mysqli_real_escape_string($conn, trim($r['name'])) . "',
                        weight    = " . (int)$r['weight'] . ",
                        max_marks = " . (int)$r['max_marks'] . ",
                        is_active = " . (isset($active[$id]) ? 1 : 0) . "
                      WHERE id = $id");
            }
            mysqli_commit($conn);
            at_cache_clear();
            $message = '✅ Saved.';
            $message_type = 'success';
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = '❌ Could not save: ' . htmlspecialchars($e->getMessage());
            $message_type = 'error';
        }
    }
}

// ------------------------------------------------------------
// DELETE - only when no marks depend on it
// ------------------------------------------------------------
if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM assessment_types WHERE id = $id"));
    $n   = marks_using_type($conn, $id);

    if (!$row) {
        $message = '❌ That assessment no longer exists.';
        $message_type = 'error';
    } elseif ($n > 0) {
        $message = '❌ <strong>' . htmlspecialchars($row['name']) . '</strong> cannot be deleted - ' .
                   "$n mark" . ($n === 1 ? '' : 's') . ' would be lost with it.<br>' .
                   'Switch it off instead: untick <em>Counts</em> and save. It then stops ' .
                   'affecting results, but the marks stay safe.';
        $message_type = 'error';
    } else {
        mysqli_query($conn, "DELETE FROM assessment_types WHERE id = $id");
        at_cache_clear();
        $message = '🗑 Deleted <strong>' . htmlspecialchars($row['name']) . '</strong>.';
        $message_type = 'success';
    }
}

$all   = assessment_types($conn, false);   // including switched-off ones
$total = at_total_weight($conn);

include 'sidebar.php';
?>

<style>
    .card {
        background: white; padding: 30px; border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;
    }
    .card h2 { font-size: 20px; color: #333; margin-bottom: 7px; }
    .card .sub { color: #777; font-size: 13.5px; margin-bottom: 22px; line-height: 1.7; }

    table.at { width: 100%; border-collapse: collapse; font-size: 14px; }
    table.at th, table.at td { border: 1px solid #e3e6ec; padding: 11px 12px; text-align: left; }
    table.at thead th { background: #667eea; color: white; font-size: 13px; }
    table.at tbody tr:nth-child(even) { background: #fafbfc; }
    table.at tr.off td { opacity: .55; }

    table.at input[type="text"], table.at input[type="number"] {
        padding: 9px 10px; border: 2px solid #e2e8f0; border-radius: 7px;
        font-size: 14px; font-family: inherit;
    }
    table.at input[type="text"]   { width: 100%; min-width: 130px; }
    table.at input[type="number"] { width: 90px; text-align: center; }
    table.at input:focus { outline: none; border-color: #667eea; }

    .btn {
        background: #667eea; color: white; border: none;
        padding: 12px 24px; border-radius: 9px;
        font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer;
    }
    .btn:hover { background: #5a6fd6; }
    .btn-green { background: #4CAF50; } .btn-green:hover { background: #43a047; }
    .del { color: #f44336; text-decoration: none; font-size: 12.5px; white-space: nowrap; }
    .del:hover { text-decoration: underline; }

    .total-row {
        display: flex; align-items: center; gap: 13px;
        margin-top: 18px; font-size: 15px; flex-wrap: wrap;
    }
    .pill { padding: 7px 16px; border-radius: 20px; font-weight: 700; }
    .pill.ok  { background: #e8f5e9; color: #2e7d32; }
    .pill.bad { background: #ffebee; color: #c62828; }

    .message { padding: 15px; border-radius: 9px; margin-bottom: 20px; font-size: 14px; line-height: 1.8; }
    .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
    .message.error   { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }

    .example {
        background: #f4f6fb; border-left: 4px solid #667eea;
        padding: 15px 17px; border-radius: 8px; font-size: 13.5px;
        color: #555; line-height: 1.9; margin-top: 20px;
    }

    .add-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
    .add-row label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 6px; }
    .add-row input {
        padding: 11px; border: 2px solid #e2e8f0; border-radius: 8px;
        font-size: 14px; font-family: inherit;
    }
    .add-row input:focus { outline: none; border-color: #667eea; }
</style>

<h2 style="margin-bottom:6px;">🧮 Assessments</h2>
<p style="color:#777;margin-bottom:24px;font-size:14px;">
    What a subject mark is made of. Teachers enter one mark per assessment on their
    Enter Marks page.
</p>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2>The assessments</h2>
    <p class="sub">
        <strong>Out of</strong> is what the teacher marks out of.
        <strong>Weight</strong> is that assessment's share of the subject's 100%.
        Untick <strong>Counts</strong> to switch one off without losing its marks.
    </p>

    <form method="POST">
        <div class="table-scroll">
        <table class="at">
            <thead>
                <tr>
                    <th>Assessment</th>
                    <th>Out of</th>
                    <th>Weight %</th>
                    <th>Counts</th>
                    <th>Marks stored</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all as $t):
                    $id  = (int)$t['id'];
                    $use = marks_using_type($conn, $id);
                ?>
                    <tr class="<?php echo $t['is_active'] ? '' : 'off'; ?>">
                        <td>
                            <input type="text" name="row[<?php echo $id; ?>][name]"
                                   value="<?php echo htmlspecialchars($t['name'] ?? ''); ?>" required>
                        </td>
                        <td>
                            <input type="number" name="row[<?php echo $id; ?>][max_marks]"
                                   value="<?php echo (int)$t['max_marks']; ?>" min="1" max="1000" required>
                        </td>
                        <td>
                            <input type="number" class="w" name="row[<?php echo $id; ?>][weight]"
                                   value="<?php echo (int)$t['weight']; ?>" min="0" max="100" required>
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" class="on" name="active[<?php echo $id; ?>]"
                                   <?php echo $t['is_active'] ? 'checked' : ''; ?>
                                   style="width:20px;height:20px;cursor:pointer;">
                        </td>
                        <td><?php echo $use; ?></td>
                        <td>
                            <?php if ($use === 0): ?>
                                <a class="del" href="assessments.php?delete=<?php echo $id; ?>"
                                   onclick="return confirm('Delete <?php echo htmlspecialchars(addslashes($t['name'])); ?>?')">🗑 Delete</a>
                            <?php else: ?>
                                <span style="color:#bbb;font-size:12px;">in use</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="total-row">
            <span>Switched-on weights add up to</span>
            <span class="pill <?php echo $total === 100 ? 'ok' : 'bad'; ?>" id="totalPill"><?php echo $total; ?>%</span>
            <span id="totalHint" style="color:#8a91a0;font-size:13px;">
                <?php echo $total === 100 ? 'Correct.' : 'This must be exactly 100% before results are right.'; ?>
            </span>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" name="save_all" class="btn">💾 Save Changes</button>
        </div>
    </form>

    <div class="example">
        <?php
        // A worked example built from the settings that are actually saved,
        // so it never drifts out of date with the table above.
        $live = assessment_types($conn);

        // When every paper is marked out of exactly what it is worth,
        // the subject score is simply the marks added up.
        $straight = count($live) > 0;
        foreach ($live as $t) {
            if ((float)$t['max_marks'] !== (float)$t['weight']) { $straight = false; break; }
        }

        // score 80% in each, so the example works whatever the maximums are
        $score = 0; $parts = []; $sums = [];
        foreach ($live as $t) {
            $mark   = round((float)$t['max_marks'] * 0.8, 1);
            $score += ($mark / max((float)$t['max_marks'], 1)) * (float)$t['weight'];
            $parts[] = '<strong>' . at_show($mark) . '</strong> out of ' . at_show($t['max_marks']) .
                       ' in the ' . htmlspecialchars(strtolower($t['name']));
            $sums[]  = $straight
                     ? at_show($mark)
                     : at_show($mark) . '/' . at_show($t['max_marks']) . '×' . at_show($t['weight']);
        }
        ?>
        <strong>Worked example</strong> using the settings above.<br>
        A student scores <?php echo implode(', ', $parts); ?>.<br>
        Subject score = <?php echo implode(' + ', $sums); ?>
        = <strong><?php echo at_show(round($score, 1)); ?> out of 100</strong>.<br>
        <span style="color:#888;">
            <?php if ($straight): ?>
                Each paper is marked out of exactly what it is worth, so the subject
                score is just the marks added together. Half marks such as 17.5 are allowed.
            <?php else: ?>
                If you would rather mark each paper out of exactly what it is worth,
                set <em>Out of</em> to match the <em>Weight</em>. The subject score then
                becomes a simple sum, and works out the same either way.
            <?php endif; ?>
        </span>
    </div>
</div>

<div class="card">
    <h2>Add another assessment</h2>
    <p class="sub">
        For example a Quiz or a Practical. Remember to rebalance the weights
        afterwards so they still total 100%.
    </p>
    <form method="POST" class="add-row">
        <div>
            <label>Name</label>
            <input type="text" name="name" placeholder="e.g. Quiz" required>
        </div>
        <div>
            <label>Out of</label>
            <input type="number" name="max_marks" value="100" min="1" max="1000" required style="width:110px;">
        </div>
        <div>
            <label>Weight %</label>
            <input type="number" name="weight" value="0" min="0" max="100" required style="width:110px;">
        </div>
        <button type="submit" name="add" class="btn btn-green">➕ Add</button>
    </form>
</div>

</div></div>

<script>
// Live total, so the admin sees straight away whether it reaches 100
(function () {
    function recalc() {
        var total = 0;
        document.querySelectorAll('table.at tbody tr').forEach(function (tr) {
            var on = tr.querySelector('input.on');
            var w  = tr.querySelector('input.w');
            tr.classList.toggle('off', !(on && on.checked));
            if (on && on.checked && w) total += parseInt(w.value, 10) || 0;
        });

        var pill = document.getElementById('totalPill');
        var hint = document.getElementById('totalHint');
        pill.textContent = total + '%';
        pill.className = 'pill ' + (total === 100 ? 'ok' : 'bad');
        hint.textContent = total === 100
            ? 'Correct.'
            : 'This must be exactly 100% before results are right.';
    }
    document.querySelectorAll('input.w, input.on').forEach(function (el) {
        el.addEventListener('input',  recalc);
        el.addEventListener('change', recalc);
    });
})();
</script>
</body>
</html>
