<?php
session_start();

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
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

$teacher_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];

// Fetch teacher's information along with the school name
$query_teacher = "SELECT CONCAT(u.firstname, ' ', u.lastname) AS teacher_name, s.school_name 
                  FROM users u
                  JOIN schools s ON u.school_id = s.id 
                  WHERE u.user_id = ?";
$stmt_teacher = $conn->prepare($query_teacher);
$stmt_teacher->bind_param("i", $teacher_id);
$stmt_teacher->execute();
$result_teacher = $stmt_teacher->get_result();
$teacher_info = $result_teacher->fetch_assoc();

$teacher_name = $teacher_info['teacher_name'];
$school_name = $teacher_info['school_name'];

// Fetch distinct classes for the teacher
$query = "SELECT DISTINCT c.id as class_id, c.name as class_name
           FROM teacher_subjects ts
           JOIN classes c ON ts.class_id = c.id
           WHERE ts.user_id = ? AND c.school_id = ?
           ORDER BY c.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $teacher_id, $school_id);
$stmt->execute();
$result = $stmt->get_result();
$classes = $result->fetch_all(MYSQLI_ASSOC);

// Fetch exam types
$query_exam_types = "SELECT DISTINCT exam_type FROM exams WHERE school_id = ?";
$stmt_exam_types = $conn->prepare($query_exam_types);
$stmt_exam_types->bind_param("i", $school_id);
$stmt_exam_types->execute();
$result_exam_types = $stmt_exam_types->get_result();
$exam_types = $result_exam_types->fetch_all(MYSQLI_ASSOC);

// Handle AJAX requests
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_subjects':
            $class_id = $_GET['class_id'];
            $query = "SELECT s.subject_id, s.subject_name
                      FROM teacher_subjects ts
                      JOIN subjects s ON ts.subject_id = s.subject_id
                      WHERE ts.user_id = ? AND ts.class_id = ? AND s.school_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $teacher_id, $class_id, $school_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            exit;

        case 'get_students':
            $class_id = $_GET['class_id'];
            $query = "SELECT id, CONCAT(firstname, ' ', lastname) AS name 
                      FROM students 
                      WHERE class_id = ? AND school_id = ?
                      ORDER BY lastname, firstname";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $class_id, $school_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            exit;

        case 'generate_template':
            $class_id = $_GET['class_id'];
            $subject_id = $_GET['subject_id'];
            $exam_type = $_GET['exam_type'];
            $max_score = $_GET['max_score'];

            // Fetch students
            $query = "SELECT id, CONCAT(firstname, ' ', lastname) AS name 
                      FROM students 
                      WHERE class_id = ? AND school_id = ?
                      ORDER BY lastname, firstname";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $class_id, $school_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $students = $result->fetch_all(MYSQLI_ASSOC);

            // Generate CSV template
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="marks_template.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Student ID', 'Student Name', 'Score (Max: ' . $max_score . ')']);
            
            foreach ($students as $student) {
                fputcsv($output, [$student['id'], $student['name'], '']);
            }
            
            fclose($output);
            exit;

        case 'get_exam_results':
            $class_id = $_GET['class_id'];
            $subject_id = $_GET['subject_id'];
            $exam_type = $_GET['exam_type'];

            $query = "SELECT s.id, CONCAT(s.firstname, ' ', s.lastname) AS student_name, er.score, e.max_score
                      FROM students s
                      LEFT JOIN exam_results er ON s.id = er.student_id
                      LEFT JOIN exams e ON er.exam_id = e.id
                      WHERE s.class_id = ? AND e.subject_id = ? AND e.exam_type = ? AND s.school_id = ?
                      ORDER BY s.lastname, s.firstname";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiis", $class_id, $subject_id, $exam_type, $school_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            exit;
    }
}

