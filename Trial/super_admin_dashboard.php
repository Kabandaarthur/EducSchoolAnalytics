<?php
session_start();

// Check if the user is logged in and is a super admin
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_school':
                $school_name = $conn->real_escape_string($_POST['school_name']);
                $registration_number = $conn->real_escape_string($_POST['registration_number']);
                $location = $conn->real_escape_string($_POST['location']);

                $stmt = $conn->prepare("INSERT INTO schools (school_name, registration_number, location) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $school_name, $registration_number, $location);

                if ($stmt->execute()) {
                    $success = "School added successfully";
                } else {
                    $error = "Failed to add school";
                }
                $stmt->close();
                break;

            case 'add_user':
                $username = $conn->real_escape_string($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $email = $conn->real_escape_string($_POST['email']);
                $first_name = $conn->real_escape_string($_POST['first_name']);
                $last_name = $conn->real_escape_string($_POST['last_name']);
                $role = 'school_admin';
                $school_id = intval($_POST['school_id']);

                $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, user_type, school_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $username, $password, $email, $first_name, $last_name, $user_type, $school_id);

                if ($stmt->execute()) {
                    $success = "School admin added successfully";
                } else {
                    $error = "Failed to add school admin";
                }
                $stmt->close();
                break;

            case 'update_school_status':
                $school_id = intval($_POST['school_id']);
                $status = $conn->real_escape_string($_POST['status']);

                $stmt = $conn->prepare("UPDATE schools SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $school_id);

                if ($stmt->execute()) {
                    $success = "School status updated successfully";
                } else {
                    $error = "Failed to update school status";
                }
                $stmt->close();
                break;
        }
    }
}

// Fetch all schools
$schools_result = $conn->query("SELECT * FROM schools");
$schools = $schools_result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
        }
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="#">SMS Admin</a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="logout.php">Sign out</a>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="#">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_school.php">
                                <i class="fas fa-school"></i> Schools
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="school_admin.php">
                                <i class="fas fa-users"></i> School Admins
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    
                </div>

                <?php
                if (!empty($error)) {
                    echo "<div class='alert alert-danger'>{$error}</div>";
                }
                if (!empty($success)) {
                    echo "<div class='alert alert-success'>{$success}</div>";
                }
                ?>

                <h2 id="schools">Manage Schools</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>School Name</th>
                                <th>Registration Number</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schools as $school): ?>
                                <tr>
                                    <td><?php echo $school['id']; ?></td>
                                    <td><?php echo $school['school_name']; ?></td>
                                    <td><?php echo $school['registration_number']; ?></td>
                                    <td><?php echo $school['location']; ?></td>
                                    <td><?php echo $school['status']; ?></td>
                                    <td>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="action" value="update_school_status">
                                            <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block w-auto">
                                                <option value="active" <?php echo $school['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $school['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

              
                

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>