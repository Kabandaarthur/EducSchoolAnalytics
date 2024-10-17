<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection (you may want to put this in a separate file)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sms';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the admin's school_id and user details
$admin_id = $_SESSION['user_id'];
$user_query = "SELECT users.school_id, schools.school_name, users.firstname, users.lastname 
               FROM users 
               JOIN schools ON users.school_id = schools.id 
               WHERE users.user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();
$school_id = $admin_data['school_id'];
$school_name = $admin_data['school_name'];
$user_fullname = $admin_data['firstname'] . ' ' . $admin_data['lastname'];
$stmt->close();

//fetch current term information

$current_term_query = "SELECT id, name, year FROM terms WHERE school_id = ? AND is_current = 1";
$stmt = $conn->prepare($current_term_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$current_term_result = $stmt->get_result();
$current_term = $current_term_result->fetch_assoc();
$stmt->close();

// Handle term registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_term'])) {
    $term_name = $_POST['term_name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $year = $_POST['year'];

    // Check if promotion is needed: only promote when registering First Term and after a Third Term has ended
    $should_promote = false;
    if ($term_name == 'First Term') {
        $should_promote = shouldPromoteStudents($conn, $school_id);
    }

    // Set all previous terms to not current
    $update_terms = "UPDATE terms SET is_current = 0 WHERE school_id = ?";
    $stmt = $conn->prepare($update_terms);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $stmt->close();

    // Insert new term
    $insert_term = "INSERT INTO terms (school_id, name, start_date, end_date, is_current, year) VALUES (?, ?, ?, ?, 1, ?)";
    $stmt = $conn->prepare($insert_term);
    $stmt->bind_param("isssi", $school_id, $term_name, $start_date, $end_date, $year);
    $stmt->execute();
    $new_term_id = $stmt->insert_id;
    $stmt->close();

    $promotion_message = '';
    // Promote students if necessary
    if ($should_promote) {
        $promotion_results = promoteStudents($conn, $school_id, $new_term_id);
        $promotion_message = "{$promotion_results['promoted']} students promoted. {$promotion_results['not_promoted']} students not promoted (highest class).";
    }

    $_SESSION['success_message'] = "New term registered successfully. " . $promotion_message;

    // Redirect to refresh the page
    header("Location: school_admin_dashboard.php");
    exit();
}

function shouldPromoteStudents($conn, $school_id) {
    // Check if the most recent term was a "Third Term" and has ended
    $query = "SELECT id, name, end_date FROM terms 
              WHERE school_id = ? 
              ORDER BY year DESC, FIELD(name, 'First Term', 'Second Term', 'Third Term') DESC 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $last_term = $result->fetch_assoc();
    $stmt->close();

    // Promotion happens only if the last term was Third Term and has ended
    if ($last_term && $last_term['name'] == 'Third Term' && strtotime($last_term['end_date']) <= time()) {
        return true;
    }
    return false;
}

function promoteStudents($conn, $school_id, $new_term_id) {
    // Get all students
    $students_query = "SELECT id, class_id FROM students WHERE school_id = ?";
    $stmt = $conn->prepare($students_query);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $students_result = $stmt->get_result();
    $stmt->close();

    $promoted_count = 0;
    $not_promoted_count = 0;

    while ($student = $students_result->fetch_assoc()) {
        // Get the next class
        $next_class_query = "SELECT id FROM classes WHERE school_id = ? AND id > ? ORDER BY id ASC LIMIT 1";
        $stmt = $conn->prepare($next_class_query);
        $stmt->bind_param("ii", $school_id, $student['class_id']);
        $stmt->execute();
        $next_class_result = $stmt->get_result();
        $next_class = $next_class_result->fetch_assoc();
        $stmt->close();

        if ($next_class) {
            // Update student's class and term
            $update_student = "UPDATE students SET class_id = ?, current_term_id = ?, last_promoted_term_id = ? WHERE id = ?";
            $stmt = $conn->prepare($update_student);
            $stmt->bind_param("iiii", $next_class['id'], $new_term_id, $new_term_id, $student['id']);
            $stmt->execute();
            $stmt->close();

            // Insert new enrollment record
            $insert_enrollment = "INSERT INTO student_enrollments (student_id, class_id, term_id, school_id, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_enrollment);
            $stmt->bind_param("iiii", $student['id'], $next_class['id'], $new_term_id, $school_id);
            $stmt->execute();
            $stmt->close();

            $promoted_count++;
        } else {
            $not_promoted_count++;
        }
    }

    return array(
        'promoted' => $promoted_count,
        'not_promoted' => $not_promoted_count
    );
}

// Fetch school-specific stats
$total_students = $conn->query("SELECT COUNT(*) FROM students WHERE school_id = $school_id")->fetch_row()[0];
$total_teachers = $conn->query("SELECT COUNT(*) FROM users WHERE school_id = $school_id AND role = 'teacher'")->fetch_row()[0];
$total_exams = $conn->query("SELECT COUNT(*) FROM exams WHERE school_id = $school_id")->fetch_row()[0];
$total_subjects = $conn->query("SELECT COUNT(*) FROM subjects WHERE school_id = $school_id")->fetch_row()[0];

// Fetch recent activity for the specific school
$recent_activity_query = "SELECT * FROM (
    SELECT 'exam' as type, name as description, created_at 
    FROM exams 
    WHERE school_id = $school_id
    UNION ALL
    SELECT 'user' as type, CONCAT(firstname, ' ', lastname, ' (', role, ')') as description, created_at 
    FROM users 
    WHERE school_id = $school_id
    UNION ALL
    SELECT 'result' as type, CONCAT(exam_name, ' results uploaded') as description, upload_date as created_at 
    FROM exam_results 
    WHERE school_id = $school_id
) as activity
ORDER BY created_at DESC
LIMIT 5";

