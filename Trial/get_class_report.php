<?php
// get_class_report.php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sms';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed');
}

$school_id = $_SESSION['school_id'];
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_type = isset($_GET['exam_type']) ? $conn->real_escape_string($_GET['exam_type']) : '';

if (!$class_id || !$exam_type) {
    http_response_code(400);
    exit('Invalid class ID or exam type');
}

// Get class name
$class_query = "SELECT name FROM classes WHERE id = ? AND school_id = ?";
$stmt = $conn->prepare($class_query);
$stmt->bind_param("ii", $class_id, $school_id);
$stmt->execute();
$class_result = $stmt->get_result();
$class_name = $class_result->fetch_assoc()['name'];

// Get class report
$report_query = "SELECT s.firstname, s.lastname, sub.subject_name, e.exam_type, er.score, e.max_score
                 FROM students s
                 JOIN exam_results er ON s.id = er.student_id
                 JOIN exams e ON er.exam_id = e.exam_id
                 JOIN subjects sub ON e.subject_id = sub.subject_id
                 WHERE s.class_id = ? AND s.school_id = ? AND e.exam_type = ?
                 ORDER BY s.lastname, s.firstname, sub.subject_name";

$stmt = $conn->prepare($report_query);
$stmt->bind_param("iis", $class_id, $school_id, $exam_type);
$stmt->execute();
$result = $stmt->get_result();

$report = [];
$subjects = [];
$max_scores = [];

while ($row = $result->fetch_assoc()) {
    $student_name = $row['lastname'] . ', ' . $row['firstname'];
    $subject = $row['subject_name'];
    
    if (!isset($report[$student_name])) {
        $report[$student_name] = [];
    }
    
    if (!in_array($subject, $subjects)) {
        $subjects[] = $subject;
        $max_scores[$subject] = $row['max_score'];
    }
    
    $report[$student_name][$subject] = $row['score'];
}

// Calculate totals and averages
foreach ($report as $student => $scores) {
    $total = 0;
    $count = 0;
    foreach ($subjects as $subject) {
        if (isset($scores[$subject])) {
            $total += ($scores[$subject] / $max_scores[$subject]) * 100; // Convert to percentage
            $count++;
        }
    }
    $average = $count > 0 ? $total / $count : 0;
    $report[$student]['total'] = round($total, 2);
    $report[$student]['average'] = round($average, 2);
}

// Sort students by average score (descending)
arsort($report);

echo json_encode([
    'class_name' => $class_name,
    'exam_type' => $exam_type,
    'report' => $report,
    'subjects' => $subjects,
    'max_scores' => $max_scores
]);
?>