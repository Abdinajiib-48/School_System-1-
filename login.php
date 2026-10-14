<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: index.php');
    exit();
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Keep what they typed so the form can fill the username back in
$_SESSION['login_username'] = $username;

// ------------------------------------------------------------
// Basic checks
// ------------------------------------------------------------
if ($username === '' || $password === '') {
    header('Location: index.php?error=empty_fields');
    exit();
}

if (strlen($password) < 4) {
    header('Location: index.php?error=password_length');
    exit();
}

// ------------------------------------------------------------
// Find the account.
//
// users.username is UNIQUE, so one username can only ever belong to
// one person with one role. That is why the login page no longer asks
// "log in as..." - the account itself already knows what it is, and
// asking the person to repeat it only created a way to get it wrong.
// ------------------------------------------------------------
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);

// One message for "no such user" and "wrong password" on purpose -
// telling a stranger which usernames exist helps nobody but them.
if (!$user || $password != $user['password']) {
    header('Location: index.php?error=invalid_credentials');
    exit();
}

$role = $user['role'];

// ---- Login success ----
session_regenerate_id(true);          // new session id, so a stolen one is useless
$_SESSION['user_id']    = $user['id'];
$_SESSION['username']   = $user['username'];
$_SESSION['role']       = $role;
$_SESSION['login_time'] = date('Y-m-d H:i:s');
unset($_SESSION['login_username']);   // signed in, no need to remember it

// Student: load student record
if ($role === 'student') {
    $uid      = (int)$user['id'];
    $stu_stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE user_id = ?");
    mysqli_stmt_bind_param($stu_stmt, 'i', $uid);
    mysqli_stmt_execute($stu_stmt);
    $stu_result = mysqli_stmt_get_result($stu_stmt);

    if (mysqli_num_rows($stu_result) === 0) {
        session_unset();
        header('Location: index.php?error=student_not_found');
        exit();
    }

    $stu = mysqli_fetch_assoc($stu_result);
    $_SESSION['student_id']   = $stu['id'];
    $_SESSION['student_name'] = $stu['name'];
}

// Parent: load parent record
if ($role === 'parent') {
    $uid      = (int)$user['id'];
    $par_stmt = mysqli_prepare($conn, "SELECT * FROM parents WHERE user_id = ?");
    mysqli_stmt_bind_param($par_stmt, 'i', $uid);
    mysqli_stmt_execute($par_stmt);
    $par_result = mysqli_stmt_get_result($par_stmt);

    if (mysqli_num_rows($par_result) === 0) {
        session_unset();
        header('Location: index.php?error=parent_not_found');
        exit();
    }

    $par = mysqli_fetch_assoc($par_result);
    $_SESSION['parent_id']   = $par['id'];
    $_SESSION['parent_name'] = $par['name'];
}

// ------------------------------------------------------------
// Straight to the right dashboard for this account
// ------------------------------------------------------------
$home = [
    'admin'   => 'admin/dashboard.php',
    'teacher' => 'teacher/dashboard.php',
    'student' => 'student/dashboard.php',
    'parent'  => 'parent/dashboard.php',
    'cashier' => 'admin/finance.php',
];

header('Location: ' . (isset($home[$role]) ? $home[$role] : 'index.php?error=unknown_role'));
exit();
?>
