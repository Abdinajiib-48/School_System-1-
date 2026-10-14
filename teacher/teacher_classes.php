<?php
include_once __DIR__ . '/../auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// Shared helper for teacher pages.
// Loads EVERY class assigned to the logged-in teacher:
//   - classes where they are the homeroom teacher
//   - classes where they are listed in class_teachers (subject teacher)
// and works out which class is currently selected (?class_id=).
//
// Expects $conn and $teacher (row from teachers table) to already exist.
// Sets: $all_classes, $selected_class_id, $class (selected class row or null)
// ============================================================

$tid = (int)$teacher['id'];

$has_ct = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'class_teachers'")) > 0;

if ($has_ct) {
    $cls_res = mysqli_query($conn,
        "SELECT DISTINCT c.*,
         CASE WHEN c.homeroom_teacher_id=$tid THEN 'Homeroom Teacher'
              ELSE 'Subject Teacher' END AS teacher_role
         FROM classes c
         LEFT JOIN class_teachers ct ON c.id=ct.class_id AND ct.teacher_id=$tid
         WHERE c.homeroom_teacher_id=$tid OR ct.teacher_id=$tid
         ORDER BY c.grade");
} else {
    $cls_res = mysqli_query($conn,
        "SELECT c.*, 'Homeroom Teacher' AS teacher_role
         FROM classes c WHERE c.homeroom_teacher_id=$tid ORDER BY c.grade");
}

$all_classes = [];
if ($cls_res) {
    while ($row = mysqli_fetch_assoc($cls_res)) {
        $all_classes[(int)$row['id']] = $row;
    }
}

// Selected class: from URL/POST, otherwise the first class in the list.
// Only classes in $all_classes are accepted, so a teacher can't view another teacher's class.
$selected_class_id = 0;
if (isset($_GET['class_id']) && isset($all_classes[(int)$_GET['class_id']])) {
    $selected_class_id = (int)$_GET['class_id'];
} elseif (isset($_POST['class_id']) && isset($all_classes[(int)$_POST['class_id']])) {
    $selected_class_id = (int)$_POST['class_id'];
} elseif (count($all_classes) > 0) {
    $selected_class_id = (int)array_key_first($all_classes);
}

$class = $selected_class_id > 0 ? $all_classes[$selected_class_id] : null;

// Renders the "Select Class" buttons. $page is the file to link to, e.g. 'report.php'
function render_class_selector($all_classes, $selected_class_id, $page, $extra_query = '') {
    ?>
    <style>
        .class-selector-card { background: white; border-radius: 15px; padding: 20px 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .class-selector-title { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 15px; padding-left: 12px; border-left: 4px solid #4CAF50; }
        .class-selector { display: flex; gap: 10px; flex-wrap: wrap; }
        .class-btn { padding: 9px 18px; border-radius: 8px; text-decoration: none; background: #f0f2f5; color: #333; font-size: 14px; font-weight: 500; border: 2px solid transparent; }
        .class-btn:hover { background: #e0f2e9; border-color: #4CAF50; }
        .class-btn.active { background: #4CAF50; color: white; }
        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 6px; }
        .role-homeroom { background: #e3f2fd; color: #1565c0; }
        .role-subject  { background: #fff3e0; color: #e65100; }
        .class-btn.active .role-badge { background: rgba(255,255,255,0.3); color: white; }
        @media print { .class-selector-card { display: none; } }
    </style>
    <div class="class-selector-card">
        <div class="class-selector-title">🏫 Select Class</div>
        <div class="class-selector">
        <?php foreach ($all_classes as $cls): ?>
            <a href="<?php echo $page; ?>?class_id=<?php echo $cls['id']; ?><?php echo $extra_query; ?>"
               class="class-btn <?php echo ($selected_class_id == $cls['id']) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cls['grade'] ?? ''); ?>
                (<?php echo htmlspecialchars($cls['academic_year'] ?? ''); ?>)
                <span class="role-badge <?php echo $cls['teacher_role'] === 'Homeroom Teacher' ? 'role-homeroom' : 'role-subject'; ?>">
                    <?php echo $cls['teacher_role'] === 'Homeroom Teacher' ? 'Homeroom' : 'Subject'; ?>
                </span>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// Renders the "no class assigned" message
function render_no_class_box() {
    ?>
    <div style="background:white;border-radius:15px;padding:50px 30px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05);">
        <div style="font-size:60px;margin-bottom:20px;">⚠️</div>
        <h2 style="color:#333;margin-bottom:15px;">No Class Assigned Yet</h2>
        <p style="color:#666;margin-bottom:10px;">You are not assigned to any class.</p>
        <p style="color:#888;font-size:14px;margin-bottom:25px;">
            Ask Admin to go to <strong>Classes → Edit</strong> and add you as homeroom teacher or subject teacher.
        </p>
        <a href="dashboard.php" style="background:#4CAF50;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;">← Back to Dashboard</a>
    </div>
    <?php
}
?>
