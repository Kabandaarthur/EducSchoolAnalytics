<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Assuming the user's school_id is stored in the session
$user_school_id = $_SESSION['school_id'];

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

// Fetch current term
$current_term_query = $conn->prepare("SELECT id, name, year FROM terms WHERE school_id = ? AND is_current = 1");
$current_term_query->bind_param("i", $user_school_id);
$current_term_query->execute();
$current_term_result = $current_term_query->get_result();
$current_term = $current_term_result->fetch_assoc();
$current_term_query->close();

// Fetch students for a specific class and current term
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;
$students = null;

// Search functionality
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Fetch all classes for the user's school
$classes = $conn->prepare("SELECT id, name FROM classes WHERE school_id = ? ORDER BY name");
$classes->bind_param("i", $user_school_id);
$classes->execute();
$classes_result = $classes->get_result();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_student'])) {
        // Add new student logic
        $firstname = $conn->real_escape_string($_POST['firstname']);
        $lastname = $conn->real_escape_string($_POST['lastname']);
        $gender = $conn->real_escape_string($_POST['gender']);
        $age = intval($_POST['age']);
        $stream = $conn->real_escape_string($_POST['stream']);
        $class_id = intval($_POST['class_id']);
        
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
            $filename = $_FILES['image']['name'];
            $filetype = $_FILES['image']['type'];
            $filesize = $_FILES['image']['size'];
        
            // Verify file extension
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (!array_key_exists($ext, $allowed)) die("Error: Please select a valid file format.");
        
            // Verify file size - 5MB maximum
            $maxsize = 5 * 1024 * 1024;
            if ($filesize > $maxsize) die("Error: File size is larger than the allowed limit.");
        
            // Verify MIME type of the file
            if (in_array($filetype, $allowed)) {
                // Check whether file exists before uploading it
                if (file_exists("uploads/" . $filename)) {
                    echo $filename . " already exists.";
                } else {
                    if (move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $filename)) {
                        $image_path = "uploads/" . $filename;
                    } else {
                        echo "Error uploading file.";
                    }
                }
            } else {
                echo "Error: There was a problem uploading your file. Please try again.";
            }
        }
        
        $sql = "INSERT INTO students (class_id, firstname, lastname, gender, age, stream, image, created_at, school_id, current_term_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssissii", $class_id, $firstname, $lastname, $gender, $age, $stream, $image_path, $user_school_id, $current_term['id']);
        
        if ($stmt->execute()) {
            $student_id = $stmt->insert_id;
            // Add entry to student_enrollments table
            $enroll_sql = "INSERT INTO student_enrollments (student_id, class_id, term_id, school_id, created_at) VALUES (?, ?, ?, ?, NOW())";
            $enroll_stmt = $conn->prepare($enroll_sql);
            $enroll_stmt->bind_param("iiii", $student_id, $class_id, $current_term['id'], $user_school_id);
            $enroll_stmt->execute();
            $enroll_stmt->close();
            $message = "Student added successfully.";
        } else {
            $message = "Error adding student: " . $conn->error;
        }
        $stmt->close();

    } elseif (isset($_POST['update_student'])) {
        // Update student logic
        $id = (int)$_POST['id'];
        $firstname = $conn->real_escape_string($_POST['firstname']);
        $lastname = $conn->real_escape_string($_POST['lastname']);
        $gender = $conn->real_escape_string($_POST['gender']);
        $age = $conn->real_escape_string($_POST['age']);
        $stream = $conn->real_escape_string($_POST['stream']);
        $class_id = $conn->real_escape_string($_POST['class_id']);
        
        // Handle image update
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
             // Handle image upload
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
            $filename = $_FILES['image']['name'];
            $filetype = $_FILES['image']['type'];
            $filesize = $_FILES['image']['size'];
        
            // Verify file extension
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (!array_key_exists($ext, $allowed)) die("Error: Please select a valid file format.");
        
            // Verify file size - 5MB maximum
            $maxsize = 5 * 1024 * 1024;
            if ($filesize > $maxsize) die("Error: File size is larger than the allowed limit.");
        
            // Verify MIME type of the file
            if (in_array($filetype, $allowed)) {
                // Check whether file exists before uploading it
                if (file_exists("uploads/" . $filename)) {
                    echo $filename . " already exists.";
                } else {
                    if (move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $filename)) {
                        $image_path = "uploads/" . $filename;
                    } else {
                        echo "Error uploading file.";
                    }
                }
            } else {
                echo "Error: There was a problem uploading your file. Please try again.";
            }
        }
        
        }
        
        $sql = "UPDATE students SET firstname = ?, lastname = ?, gender = ?, age = ?, stream = ?, class_id = ?";
        $params = array($firstname, $lastname, $gender, $age, $stream, $class_id);
        $types = "sssssi";
        
        if ($image_path) {
            $sql .= ", image = ?";
            $params[] = $image_path;
            $types .= "s";
        }
        
        $sql .= " WHERE id = ? AND school_id = ?";
        $params[] = $id;
        $params[] = $user_school_id;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $message = "Student updated successfully.";
        } else {
            $message = "Error updating student: " . $conn->error;
        }
        $stmt->close();
    }
}