$recent_activity = $conn->query($recent_activity_query)->fetch_all(MYSQLI_ASSOC);

// Fetch top-performing students
$top_students_query = "SELECT s.firstname, s.lastname, AVG(er.score) as avg_score 
                       FROM students s 
                       JOIN exam_results er ON s.id = er.student_id 
                       WHERE s.school_id = $school_id 
                       GROUP BY s.id 
                       ORDER BY avg_score DESC 
                       LIMIT 5";
$top_students = $conn->query($top_students_query)->fetch_all(MYSQLI_ASSOC);

// Fetch best done subjects
$best_subjects_query = "SELECT s.subject_name, AVG(er.score) as avg_score
                        FROM subjects s
                        JOIN exams e ON s.subject_id = e.subject_id
                        JOIN exam_results er ON e.exam_id = er.exam_id
                        WHERE s.school_id = $school_id
                        GROUP BY s.subject_id
                        ORDER BY avg_score DESC
                        LIMIT 5";
$best_subjects = $conn->query($best_subjects_query)->fetch_all(MYSQLI_ASSOC);

$conn->close();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
        }
        .sidebar {
            background-color: #2c3e50;
            color: #ecf0f1;
            height: 100vh;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            background-color: #34495e;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e0e0e0;
        }
        .stat-card {
            text-align: center;
            padding: 15px;
        }
        .stat-card .stat-value {
            font-size: 20px;
            font-weight: bold;
        }
        .stat-card .stat-label {
            font-size: 12px;
            color: #777;
        }
        .chart-container {
            height: 300px;
        }
        .mobile-nav {
            display: none;
        }
        @media (max-width: 767.98px) {
            .mobile-nav {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1030;
            }
            .sidebar {
                display: none;
            }
            .main-content {
                margin-top: 56px;
                padding: 15px;
            }
            .card-title {
                font-size: 18px;
            }
            .stat-card .stat-value {
                font-size: 18px;
            }
            .stat-card .stat-label {
                font-size: 11px;
            }
            .list-group-item {
                padding: 0.5rem 1rem;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Mobile Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mobile-nav">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><?php echo htmlspecialchars($school_name); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="school_admin_dashboard.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_teachers.php">Teachers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_subjects.php">Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_exams.php">Exams</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_report.php">Reports</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (visible on larger screens) -->
            <nav class="col-md-2 d-none d-md-block sidebar">
                <!-- ... (keep sidebar content unchanged) ... -->
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <img src="https://via.placeholder.com/80" alt="Logo" class="rounded-circle">
                        <h5 class="mt-2"><?php echo htmlspecialchars($user_fullname); ?></h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="school_admin_dashboard.php">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_students.php">
                                <i class="fas fa-user-graduate"></i> Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_teachers.php">
                                <i class="fas fa-chalkboard-teacher"></i> Teachers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_subjects.php">
                                <i class="fas fa-book"></i> Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_exams.php">
                                <i class="fas fa-file-alt"></i> Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_report.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Admin Dashboard</h1>
                </div>

                <!-- Stats cards -->
                <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 g-2 g-md-4">
                    <div class="col">
                        <div class="card stat-card">
                            <div class="stat-value"><?php echo $total_students; ?></div>
                            <div class="stat-label">Students</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card">
                            <div class="stat-value"><?php echo $total_teachers; ?></div>
                            <div class="stat-label">Teachers</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card">
                            <div class="stat-value"><?php echo $total_subjects; ?></div>
                            <div class="stat-label">Subjects</div>
                        </div>
                    </div>
                </div>

                <!-- Chart and Recent Activities -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <li class="list-group-item">
                                            <?php
                                            switch ($activity['type']) {
                                                case 'exam':
                                                    echo "<i class='fas fa-file-alt text-warning'></i> New exam: " . htmlspecialchars($activity['description']);
                                                    break;
                                                case 'user':
                                                    echo "<i class='fas fa-user-plus text-primary'></i> New user: " . htmlspecialchars($activity['description']);
                                                    break;
                                                case 'result':
                                                    echo "<i class='fas fa-poll text-success'></i> " . htmlspecialchars($activity['description'] ?? '', ENT_QUOTES, 'UTF-8');
                                                    break;
                                            }
                                            ?>
                                            <small class="text-muted d-block mt-1"><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Best Performing Students and Best Done Subjects -->
                <div class="row mt-4">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Top Students</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($top_students as $index => $student): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo ($index + 1) . ". " . htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?>
                                            <span class="badge bg-primary rounded-pill"><?php echo number_format($student['avg_score'], 2); ?>%</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Top Subjects</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($best_subjects as $index => $subject): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo ($index + 1) . ". " . htmlspecialchars($subject['subject_name']); ?>
                                            <span class="badge bg-success rounded-pill"><?php echo number_format($subject['avg_score'], 2); ?>%</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Term Management Section -->
                <div class="row mt-4">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Current Term</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($current_term): ?>
                                    <p>Term: <?php echo htmlspecialchars($current_term['name']); ?></p>
                                    <p>Year: <?php echo htmlspecialchars($current_term['year']); ?></p>
                                <?php else: ?>
                                    <p>No active term.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Register New Term</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="term_name" class="form-label">Term Name</label>
                                        <select name="term_name" id="term_name" class="form-select" required>
                                            <option value="First Term">First Term</option>
                                            <option value="Second Term">Second Term</option>
                                            <option value="Third Term">Third Term</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" name="start_date" id="start_date" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" name="end_date" id="end_date" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="year" class="form-label">Year</label>
                                        <input type="number" name="year" id="year" class="form-control" required>
                                    </div>
                                    <button type="submit" name="register_term" class="btn btn-primary">Register Term</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>