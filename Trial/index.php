<?php
session_start();

//database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "sms";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error){
    die("Connection Error:" . $conn->connect_error);
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Prepare SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['school_id'] = $user['school_id']?? null;

            switch ($user['role'])  {
                case 'super_admin':
                    header("Location: super_admin_dashboard.php");
                    break;
                case 'admin':
                    header("Location: school_admin_dashboard.php?school_id=" . $user['school_id']);
                    break;
                case 'teacher':
                    header("Location: teacher_dashboard.php?school_id=" . $user['school_id']);
                    break;
                default:
                    $error = "Invalid user role";
            }
            exit();
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Invalid email or password";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS - Login</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #f6e6b4 0%, #ed9017 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 400px;
            transition: all 0.3s ease;
        }
        h1 {
            text-align: center;
            color: #d4af37;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        form {
            display: flex;
            flex-direction: column;
        }
        input, button {
            margin: 10px 0;
            padding: 12px;
            border: 1px solid #d4af37;
            border-radius: 25px;
            font-size: 1rem;
        }
        input {
            background-color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }
        input:focus {
            outline: none;
            box-shadow: 0 0 5px #d4af37;
        }
        button {
            background-color: #d4af37;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.1s;
            font-weight: bold;
        }
        button:hover {
            background-color: #c19b2e;
            transform: translateY(-2px);
        }
        button:active {
            transform: translateY(0);
        }
        .error, .success {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
        }
        .error {
            background-color: #ffebee;
            color: #c62828;
        }
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .toggle-form {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .toggle-form a {
            color: #d4af37;
            text-decoration: none;
            font-weight: bold;
        }
        .toggle-form a:hover {
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .container {
                width: 95%;
                padding: 1.5rem;
            }
            h1 {
                font-size: 1.75rem;
            }
            input, button {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>School Management System</h1>
        <?php
        if (!empty($error)) {
            echo "<p class='error'>$error</p>";
        }
        if (!empty($success)) {
            echo "<p class='success'>$success</p>";
        }
        ?>
        <div id="loginForm">
            <form method="POST" action="">
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
            <div class="toggle-form">
                <p>Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
</body>
</html>