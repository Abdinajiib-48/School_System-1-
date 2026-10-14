<?php
// ============================================================
// admin/timetable.php - Build the weekly timetable - one lesson per class, day and period.
//
// Who can open it: admin
//
// Refuses to put a teacher in two classes at the same time.
// ============================================================
include '../config.php';

require_role('admin');

include_once '../timetable_helper.php';

$installed = tt_installed($conn);
$message = ""; $message_type = "";

// ---- Which class are we building? ----
$classes = [];
$c_res = mysqli_query($conn, "SELECT * FROM classes ORDER BY grade");
while ($r = mysqli_fetch_assoc($c_res)) $classes[(int)$r['id']] = $r;

$class_id = 0;
if (isset($_GET['class_id']) && isset($classes[(int)$_GET['class_id']]))      $class_id = (int)$_GET['class_id'];
elseif (isset($_POST['class_id']) && isset($classes[(int)$_POST['class_id']])) $class_id = (int)$_POST['class_id'];
elseif (count($classes) > 0)                                                  $class_id = (int)array_key_first($classes);
$class = $class_id > 0 ? $classes[$class_id] : null;

// ---- Remove one lesson ----
if ($installed && isset($_GET['remove'])) {
    $id = (int)$_GET['remove'];
    mysqli_query($conn, "DELETE FROM timetable WHERE id = $id");
    $message = "✅ Lesson removed."; $message_type = "success";
}

// ---- Clear the whole week for this class ----
if ($installed && isset($_GET['clear']) && $class_id > 0) {
    mysqli_query($conn, "DELETE FROM timetable WHERE class_id = $class_id");
    $message = "✅ Timetable cleared for class " . htmlspecialchars($class['grade'] ?? '') . "."; $message_type = "success";
}

