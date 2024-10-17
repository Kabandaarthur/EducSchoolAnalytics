<?php
// Start session to access session variables
session_start();

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection details
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sms';

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get teacher ID and school ID from session
$teacher_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];

// Check if exam_id is provided
if (!isset($_GET['exam_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Exam ID is required']);
    exit();
}

$exam_id = intval($_GET['exam_id']);

// Verify that the exam belongs to the teacher's school and subject
$verify_query = "SELECT e.id 
                 FROM exams e
                 JOIN subjects s ON e.subject_id = s.subject_id
                 JOIN teacher_subjects ts ON s.subject_id = ts.subject_id
                 WHERE e.id = ? AND e.school_id = ? AND ts.user_id = ?";
$stmt_verify = $conn->prepare($verify_query);
$stmt_verify->bind_param("iii", $exam_id, $school_id, $teacher_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();

if ($result_verify->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access to this exam']);
    exit();
}

// Fetch exam details and student results
$query = "SELECT e.id as exam_id, e.name as exam_name, e.max_score, 
                 er.id as exam_result_id, s.id as student_id, 
                 CONCAT(s.firstname, ' ', s.lastname) AS student_name, er.score
          FROM exams e
          LEFT JOIN exam_results er ON e.id = er.exam_id
          LEFT JOIN students s ON er.student_id = s.id
          WHERE e.id = ? AND e.school_id = ?
          ORDER BY s.lastname, s.firstname";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $exam_id, $school_id);
$stmt->execute();
$result = $stmt->get_result();

$exam_info = null;
$students = [];

while ($row = $result->fetch_assoc()) {
    if ($exam_info === null) {
        $exam_info = [
            'exam_id' => $row['exam_id'],
            'exam_name' => $row['exam_name'],
            'max_score' => $row['max_score']
        ];
    }
    
    if ($row['student_id'] !== null) {
        $students[] = [
            'student_id' => $row['student_id'],
            'student_name' => $row['student_name'],
            'exam_result_id' => $row['exam_result_id'],
            'score' => $row['score']
        ];
    }
}

// Prepare the response
$response = [
    'exam_info' => $exam_info,
    'students' => $students
];

// Set the response header to JSON
header('Content-Type: application/json');

// Output the JSON-encoded response
echo json_encode($response);

// Close the database connection
$conn->close();
?>