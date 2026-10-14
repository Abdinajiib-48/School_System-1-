<?php
// ============================================================
// admin/fees.php - Set the fee for each class and academic year.
//
// Who can open it: admin, cashier
// ============================================================
include '../config.php';
include 'finance_common.php';

$message = "";
$message_type = "";

// ---- Delete fee ----
if ($has_finance && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM fee_structure WHERE id = $id");
    $message = "✅ Fee deleted successfully!";
    $message_type = "success";
}

// ---- Add / Edit fee ----
if ($has_finance && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id      = (int)$_POST['class_id'];
    $academic_year = mysqli_real_escape_string($conn, trim($_POST['academic_year']));
    $amount        = $_POST['amount'];
    $description   = mysqli_real_escape_string($conn, trim($_POST['description']));
    $edit_id       = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $errors = [];

    if ($class_id <= 0)                       $errors[] = "❌ Please select a class!";
    if ($academic_year === '')                $errors[] = "❌ Academic year is required!";
    if (!is_numeric($amount) || $amount < 0)  $errors[] = "❌ Amount must be a positive number!";
    if ($description === '')                  $description = 'Tuition Fee';

    // Same class + year must not exist twice
    if (count($errors) == 0) {
        $dup_sql = "SELECT id FROM fee_structure WHERE class_id = $class_id AND academic_year = '$academic_year'";
        if ($edit_id > 0) $dup_sql .= " AND id != $edit_id";
        if (mysqli_num_rows(mysqli_query($conn, $dup_sql)) > 0) {
            $errors[] = "❌ A fee for this class and year already exists! Edit it instead.";
        }
    }

    if (count($errors) == 0) {
        $amount = (float)$amount;
        if ($edit_id > 0) {
            $sql = "UPDATE fee_structure SET class_id=$class_id, academic_year='$academic_year',
                    amount=$amount, description='$description' WHERE id=$edit_id";
            $ok_msg = "✅ Fee updated successfully!";
        } else {
            $sql = "INSERT INTO fee_structure (class_id, academic_year, amount, description)
                    VALUES ($class_id, '$academic_year', $amount, '$description')";
            $ok_msg = "✅ Fee added successfully!";
        }
        if (mysqli_query($conn, $sql)) {
            $message = $ok_msg;
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
$classes = [];
$c_res = mysqli_query($conn, "SELECT * FROM classes ORDER BY grade");
while ($row = mysqli_fetch_assoc($c_res)) $classes[] = $row;

$fees = [];
if ($has_finance) {
    $f_res = mysqli_query($conn,
        "SELECT f.*, c.grade,
                (SELECT COUNT(*) FROM students s WHERE s.class_id = f.class_id) AS student_count
         FROM fee_structure f
         JOIN classes c ON f.class_id = c.id
         ORDER BY f.academic_year DESC, c.grade");
    while ($row = mysqli_fetch_assoc($f_res)) $fees[] = $row;
}

$edit_fee = null;
if ($has_finance && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_fee = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM fee_structure WHERE id = $eid"));
}

include 'sidebar.php';
render_finance_styles();
render_finance_tabs('fees.php');
?>

<?php if (!$has_finance): render_finance_missing(); else: ?>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $edit_fee ? '✏️ Edit Fee' : '➕ Set Fee for a Class'; ?></h2>
    <form method="POST" action="fees.php">
        <?php if ($edit_fee): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_fee['id']; ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label>Class: <span style="color:red;">*</span></label>
                <select name="class_id" required>
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"
                            data-year="<?php echo htmlspecialchars($c['academic_year'] ?? ''); ?>"
                            <?php echo ($edit_fee && $edit_fee['class_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['grade'] ?? ''); ?> (<?php echo htmlspecialchars($c['academic_year'] ?? ''); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Academic Year: <span style="color:red;">*</span></label>
                <input type="text" name="academic_year" id="academic_year" required
                       value="<?php echo $edit_fee ? htmlspecialchars($edit_fee['academic_year'] ?? '') : ''; ?>"
                       placeholder="e.g. 2026">
                <div class="hint">Fills in automatically when you pick a class</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Fee Amount: <span style="color:red;">*</span></label>
                <input type="number" name="amount" min="0" step="0.01" required
                       value="<?php echo $edit_fee ? $edit_fee['amount'] : ''; ?>"
                       placeholder="e.g. 2000">
                <div class="hint">Total fee each student in this class must pay for the year</div>
            </div>

            <div class="form-group">
                <label>Description:</label>
                <input type="text" name="description"
                       value="<?php echo $edit_fee ? htmlspecialchars($edit_fee['description'] ?? '') : 'Tuition Fee'; ?>"
                       placeholder="e.g. Tuition Fee">
            </div>
        </div>

        <button type="submit"><?php echo $edit_fee ? 'Update Fee' : 'Save Fee'; ?></button>
        <?php if ($edit_fee): ?>
            <a href="fees.php" style="margin-left:10px;color:#666;text-decoration:none;">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>📋 Fee Structure</h2>
    <?php if (count($fees) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Academic Year</th>
                    <th>Description</th>
                    <th class="num">Fee per Student</th>
                    <th class="num">Students</th>
                    <th class="num">Expected Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fees as $f): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($f['grade'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($f['academic_year'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($f['description'] ?? ''); ?></td>
                        <td class="num"><?php echo money($f['amount']); ?></td>
                        <td class="num"><?php echo $f['student_count']; ?></td>
                        <td class="num"><strong><?php echo money($f['amount'] * $f['student_count']); ?></strong></td>
                        <td>
                            <a href="fees.php?edit=<?php echo $f['id']; ?>" class="btn btn-sm btn-orange">Edit</a>
                            <a href="fees.php?delete=<?php echo $f['id']; ?>" class="btn btn-sm btn-red"
                               onclick="return confirm('Delete this fee?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color:#999;text-align:center;padding:30px;">No fees set yet. Use the form above to set a fee for each class.</p>
    <?php endif; ?>
</div>

<?php endif; // has_finance ?>

<script>
    // Auto-fill academic year from the chosen class
    var classSel = document.querySelector('select[name="class_id"]');
    if (classSel) {
        classSel.addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            if (opt && opt.dataset.year) {
                document.getElementById('academic_year').value = opt.dataset.year;
            }
        });
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
