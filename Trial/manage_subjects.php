<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_class':
                $name = $conn->real_escape_string($_POST['class_name']);
                $school_id = intval($_SESSION['school_id']); // Assuming school_id is stored in session
                $code = $conn->real_escape_string($_POST['class_code']);
                $description = $conn->real_escape_string($_POST['class_description']);
                
                $sql = "INSERT INTO classes (school_id, name, code, description) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isss", $school_id, $name, $code, $description);
                
                if ($stmt->execute()) {
                    $message = "New class added successfully";
                    $messageType = "success";
                } else {
                    $message = "Error: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
                break;
                break;

                case 'add_subject':
                    $name = $conn->real_escape_string($_POST['subject_name']);
                    $subject_code = $conn->real_escape_string($_POST['subject_code']);
                    $school_id = intval($_SESSION['school_id']); // Assuming school_id is stored in session
                    $class_id = intval($_POST['class_id']);
                    
                    $sql = "INSERT INTO subjects (school_id, class_id, subject_name, subject_code) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iiss", $school_id, $class_id, $name, $subject_code);
                    
                    if ($stmt->execute()) {
                        $message = "New subject added successfully";
                        $messageType = "success";
                    } else {
                        $message = "Error: " . $stmt->error;
                        $messageType = "error";
                    }
                    $stmt->close();
                    break;
                

            case 'assign_teacher':
                $teacher_id = intval($_POST['teacher_id']);
                $subject_id = intval($_POST['subject_id']);
                $class_id = intval($_POST['class_id']);
                
                $sql = "INSERT INTO teacher_subjects (user_id, subject_id, class_id) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
                
                if ($stmt->execute()) {
                    $message = "Teacher assigned to subject and class successfully";
                    $messageType = "success";
                } else {
                    $message = "Error: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
                break;
                break;

            case 'delete_assignment':
                $assignment_id = intval($_POST['assignment_id']);
                
                $sql = "DELETE FROM teacher_subjects WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $assignment_id);
                
                if ($stmt->execute()) {
                    $message = "Assignment deleted successfully";
                    $messageType = "success";
                } else {
                    $message = "Error: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
                break;

            case 'edit_assignment':
                $assignment_id = intval($_POST['assignment_id']);
                $teacher_id = intval($_POST['teacher_id']);
                
                $sql = "UPDATE teacher_subjects SET user_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $teacher_id, $assignment_id);
                
                if ($stmt->execute()) {
                    $message = "Assignment updated successfully";
                    $messageType = "success";
                } else {
                    $message = "Error: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
                break;
        }
    }
}

// Fetch all classes, subjects, and teachers for the current school
$school_id = intval($_SESSION['school_id']); // Assuming school_id is stored in session
$classes = $conn->query("SELECT * FROM classes WHERE school_id = $school_id ORDER BY name");
$subjects = $conn->query("SELECT * FROM subjects WHERE school_id = $school_id ORDER BY subject_name");
$teachers = $conn->query("SELECT * FROM users WHERE role = 'teacher' AND school_id = $school_id ORDER BY username");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes, Subjects, and Teachers - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 p-4 text-white">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Manage Classes, Subjects, and Teachers</h1>
            <a href="school_admin_dashboard.php" class="bg-blue-500 hover:bg-blue-700 px-4 py-2 rounded">Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mx-auto mt-8">
        <?php if ($message): ?>
            <div class="bg-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-100 border border-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-400 text-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Add New Class</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_class">
                    <div class="mb-4">
                        <label for="class_name" class="block mb-2">Class Name</label>
                        <input type="text" id="class_name" name="class_name" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label for="class_code" class="block mb-2">Class Code</label>
                        <input type="text" id="class_code" name="class_code" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label for="class_description" class="block mb-2">Description</label>
                        <textarea id="class_description" name="class_description" class="w-full p-2 border rounded" rows="3"></textarea>
                    </div>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">Add Class</button>
                </form>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Add New Subject</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_subject">
                    <div class="mb-4">
                        <label for="subject_name" class="block mb-2">Subject Name</label>
                        <input type="text" id="subject_name" name="subject_name" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="mb-4">
    <label for="subject_code" class="block mb-2">Subject Code</label>
    <input type="text" id="subject_code" name="subject_code" class="w-full p-2 border rounded" required>
