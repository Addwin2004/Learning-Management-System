<?php
session_start();
include("../config/db.php");

if ($_SESSION['role'] != 'instructor') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = ""; // 'success' or 'error'
$instructor_id = $_SESSION['user_id'];

// Get instructor courses
$courses = $conn->query("SELECT course_id, course_name FROM Courses WHERE instructor_id = $instructor_id ORDER BY course_name");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $desc = isset($_POST['description']) ? trim($_POST['description']) : '';
    $due = isset($_POST['due_date']) ? $_POST['due_date'] : '';

    if (!$course_id || !$title || !$due) {
        $msg = "Please fill in all required fields";
        $msg_type = "error";
    } else {
        $title = $conn->real_escape_string($title);
        $desc = $conn->real_escape_string($desc);
        $due = $conn->real_escape_string($due);

        $stmt = $conn->prepare("INSERT INTO Assignments(course_id, title, description, due_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $course_id, $title, $desc, $due);

        if ($stmt->execute()) {
            $msg = "Assignment created successfully!";
            $msg_type = "success";
            // Clear form
            $_POST = array();
        } else {
            $msg = "Error creating assignment. Please try again.";
            $msg_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Assignment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/instructor_add_assignment.css">
</head>

<body>
    <!-- Animated Background -->
    <div class="background-wrapper">
        <div class="animated-blob blob-1"></div>
        <div class="animated-blob blob-2"></div>
        <div class="animated-blob blob-3"></div>
    </div>

    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <h2><i class="fas fa-file-pen"></i> Add Assignment</h2>
            </div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="grade.php" class="nav-link">
                    <i class="fas fa-star"></i> Grade Students
                </a>
                <a href="../login.php?logout=1" class="nav-link nav-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </header>

        <!-- Main Content -->
        <main class="main">
            <!-- Status Message -->
            <?php if ($msg): ?>
                <div class="alert alert-<?php echo $msg_type; ?>">
                    <i class="fas fa-<?php echo $msg_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="form-card">
                <div class="form-header">
                    <h3><i class="fas fa-plus-circle"></i> Create New Assignment</h3>
                    <p>Fill in the details to create an assignment for your course</p>
                </div>

                <form method="POST" class="form">
                    <!-- Course Selection -->
                    <div class="form-group">
                        <label for="course_id" class="form-label">
                            <i class="fas fa-book"></i> Select Course <span class="required">*</span>
                        </label>
                        <select name="course_id" id="course_id" class="form-input" required>
                            <option value="">-- Choose a course --</option>
                            <?php 
                            while($row = $courses->fetch_assoc()) { 
                                $selected = isset($_POST['course_id']) && $_POST['course_id'] == $row['course_id'] ? 'selected' : '';
                            ?>
                                <option value="<?php echo $row['course_id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($row['course_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Assignment Title -->
                    <div class="form-group">
                        <label for="title" class="form-label">
                            <i class="fas fa-heading"></i> Assignment Title <span class="required">*</span>
                        </label>
                        <input type="text" name="title" id="title" class="form-input" 
                               placeholder="Enter assignment title" 
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                               required>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left"></i> Description
                        </label>
                        <textarea name="description" id="description" class="form-input form-textarea" 
                                  placeholder="Enter assignment description (optional)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <small class="form-hint">Provide detailed instructions for students</small>
                    </div>

                    <!-- Due Date -->
                    <div class="form-group">
                        <label for="due_date" class="form-label">
                            <i class="fas fa-calendar-alt"></i> Due Date <span class="required">*</span>
                        </label>
                        <input type="date" name="due_date" id="due_date" class="form-input" 
                               value="<?php echo isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : ''; ?>"
                               required>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Assignment
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Auto-hide success message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                setTimeout(function() {
                    alert.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 3000);
            }
        });
    </script>
</body>
</html>