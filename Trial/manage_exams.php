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

// Get the school_id for the logged-in admin
$admin_id = $_SESSION['user_id'];
$school_query = "SELECT school_id FROM users WHERE user_id = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();
$school_id = $admin_data['school_id'];
$stmt->close();

// Initialize variables
$exam_type = '';
$exam_date = '';
$term_id = '';
$is_active = 1; // Active by default

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_exam'])) {
    $exam_type = $_POST['exam_type'];
    $exam_date = $_POST['exam_date'];
    $term_id = $_POST['term_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Insert into database
    $sql = "INSERT INTO exams (school_id, term_id, exam_type, exam_date, is_active) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissi", $school_id, $term_id, $exam_type, $exam_date, $is_active);
    $stmt->execute();
    $stmt->close();
}

// Toggle status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_status'])) {
    $id = $_POST['id'];
    $is_active = $_POST['is_active'] == 1 ? 0 : 1;

    // Update status
    $sql = "UPDATE exams SET is_active = ? WHERE exam_id = ? AND school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $is_active, $id, $school_id);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_exam'])){
   $id = $_POST['id'];

   // Delete the exam
   $sql = "DELETE FROM exams WHERE exam_id = ? AND school_id = ?";
   $stmt = $conn->prepare($sql);
   $stmt->bind_param("ii", $id, $school_id);
   $stmt->execute();
   $stmt->close();
}

// Fetch existing terms for the school
$terms_query = "SELECT id, name, year FROM terms WHERE school_id = ? ORDER BY year DESC, start_date DESC";
$stmt = $conn->prepare($terms_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$terms_result = $stmt->get_result();
$stmt->close();

// Fetch existing exams for the school, including term information
$exams_query = "SELECT e.*, t.name as term_name, t.year as term_year 
                FROM exams e 
                JOIN terms t ON e.term_id = t.id 
                WHERE e.school_id = ? 
                ORDER BY t.year DESC, t.start_date DESC, e.exam_date DESC";
$stmt = $conn->prepare($exams_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$exams_result = $stmt->get_result();
$stmt->close();

// Fetch school name
$school_name_query = "SELECT school_name FROM schools WHERE id = ?";
$stmt = $conn->prepare($school_name_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school_result = $stmt->get_result();
$school_data = $school_result->fetch_assoc();
$school_name = $school_data['school_name'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="mb-4">Manage Exams - <?php echo htmlspecialchars($school_name); ?></h1>

        <!-- Form to add new exam -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Add New Exam</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="term_id" class="form-label">Term:</label>
                        <select class="form-select" id="term_id" name="term_id" required>
                            <?php while ($term = $terms_result->fetch_assoc()): ?>
                                <option value="<?php echo $term['id']; ?>">
                                    <?php echo htmlspecialchars($term['name'] . ' (' . $term['year'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="exam_type" class="form-label">Exam Type:</label>
                        <input type="text" class="form-control" id="exam_type" name="exam_type" required>
                    </div>
                    <div class="mb-3">
                        <label for="exam_date" class="form-label">Exam Date:</label>
                        <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <button type="submit" name="add_exam" class="btn btn-primary"><i class="fas fa-save"></i> Add Exam</button>
                </form>
            </div>
        </div>

        <!-- List of existing exams -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Existing Exams</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Term</th>
                            <th>Exam Type</th>
                            <th>Exam Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $exams_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['term_name'] . ' (' . $row['term_year'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($row['exam_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['exam_date']); ?></td>
                            <td><?php echo $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
                            <td>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="id" value="<?php echo $row['exam_id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $row['is_active']; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $row['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <i class="fas <?php echo $row['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                        <?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form action="" method="POST" class="d-inline">
                                    <input type="hidden" name="id" value="<?php echo $row['exam_id']; ?>">
                                    <button type="submit" name="delete_exam" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>