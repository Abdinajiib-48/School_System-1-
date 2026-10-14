<?php
// ============================================================
// admin/get_student_details.php - AJAX endpoint - one student's details as JSON.
//
// Who can open it: admin
//
// Answers with JSON, never HTML.
// ============================================================
include '../config.php';

header('Content-Type: application/json');

require_role_json('admin', ['success' => false, 'message' => 'Unauthorized']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

$sql = "SELECT s.*, c.grade as class_name,
        (SELECT AVG(marks_obtained) FROM marks WHERE student_id = s.id) as avg_marks
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.id = $id";

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $student = mysqli_fetch_assoc($result);
    echo json_encode([
        'success' => true,
        'student' => $student
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
}
?>