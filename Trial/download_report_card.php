<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require('fpdf.php'); // Make sure to include the FPDF library

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
$student_name = isset($_GET['student']) ? urldecode($_GET['student']) : '';

if (!$class_id || !$student_name) {
    die('Invalid parameters');
}

// Get school details with year
$school_query = "SELECT s.school_name, s.motto, s.email, s.badge, t.name, t.year 
                 FROM schools s
                 LEFT JOIN terms t ON s.id = t.school_id AND t.is_current = 1
                 WHERE s.id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_result = $stmt->get_result();
$school_details = $school_result->fetch_assoc();

// Get student details including image
$student_query = "SELECT s.id, s.firstname, s.lastname, c.name, s.stream, s.image
                  FROM students s
                  JOIN classes c ON s.class_id = c.id
                  WHERE CONCAT(s.lastname, ', ', s.firstname) = ? AND s.class_id = ? AND s.school_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("sii", $student_name, $class_id, $school_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student_details = $student_result->fetch_assoc();

if (!$student_details) {
    die('Student not found');
}

// Get all exam types for the school
$exam_types_query = "SELECT DISTINCT exam_type FROM exams WHERE school_id = ? ORDER BY exam_date";
$stmt = $conn->prepare($exam_types_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$exam_types_result = $stmt->get_result();
$exam_types = [];
while ($row = $exam_types_result->fetch_assoc()) {
    $exam_types[] = $row['exam_type'];
}

// Get student's exam results
$results_query = "SELECT s.subject_name, e.exam_type, er.score, e.max_score, 
                         e.exam_date, ANY_VALUE(u.firstname) AS teacher_firstname, 
                         ANY_VALUE(u.lastname) AS teacher_lastname
                  FROM subjects s
                  JOIN exams e ON s.subject_id = e.subject_id
                  LEFT JOIN exam_results er ON e.exam_id = er.exam_id AND er.student_id = ?
                  LEFT JOIN teacher_subjects ts ON s.subject_id = ts.subject_id
                  LEFT JOIN users u ON ts.user_id = u.user_id AND u.role = 'teacher'
                  WHERE e.school_id = ?
                  GROUP BY s.subject_name, e.exam_type, er.score, e.max_score, e.exam_date
                  ORDER BY s.subject_name, e.exam_date";

$stmt = $conn->prepare($results_query);
$stmt->bind_param("ii", $student_details['id'], $school_id);
$stmt->execute();
$results = $stmt->get_result();

// Process results
$exam_results = [];
$subject_totals = [];
$exam_type_max_scores = [];

while ($row = $results->fetch_assoc()) {
    $subject = $row['subject_name'];
    $exam_type = $row['exam_type'];
    
    if (!isset($exam_results[$subject])) {
        $exam_results[$subject] = [
            'teacher' => $row['teacher_firstname'] . ' ' . $row['teacher_lastname'],
            'scores' => []
        ];
    }
    
    $exam_results[$subject]['scores'][$exam_type] = [
        'score' => $row['score'],
        'max_score' => $row['max_score']
    ];
    
    if (!isset($subject_totals[$subject])) {
        $subject_totals[$subject] = ['score' => 0, 'max_score' => 0];
    }
    $subject_totals[$subject]['score'] += $row['score'] ?? 0;
    $subject_totals[$subject]['max_score'] += $row['max_score'];
    
    if (!isset($exam_type_max_scores[$exam_type])) {
        $exam_type_max_scores[$exam_type] = $row['max_score'];
    }
}

// Calculate overall totals and average
$total_score = 0;
$total_max_score = 0;
foreach ($subject_totals as $subject => $totals) {
    $total_score += $totals['score'];
    $total_max_score += $totals['max_score'];
}
$average_percentage = ($total_max_score > 0) ? ($total_score / $total_max_score) * 100 : 0;

// Comment generation functions
function generateClassTeacherComment($average_percentage) {
    if ($average_percentage >= 90) {
        return "Outstanding work. Your dedication and hard work have paid off.";
    } elseif ($average_percentage >= 80) {
        return "Excellent performance. Keep up the good work.";
    } elseif ($average_percentage >= 70) {
        return "Good performance. There's room for improvement.";
    } elseif ($average_percentage >= 60) {
        return "Fair performance. More effort is needed to improve.";
    } elseif ($average_percentage >= 50) {
        return "Needs significant improvement. Please seek extra help.";
    } else {
        return "Performance is very poor. Immediate intervention is required.";
    }
}

function generateHeadTeacherComment($average_percentage) {
    if ($average_percentage >= 90) {
        return "Exceptional academic performance. Keep challenging yourself.";
    } elseif ($average_percentage >= 80) {
        return "Strong academic abilities demonstrated. Continue to strive for excellence.";
    } elseif ($average_percentage >= 70) {
        return "Good overall performance. Focus on areas for improvement.";
    } elseif ($average_percentage >= 60) {
        return "Satisfactory performance. Encourage more consistent study habits.";
    } elseif ($average_percentage >= 50) {
        return "Performance needs improvement. Consider additional academic support.";
    } else {
        return "Serious academic concerns. Immediate parent-teacher meeting recommended.";
    }
}

class PDF extends FPDF
{
    protected $backgroundImage;
    
    function setBackgroundImage($image) {
        $this->backgroundImage = $image;
    }
    
    function Header()
    {
        global $school_details;
        
        // Add background image
        if ($this->backgroundImage && file_exists($this->backgroundImage)) {
            $this->Image($this->backgroundImage, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }
        
        // Reduce header height
        $this->SetFillColor(52, 73, 94); // Dark blue background
        $this->Rect(0, 0, 210, 45, 'F');
        $this->SetTextColor(255, 255, 255); // White text
        
        // School name
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, $school_details['school_name'], 0, 1, 'C');
        
        // Motto
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 8, 'Motto: ' . $school_details['motto'], 0, 1, 'C');
        
        // Email
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, 'Email: ' . $school_details['email'], 0, 1, 'C');
        
        // End of Term Report
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'End of Term Report - ' . $school_details['name'] . ' (' . $school_details['year'] . ')', 0, 1, 'C');
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetTextColor(52, 73, 94); // Dark blue text
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function AddStudentInfo($student_details)
{
    $this->SetTextColor(52, 73, 94); // Dark blue text
    $this->SetFont('Arial', 'B', 12);
    $this->Cell(0, 10, 'Student Information', 0, 1);
    $this->SetFont('Arial', '', 10);

    // Column width and height settings
    $col_width = 70;  // Adjust as needed for left column
    $line_height = 6;
    $image_width = 35; // Width of the student image
    $image_height = 20; // Height of the student image

    // Start position for details and image
    $x_start = $this->GetX();
    $y_start = $this->GetY();

    // Left column: Student details
    $this->Cell(40, $line_height, 'Name:', 0, 0);
    $this->Cell($col_width - 40, $line_height, $student_details['firstname'] . ' ' . $student_details['lastname'], 0, 1);
    $this->Cell(40, $line_height, 'Class:', 0, 0);
    $this->Cell($col_width - 40, $line_height, $student_details['name'], 0, 1);
    $this->Cell(40, $line_height, 'Stream:', 0, 0);
    $this->Cell($col_width - 40, $line_height, $student_details['stream'], 0, 1);

    // Right column: Student image (on the same line)
    $this->SetXY($this->GetPageWidth() - $image_width - 10, $y_start); // Move the cursor to where the image will be

    if (!empty($student_details['image'])) {
        $img_path = 'uploads/' . basename($student_details['image']);
        if (file_exists($img_path)) {
            // Add image to the PDF
            $this->Image($img_path, $this->GetPageWidth() - $image_width - 10, $y_start, $image_width, $image_height);
        } else {
            // If the file doesn't exist, draw a placeholder rectangle
            $this->Rect($this->GetPageWidth() - $image_width - 10, $y_start, $image_width, $image_height);
            $this->SetXY($this->GetPageWidth() - $image_width - 10, $y_start + $image_height / 2 - 5);
            $this->Cell($image_width, 10, 'No Image', 1, 0, 'C');
        }
    }

    // Set Y position after the image (adjust as needed based on image height)
    $this->SetY($y_start + $image_height + 5); // Add some space after the image and student details
}

}

