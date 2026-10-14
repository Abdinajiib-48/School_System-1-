<?php
// ============================================================
// admin/settings.php - The school's own name, motto and logo.
//
// Who can open it: admin ONLY - not the cashier
//
// Whatever is set here appears at the top of every dashboard.
// ============================================================
include '../config.php';
include_once '../settings_helper.php';

// ------------------------------------------------------------
// ONLY a full admin may open this page.
// A cashier is also inside the admin/ folder, so the role is
// checked exactly - not just "is logged in".
require_role('admin');

if (!settings_installed($conn)) {
    die('<h3 style="font-family:sans-serif;padding:40px;">
         The <code>school_settings</code> table is missing.<br>
         Import <code>school_settings_schema.sql</code> in phpMyAdmin, then reload this page.
         </h3>');
}

$message = '';
$message_type = '';

// Pictures we accept, and the real image type each one must be
$ALLOWED = [
    'jpg'  => IMAGETYPE_JPEG,
    'jpeg' => IMAGETYPE_JPEG,
    'png'  => IMAGETYPE_PNG,
    'gif'  => IMAGETYPE_GIF,
    'webp' => IMAGETYPE_WEBP,
];
$MAX_BYTES = 2 * 1024 * 1024;   // 2 MB

// ------------------------------------------------------------
// DELETE the logo
// ------------------------------------------------------------
if (isset($_POST['delete_logo'])) {
    $old = school_logo_file($conn);
    if ($old !== '') {
        @unlink(LOGO_DIR . $old);
        set_setting($conn, 'school_logo', '');
        $message = '🗑 Logo removed. The system now shows the school name only.';
        $message_type = 'success';
    } else {
        $message = 'There is no logo to remove.';
        $message_type = 'error';
    }
}

// ------------------------------------------------------------
// SAVE the school name / motto
// ------------------------------------------------------------
if (isset($_POST['save_details'])) {
    $name  = trim($_POST['school_name'] ?? '');
    $motto = trim($_POST['school_motto'] ?? '');

    if ($name === '') {
        $message = '❌ The school name cannot be empty.';
        $message_type = 'error';
    } else {
        set_setting($conn, 'school_name',  $name);
        set_setting($conn, 'school_motto', $motto);
        $message = '✅ School details saved.';
        $message_type = 'success';
    }
}

// ------------------------------------------------------------
// UPLOAD a new logo
// ------------------------------------------------------------
if (isset($_POST['upload_logo']) && isset($_FILES['logo_file'])) {
    $f = $_FILES['logo_file'];

    if ($f['error'] === UPLOAD_ERR_NO_FILE) {
        $message = '❌ Please choose a picture first.';
        $message_type = 'error';

    } elseif ($f['error'] !== UPLOAD_ERR_OK) {
        // the commonest cause is a file bigger than PHP's own limit
        $message = '❌ Upload failed (code ' . $f['error'] . '). The file may be too big.';
        $message_type = 'error';

    } elseif ($f['size'] > $MAX_BYTES) {
        $message = '❌ That file is ' . round($f['size'] / 1048576, 1) . ' MB. The limit is 2 MB.';
        $message_type = 'error';

    } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

        // 1. the name must end in an allowed extension, AND
        // 2. the file must really BE that kind of picture.
        //    Checking only the name would let someone rename a .php to .png.
        $info = @getimagesize($f['tmp_name']);

        if (!isset($ALLOWED[$ext])) {
            $message = '❌ Only JPG, PNG, GIF or WEBP pictures are allowed.';
            $message_type = 'error';
        } elseif (!$info || $info[2] !== $ALLOWED[$ext]) {
            $message = '❌ That file is not a real ' . strtoupper($ext) . ' picture.';
            $message_type = 'error';
        } else {
            if (!is_dir(LOGO_DIR)) {
                @mkdir(LOGO_DIR, 0777, true);
            }

            // Our own safe file name - nothing from the uploader is reused
            $new_name = 'school-logo-' . time() . '.' . $ext;

            if (move_uploaded_file($f['tmp_name'], LOGO_DIR . $new_name)) {
                // remove the previous logo so old files do not pile up
                $old = school_logo_file($conn);
                if ($old !== '' && $old !== $new_name) {
                    @unlink(LOGO_DIR . $old);
                }
                set_setting($conn, 'school_logo', $new_name);
                $message = '✅ Logo uploaded. It now shows at the top of every dashboard.';
                $message_type = 'success';
            } else {
                $message = '❌ Could not save the file. Check that <code>uploads/logo/</code> is writable.';
                $message_type = 'error';
            }
        }
    }
}

// Values shown in the form (read AFTER any save above)
$cur_name  = school_name($conn);
$cur_motto = school_motto($conn);
$cur_logo  = school_logo_url($conn, '../');
$cur_file  = school_logo_file($conn);

include 'sidebar.php';
?>

