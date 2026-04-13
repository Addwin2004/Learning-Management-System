<?php
session_start();
include("../config/db.php");

if ($_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// ---- HANDLE AJAX REQUESTS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];

    // Get Enrolled Courses
    if ($action === 'get_enrolled_courses') {
        $query = "SELECT c.course_id, c.course_name, c.description, u.name as instructor_name 
                  FROM Enrollments e
                  JOIN Courses c ON e.course_id = c.course_id
                  LEFT JOIN Users u ON c.instructor_id = u.user_id
                  WHERE e.student_id = $student_id
                  ORDER BY c.course_id DESC LIMIT 10";
        $result = $conn->query($query);
        $courses = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
        }
        
        echo json_encode(['courses' => $courses]);
        exit();
    }

    // Get Available Courses (not enrolled)
    if ($action === 'get_available_courses') {
        $query = "SELECT c.course_id, c.course_name, c.description, u.name as instructor_name
                  FROM Courses c
                  LEFT JOIN Users u ON c.instructor_id = u.user_id
                  WHERE c.course_id NOT IN (SELECT course_id FROM Enrollments WHERE student_id = $student_id)
                  ORDER BY c.course_id DESC LIMIT 10";
        $result = $conn->query($query);
        $courses = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
        }
        
        echo json_encode(['courses' => $courses]);
        exit();
    }

    // Get My Assignments
    if ($action === 'get_my_assignments') {
        $query = "SELECT a.assignment_id, a.title, a.due_date, c.course_name, u.name as instructor_name
                  FROM Assignments a
                  JOIN Courses c ON a.course_id = c.course_id
                  LEFT JOIN Users u ON c.instructor_id = u.user_id
                  JOIN Enrollments e ON c.course_id = e.course_id
                  WHERE e.student_id = $student_id
                  ORDER BY a.due_date ASC LIMIT 15";
        $result = $conn->query($query);
        $assignments = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $assignments[] = $row;
            }
        }
        
        echo json_encode(['assignments' => $assignments]);
        exit();
    }

    // Enroll in Course
    if ($action === 'enroll_course') {
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        
        if ($course_id) {
            $check = $conn->query("SELECT * FROM Enrollments WHERE student_id = $student_id AND course_id = $course_id");
            
            if ($check->num_rows == 0) {
                $insert = $conn->query("INSERT INTO Enrollments(student_id, course_id) VALUES ($student_id, $course_id)");
                if ($insert) {
                    echo json_encode(['success' => true, 'message' => 'Enrolled successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Enrollment failed']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Already enrolled in this course']);
            }
        }
        exit();
    }
}

// Get total counts
$total_courses = $conn->query("SELECT COUNT(*) as count FROM Enrollments WHERE student_id = $student_id")->fetch_assoc()['count'];
$total_assignments = $conn->query("SELECT COUNT(*) as count FROM Assignments a
                                  JOIN Enrollments e ON a.course_id = e.course_id
                                  WHERE e.student_id = $student_id")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/student_dashboard.css">
</head>

<body>
    <!-- Animated Background Elements -->
    <div class="background-wrapper">
        <div class="animated-blob blob-1"></div>
        <div class="animated-blob blob-2"></div>
        <div class="animated-blob blob-3"></div>
    </div>

    <div class="container">
        <!-- Header Section -->
        <header class="header">
            <div class="header-left">
                <div class="student-greeting">
                    <i class="fas fa-graduation-cap"></i>
                    <div class="greeting-text">
                        <p class="greeting-label">Welcome</p>
                        <h2>Student: <?php echo $student_name; ?></h2>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <span class="timestamp">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="current-date"></span>
                </span>
                <a href="../login.php?logout=1" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </header>

        <!-- Navigation Section -->
        <nav class="nav-section">
            <h3 class="nav-title"><i class="fas fa-compass"></i> Quick Access</h3>
            <div class="nav-grid">
                <a href="courses.php" class="nav-card nav-primary">
                    <div class="nav-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="nav-content">
                        <h4><?php echo $total_courses; ?></h4>
                        <p>Courses Enrolled</p>
                    </div>
                    <i class="fas fa-arrow-right nav-arrow"></i>
                </a>

                <a href="assignments.php" class="nav-card nav-info">
                    <div class="nav-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="nav-content">
                        <h4><?php echo $total_assignments; ?></h4>
                        <p>Assignments</p>
                    </div>
                    <i class="fas fa-arrow-right nav-arrow"></i>
                </a>

                <a href="grades.php" class="nav-card nav-success">
                    <div class="nav-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="nav-content">
                        <h4>View</h4>
                        <p>My Grades</p>
                    </div>
                    <i class="fas fa-arrow-right nav-arrow"></i>
                </a>
            </div>
        </nav>

        <!-- My Courses Section -->
        <section class="data-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-book-open"></i>
                    <h3>My Courses</h3>
                    <span class="count-badge"><?php echo $total_courses; ?></span>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-book"></i> Course Name</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-chalkboard-user"></i> Instructor</th>
                        </tr>
                    </thead>
                    <tbody id="courses_tbody">
                        <tr><td colspan="3" class="no-data">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Assignments Section -->
        <section class="data-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-list-check"></i>
                    <h3>My Assignments</h3>
                    <span class="count-badge"><?php echo $total_assignments; ?></span>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-file-alt"></i> Assignment</th>
                            <th><i class="fas fa-book"></i> Course</th>
                            <th><i class="fas fa-chalkboard-user"></i> Instructor</th>
                            <th><i class="fas fa-calendar"></i> Due Date</th>
                        </tr>
                    </thead>
                    <tbody id="assignments_tbody">
                        <tr><td colspan="4" class="no-data">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Available Courses Section -->
        <section class="data-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Available Courses</h3>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-book"></i> Course Name</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-chalkboard-user"></i> Instructor</th>
                            <th><i class="fas fa-arrow-right"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody id="available_tbody">
                        <tr><td colspan="4" class="no-data">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Main Content -->
        <main class="main-content">
            <div class="welcome-section">
                <h3><i class="fas fa-info-circle"></i> Dashboard Info</h3>
                <p>View your enrolled courses and assignments. Browse available courses and enroll to start learning. Check your grades regularly to monitor your progress.</p>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            updateDate();
            loadEnrolledCourses();
            loadMyAssignments();
            loadAvailableCourses();
        });

        function updateDate() {
            const dateElement = document.getElementById('current-date');
            const today = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateElement.textContent = today.toLocaleDateString('en-US', options);
        }

        function loadEnrolledCourses() {
            const formData = new FormData();
            formData.append('action', 'get_enrolled_courses');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('courses_tbody');
                tbody.innerHTML = '';

                if (data.courses.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="no-data">No courses enrolled yet</td></tr>';
                    return;
                }

                data.courses.forEach((course) => {
                    const description = course.description ? course.description.substring(0, 60) + (course.description.length > 60 ? '...' : '') : 'No description';
                    const instructor = course.instructor_name || 'Unassigned';

                    const row = document.createElement('tr');
                    row.className = 'table-row';
                    row.innerHTML = `
                        <td class="cell-name">
                            <div class="course-avatar"><i class="fas fa-book"></i></div>
                            <span>${escapeHtml(course.course_name)}</span>
                        </td>
                        <td class="cell-description">${escapeHtml(description)}</td>
                        <td class="cell-instructor">${escapeHtml(instructor)}</td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error loading courses:', error));
        }

        function loadMyAssignments() {
            const formData = new FormData();
            formData.append('action', 'get_my_assignments');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('assignments_tbody');
                tbody.innerHTML = '';

                if (data.assignments.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="no-data">No assignments available</td></tr>';
                    return;
                }

                data.assignments.forEach((assignment) => {
                    const dueDate = new Date(assignment.due_date);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    dueDate.setHours(0, 0, 0, 0);
                    
                    const isOverdue = dueDate < today ? 'overdue' : '';
                    const isUpcoming = dueDate.getTime() - today.getTime() <= 3 * 24 * 60 * 60 * 1000 ? 'upcoming' : '';
                    
                    const dueDateStr = dueDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });

                    const row = document.createElement('tr');
                    row.className = 'table-row';
                    row.innerHTML = `
                        <td class="cell-assignment">
                            <div class="assignment-icon"><i class="fas fa-file-alt"></i></div>
                            <span>${escapeHtml(assignment.title)}</span>
                        </td>
                        <td class="cell-course">${escapeHtml(assignment.course_name)}</td>
                        <td class="cell-instructor">${escapeHtml(assignment.instructor_name || 'Unassigned')}</td>
                        <td class="cell-date ${isOverdue} ${isUpcoming}">
                            <span class="due-badge ${isOverdue ? 'badge-overdue' : isUpcoming ? 'badge-upcoming' : 'badge-normal'}">
                                ${dueDateStr}
                            </span>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error loading assignments:', error));
        }

        function loadAvailableCourses() {
            const formData = new FormData();
            formData.append('action', 'get_available_courses');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('available_tbody');
                tbody.innerHTML = '';

                if (data.courses.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="no-data">All courses are already enrolled or no courses available</td></tr>';
                    return;
                }

                data.courses.forEach((course) => {
                    const description = course.description ? course.description.substring(0, 60) + (course.description.length > 60 ? '...' : '') : 'No description';
                    const instructor = course.instructor_name || 'Unassigned';

                    const row = document.createElement('tr');
                    row.className = 'table-row';
                    row.innerHTML = `
                        <td class="cell-name">
                            <div class="course-avatar"><i class="fas fa-book"></i></div>
                            <span>${escapeHtml(course.course_name)}</span>
                        </td>
                        <td class="cell-description">${escapeHtml(description)}</td>
                        <td class="cell-instructor">${escapeHtml(instructor)}</td>
                        <td class="cell-action">
                            <button class="btn-enroll" onclick="enrollCourse(${course.course_id})">
                                <i class="fas fa-plus"></i> Enroll
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error loading available courses:', error));
        }

        function enrollCourse(courseId) {
            const formData = new FormData();
            formData.append('action', 'enroll_course');
            formData.append('course_id', courseId);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Successfully enrolled in the course!');
                    loadEnrolledCourses();
                    loadMyAssignments();
                    loadAvailableCourses();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error enrolling in course');
            });
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>