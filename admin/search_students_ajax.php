<?php
// ============================================================
// admin/search_students_ajax.php - AJAX endpoint - live student search as you type.
//
// Who can open it: admin
//
// Answers with JSON, never HTML.
// ============================================================
include '../config.php';

header('Content-Type: application/json');

require_role_json('admin', ['students' => [], 'total' => 0]);

$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';

if (!empty($search)) {
    $sql = "SELECT s.id, s.student_id, s.name, s.gender, s.parent_phone, s.address, c.grade as class_name,
            (SELECT AVG(marks_obtained) FROM marks WHERE student_id = s.id) as avg_marks
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id 
            WHERE s.student_id LIKE '%$search%' 
               OR s.name LIKE '%$search%' 
               OR s.parent_phone LIKE '%$search%'
            ORDER BY s.name";
} else {
    $sql = "SELECT s.id, s.student_id, s.name, s.gender, s.parent_phone, s.address, c.grade as class_name,
            (SELECT AVG(marks_obtained) FROM marks WHERE student_id = s.id) as avg_marks
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id 
            ORDER BY s.name";
}

$result = mysqli_query($conn, $sql);
$students = [];

while($row = mysqli_fetch_assoc($result)) {
    $students[] = $row;
}

echo json_encode([
    'students' => $students,
    'total' => count($students)
]);
?>