// Handle the CSV file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_marks'])) {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $exam_type = $_POST['exam_type'];
    $max_score = floatval($_POST['max_score']);

    // Handle file upload
    if (isset($_FILES['marks_file']) && $_FILES['marks_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['marks_file']['tmp_name'];

        // Check if an exam entry already exists
        $check_exam_query = "SELECT id FROM exams WHERE exam_type = ? AND subject_id = ? AND school_id = ?";
        $stmt_check_exam = $conn->prepare($check_exam_query);
        $stmt_check_exam->bind_param("sii", $exam_type, $subject_id, $school_id);
        $stmt_check_exam->execute();
        $result_check_exam = $stmt_check_exam->get_result();

        if ($result_check_exam->num_rows > 0) {
            // Update existing exam
            $exam_row = $result_check_exam->fetch_assoc();
            $exam_id = $exam_row['id'];
            $update_exam_query = "UPDATE exams SET max_score = ? WHERE id = ?";
            $stmt_update_exam = $conn->prepare($update_exam_query);
            $stmt_update_exam->bind_param("di", $max_score, $exam_id);
            $stmt_update_exam->execute();
        } else {
            // Create a new exam entry
            $insert_exam_query = "INSERT INTO exams (name, exam_type, exam_date, subject_id, school_id, max_score) 
                                  VALUES (?, ?, CURDATE(), ?, ?, ?)";
            $stmt_insert_exam = $conn->prepare($insert_exam_query);
            $exam_name = $exam_type . " - " . date("Y-m-d");
            $stmt_insert_exam->bind_param("ssiid", $exam_name, $exam_type, $subject_id, $school_id, $max_score);
            $stmt_insert_exam->execute();
            $exam_id = $stmt_insert_exam->insert_id;
        }

        $insert_query = "INSERT INTO exam_results (exam_id, school_id, student_id, subject_id, score, upload_date) 
                         VALUES (?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE score = VALUES(score), upload_date = NOW()";
        $stmt_insert = $conn->prepare($insert_query);

        $success = true;
        if (($handle = fopen($file, "r")) !== FALSE) {
            // Skip the header row
            fgetcsv($handle, 1000, ",");
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $student_id = $data[0];
                $score = floatval($data[2]);

                $stmt_insert->bind_param("iiiid", $exam_id, $school_id, $student_id, $subject_id, $score);
                if (!$stmt_insert->execute()) {
                    $success = false;
                    break;
                }
            }
            fclose($handle);
        }

        if ($success) {
            $message = '<div class="success-message">Marks uploaded successfully!</div>';
        } else {
            $message = '<div class="error-message">Failed to upload marks!</div>';
        }
    } else {
        $message = '<div class="error-message">Error uploading file!</div>';
    }
}

