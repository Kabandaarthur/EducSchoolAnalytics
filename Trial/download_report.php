<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$class_id = $_GET['class_id'] ?? null;
$format = $_GET['format'] ?? 'csv';
$exam_type = $_GET['exam_type'] ?? '';

if (!$class_id || !$exam_type) {
    die("Missing required parameters");
}

// Include database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sms';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$school_id = $_SESSION['school_id'];

// Function to get class report
function getClassReport($conn, $class_id, $school_id, $exam_type) {
    $report_query = "SELECT s.firstname, s.lastname, sub.subject_name, er.score
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
    while ($row = $result->fetch_assoc()) {
        $student_name = $row['lastname'] . ', ' . $row['firstname'];
        if (!isset($report[$student_name])) {
            $report[$student_name] = [];
        }
        $report[$student_name][$row['subject_name']] = $row['score'];
        if (!in_array($row['subject_name'], $subjects)) {
            $subjects[] = $row['subject_name'];
        }
    }
    
    return ['report' => $report, 'subjects' => $subjects];
}

// Fetch report data
$report_data = getClassReport($conn, $class_id, $school_id, $exam_type);

if ($format === 'csv') {
    // Generate CSV file
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="class_report.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    $headers = array_merge(['Student'], $report_data['subjects']);
    fputcsv($output, $headers);
    
    // Add data
    foreach ($report_data['report'] as $student => $scores) {
        $row = [$student];
        foreach ($report_data['subjects'] as $subject) {
            $row[] = $scores[$subject] ?? '-';
        }
        fputcsv($output, $row);
    }
    
    fclose($output);
} 
elseif ($format === 'pdf') {
    // Generate PDF file
    require('fpdf.php');

    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Helvetica', 'B', 15);
            $this->Cell(0, 10, 'Class Report - ' . $GLOBALS['exam_type'], 0, 1, 'C');
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF('L', 'mm', 'A4'); // Set to Landscape
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 10); // Smaller font size to fit more subjects

    // Calculate column widths
    $pageWidth = $pdf->GetPageWidth() - 20; // 10mm margin on each side
    $studentColumnWidth = 50;
    $subjectCount = count($report_data['subjects']);
    $subjectColumnWidth = ($pageWidth - $studentColumnWidth) / $subjectCount;

    // Add table headers
    $pdf->SetFillColor(200, 220, 255);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell($studentColumnWidth, 10, 'Student', 1, 0, 'L', true);
    foreach ($report_data['subjects'] as $subject) {
        $pdf->Cell($subjectColumnWidth, 10, $subject, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Add data
    $pdf->SetFont('Helvetica', '', 10);
    $fill = false;
    foreach ($report_data['report'] as $student => $scores) {
        $pdf->Cell($studentColumnWidth, 10, $student, 1, 0, 'L', $fill);
        foreach ($report_data['subjects'] as $subject) {
            $score = $scores[$subject] ?? '-';
            $pdf->Cell($subjectColumnWidth, 10, $score, 1, 0, 'C', $fill);
        }
        $pdf->Ln();
        $fill = !$fill; // Alternate row colors
    }

    $pdf->Output('D', 'class_report.pdf');
} else {
    die("Invalid format specified");
}


$conn->close();