// Create PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->Ln(5); // Add a small space after the header

// Add student information with image
$pdf->AddStudentInfo($student_details);

// Table header
$pdf->SetFillColor(52, 73, 94); // Dark blue background
$pdf->SetTextColor(255, 255, 255); // White text
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 8, 'Subject', 1, 0, 'C', true);

$exam_count = count($exam_types);
for ($i = 1; $i <= $exam_count; $i++) {
    $pdf->Cell(25, 8, 'Score ' . $i, 1, 0, 'C', true);
}

$pdf->Cell(20, 8, 'Final', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Out of 100', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Grade', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Remarks', 1, 1, 'C', true);
// Initialize $total_final_score before the loop
$total_final_score = 0;

// Table content
$pdf->SetTextColor(52, 73, 94); // Dark blue text
$pdf->SetFont('Arial', '', 10);
$total_final_score = 0;
$subject_count = 0;

foreach ($exam_results as $subject => $data) {
    $pdf->Cell(30, 7, $subject, 1, 0, 'L');
    
    $score_sum = 0;
    $score_count = 0;
    $max_possible_sum = 0;
    foreach ($exam_types as $exam_type) {
        $score = $data['scores'][$exam_type]['score'] ?? '-';
        $max_possible = $data['scores'][$exam_type]['max_score'] ?? 0;
        
        // Display the score
        $pdf->Cell(25, 7, $score, 1, 0, 'C');
        
        if (is_numeric($score)) {
            $score_sum += $score;
            $score_count++;
            $max_possible_sum += $max_possible;
        }
    }
    
    // Calculate the average score for this subject
    $subject_average = ($score_count > 0) ? $score_sum / $score_count : 0;
    
    // Calculate the percentage for this subject
    $subject_percentage = ($max_possible_sum > 0) ? ($score_sum / $max_possible_sum) * 100 : 0;
    
    // Add to totals for overall calculation
    $total_final_score += $subject_average;
    $subject_count++;
    
    // Get grade and remarks based on the subject percentage
    $grade = getGrade($subject_percentage);
    $remarks = getRemarks($subject_percentage);
    
    // Display the calculated values
    $pdf->Cell(20, 7, number_format($subject_average, 2), 1, 0, 'C'); // Average score for the subject
    $pdf->Cell(20, 7, number_format($subject_percentage, 2), 1, 0, 'C');
    $pdf->Cell(20, 7, $grade, 1, 0, 'C');
    $pdf->Cell(25, 7, $remarks, 1, 1, 'C');
}

// Calculate overall average
$overall_average = ($subject_count > 0) ? $total_final_score / $subject_count : 0;

// Add total row for Final Score without percentage, grade, and remarks
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30 + (count($exam_types) * 25), 7, 'Average score out of 3', 1, 0, 'R');
$pdf->Cell(20, 7, number_format($overall_average, 2), 1, 1, 'C'); // Display only the overall average

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);