// Handle direct mark input
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enter_marks'])) {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $exam_type = $_POST['exam_type'];
    $max_score = floatval($_POST['max_score']);

    // Check if an exam entry already exists
    $check_exam_query = "SELECT id FROM exams WHERE exam_type = ? AND subject_id = ? AND school_id = ?";
    $stmt_check_exam = $conn->prepare($check_exam_query);
    $stmt_check_exam->bind_param("sii", $exam_type, $subject_id, $school_id);
    $stmt_check_exam->execute();
    $result_check_exam = $stmt_check_exam->get_result();

    if ($result_check_exam->num_rows > 0) {
        // Update existing exam
        $exam_row = $result_check_exam->fetch_assoc();
        $exam_id = $exam_row['id'];
        $update_exam_query = "UPDATE exams SET max_score = ? WHERE id = ?";
        $stmt_update_exam = $conn->prepare($update_exam_query);
        $stmt_update_exam->bind_param("di", $max_score, $exam_id);
        $stmt_update_exam->execute();
    } else {
        // Create a new exam entry
        $insert_exam_query = "INSERT INTO exams (name, exam_type, exam_date, subject_id, school_id, max_score) 
                              VALUES (?, ?, CURDATE(), ?, ?, ?)";
        $stmt_insert_exam = $conn->prepare($insert_exam_query);
        $exam_name = $exam_type . " - " . date("Y-m-d");
        $stmt_insert_exam->bind_param("ssiid", $exam_name, $exam_type, $subject_id, $school_id, $max_score);
        $stmt_insert_exam->execute();
        $exam_id = $stmt_insert_exam->insert_id;
    }

    $insert_query = "INSERT INTO exam_results (exam_id, school_id, student_id, subject_id, score, upload_date) 
                     VALUES (?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE score = VALUES(score), upload_date = NOW()";
    $stmt_insert = $conn->prepare($insert_query);

    $success = true;
    foreach ($_POST['students'] as $student_id => $score) {
        $score = floatval($score);
        if ($score <= $max_score) {
            $stmt_insert->bind_param("iiiid", $exam_id, $school_id, $student_id, $subject_id, $score);
            if (!$stmt_insert->execute()) {
                $success = false;
                break;
            }
        }
    }

    if ($success) {
        $message = '<div class="success-message">Marks entered successfully!</div>';
    } else {
        $message = '<div class="error-message">Failed to enter marks!</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <style>
      :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --background-color: #f4f4f4;
            --text-color: #333;
            --border-color: #ddd;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--background-color);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .teacher-info {
            text-align: right;
        }

        h1, h2 {
            color: var(--primary-color);
        }

        h1 {
            margin: 0;
        }

        .dashboard-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            padding: 30px;
        }

        form {
            display: grid;
            gap: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--primary-color);
        }

        select, input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 16px;
        }

        select:focus, input[type="number"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        button {
            background-color: var(--secondary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #27ae60;
        }

        button:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }

        .success-message, .error-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .student-list {
            margin-top: 30px;
        }

        .student-list table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .student-list th, .student-list td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .student-list th {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }

        .student-list tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .student-list tr:hover {
            background-color: #e9ecef;
        }

        .file-upload {
            margin-top: 20px;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload label {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .file-upload label:hover {
            background-color: #2980b9;
        }

        .file-name {
            margin-top: 10px;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .dashboard-section {
                padding: 20px;
            }

            button {
                width: 100%;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .teacher-info {
                text-align: center;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <h1>Teacher Dashboard</h1>
            <div class="teacher-info">
                <p>Welcome, Teacher - <?php echo htmlspecialchars($teacher_name); ?></p>
                <p><?php echo htmlspecialchars($school_name); ?></p>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (isset($message)) echo $message; ?>

        <div class="dashboard-section">
            <h2>Upload Marks</h2>
            <form method="post" enctype="multipart/form-data" id="marksForm">
                <div>
                    <label for="class_id">Select Class:</label>
                    <select id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="subject_id">Select Subject:</label>
                    <select id="subject_id" name="subject_id" required disabled></select>
                </div>

                <div>
                    <label for="exam_type">Select Exam Type:</label>
                    <select id="exam_type" name="exam_type" required>
                        <option value="">Select Exam Type</option>
                        <?php foreach ($exam_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['exam_type']); ?>"><?php echo htmlspecialchars($type['exam_type']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="max_score">Max Score:</label>
                    <select id="max_score" name="max_score" required>
                        <option value="">Select Max Score</option>
                        <option value="3">3</option>
                        <option value="25">25</option>
                        <option value="100">100</option>
                    </select>
                </div>

                <button type="button" id="generateTemplateBtn" disabled>Generate CSV Template</button>

                <div class="file-upload">
                    <label for="marks_file">Choose CSV File</label>
                    <input type="file" id="marks_file" name="marks_file" accept=".csv" required>
                    <div class="file-name" id="file-name"></div>
                </div>

                <button type="submit" name="upload_marks" id="uploadMarksBtn" disabled>Upload Marks</button>
            </form>
        </div>

        <div class="dashboard-section">
            <h2>Enter Marks Directly</h2>
            <form method="post" id="enterMarksForm">
                <input type="hidden" name="class_id" value="" id="directClassId">
                <input type="hidden" name="subject_id" value="" id="directSubjectId">
                <input type="hidden" name="exam_type" value="" id="directExamType">
                <input type="hidden" name="max_score" value="" id="directMaxScore">

                <button type="button" id="loadStudentsBtn" disabled>Load Students</button>

                <div id="studentMarksSection" class="student-list" style="display: none;"></div>

                <button type="submit" name="enter_marks" id="submitMarksBtn" disabled>Submit Marks</button>
            </form>
        </div>

        <div class="dashboard-section">
            <h2>View Exam Results</h2>
            <div>
                <label for="view_class_id">Select Class:</label>
                <select id="view_class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="view_subject_id">Select Subject:</label>
                <select id="view_subject_id" required disabled></select>
            </div>
            <div>
                <label for="view_exam_type">Select Exam Type:</label>
                <select id="view_exam_type" required>
                    <option value="">Select Exam Type</option>
                    <?php foreach ($exam_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['exam_type']); ?>"><?php echo htmlspecialchars($type['exam_type']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" id="viewResultsBtn" disabled>View Results</button>
            <div id="examResultsSection" class="student-list" style="display: none;"></div>
        </div>
    </div>

    <script>
        const classSelect = document.getElementById('class_id');
        const subjectSelect = document.getElementById('subject_id');
        const generateTemplateBtn = document.getElementById('generateTemplateBtn');
        const loadStudentsBtn = document.getElementById('loadStudentsBtn');
        const maxScoreSelect = document.getElementById('max_score');
        const fileInput = document.getElementById('marks_file');
        const fileName = document.getElementById('file-name');
        const uploadMarksBtn = document.getElementById('uploadMarksBtn');

        const viewClassSelect = document.getElementById('view_class_id');
        const viewSubjectSelect = document.getElementById('view_subject_id');
        const viewExamTypeSelect = document.getElementById('view_exam_type');
        const viewResultsBtn = document.getElementById('viewResultsBtn');

        function updateSubjects(classId, subjectSelectElement) {
            if (classId) {
                fetch(`?action=get_subjects&class_id=${classId}`)
                    .then(response => response.json())
                    .then(subjects => {
                        subjectSelectElement.innerHTML = '<option value="">Select Subject</option>';
                        subjects.forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject.subject_id;
                            option.textContent = subject.subject_name;
                            subjectSelectElement.appendChild(option);
                        });
                        subjectSelectElement.disabled = false;
                    });
            } else {
                subjectSelectElement.innerHTML = '<option value="">Select Subject</option>';
                subjectSelectElement.disabled = true;
            }
        }

        classSelect.addEventListener('change', function () {
            updateSubjects(this.value, subjectSelect);
        });

        viewClassSelect.addEventListener('change', function () {
            updateSubjects(this.value, viewSubjectSelect);
        });

        maxScoreSelect.addEventListener('change', function () {
            generateTemplateBtn.disabled = !this.value;
            loadStudentsBtn.disabled = !this.value;
        });

        fileInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                fileName.textContent = this.files[0].name;
                uploadMarksBtn.disabled = false;
            } else {
                fileName.textContent = '';
                uploadMarksBtn.disabled = true;
            }
        });

        generateTemplateBtn.addEventListener('click', function () {
            const classId = classSelect.value;
            const subjectId = subjectSelect.value;
            const examType = document.getElementById('exam_type').value;
            const maxScore = maxScoreSelect.value;

            if (classId && subjectId && examType && maxScore) {
                window.location.href = `?action=generate_template&class_id=${classId}&subject_id=${subjectId}&exam_type=${examType}&max_score=${maxScore}`;
            } else {
                alert("Please select a class, subject, exam type, and max score.");
            }
        });

        loadStudentsBtn.addEventListener('click', function () {
            const classId = classSelect.value;
            const subjectId = subjectSelect.value;
            const examType = document.getElementById('exam_type').value;
            const maxScore = maxScoreSelect.value;

            if (classId && subjectId && examType && maxScore) {
                document.getElementById('directClassId').value = classId;
                document.getElementById('directSubjectId').value = subjectId;
                document.getElementById('directExamType').value = examType;
                document.getElementById('directMaxScore').value = maxScore;

                fetch(`?action=get_students&class_id=${classId}`)
                    .then(response => response.json())
                    .then(students => {
                        const studentMarksSection = document.getElementById('studentMarksSection');
                        studentMarksSection.innerHTML = '';
                        students.forEach(student => {
                            const div = document.createElement('div');
                            div.innerHTML = `
                                <label>${student.name}</label>
                                <input type="number" name="students[${student.id}]" min="0" max="${maxScore}" step="0.1" required>
                            `;
                            studentMarksSection.appendChild(div);
                        });
                        studentMarksSection.style.display = 'block';
                        document.getElementById('submitMarksBtn').disabled = false;
                    });
            } else {
                alert("Please select a class, subject, exam type, and max score.");
            }
        });

        viewResultsBtn.addEventListener('click', function () {
            const classId = viewClassSelect.value;
            const subjectId = viewSubjectSelect.value;
            const examType = viewExamTypeSelect.value;

            if (classId && subjectId && examType) {
                fetch(`?action=get_exam_results&class_id=${classId}&subject_id=${subjectId}&exam_type=${examType}`)
                    .then(response => response.json())
                    .then(results => {
                        const examResultsSection = document.getElementById('examResultsSection');
                        examResultsSection.innerHTML = '';
                        
                        if (results.length > 0) {
                            const table = document.createElement('table');
                            table.innerHTML = `
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Score</th>
                                        <th>Max Score</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            `;
                            
                            results.forEach(result => {
                                const row = table.tBodies[0].insertRow();
                                row.innerHTML = `
                                    <td>${result.student_name}</td>
                                    <td>${result.score !== null ? result.score : 'N/A'}</td>
                                    <td>${result.max_score}</td>
                                `;
                            });
                            
                            examResultsSection.appendChild(table);
                        } else {
                            examResultsSection.textContent = 'No results found for this exam.';
                        }
                        
                        examResultsSection.style.display = 'block';
                    });
            } else {
                alert("Please select a class, subject, and exam type.");
            }
        });

        // Enable/disable the View Results button
        function checkViewResultsForm() {
            viewResultsBtn.disabled = !(viewClassSelect.value && viewSubjectSelect.value && viewExamTypeSelect.value);
        }

        viewClassSelect.addEventListener('change', checkViewResultsForm);
        viewSubjectSelect.addEventListener('change', checkViewResultsForm);
        viewExamTypeSelect.addEventListener('change', checkViewResultsForm);
    </script>
</body>
</html>