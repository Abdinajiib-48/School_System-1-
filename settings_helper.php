<?php
include_once __DIR__ . '/auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// School settings helper - the school logo and school name.
//
// Every portal (admin, cashier, teacher, student, parent) reads
// the logo through this one file, so the admin uploads it once
// and it changes everywhere.
//
// Settings live in the school_settings table (key -> value).
// Run school_settings_schema.sql once to create it.
// ============================================================

if (!function_exists('settings_installed')) {

    // Folder the logo file is kept in, relative to this file
    define('LOGO_DIR', __DIR__ . '/uploads/logo/');

    // Is the settings table there? Checked once per page load.
    function settings_installed($conn) {
        static $ok = null;
        if ($ok === null) {
            $r  = @mysqli_query($conn, "SHOW TABLES LIKE 'school_settings'");
            $ok = $r && mysqli_num_rows($r) > 0;
        }
        return $ok;
    }

    // Read every setting once, then answer from memory.
    // The cache is a global (not a static) so set_setting() can clear it.
    function all_settings($conn) {
        if (isset($GLOBALS['__settings_cache'])) return $GLOBALS['__settings_cache'];

        $cache = [];
        if (settings_installed($conn)) {
            $res = @mysqli_query($conn, "SELECT setting_key, setting_value FROM school_settings");
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $cache[$r['setting_key']] = $r['setting_value'];
                }
            }
        }
        $GLOBALS['__settings_cache'] = $cache;
        return $cache;
    }

    // One setting, with a fallback when it is missing or empty
    function get_setting($conn, $key, $default = '') {
        $all = all_settings($conn);
        return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
    }

    // Save a setting (admin only - the caller checks the role)
    function set_setting($conn, $key, $value) {
        if (!settings_installed($conn)) return false;
        $k = mysqli_real_escape_string($conn, $key);
        $v = mysqli_real_escape_string($conn, $value);
        $ok = mysqli_query($conn,
            "INSERT INTO school_settings (setting_key, setting_value) VALUES ('$k', '$v')
             ON DUPLICATE KEY UPDATE setting_value = '$v'");
        // the cached copy is now out of date
        settings_cache_clear();
        return $ok;
    }

    // Forget the cached settings so the next read hits the database.
    // Used right after a save, so the page shows the new logo at once.
    function settings_cache_clear() {
        unset($GLOBALS['__settings_cache']);
    }

    // ------------------------------------------------------------
    // The school name, and the small line under it
    // ------------------------------------------------------------
    function school_name($conn) {
        return get_setting($conn, 'school_name', 'My School');
    }

    function school_motto($conn) {
        return get_setting($conn, 'school_motto', '');
    }

    // ------------------------------------------------------------
    // The logo
    // ------------------------------------------------------------

    // Just the file name stored in the database, or '' when none
    function school_logo_file($conn) {
        $f = get_setting($conn, 'school_logo', '');
        if ($f === '') return '';
        // the row may point at a file that was deleted by hand
        return is_file(LOGO_DIR . $f) ? $f : '';
    }

    // Web address of the logo, ready for <img src="...">, or '' when none.
    // $base is how deep the page is: '../' from admin/, teacher/, etc.
    // ?v=<modified time> makes the browser fetch a newly uploaded logo
    // instead of showing the old cached one.
    function school_logo_url($conn, $base = '../') {
        $f = school_logo_file($conn);
        if ($f === '') return '';
        $v = @filemtime(LOGO_DIR . $f);
        return $base . 'uploads/logo/' . rawurlencode($f) . ($v ? '?v=' . $v : '');
    }

    function has_school_logo($conn) {
        return school_logo_file($conn) !== '';
    }

    // ------------------------------------------------------------
    // The brand block drawn at the top of every dashboard.
    // Shows the logo when there is one, and always the school name.
    // ------------------------------------------------------------
    function render_school_brand($conn, $base = '../') {
        $logo = school_logo_url($conn, $base);
        $name = school_name($conn);
        ?>
        <div class="school-brand">
            <?php if ($logo !== ''): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>"
                     alt="<?php echo htmlspecialchars($name); ?>"
                     class="school-brand-logo"
                     onerror="this.onerror=null;this.style.display='none';">
            <?php endif; ?>
            <span class="school-brand-name"><?php echo htmlspecialchars($name); ?></span>
        </div>
        <?php
    }

    // Styles for the brand block. Printed once inside each sidebar's <head>.
    function school_brand_styles() {
        ?>
        <style>
            .school-brand {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;          /* lets the name shrink instead of pushing */
            }
            .school-brand-logo {
                height: 40px;
                width: auto;
                max-width: 140px;
                object-fit: contain;
                display: block;
            }
            .school-brand-name {
                font-size: 17px;
                font-weight: 700;
                color: #333;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            /* On a phone the ☰ button and the user chip need the room */
            @media (max-width: 768px) {
                .school-brand-logo { height: 32px; max-width: 90px; }
                .school-brand-name { font-size: 14px; max-width: 120px; }
            }
            @media (max-width: 480px) {
                .school-brand-name { display: none; }   /* logo only */
            }
        </style>
        <?php
    }
}
?>
