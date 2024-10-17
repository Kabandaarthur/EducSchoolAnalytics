<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sms';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$school_id = $_SESSION['school_id'];
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$exam_type = isset($_GET['exam_type']) ? $conn->real_escape_string($_GET['exam_type']) : '';
$student_name = isset($_GET['student']) ? urldecode($_GET['student']) : '';

if (!$class_id || !$exam_type || !$student_name) {
    die('Invalid parameters');
}

// Get school details
$school_query = "SELECT school_name FROM schools WHERE id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_result = $stmt->get_result();
$school_details = $school_result->fetch_assoc();

// Get student details
$student_query = "SELECT students.id, firstname, lastname, name FROM students 
JOIN classes ON students.class_id = classes.id 
WHERE CONCAT(lastname, ', ', firstname) = ? AND students.class_id = ? AND students.school_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("sii", $student_name, $class_id, $school_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student_details = $student_result->fetch_assoc();

if (!$student_details) {
    die('Student not found');
}

// Get student's exam results
$results_query = "SELECT sub.subject_name, e.exam_type, er.score, e.max_score
                  FROM exam_results er
                  JOIN exams e ON er.exam_id = e.exam_id
                  JOIN subjects sub ON e.subject_id = sub.subject_id
                  WHERE er.student_id = ? AND e.exam_type = ?
                  ORDER BY sub.subject_name";

$stmt = $conn->prepare($results_query);
$stmt->bind_param("is", $student_details['id'], $exam_type);
$stmt->execute();
$results = $stmt->get_result();

// Calculate total and average
$total_score = 0;
$total_max_score = 0;
$subject_count = 0;

$exam_results = [];
while ($row = $results->fetch_assoc()) {
    $exam_results[] = $row;
    $total_score += $row['score'];
    $total_max_score += $row['max_score'];
    $subject_count++;
}

$average_percentage = ($total_score / $total_max_score) * 100;

function getGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    return 'F';
}

function getRemarks($grade) {
    switch ($grade) {
        case 'A+':
        case 'A':
            return 'Excellent';
        case 'B':
            return 'Good';
        case 'C':
            return 'Average';
        case 'D':
            return 'Fair';
        default:
            return 'Poor';
    }
}

// Generate HTML for the report card
$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report Card</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 80%;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .student-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .student-info p {
            margin: 5px 0;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #2c3e50;
            color: #fff;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .summary {
            font-weight: bold;
            margin-top: 20px;
            text-align: right;
        }
        .grade {
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($school_details['school_name']) . '</h1>
            <h2>Student Report Card</h2>
        </div>
        
        <div class="student-info">
            <p><strong>Name:</strong> ' . htmlspecialchars($student_details['firstname'] . ' ' . $student_details['lastname']) . '</p>
            <p><strong>Class:</strong> ' . htmlspecialchars($student_details['name']) . '</p>
            <p><strong>Exam Type:</strong> ' . htmlspecialchars($exam_type) . '</p>
        </div>
        
        <table>
            <tr>
                <th>Subject</th>
                <th>Score</th>
                <th>Converted Score (out of 100)</th>
                <th>Grade</th>
                <th>Remarks</th>
            </tr>';

foreach ($exam_results as $result) {
    $percentage = ($result['score'] / $result['max_score']) * 100;
    $grade = getGrade($percentage);
    $remarks = getRemarks($grade);
    
    // Calculate the converted score out of 100
    $converted_score = ($result['score'] / $result['max_score']) * 100;

    $html .= '
            <tr>
                <td>' . htmlspecialchars($result['subject_name']) . '</td>
                <td>' . $result['score'] . '</td>
                <td>' . number_format($converted_score, 2) . '</td> <!-- Display converted score -->
                <td class="grade">' . $grade . '</td>
                <td>' . $remarks . '</td>
            </tr>';
}

$overall_grade = getGrade($average_percentage);
$overall_remarks = getRemarks($overall_grade);

$html .= '
        </table>
        
        <div class="summary">
            <p>Total Score: ' . $total_score . '</p> <!-- Removed max score -->
            <p>Average Percentage: ' . number_format($average_percentage, 2) . '%</p>
            <p>Overall Grade: <span class="grade">' . $overall_grade . '</span></p>
            <p>Overall Remarks: ' . $overall_remarks . '</p>
        </div>
    </div>
</body>
</html>';

// Output the HTML
echo $html;

// Optionally, you can add a download button
echo '
<div style="text-align: center; margin-top: 20px;">
    <a href="download_report_card.php?class_id=' . $class_id . '&exam_type=' . urlencode($exam_type) . '&student=' . urlencode($student_name) . '" style="background-color: #2c3e50; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;" target="_blank">Download PDF</a>
</div>';
?>