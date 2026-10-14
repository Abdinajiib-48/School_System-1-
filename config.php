<?php
// ============================================================
// config.php - the first file every page loads.
//
// It does four things:
//   1. connects to the database  ->  $conn
//   2. starts the session        ->  $_SESSION
//   3. decides which PHP errors are shown
//   4. loads auth.php, so require_role() is ready everywhere
//
// Every page in this project begins with:
//     include '../config.php';        (or 'config.php' in the root)
// ============================================================

// ------------------------------------------------------------
// 1. Database
// ------------------------------------------------------------
$host     = 'localhost';
$user     = 'root';
$password = '';
$database = 'School_db1';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

// utf8mb4 (not plain utf8) so Somali/Amharic letters and emoji are stored whole
mysqli_set_charset($conn, 'utf8mb4');

// ------------------------------------------------------------
// 2. Session
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------
// 3. Errors
//
// Real errors are shown, because this runs on a school's own XAMPP
// machine and the admin needs to see what broke.
//
// Deprecation and notice messages are hidden ON PURPOSE. PHP prints
// them in the middle of the page - and when that happens inside a
// form's value="..." the warning text gets submitted and saved into
// the database. That really happened here once.
//
// Before putting this online, change display_errors to 0 and read
// the messages from the PHP error log instead.
// ------------------------------------------------------------
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);

// ------------------------------------------------------------
// 4. Who is allowed where - require_role(), is_admin(), ...
// ------------------------------------------------------------
include_once __DIR__ . '/auth.php';

// ------------------------------------------------------------
// Small shared validators
// ------------------------------------------------------------

/**
 * Clean a value that is about to be put straight into an SQL string.
 *
 * NOTE: this also runs htmlspecialchars(), so the value is stored
 * already escaped for HTML. Prefer a prepared statement for new code
 * (see login.php) - this is kept because older pages rely on it.
 */
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data ?? '');
    return mysqli_real_escape_string($conn, $data);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

// Student IDs look like ABC001/16 - letters, digits, slash, dash
function validateStudentId($student_id) {
    return preg_match('/^[A-Z0-9\/\-]+$/', $student_id);
}

function validateMarks($marks) {
    return is_numeric($marks) && $marks >= 0 && $marks <= 100;
}

function validateName($name) {
    return preg_match('/^[a-zA-Z\s\-]+$/', $name);
}
?>