</div>

                    <div class="mb-4">
                        <label for="class_id" class="block mb-2">Class</label>
                        <select name="class_id" id="class_id" class="w-full p-2 border rounded" required>
                            <?php while ($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">Add Subject</button>
                </form>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Assign Teacher to Subject</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_teacher">
                    <div class="mb-4">
                        <label for="teacher_id" class="block mb-2">Teacher</label>
                        <select name="teacher_id" id="teacher_id" class="w-full p-2 border rounded" required>
                            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['user_id']; ?>"><?php echo htmlspecialchars($teacher['username']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="subject_id" class="block mb-2">Subject</label>
                        <select name="subject_id" id="subject_id" class="w-full p-2 border rounded" required>
                            <?php 
                            $subjects->data_seek(0);
                            while ($subject = $subjects->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="class_id" class="block mb-2">Class</label>
                        <select name="class_id" id="class_id" class="w-full p-2 border rounded" required>
                            <?php 
                            $classes->data_seek(0);
                            while ($class = $classes->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded">Assign Teacher</button>
                </form>
            </div>
        </div>

        <!-- Display Classes, Subjects, and Teachers -->
        <div class="bg-white p-6 rounded-lg shadow-md mt-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-2">Classes, Subjects, and Teachers</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                $classes->data_seek(0);
                while ($class = $classes->fetch_assoc()):
                    $class_id = $class['id'];
                    $class_subjects = $conn->query("SELECT s.subject_id, s.subject_name, ts.id as assignment_id, u.user_id as teacher_id, u.username as teacher_name 
                                                    FROM subjects s 
                                                    LEFT JOIN teacher_subjects ts ON s.subject_id = ts.subject_id AND ts.class_id = $class_id
                                                    LEFT JOIN users u ON ts.user_id = u.user_id
                                                    WHERE s.class_id = $class_id");
                ?>
                    <div class="bg-gray-50 rounded-lg p-4 shadow-sm hover:shadow-md transition duration-300">
                        <h3 class="text-xl font-semibold mb-2 text-blue-600">
                            <?php echo htmlspecialchars($class['name']); ?>
                            <span class="text-sm font-normal text-gray-600">(<?php echo htmlspecialchars($class['code']); ?>)</span>
                        </h3>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($class['description']); ?></p>
                        <div class="mt-4">
                            <h4 class="text-lg font-medium mb-2 text-gray-700">Subjects and Teachers:</h4>
                            <?php if ($class_subjects->num_rows > 0): ?>
                                <ul class="space-y-2">
                                    <?php while ($subject = $class_subjects->fetch_assoc()): ?>
                                        <li class="flex flex-col">
                                            <span class="font-medium">
                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            </span>
                                            <div class="flex items-center justify-between mt-1">
                                                <span class="text-sm text-gray-500">
                                                    <?php echo $subject['teacher_name'] ? htmlspecialchars($subject['teacher_name']) : 'Not assigned'; ?>
                                                </span>
                                                <?php if ($subject['assignment_id']): ?>
                                                    <div>
                                                        <button onclick="openEditModal(<?php echo $subject['assignment_id']; ?>, <?php echo $subject['teacher_id']; ?>)" class="text-blue-500 hover:text-blue-700 mr-2">Edit</button>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="delete_assignment">
                                                            <input type="hidden" name="assignment_id" value="<?php echo $subject['assignment_id']; ?>">
                                                            <button type="submit" class="text-red-500 hover:text-red-700">Delete</button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-gray-500 italic">No subjects assigned yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Edit Assignment Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Teacher Assignment</h3>
                <form id="editForm" method="POST" class="mt-2">
                    <input type="hidden" name="action" value="edit_assignment">
                    <input type="hidden" id="assignmentId" name="assignment_id" value="">
                    <div class="mt-2">
                        <select name="teacher_id" id="teacherSelect" class="w-full p-2 border rounded" required>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['user_id']; ?>"><?php echo htmlspecialchars($teacher['username']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="items-center px-4 py-3">
                        <button id="updateBtn" type="submit" class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditModal(assignmentId, teacherId) {
            document.getElementById('assignmentId').value = assignmentId;
            document.getElementById('teacherSelect').value = teacherId;
            document.getElementById('editModal').classList.remove('hidden');
        }

        document.getElementById('editModal').addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>