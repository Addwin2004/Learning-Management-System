<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

$query = "
SELECT c.course_id, c.course_name, c.description, u.name AS instructor_name
FROM Enrollments e
JOIN Courses c ON e.course_id = c.course_id
JOIN Users u ON c.instructor_id = u.user_id
WHERE e.student_id = ?
ORDER BY c.course_id DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/student_courses.css">
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
                <h2><i class="fas fa-book-open"></i> My Courses</h2>
            </div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="assignments.php" class="nav-link">
                    <i class="fas fa-tasks"></i> Assignments
                </a>
                <a href="grades.php" class="nav-link">
                    <i class="fas fa-star"></i> Grades
                </a>
                <a href="../login.php?logout=1" class="nav-link nav-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </header>

        <!-- Main Content -->
        <main class="main">
            <?php if ($result->num_rows > 0) { ?>
                <div class="courses-grid">
                    <?php $index = 0; while($row = $result->fetch_assoc()) { $index++; ?>
                        <div class="course-card" style="animation-delay: <?php echo $index * 0.1; ?>s">
                            <div class="course-header">
                                <div class="course-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($row['course_name']); ?></h3>
                            </div>

                            <div class="course-body">
                                <div class="course-description">
                                    <h4>Course Description</h4>
                                    <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                </div>

                                <div class="course-instructor">
                                    <i class="fas fa-chalkboard-user"></i>
                                    <div class="instructor-info">
                                        <span class="instructor-label">Instructor</span>
                                        <span class="instructor-name"><?php echo htmlspecialchars($row['instructor_name']); ?></span>
                                    </div>
                                </div>
                            </div>

                            
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Courses Yet</h3>
                    <p>You haven't enrolled in any courses. Check back soon for available courses!</p>
                    <a href="dashboard.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            <?php } ?>
        </main>
    </div>

    <script>
        function viewCourseDetails(courseId, courseName) {
            // Can be extended to show more details or navigate to course page
            console.log("[v0] Viewing course:", courseName);
            // For now, just show an alert or you can redirect to a course detail page
            // window.location.href = 'course-detail.php?id=' + courseId;
        }
    </script>
</body>
</html>
