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
$new_admin = null;

// Handle form submission for adding a school admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_admin') {
    $school_id = intval($_POST['school_id']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $conn->real_escape_string($_POST['email']);
    $firstname = $conn->real_escape_string($_POST['firstname']);
    $lastname = $conn->real_escape_string($_POST['lastname']);
    $role = 'admin';
    $created_at = date('Y-m-d H:i:s');
    $updated_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO users (username, password, email, firstname, lastname, school_id, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssisss", $username, $password, $email, $firstname, $lastname, $school_id, $role, $created_at, $updated_at);

    if ($stmt->execute()) {
        $success = "School admin added successfully";
        $new_admin = [
            'school_id' => $school_id,
            'user_id' => $stmt->insert_id,
            'firstname' => $firstname,
            'lastname' => $lastname
        ];
    } else {
        $error = "Failed to add school admin: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch all schools with their admins
$schools_query = "SELECT s.id, s.school_name, s.registration_number, s.location, s.status, 
                         u.user_id, u.firstname, u.lastname 
                  FROM schools s 
                  LEFT JOIN users u ON s.id = u.school_id AND u.role = 'admin'";
$schools_result = $conn->query($schools_query);

if (!$schools_result) {
    die("Error fetching schools: " . $conn->error);
}

$schools = $schools_result->fetch_all(MYSQLI_ASSOC);

// Update the schools array with the newly added admin
if ($new_admin) {
    foreach ($schools as &$school) {
        if ($school['id'] == $new_admin['school_id']) {
            $school['user_id'] = $new_admin['user_id'];
            $school['firstname'] = $new_admin['firstname'];
            $school['lastname'] = $new_admin['lastname'];
            break;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Admin Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 50px;
        }
        h1 {
            color: #007bff;
            margin-bottom: 30px;
        }
        .table {
            background-color: #ffffff;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .modal-content {
            border-radius: 15px;
        }
        .modal-header {
            background-color: #007bff;
            color: #ffffff;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .modal-title {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">School Admin Management</h1>
        <a href="super_admin_dashboard.php" class="btn btn-secondary mb-4"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        <?php
        if (!empty($error)) {
            echo "<div class='alert alert-danger'>{$error}</div>";
        }
        if (!empty($success)) {
            echo "<div class='alert alert-success'>{$success}</div>";
        }
        ?>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>School Name</th>
                        <th>Registration Number</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Current Admin</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schools as $school): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                        <td><?php echo htmlspecialchars($school['registration_number']); ?></td>
                        <td><?php echo htmlspecialchars($school['location']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $school['status'] == 'active' ? 'success' : 'warning'; ?>">
                                <?php echo htmlspecialchars($school['status']); ?>
                            </span>
                        </td>
                        <td>
                             <?php
                                 if ($school['user_id']) {
                                       echo htmlspecialchars($school['firstname'] . ' ' . $school['lastname']);
                                      } else {
                                          echo "<span class='text-muted'>No admin assigned</span>";
                                    }
                             ?>
                        </td>
                        <td>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal<?php echo $school['id']; ?>">
    <i class="fas <?php echo $school['user_id'] ? 'fa-user-edit' : 'fa-user-plus'; ?> me-2"></i>
    <?php echo $school['user_id'] ? 'Change Admin' : 'Add Admin'; ?>
</button>
                        </td>
                    </tr>

                    <!-- Add/Change Admin Modal -->
                    <div class="modal fade" id="addAdminModal<?php echo $school['id']; ?>" tabindex="-1" aria-labelledby="addAdminModalLabel<?php echo $school['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="addAdminModalLabel<?php echo $school['id']; ?>">
                                        <?php echo $school['user_id'] ? 'Change Admin' : 'Add Admin'; ?> for <?php echo htmlspecialchars($school['school_name']); ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="add_admin">
                                        <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" name="username" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="firstname" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="firstname" name="firstname" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="lastname" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="lastname" name="lastname" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Save</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>