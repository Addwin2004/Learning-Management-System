<?php
session_start();
include("../config/db.php");

if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = "";

// Fetch instructors
$instructors = $conn->query("SELECT user_id, name FROM Users WHERE role='instructor'");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST['course_name'];
    $desc = $_POST['description'];
    $instructor = $_POST['instructor'];

    if ($name && $instructor) {

        $stmt = $conn->prepare("INSERT INTO Courses(course_name,description,instructor_id) VALUES (?,?,?)");
        $stmt->bind_param("ssi", $name, $desc, $instructor);

        if ($stmt->execute()) {
            $msg = "Course created successfully!";
        } else {
            $msg = "Error creating course";
        }
    } else {
        $msg = "All required fields must be filled";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course - LMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_create_course.css">
</head>

<body>

<!-- Animated Background -->
<div class="background-animation">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
</div>

<div class="container">
    <!-- Header Section -->
    <header class="header-section">
        <div class="header-left">
            <div class="back-button">
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
        <div class="header-right">
            <a href="../login.php?logout=1" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </header>

    <!-- Form Container -->
    <div class="form-container">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <h1>Create New Course</h1>
            <p class="form-subtitle">Add a new course to your learning management system</p>
        </div>

        <!-- Status Message -->
        <?php if ($msg): ?>
            <div class="status-message <?php echo strpos($msg, 'successfully') !== false ? 'success' : 'error'; ?>">
                <i class="fas <?php echo strpos($msg, 'successfully') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" class="course-form" onsubmit="return validateForm()">
            
            <!-- Course Name Field -->
            <div class="form-group">
                <label for="course_name">
                    <i class="fas fa-book"></i> Course Name
                </label>
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="course_name" 
                        name="course_name" 
                        placeholder="Enter course name" 
                        required
                        class="form-input"
                    >
                    <div class="input-underline"></div>
                </div>
            </div>

            <!-- Description Field -->
            <div class="form-group">
                <label for="description">
                    <i class="fas fa-align-left"></i> Course Description
                </label>
                <textarea 
                    id="description" 
                    name="description" 
                    placeholder="Enter course description..." 
                    class="form-textarea"
                ></textarea>
            </div>

            <!-- Instructor Field -->
            <div class="form-group">
                <label for="instructor">
                    <i class="fas fa-chalkboard-user"></i> Assign Instructor
                </label>
                <div class="select-wrapper">
                    <select id="instructor" name="instructor" required class="form-select">
                        <option value="">-- Select Instructor --</option>
                        <?php while($row = $instructors->fetch_assoc()) { ?>
                            <option value="<?php echo $row['user_id']; ?>">
                                <?php echo htmlspecialchars($row['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <i class="fas fa-chevron-down select-icon"></i>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-submit">
                <i class="fas fa-plus-circle"></i>
                <span>Create Course</span>
            </button>
        </form>

        <!-- Form Footer -->
        <div class="form-footer">
            <p><a href="dashboard.php">Back to dashboard</a></p>
        </div>
    </div>
</div>

<script>
function validateForm() {
    const courseName = document.getElementById('course_name').value.trim();
    const instructor = document.getElementById('instructor').value;

    if (courseName.length < 2) {
        alert('Course name must be at least 2 characters');
        return false;
    }

    if (!instructor) {
        alert('Please select an instructor');
        return false;
    }

    return true;
}
</script>

</body>
</html>