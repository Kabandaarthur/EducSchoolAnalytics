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

// Fetch all classes for the school
$classes_query = "SELECT id, name FROM classes WHERE school_id = ? ORDER BY name";
$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$classes_result = $stmt->get_result();

//Fetch exam_type
$exam_types_query = "SELECT DISTINCT exam_type FROM exams WHERE school_id = ? ORDER BY exam_type";
$stmt = $conn->prepare($exam_types_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$exam_types_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            line-height: 1.6;
            background-color: #f8f9fa;
            color: #333;
        }
        .container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        h1, h2, h3 {
            color: #007bff;
            margin-bottom: 20px;
        }
        .student-name {
            font-weight: bold;
        }
        .selection-container {
            background-color: #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .class-list, .exam-type-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .class-button, .exam-type-button {
            padding: 10px 15px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        .class-button:hover, .exam-type-button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .class-button.selected, .exam-type-button.selected {
            background-color: #28a745;
        }
        .exam-type-container {
            margin-top: 20px;
            display: none;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            max-width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
        }
        .table th,
        .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }
        .table tbody + tbody {
            border-top: 2px solid #dee2e6;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .download-links {
            margin: 20px 0;
        }
        .download-link {
            margin-right: 15px;
            text-decoration: none;
            padding: 8px 15px;
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            transition: all 0.3s ease;
            display: inline-block;
            margin-bottom: 10px;
        }
        .download-link:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .individual-report-btn {
            margin-top: 10px;
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .individual-report-btn:hover {
            background-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        @media (max-width: 768px) {
            .class-list, .exam-type-list {
                flex-direction: column;
            }
            .class-button, .exam-type-button {
                width: 100%;
                margin-bottom: 10px;
            }
            .container {
                padding: 15px;
            }
        }
        .table-container {
            margin-top: 2rem;
            overflow-x: auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: #fff;
        }
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            vertical-align: middle;
            border: none;
        }
        .table thead th {
            background-color: #007bff;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table thead th:first-child {
            border-top-left-radius: 8px;
        }
        .table thead th:last-child {
            border-top-right-radius: 8px;
        }
        .table tbody tr:nth-of-type(even) {
            background-color: #f8f9fa;
        }
        .table tbody tr:hover {
            background-color: #e9ecef;
        }
        .table tbody td {
            border-bottom: 1px solid #dee2e6;
        }
        .student-name {
            font-weight: 600;
            color: #495057;
        }
        .score-cell {
            text-align: center;
        }
        .total-cell, .average-cell {
            font-weight: 600;
            color: #28a745;
        }
        .individual-report-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            background-color: #17a2b8;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .individual-report-btn:hover {
            background-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4 text-center">View Reports - Admin Dashboard</h1>
        
        <div class="selection-container">
            <h2>Select Class</h2>
            <div class="class-list">
                <?php while ($class = $classes_result->fetch_assoc()): ?>
                    <button class="class-button" onclick="selectClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['name']); ?>', this)">
                        <i class="fas fa-chalkboard-teacher me-2"></i><?php echo htmlspecialchars($class['name']); ?>
                    </button>
                <?php endwhile; ?>
            </div>
            
            <div id="exam-type-container" class="exam-type-container">
                <h3>Select Exam Type</h3>
                <div class="exam-type-list">
                    <?php while ($exam_type = $exam_types_result->fetch_assoc()): ?>
                        <button class="exam-type-button" onclick="selectExamType('<?php echo htmlspecialchars($exam_type['exam_type']); ?>', this)">
                            <i class="fas fa-file-alt me-2"></i><?php echo htmlspecialchars($exam_type['exam_type']); ?>
                        </button>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        
        <div id="report-container"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let selectedClassId, selectedClassName, selectedExamType;

    function selectClass(classId, className, button) {
        selectedClassId = classId;
        selectedClassName = className;
        document.querySelectorAll('.class-button').forEach(btn => btn.classList.remove('selected'));
        button.classList.add('selected');
        document.getElementById('exam-type-container').style.display = 'block';
        document.querySelectorAll('.exam-type-button').forEach(btn => btn.classList.remove('selected'));
        document.getElementById('report-container').innerHTML = '';
    }

    function selectExamType(examType, button) {
        selectedExamType = examType;
        document.querySelectorAll('.exam-type-button').forEach(btn => btn.classList.remove('selected'));
        button.classList.add('selected');
        viewClassReport();
    }

    function viewClassReport() {
        fetch(`get_class_report.php?class_id=${selectedClassId}&exam_type=${encodeURIComponent(selectedExamType)}`)
            .then(response => response.json())
            .then(data => {
                const reportContainer = document.getElementById('report-container');
                let reportHtml = `
                    <h2 class="mt-4">Class Report: ${selectedClassName}</h2>
                    <h3>${selectedExamType} Exam Results</h3>
                    
                    <div class="download-links">
                        <a href="download_report.php?class_id=${selectedClassId}&format=csv&exam_type=${encodeURIComponent(selectedExamType)}" class="download-link">
                            <i class="fas fa-file-csv me-2"></i>Download CSV
                        </a>
                        <a href="download_report.php?class_id=${selectedClassId}&format=pdf&exam_type=${encodeURIComponent(selectedExamType)}" class="download-link">
                            <i class="fas fa-file-pdf me-2"></i>Download PDF
                        </a>
                    </div>
                    
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    ${data.subjects.map(subject => `<th>${subject}</th>`).join('')}
                                    <th>Total</th>
                                    <th>Average</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                for (const [student, scores] of Object.entries(data.report)) {
                    const studentName = student.replace(/,/g, '');
                    reportHtml += `
                        <tr>
                            <td class="student-name">${studentName}</td>
                            ${data.subjects.map(subject => `<td class="score-cell">${scores[subject] || '-'}</td>`).join('')}
                            <td class="total-cell">${scores.total}</td>
                            <td class="average-cell">${scores.average}</td>
                            <td>
                                <button class="individual-report-btn" onclick="viewIndividualReport('${encodeURIComponent(student)}')">
                                    <i class="fas fa-user me-2"></i>View Report Card
                                </button>
                            </td>
                        </tr>
                    `;
                }
                
                reportHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                reportContainer.innerHTML = reportHtml;
            })
            .catch(error => {
                console.error('Error fetching class report:', error);
                document.getElementById('report-container').innerHTML = '<p class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading report. Please try again.</p>';
            });
    }
    

    function viewIndividualReport(studentName) {
        const url = `generate_report_card.php?class_id=${selectedClassId}&exam_type=${encodeURIComponent(selectedExamType)}&student=${studentName}`;
        window.open(url, '_blank');
    }
    </script>
</body>
</html>