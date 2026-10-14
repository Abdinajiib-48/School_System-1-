<?php
// This file is only ever included by another page. Opening it
// directly in the browser should look like it does not exist.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit('Not found');
}
// ============================================================
// auth.php - who is allowed on this page
//
// Before this file existed, every page repeated the same six lines:
//
//     if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
//         header('Location: ../index.php');
//         exit();
//     }
//
// That was copied into 25 files. If the login rule ever changed you had
// to remember all 25. Now each page says one line instead:
//
//     require_role('admin');                  // admins only
//     require_role(['admin', 'cashier']);     // either of the two
//     require_role_json('admin');             // for AJAX endpoints
//
// Put the call at the very top of the page, right after config.php,
// BEFORE anything is printed - a redirect cannot work once output
// has started.
// ============================================================

if (!function_exists('require_role')) {

    // The five kinds of account the system knows about
    define('ALL_ROLES', ['admin', 'teacher', 'student', 'parent', 'cashier']);

    // Where each kind of account goes when it signs in
    define('ROLE_HOME', [
        'admin'   => 'admin/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
        'parent'  => 'parent/dashboard.php',
        'cashier' => 'admin/finance.php',
    ]);

    // ------------------------------------------------------------
    // Who is signed in?
    // ------------------------------------------------------------

    function is_logged_in() {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    function current_user_id() {
        return is_logged_in() ? (int)$_SESSION['user_id'] : 0;
    }

    // 'admin', 'teacher', ... or '' when nobody is signed in
    function current_role() {
        return isset($_SESSION['role']) ? $_SESSION['role'] : '';
    }

    function current_username() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : '';
    }

    // Is the person signed in one of these roles?
    // Accepts a single role or a list: has_role('admin'), has_role(['admin','cashier'])
    function has_role($roles) {
        if (!is_logged_in()) return false;
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array(current_role(), $roles, true);
    }

    // Short forms for the two checks that come up most often
    function is_admin()   { return has_role('admin'); }
    function is_cashier() { return has_role('cashier'); }

    // ------------------------------------------------------------
    // The guards
    // ------------------------------------------------------------

    /**
     * Stop the page unless the visitor holds one of these roles.
     * Anyone else is sent back to the login page.
     *
     * @param string|array $roles  'admin' or ['admin', 'cashier']
     */
    function require_role($roles) {
        if (has_role($roles)) return;
        header('Location: ' . login_url());
        exit();
    }

    /**
     * The path back to the login page from wherever the caller sits.
     * Pages live either in the project root or one folder down
     * (admin/, teacher/, student/, parent/), so it is 'index.php'
     * or '../index.php'.
     */
    function login_url() {
        $root       = realpath(__DIR__);                                  // project root
        $page_folder = realpath(dirname($_SERVER['SCRIPT_FILENAME']));    // folder of the page asking
        return ($page_folder === $root) ? 'index.php' : '../index.php';
    }

    /**
     * The same check for an AJAX endpoint. A browser redirect is no use
     * to JavaScript expecting JSON, so we answer with JSON and stop.
     *
     * @param string|array $roles
     * @param array        $payload  what to send back when refused
     */
    function require_role_json($roles, $payload = null) {
        if (has_role($roles)) return;

        if ($payload === null) {
            $payload = ['success' => false, 'message' => 'Unauthorized'];
        }
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode($payload);
        exit();
    }

    /**
     * For files that are only ever `include`d by another page, never
     * opened in the browser. Without this, loading one directly prints
     * PHP warnings that show the server's folder structure.
     *
     * Put it on the first line: internal_file_only(__FILE__);
     */
    function internal_file_only($file) {
        $requested = realpath($_SERVER['SCRIPT_FILENAME']);
        if ($requested !== false && $requested === realpath($file)) {
            http_response_code(404);
            exit('Not found');
        }
    }

    /**
     * Send an already signed-in person to their own dashboard.
     * Used on the login page so going "back" does not show it again.
     */
    function redirect_to_home_if_logged_in() {
        if (!is_logged_in()) return;
        $home = ROLE_HOME;
        $role = current_role();
        if (isset($home[$role])) {
            header('Location: ' . $home[$role]);
            exit();
        }
    }
}
?>
