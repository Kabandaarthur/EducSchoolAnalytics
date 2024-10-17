<?php
session_start();


// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
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

// Fetch all teachers for the current school
$stmt = $conn->prepare("SELECT * FROM users WHERE school_id = ? AND (role = 'teacher' OR role = 'admin')");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$teachers = $result->fetch_all(MYSQLI_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_teacher'])) {
        // Add new teacher
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $_POST['email'];
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $role = $_POST['role'];

        $stmt = $conn->prepare("INSERT INTO users (username, password, email, firstname, lastname, school_id, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $username, $password, $email, $firstname, $lastname, $school_id, $role);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "Teacher added successfully.";
        } else {
            $error = "Error adding teacher.";
        }
    } elseif (isset($_POST['update_teacher'])) {
        // Update teacher
        $user_id = $_POST['user_id'];
        $email = $_POST['email'];
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $role = $_POST['role'];

        $stmt = $conn->prepare("UPDATE users SET email = ?, firstname = ?, lastname = ?, role = ? WHERE user_id = ? AND school_id = ?");
        $stmt->bind_param("ssssii", $email, $firstname, $lastname, $role, $user_id, $school_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "Teacher updated successfully.";
        } else {
            $error = "Error updating teacher.";
        }
    } elseif (isset($_POST['delete_teacher'])) {
        // Delete teacher
        $user_id = $_POST['user_id'];

        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND school_id = ?");
        $stmt->bind_param("ii", $user_id, $school_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "Teacher deleted successfully.";
        } else {
            $error = "Error deleting teacher.";
        }
    }

    // Refresh the teacher list after any action
    $stmt = $conn->prepare("SELECT * FROM users WHERE school_id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teachers = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            background-color: #f4f4f4;
            margin: 0;
            padding: 10px;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
            font-size: 1.5rem;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .message, .error { 
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .message {
            color: #4CAF50;
            background-color: #e8f5e9;
        }
        .error { 
            color: #f44336;
            background-color: #ffebee;
        }
        form {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        input[type="text"], input[type="password"], input[type="email"], select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="submit"], button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
            margin-bottom: 5px;
        }
        input[type="submit"]:hover, button:hover {
            background-color: #45a049;
        }
        .btn-delete {
            background-color: #f44336;
        }
        .btn-delete:hover {
            background-color: #d32f2f;
        }
        .btn-update {
            background-color: #2196F3;
        }
        .btn-update:hover {
            background-color: #1976D2;
        }
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        @media screen and (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                margin-bottom: 15px;
                border: 1px solid #ccc;
            }
            td {
                border: none;
                position: relative;
                padding-left: 50%;
            }
            td:before {
                position: absolute;
                top: 6px;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                content: attr(data-label);
                font-weight: bold;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</h1>
        
        <?php if (isset($message)): ?>
            <p class="message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></p>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <p class="error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
        <?php endif; ?>

        <h2><i class="fas fa-user-plus"></i> Add New Teacher</h2>
        <form method="post">
            <input type="text" name="username" required placeholder="Username">
            <input type="password" name="password" required placeholder="Password">
            <input type="email" name="email" required placeholder="Email">
            <input type="text" name="firstname" required placeholder="First Name">
            <input type="text" name="lastname" required placeholder="Last Name">
            <select name="role" required>
                <option value="teacher">Teacher</option>
                <option value="admin">Admin</option>
            </select>
            <input type="submit" name="add_teacher" value="Add Teacher">
        </form>

        <h2><i class="fas fa-list"></i> Teachers List</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                    <tr>
                        <td data-label="Username"><?php echo htmlspecialchars($teacher['username']); ?></td>
                        <td data-label="Email"><?php echo htmlspecialchars($teacher['email']); ?></td>
                        <td data-label="First Name"><?php echo htmlspecialchars($teacher['firstname']); ?></td>
                        <td data-label="Last Name"><?php echo htmlspecialchars($teacher['lastname']); ?></td>
                        <td data-label="Role"><?php echo htmlspecialchars($teacher['role']); ?></td>
                        <td data-label="Actions" class="action-buttons">
                            <form method="post" style="display: inline; padding: 0; background: none;">
                                <input type="hidden" name="user_id" value="<?php echo $teacher['user_id']; ?>">
                                <button type="submit" name="delete_teacher" class="btn-delete" onclick="return confirm('Are you sure you want to delete this teacher?');"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                            <button class="btn-update" onclick="showUpdateForm(<?php echo htmlspecialchars(json_encode($teacher)); ?>)"><i class="fas fa-edit"></i> Update</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div id="updateForm" style="display: none;">
            <h2><i class="fas fa-user-edit"></i> Update Teacher</h2>
            <form method="post">
                <input type="hidden" id="update_user_id" name="user_id">
                <input type="email" id="update_email" name="email" required placeholder="Email">
                <input type="text" id="update_firstname" name="firstname" required placeholder="First Name">
                <input type="text" id="update_lastname" name="lastname" required placeholder="Last Name">
                <select id="update_role" name="role" required>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>
                </select>
                <input type="submit" name="update_teacher" value="Update Teacher">
            </form>
        </div>
    </div>

    <script>
        function showUpdateForm(teacher) {
            document.getElementById('updateForm').style.display = 'block';
            document.getElementById('update_user_id').value = teacher.user_id;
            document.getElementById('update_email').value = teacher.email;
            document.getElementById('update_firstname').value = teacher.firstname;
            document.getElementById('update_lastname').value = teacher.lastname;
            document.getElementById('update_role').value = teacher.role;
            document.getElementById('updateForm').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>