// Delete student
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $sql = "DELETE FROM students WHERE id = ? AND school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $user_school_id);

    if ($stmt->execute()) {
        $message = "Student deleted successfully.";
    } else {
        $message = "Error deleting student: " . $conn->error;
    }
    $stmt->close();
}

// Fetch students for a specific class with search
if ($selected_class_id && $current_term) {
    $students_query = $conn->prepare("SELECT s.id, s.firstname, s.lastname, s.gender, s.age, s.stream, s.image, c.name AS class_name, s.created_at, s.class_id 
                                      FROM students s 
                                      LEFT JOIN classes c ON s.class_id = c.id 
                                      WHERE s.school_id = ? AND s.class_id = ? AND s.current_term_id = ?
                                      AND (s.firstname LIKE ? OR s.lastname LIKE ? OR s.stream LIKE ?)
                                      ORDER BY s.lastname, s.firstname");
    $search_param = "%$search_query%";
    $students_query->bind_param("iiisss", $user_school_id, $selected_class_id, $current_term['id'], $search_param, $search_param, $search_param);
    $students_query->execute();
    $students = $students_query->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - School Exam Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 p-4 text-white">
        <div class="container mx-auto">
            <h1 class="text-2xl font-bold">Manage Students</h1>
        </div>
    </nav>

    <div class="container mx-auto mt-8 px-4">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Current Term Information -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-xl font-semibold mb-4">Current Term</h2>
            <?php if ($current_term): ?>
                <p>Term: <?php echo htmlspecialchars($current_term['name']); ?></p>
                <p>Year: <?php echo htmlspecialchars($current_term['year']); ?></p>
            <?php else: ?>
                <p>No active term. Please register a term in the admin dashboard.</p>
            <?php endif; ?>
        </div>

        <!-- Class Selection -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-xl font-semibold mb-4">Select Class</h2>
            <form action="" method="GET" class="flex flex-wrap items-center">
                <select name="class_id" class="w-full sm:w-auto px-3 py-2 border rounded mr-2 mb-2 sm:mb-0">
                    <option value="">Select Class</option>
                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">View Students</button>
            </form>
        </div>

        <?php if ($selected_class_id): ?>
            <!-- Search Bar -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h2 class="text-xl font-semibold mb-4">Search Students</h2>
                <form action="" method="GET" class="flex flex-wrap items-center">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                    <input type="text" name="search" placeholder="Search by name or stream" value="<?php echo htmlspecialchars($search_query); ?>" class="w-full sm:w-auto flex-grow px-3 py-2 border rounded mr-2 mb-2 sm:mb-0">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Search</button>
                </form>
            </div>

            <!-- Add Student Form -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h2 class="text-xl font-semibold mb-4">Add New Student</h2>
                <form action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="firstname" class="block mb-2">Firstname</label>
                        <input type="text" id="firstname" name="firstname" required class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label for="lastname" class="block mb-2">Lastname</label>
                        <input type="text" id="lastname" name="lastname" required class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label for="gender" class="block mb-2">Gender</label>
                        <select id="gender" name="gender" required class="w-full px-3 py-2 border rounded">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label for="age" class="block mb-2">Age</label>
                        <input type="number" id="age" name="age" required class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label for="stream" class="block mb-2">Stream</label>
                        <input type="text" id="stream" name="stream" required class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label for="image" class="block mb-2">Student Image</label>
                        <input type="file" id="image" name="image" accept="image/*" class="w-full px-3 py-2 border rounded">
                    </div>
                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                    <div class="col-span-full">
                        <button type="submit" name="add_student" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Add Student</button>
                    </div>
                </form>
            </div>

            <!-- Students List -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Students List</h2>
                <?php if ($search_query): ?>
                    <p class="mb-4">Search results for: "<?php echo htmlspecialchars($search_query); ?>"</p>
                <?php endif; ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border-b text-left">Image</th>
                                <th class="py-2 px-4 border-b text-left">Name</th>
                                <th class="py-2 px-4 border-b text-left">Gender</th>
                                <th class="py-2 px-4 border-b text-left">Age</th>
                                <th class="py-2 px-4 border-b text-left">Stream</th>
                                <th class="py-2 px-4 border-b text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students && $students->num_rows > 0): ?>
                                <?php while ($student = $students->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-4 border-b">
                                            <?php if ($student['image']): ?>
                                                <img src="<?php echo htmlspecialchars($student['image']); ?>" alt="Student Image" class="w-16 h-16 object-cover rounded-full">
                                            <?php else: ?>
                                                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                                                    <span class="text-gray-500">No Image</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($student['lastname'] . ' ' . $student['firstname']); ?></td>
                                        <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($student['gender']); ?></td>
                                        <td class="py-2 px-4 border-b"><?php echo $student['age']; ?></td>
                                        <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($student['stream']); ?></td>
                                        <td class="py-2 px-4 border-b">
                                            <button onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($student)); ?>)" class="bg-yellow-500 text-white px-2 py-1 rounded hover:bg-yellow-600 mr-2">Update</button>
                                            <a href="?delete=<?php echo $student['id']; ?>&class_id=<?php echo $selected_class_id; ?>" onclick="return confirm('Are you sure you want to delete this student?')" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-4 px-4 border-b text-center">
                                        <?php if ($search_query): ?>
                                            No students found matching your search.
                                        <?php else: ?>
                                            No students found in this class for the current term.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($selected_class_id && !$current_term): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">No active term. Please register a term in the admin dashboard before managing students.</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Update Student Modal -->
    <div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Update Student</h3>
            <form id="updateForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="update_id" name="id">
                <div class="mb-4">
                    <label for="update_firstname" class="block mb-2">FirstName</label>
                    <input type="text" id="update_firstname" name="firstname" required class="w-full px-3 py-2 border rounded">
                </div>
                <div class="mb-4">
                    <label for="update_lastname" class="block mb-2">Last Name</label>
                    <input type="text" id="update_lastname" name="lastname" required class="w-full px-3 py-2 border rounded">
                </div>
                <div class="mb-4">
                    <label for="update_gender" class="block mb-2">Gender</label>
                    <select id="update_gender" name="gender" required class="w-full px-3 py-2 border rounded">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="update_age" class="block mb-2">Age</label>
                    <input type="number" id="update_age" name="age" required class="w-full px-3 py-2 border rounded">
                </div>
                <div class="mb-4">
                    <label for="update_stream" class="block mb-2">Stream</label>
                    <input type="text" id="update_stream" name="stream" required class="w-full px-3 py-2 border rounded">
                </div>
                <div class="mb-4">
                    <label for="update_image" class="block mb-2">Student Image</label>
                    <input type="file" id="update_image" name="image" accept="image/*" class="w-full px-3 py-2 border rounded">
                </div>
                <input type="hidden" id="update_class_id" name="class_id">
                <div class="mt-4">
                    <button type="submit" name="update_student" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Update Student</button>
                    <button type="button" onclick="closeUpdateModal()" class="ml-2 bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUpdateModal(student) {
            document.getElementById('update_id').value = student.id;
            document.getElementById('update_firstname').value = student.firstname;
            document.getElementById('update_lastname').value = student.lastname;
            document.getElementById('update_gender').value = student.gender;
            document.getElementById('update_age').value = student.age;
            document.getElementById('update_stream').value = student.stream;
            document.getElementById('update_class_id').value = student.class_id;
            // Note: We can't set the file input value due to security restrictions
            document.getElementById('updateModal').classList.remove('hidden');
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
        }
    </script>
</body>
</html>