<?php
// ============================================================
// teacher/get_teacher_subjects.php - AJAX endpoint - how many subjects a teacher takes.
//
// Who can open it: teacher
//
// Answers with JSON, never HTML.
// ============================================================
include '../config.php';

header('Content-Type: application/json');

require_role_json('teacher', ['count' => 0]);

$user_id = $_SESSION['user_id'];
$sql = "SELECT t.id FROM teachers t WHERE t.user_id = '$user_id'";
$result = mysqli_query($conn, $sql);
$teacher = mysqli_fetch_assoc($result);

if ($teacher) {
            include_once '../subjects_helper.php';
    echo json_encode(['count' => count(teacher_subjects($conn, $teacher['id']))]);
} else {
    echo json_encode(['count' => 0]);
}
?>