// ---- Save a lesson ----
if ($installed && isset($_POST['save_slot'])) {
    $day        = (int)$_POST['day'];
    $period_id  = (int)$_POST['period_id'];
    $subject_id = (int)$_POST['subject_id'];
    $room       = mysqli_real_escape_string($conn, trim($_POST['room']));
    $errors = [];

    if ($class_id <= 0)               $errors[] = "❌ Please choose a class!";
    if ($day < 1 || $day > 5)         $errors[] = "❌ Invalid day!";
    if ($period_id <= 0)              $errors[] = "❌ Invalid period!";
    if ($subject_id <= 0)             $errors[] = "❌ Please choose a subject!";

    // The subject must be one that is actually taught to this class
    $teacher_id = 0; $subject_name = '';
    if (count($errors) == 0) {
        foreach (tt_class_subjects($conn, $class_id) as $s) {
            if ((int)$s['subject_id'] === $subject_id) {
                $teacher_id   = (int)$s['teacher_id'];
                $subject_name = $s['subject_name'];
                break;
            }
        }
        if (!$teacher_id) $errors[] = "❌ No teacher of that subject is assigned to this class.";
    }

    // The period must not be a break
    if (count($errors) == 0) {
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_break, name FROM periods WHERE id = $period_id"));
        if (!$p)               $errors[] = "❌ Period not found!";
        elseif ($p['is_break']) $errors[] = "❌ " . htmlspecialchars($p['name'] ?? '') . " is a break — no lesson can be placed there.";
    }

    // ---- CLASH CHECK: is the teacher already in another class then? ----
    if (count($errors) == 0) {
        $busy = tt_teacher_clash($conn, $teacher_id, $day, $period_id, $class_id);
        if ($busy !== null) {
            $days = tt_days();
            $pn   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM periods WHERE id = $period_id"));
            $errors[] = "⚠️ <strong>Clash!</strong> That teacher is already teaching <strong>class "
                      . htmlspecialchars($busy ?? '') . "</strong> on <strong>" . $days[$day]
                      . ", " . htmlspecialchars($pn['name'] ?? '') . "</strong>. A teacher cannot be in two classes at once.";
        }
    }

    if (count($errors) == 0) {
        // one lesson per class per slot - replace whatever was there
        mysqli_query($conn, "DELETE FROM timetable WHERE class_id = $class_id AND day_of_week = $day AND period_id = $period_id");
        $ok = mysqli_query($conn,
            "INSERT INTO timetable (class_id, day_of_week, period_id, subject_id, teacher_id, room)
             VALUES ($class_id, $day, $period_id, $subject_id, $teacher_id, '$room')");
        if ($ok) {
            $message = "✅ " . htmlspecialchars($subject_name ?? '') . " added to the timetable.";
            $message_type = "success";
        } else {
            $message = "❌ Error: " . mysqli_error($conn); $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors); $message_type = "error";
    }
}

// ---- Edit the time slots ----
if ($installed && isset($_POST['save_period'])) {
    $pid   = (int)$_POST['period_id'];
    $start = trim($_POST['start_time']);
    $end   = trim($_POST['end_time']);
    if (preg_match('/^\d{2}:\d{2}$/', $start) && preg_match('/^\d{2}:\d{2}$/', $end) && $start < $end) {
        mysqli_query($conn, "UPDATE periods SET start_time = '$start:00', end_time = '$end:00' WHERE id = $pid");
        $message = "✅ Time updated."; $message_type = "success";
    } else {
        $message = "❌ End time must be after start time!"; $message_type = "error";
    }
}

// ---- Data for the page ----
$periods       = $installed ? tt_periods($conn) : [];
$grid          = ($installed && $class_id) ? tt_class_grid($conn, $class_id) : [];
$subjects      = $class_id ? tt_class_subjects($conn, $class_id) : [];
$days          = tt_days();
$teaching_slots = 0;
foreach ($periods as $p) if (!$p['is_break']) $teaching_slots++;
$total_slots = $teaching_slots * count($days);
$filled      = $installed && $class_id ? tt_class_filled($conn, $class_id) : 0;

// A cell was clicked -> pre-fill the form
$sel_day    = isset($_GET['day'])    ? (int)$_GET['day']    : 0;
$sel_period = isset($_GET['period']) ? (int)$_GET['period'] : 0;

include 'sidebar.php';
tt_styles();
?>

<style>
    .card { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card h2 { margin-bottom: 18px; color: #333; font-size: 20px; }
    .howto { background: #e7f3fe; border-left: 5px solid #2196F3; color: #0d47a1;
             border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; font-size: 14px; line-height: 1.8; }
    .howto .t { font-weight: bold; margin-bottom: 4px; }
    .howto ul { list-style: none; padding: 0; margin: 0; }
    .howto li::before { content: "\2022  "; }

    .class-selector { display: flex; gap: 10px; flex-wrap: wrap; }
    .class-btn { padding: 9px 18px; border-radius: 8px; text-decoration: none; background: #f0f2f5; color: #333;
                 font-size: 14px; font-weight: 500; border: 2px solid transparent; }
    .class-btn:hover { background: #e0f2e9; border-color: #4CAF50; }
    .class-btn.active { background: #4CAF50; color: white; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 14px; }
    input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
    input:focus, select:focus { outline: none; border-color: #4CAF50; }
    .hint { font-size: 12px; color: #666; margin-top: 4px; }

    button, .btn { background: #4CAF50; color: white; padding: 11px 22px; border: none; border-radius: 8px;
                   cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; }
    button:hover, .btn:hover { background: #45a049; }
    .btn-sm { padding: 4px 9px; font-size: 11px; border-radius: 5px; }
    .btn-red { background: #f44336; } .btn-red:hover { background: #d32f2f; }
    .btn-grey { background: #9e9e9e; } .btn-grey:hover { background: #757575; }

    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    .progress-bar { background: #eee; border-radius: 10px; height: 14px; overflow: hidden; margin-top: 10px; }
    .progress-bar > div { height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); }

    /* clickable cells in the builder */
    table.tt td.editable { cursor: pointer; position: relative; }
    table.tt td.editable a.cell-link { display: block; color: inherit; text-decoration: none; padding: 2px; }
    table.tt td.free:hover { background: #f1f8f2; }
    table.tt td .rm { position: absolute; top: 2px; right: 3px; font-size: 11px; color: #c62828; text-decoration: none; }
    table.tt td.picked { box-shadow: inset 0 0 0 3px #ff9800; }

    .period-row { display: flex; gap: 8px; align-items: center; margin-bottom: 7px; flex-wrap: wrap; }
    .period-row .nm { width: 95px; font-weight: 600; font-size: 14px; }
    .period-row input { width: 110px; padding: 6px; }

    @media (max-width: 900px) { .form-row { grid-template-columns: 1fr; } }
</style>

<?php if (!$installed): ?>
    <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:10px;padding:20px;">
        <strong>⚠️ Timetable tables not found.</strong><br>
        Import <strong>timetable_schema.sql</strong> in phpMyAdmin (database <strong>School_db1</strong>) first.
    </div>
<?php else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card no-print">
    <h2>🗓️ Timetable Builder</h2>

    <div class="howto">
        <div class="t">📌 How to build a timetable:</div>
        <ul>
            <li>Pick a class below, then <strong>click any empty cell</strong> in the grid.</li>
            <li>Choose the subject — the <strong>teacher fills in automatically</strong>, because each teacher teaches one subject.</li>
            <li>The system <strong>blocks clashes</strong>: a teacher cannot be in two classes at the same time.</li>
            <li>Only teachers linked to the class appear. Add more from <strong>Classes → Edit → Subject Teachers</strong>.</li>
        </ul>
    </div>

    <div class="class-selector">
        <?php foreach ($classes as $c): ?>
            <a href="timetable.php?class_id=<?php echo $c['id']; ?>"
               class="class-btn <?php echo ($class_id == $c['id']) ? 'active' : ''; ?>">
                🏫 <?php echo htmlspecialchars($c['grade'] ?? ''); ?>
                <small>(<?php echo htmlspecialchars($c['academic_year'] ?? ''); ?>)</small>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!$class): ?>
    <div class="card"><p style="color:#999;text-align:center;padding:30px;">No classes found. Add a class first.</p></div>
<?php elseif (count($subjects) === 0): ?>
    <div class="card">
        <h2>🏫 Class <?php echo htmlspecialchars($class['grade'] ?? ''); ?></h2>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;color:#856404;border-radius:8px;padding:15px 18px;">
            ⚠️ <strong>No teachers are linked to this class yet</strong>, so there are no subjects to place.<br>
            Go to <strong>Classes → Edit <?php echo htmlspecialchars($class['grade'] ?? ''); ?></strong> and pick a homeroom teacher
            or tick teachers under <strong>Subject Teachers</strong>.
        </div>
    </div>
<?php else: ?>

<!-- ============ ASSIGN A LESSON ============ -->
<div class="card no-print">
    <h2>➕ Place a Lesson — Class <?php echo htmlspecialchars($class['grade'] ?? ''); ?></h2>
    <form method="POST" action="timetable.php?class_id=<?php echo $class_id; ?>">
        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Day:</label>
                <select name="day" required>
                    <?php foreach ($days as $d => $label): ?>
                        <option value="<?php echo $d; ?>" <?php echo ($sel_day == $d) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Period:</label>
                <select name="period_id" required>
                    <?php foreach ($periods as $p): if ($p['is_break']) continue; ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($sel_period == $p['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name'] ?? ''); ?> (<?php echo tt_time($p); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subject:</label>
                <select name="subject_id" id="subject_id" required onchange="showTeacher()">
                    <option value="">-- Choose Subject --</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?php echo $s['subject_id']; ?>" data-teacher="<?php echo htmlspecialchars($s['teacher_name'] ?? ''); ?>">
                            <?php echo htmlspecialchars($s['subject_name'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint" id="teacherHint">Teacher fills in automatically</div>
            </div>
            <div class="form-group">
                <label>Room: <span style="color:#999;font-weight:normal;">(optional)</span></label>
                <input type="text" name="room" placeholder="e.g. Lab 1">
            </div>
        </div>
        <button type="submit" name="save_slot">💾 Place Lesson</button>
        <a href="timetable.php?class_id=<?php echo $class_id; ?>&clear=1" class="btn btn-red" style="margin-left:8px;"
           onclick="return confirm('Clear the WHOLE timetable for this class?')">🗑️ Clear Whole Week</a>
        <button type="button" class="btn btn-grey" onclick="window.print()" style="margin-left:8px;">🖨️ Print</button>
    </form>
</div>

<!-- ============ THE GRID ============ -->
<div class="card">
    <h2>🗓️ Class <?php echo htmlspecialchars($class['grade'] ?? ''); ?> — Weekly Timetable</h2>
    <p style="color:#666;font-size:14px;">
        <?php echo $filled; ?> of <?php echo $total_slots; ?> lesson slots filled
        <?php if ($filled < $total_slots): ?>
            — <strong><?php echo $total_slots - $filled; ?> still empty</strong>
        <?php else: ?>
            — <strong style="color:#2e7d32;">complete ✅</strong>
        <?php endif; ?>
    </p>
    <div class="progress-bar"><div style="width:<?php echo $total_slots ? round($filled / $total_slots * 100) : 0; ?>%"></div></div>

    <div class="tt-wrap">
    <table class="tt">
        <thead>
            <tr>
                <th class="slot">Time</th>
                <?php $today = (int)date('N'); foreach ($days as $d => $label): ?>
                    <th class="<?php echo $d === $today ? 'today-col' : ''; ?>"><?php echo $label; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($periods as $p): ?>
                <?php if ($p['is_break']): ?>
                    <tr class="break-row">
                        <td class="slot"><?php echo htmlspecialchars($p['name'] ?? ''); ?><small><?php echo tt_time($p); ?></small></td>
                        <td colspan="<?php echo count($days); ?>"><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td class="slot"><?php echo htmlspecialchars($p['name'] ?? ''); ?><small><?php echo tt_time($p); ?></small></td>
                        <?php foreach ($days as $d => $label):
                            $cell   = isset($grid[(int)$p['id']][$d]) ? $grid[(int)$p['id']][$d] : null;
                            $picked = ($sel_day == $d && $sel_period == $p['id']); ?>
                            <td class="editable <?php echo $cell ? 'lesson' : 'free'; ?> <?php echo $picked ? 'picked' : ''; ?> <?php echo $d === $today ? 'today-col' : ''; ?>">
                                <?php if ($cell): ?>
                                    <a class="rm no-print" title="Remove"
                                       href="timetable.php?class_id=<?php echo $class_id; ?>&remove=<?php echo $cell['id']; ?>"
                                       onclick="return confirm('Remove this lesson?')">✕</a>
                                    <a class="cell-link" href="timetable.php?class_id=<?php echo $class_id; ?>&day=<?php echo $d; ?>&period=<?php echo $p['id']; ?>">
                                        <span class="subj"><?php echo htmlspecialchars($cell['subject_name'] ?? ''); ?></span>
                                        <span class="who"><?php echo htmlspecialchars($cell['teacher_name'] ?: '—'); ?></span>
                                        <?php if (!empty($cell['room'])): ?>
                                            <span class="room">📍 <?php echo htmlspecialchars($cell['room'] ?? ''); ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <a class="cell-link no-print" href="timetable.php?class_id=<?php echo $class_id; ?>&day=<?php echo $d; ?>&period=<?php echo $p['id']; ?>">+ add</a>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div class="tt-legend">
        <span><i style="background:#e8f5e9;border:1px solid #2e7d32;"></i>Lesson</span>
        <span><i style="background:#fff8e1;border:1px solid #8a6d00;"></i>Break / Lunch</span>
        <span><i style="background:#2196F3;"></i>Today</span>
        <span>Click a cell to fill it · ✕ removes a lesson</span>
    </div>
</div>

<!-- ============ SUBJECTS AVAILABLE ============ -->
<div class="card no-print">
    <h2>📚 Subjects Available for Class <?php echo htmlspecialchars($class['grade'] ?? ''); ?></h2>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead><tr style="background:#4CAF50;color:white;">
            <th style="padding:10px;text-align:left;">Subject</th>
            <th style="padding:10px;text-align:left;">Teacher</th>
            <th style="padding:10px;text-align:right;">Lessons this week</th>
        </tr></thead>
        <tbody>
            <?php foreach ($subjects as $s):
                $n = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COUNT(*) AS c FROM timetable WHERE class_id = $class_id AND subject_id = " . (int)$s['subject_id'])); ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:10px;"><strong><?php echo htmlspecialchars($s['subject_name'] ?? ''); ?></strong></td>
                    <td style="padding:10px;"><?php echo htmlspecialchars($s['teacher_name'] ?? ''); ?></td>
                    <td style="padding:10px;text-align:right;">
                        <?php echo (int)$n['c']; ?>
                        <?php if ((int)$n['c'] === 0): ?><span style="color:#c62828;font-size:12px;"> — not placed yet</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<!-- ============ PERIOD TIMES ============ -->
<div class="card no-print">
    <h2>⏰ School Day Times</h2>
    <p style="color:#666;font-size:13px;margin-bottom:12px;">These times are used by every class.</p>
    <?php foreach ($periods as $p): ?>
        <form method="POST" action="timetable.php?class_id=<?php echo $class_id; ?>" class="period-row">
            <input type="hidden" name="period_id" value="<?php echo $p['id']; ?>">
            <span class="nm"><?php echo htmlspecialchars($p['name'] ?? ''); ?><?php echo $p['is_break'] ? ' ☕' : ''; ?></span>
            <input type="time" name="start_time" value="<?php echo substr($p['start_time'], 0, 5); ?>" required>
            <span>to</span>
            <input type="time" name="end_time" value="<?php echo substr($p['end_time'], 0, 5); ?>" required>
            <button type="submit" name="save_period" class="btn btn-sm btn-grey">Save</button>
        </form>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<script>
    function showTeacher() {
        var sel = document.getElementById('subject_id');
        var hint = document.getElementById('teacherHint');
        if (!sel || !hint) return;
        var opt = sel.options[sel.selectedIndex];
        hint.innerHTML = (opt && opt.dataset.teacher)
            ? '👨‍🏫 Teacher: <strong>' + opt.dataset.teacher + '</strong>'
            : 'Teacher fills in automatically';
    }
    showTeacher();
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