// Adjust the width of the cells
$pdf -> Ln(5);
$pdf->Cell(65, 7, 'Average Percentage:', 0, 0, 'R'); // First cell with text "Average Percentage"
$pdf->Cell(30, 7, number_format($average_percentage, 2) . '%', 0, 0); // Cell for the average percentage value
$pdf->Cell(35, 7, 'Overall Grade:', 0, 0, 'R'); // Cell with text "Overall Grade"
$overall_grade = getGrade($average_percentage);
$pdf->Cell(0, 7, $overall_grade, 0, 1); // Cell for the overall grade


// Teacher Comments
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 7, 'Class Teacher\'s Comment:', 0, 0, 'L'); // Increased width for more space
$pdf->SetFont('Arial', '', 10);
$class_teacher_comment = generateClassTeacherComment($average_percentage, $overall_grade);
$pdf->Cell(0, 7, '  ' . $class_teacher_comment, 0, 1, 'L'); // Add a couple of spaces manually before the comment


$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 7, 'Head Teacher\'s Comment:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$head_teacher_comment = generateHeadTeacherComment($average_percentage, $overall_grade);
$pdf->MultiCell(0, 7, ' ' .$head_teacher_comment, 0, 'L');

$pdf->Ln(2);

// Class Teacher's Signature Line
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 7, 'Head Teacher Signature:', 0, 0, 'L'); // Label for the signature
$pdf->Cell(100, 7, '____________________', 0, 1, 'L'); // Line for the signature

// Title for Grading System
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 7, 'Grading System:', 0, 0, 'L'); // Grading System title on the left
$pdf->Cell(95, 7, 'Points Weight:', 0, 1, 'L'); // Points Weight title on the right

// Set up Grading System table (left)
$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor(230, 230, 230); // Light gray background for the header

// Grading System Row
$pdf->Cell(15, 7, 'A+', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'A', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'B', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'C', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'D', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'E', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'F', 1, 0, 'C', true);

// Move to the right before starting the next table
$pdf->Cell(10); // Adding a small space before the next table

// Points Weight Header Row (right)
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(25, 8, '3.00 - 2.41', 1, 0, 'C', true);
$pdf->Cell(25, 8, '2.40 - 1.51', 1, 0, 'C', true);
$pdf->Cell(25, 8, '1.50 - 0.90', 1, 1, 'C', true);

// Grading System Points Row
$pdf->Cell(15, 7, '100 - 90', 1, 0, 'C');
$pdf->Cell(15, 7, '89 - 80', 1, 0, 'C');
$pdf->Cell(15, 7, '79 - 70', 1, 0, 'C');
$pdf->Cell(15, 7, '69 - 61', 1, 0, 'C');
$pdf->Cell(15, 7, '60 - 51', 1, 0, 'C');
$pdf->Cell(15, 7, '50 - 40', 1, 0, 'C');
$pdf->Cell(15, 7, '39 - 0', 1, 0, 'C');

// Move to the right before starting the next table row
$pdf->Cell(10); // Adding space before the next table

// Points Weight Description Row
$pdf->Cell(25, 8, 'Outstanding', 1, 0, 'C');
$pdf->Cell(25, 8, 'Satisfactory', 1, 0, 'C');
$pdf->Cell(25, 8, 'Moderate', 1, 1, 'C');
      // Points for B

// Output PDF
$pdf->Output('D', 'report_card_' . str_replace(' ', '_', $student_details['firstname'] . '_' . $student_details['lastname']) . '.pdf');


function getGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    if ($percentage >= 40) return 'E';
    return 'F';
}

function getRemarks($percentage) {
    if ($percentage >= 80) return 'Outstanding';
    if ($percentage >= 60) return 'Satisfactory';
    return 'Moderate';
}

?>