<style>
    .settings-grid {
        display: grid;
        grid-template-columns: 1.1fr 1fr;
        gap: 25px;
        align-items: start;
    }

    .card {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .card h3 {
        margin-bottom: 6px;
        color: #333;
        font-size: 19px;
    }

    .card .hint {
        color: #777;
        font-size: 13px;
        margin-bottom: 22px;
        line-height: 1.6;
    }

    .form-group { margin-bottom: 18px; }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        font-weight: 600;
        color: #444;
        font-size: 14px;
    }

    .form-group input[type="text"] {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }

    .form-group input[type="text"]:focus {
        outline: none;
        border-color: #667eea;
    }

    .form-group small { color: #888; font-size: 12px; }

    .btn {
        background: #667eea;
        color: white;
        padding: 12px 22px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
    }
    .btn:hover { background: #5a6fd6; }
    .btn-danger { background: #f44336; }
    .btn-danger:hover { background: #d93025; }

    /* ---- the logo preview box ---- */
    .logo-preview {
        border: 2px dashed #d5d8e0;
        border-radius: 12px;
        background: #fafbfc;
        min-height: 170px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 22px;
        margin-bottom: 18px;
        text-align: center;
    }
    .logo-preview img {
        max-height: 110px;
        max-width: 100%;
        object-fit: contain;
    }
    .logo-preview .empty {
        color: #9aa0ac;
        font-size: 14px;
    }
    .logo-preview .empty .big { font-size: 42px; display: block; margin-bottom: 8px; }

    .file-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .file-row input[type="file"] {
        flex: 1;
        min-width: 200px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: white;
        font-size: 13px;
    }

    .rules {
        background: #f4f6fb;
        border-left: 4px solid #667eea;
        padding: 14px 16px;
        border-radius: 8px;
        font-size: 13px;
        color: #555;
        line-height: 1.8;
        margin-top: 18px;
    }

    .message {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-size: 14px;
    }
    .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
    .message.error   { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }

    /* ---- small preview of how the top bar will look ---- */
    .bar-demo {
        background: white;
        border: 1px solid #e6e8ee;
        border-radius: 10px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-top: 8px;
    }
    .bar-demo .left { display: flex; align-items: center; gap: 12px; }
    .bar-demo img { height: 36px; width: auto; max-width: 120px; object-fit: contain; }
    .bar-demo .nm { font-weight: 700; color: #333; font-size: 16px; }
    .bar-demo .chip {
        width: 32px; height: 32px; border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    @media (max-width: 900px) {
        .settings-grid { grid-template-columns: 1fr; }
    }
</style>

<h2 style="margin-bottom:6px;">⚙️ School Settings</h2>
<p style="color:#777;margin-bottom:25px;font-size:14px;">
    Only the admin can open this page. Whatever you set here appears at the top of
    <strong>every</strong> dashboard - admin, cashier, teacher, student and parent.
</p>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="settings-grid">

    <!-- ============ LOGO ============ -->
    <div class="card">
        <h3>🖼 School Logo</h3>
        <p class="hint">
            Upload your school badge. It shows next to the school name at the top of every page,
            for every kind of user. Uploading a new one replaces the old one automatically.
        </p>

        <div class="logo-preview">
            <?php if ($cur_logo !== ''): ?>
                <img src="<?php echo htmlspecialchars($cur_logo); ?>" alt="School logo" id="previewImg">
                <small style="color:#888;"><?php echo htmlspecialchars($cur_file); ?></small>
            <?php else: ?>
                <div class="empty" id="emptyBox">
                    <span class="big">🏫</span>
                    No logo yet - the school name is shown on its own.
                </div>
                <img src="" alt="" id="previewImg" style="display:none;">
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="file-row">
                <input type="file" name="logo_file" id="logoFile"
                       accept=".jpg,.jpeg,.png,.gif,.webp">
                <button type="submit" name="upload_logo" class="btn">⬆️ Upload Logo</button>
            </div>
        </form>

        <?php if ($cur_logo !== ''): ?>
            <form method="POST" style="margin-top:14px;"
                  onsubmit="return confirm('Remove the school logo?\n\nEvery dashboard will go back to showing the school name only. You can upload a new one at any time.');">
                <button type="submit" name="delete_logo" class="btn btn-danger">🗑 Delete Logo</button>
            </form>
        <?php endif; ?>

        <div class="rules">
            <strong>For the best result:</strong><br>
            • A <strong>PNG with a see-through background</strong> looks cleanest<br>
            • Roughly <strong>square</strong>, about 200 × 200 pixels<br>
            • JPG, PNG, GIF or WEBP &nbsp;•&nbsp; up to <strong>2 MB</strong>
        </div>
    </div>

    <!-- ============ NAME ============ -->
    <div class="card">
        <h3>🏫 School Details</h3>
        <p class="hint">
            The name shown beside the logo, and on the login page.
        </p>

        <form method="POST">
            <div class="form-group">
                <label>School Name: <span style="color:red;">*</span></label>
                <input type="text" name="school_name" required maxlength="120"
                       value="<?php echo htmlspecialchars($cur_name ?? ''); ?>">
                <small>For example: Haramaya High School</small>
            </div>

            <div class="form-group">
                <label>Short Line (optional):</label>
                <input type="text" name="school_motto" maxlength="120"
                       value="<?php echo htmlspecialchars($cur_motto ?? ''); ?>">
                <small>A motto or subtitle, shown under the name on the login page.</small>
            </div>

            <button type="submit" name="save_details" class="btn">💾 Save Details</button>
        </form>

        <h3 style="margin-top:30px;font-size:16px;">👀 This is how the top bar will look</h3>
        <div class="bar-demo">
            <div class="left">
                <?php if ($cur_logo !== ''): ?>
                    <img src="<?php echo htmlspecialchars($cur_logo); ?>" alt="">
                <?php endif; ?>
                <span class="nm"><?php echo htmlspecialchars($cur_name ?? ''); ?></span>
            </div>
            <div class="chip"></div>
        </div>
    </div>
</div>

</div></div>

<script>
// Show the chosen picture straight away, before uploading
var fileInput = document.getElementById('logoFile');
if (fileInput) {
    fileInput.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        var img   = document.getElementById('previewImg');
        var empty = document.getElementById('emptyBox');
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            img.style.display = 'block';
            if (empty) empty.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });
}
</script>
</body>
</html>
