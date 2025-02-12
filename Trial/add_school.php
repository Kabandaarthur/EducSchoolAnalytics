<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sms';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_name = $conn->real_escape_string($_POST['school_name']);
    $registration_number = $conn->real_escape_string($_POST['registration_number']);
    $location = $conn->real_escape_string($_POST['location']);
    $status = $conn->real_escape_string($_POST['status']);
    $motto = $conn->real_escape_string($_POST['motto']);
    $email = $conn->real_escape_string($_POST['email']);

    // Handle file upload
    $badge = null;
    if (isset($_FILES['badge']) && $_FILES['badge']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['badge']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        if (in_array(strtolower($filetype), $allowed)) {
            $badge = file_get_contents($_FILES['badge']['tmp_name']);
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG and GIF files are allowed.";
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO schools (school_name, registration_number, location, status, motto, email, badge) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $school_name, $registration_number, $location, $status, $motto, $email, $badge);

        if ($stmt->execute()) {
            $_SESSION['notification'] = "School '{$school_name}' has been successfully registered!";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Check for notification
if (isset($_SESSION['notification'])) {
    $success = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New School - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h2 class="text-center mb-0"><i class="fas fa-school me-2"></i>Add New School</h2>
            </div>
            <div class="card-body">
                <?php
                if (!empty($error)) {
                    echo "<div class='alert alert-danger'>{$error}</div>";
                }
                if (!empty($success)) {
                    echo "<div class='alert alert-success'>{$success}</div>";
                }
                ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="school_name" class="form-label">School Name</label>
                        <input type="text" class="form-control" id="school_name" name="school_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="registration_number" class="form-label">Registration Number</label>
                        <input type="text" class="form-control" id="registration_number" name="registration_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" required>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="motto" class="form-label">School Motto</label>
                        <input type="text" class="form-control" id="motto" name="motto" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">School Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="badge" class="form-label">School Badge (Image)</label>
                        <input type="file" class="form-control" id="badge" name="badge" accept="image/*" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add School</button>
                        <a href="super_admin_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>