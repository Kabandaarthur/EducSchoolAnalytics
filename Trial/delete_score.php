<?php
session_start();

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sms';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$studentId = $data['studentId'];
$classId = $data['classId'];
$subjectId = $data['subjectId'];
$examType = $data['examType'];

// Get the exam_id
$query = "SELECT exam_id FROM exams WHERE exam_type = ? AND subject_id = ? AND school_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $examType, $subjectId, $_SESSION['school_id']);
$stmt->execute();
$result = $stmt->get_result();
$exam = $result->fetch_assoc();
$examId = $exam['exam_id'];

// Delete the score
$query = "DELETE FROM exam_results WHERE exam_id = ? AND student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $examId, $studentId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete score']);
}

$conn->close();
?>