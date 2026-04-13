<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'];

// ---- HANDLE AJAX REQUESTS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];

    // Get Courses
    if ($action === 'get_courses') {
        $query = "SELECT course_id, course_name, description FROM Courses WHERE instructor_id = $instructor_id ORDER BY course_id DESC LIMIT 10";
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

    // Get Students (enrolled in instructor's courses)
    if ($action === 'get_students') {
        $course_filter = isset($_POST['course_filter']) ? $_POST['course_filter'] : 'all';
        
        $query = "SELECT DISTINCT u.user_id, u.name, u.email, u.role, u.created_at FROM Users u 
                  INNER JOIN Enrollments e ON u.user_id = e.student_id 
                  INNER JOIN Courses c ON e.course_id = c.course_id 
                  WHERE c.instructor_id = $instructor_id AND u.role = 'student'";
        
        if ($course_filter != 'all') {
            $course_filter = intval($course_filter);
            $query .= " AND e.course_id = $course_filter";
        }
        
        $query .= " ORDER BY u.created_at DESC LIMIT 20";
        $result = $conn->query($query);
        $students = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
        }
        
        echo json_encode(['students' => $students]);
        exit();
    }

    // Get My Courses with counts
    if ($action === 'get_all_courses') {
        $query = "SELECT c.course_id, c.course_name, c.description, 
                  COUNT(e.student_id) as student_count 
                  FROM Courses c 
                  LEFT JOIN Enrollments e ON c.course_id = e.course_id 
                  WHERE c.instructor_id = $instructor_id 
                  GROUP BY c.course_id 
                  ORDER BY c.course_id DESC";
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

    // Get Assignments for a course
    if ($action === 'get_assignments') {
        $course_id = intval($_POST['course_id']);
        $query = "SELECT assignment_id, title, description, due_date, created_at 
                  FROM Assignments 
                  WHERE course_id = $course_id 
                  ORDER BY due_date ASC";
        $result = $conn->query($query);
        $assignments = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $assignments[] = $row;
            }
        }
        
        echo json_encode(['assignments' => $assignments]);
        exit();
    }

    // Update course details
    if ($action === 'update_course') {
        $course_id = intval($_POST['course_id']);
        $course_name = $conn->real_escape_string(trim($_POST['course_name']));
        $description = $conn->real_escape_string(trim($_POST['description']));

        // Make sure this course belongs to this instructor
        $check = $conn->query("SELECT course_id FROM Courses WHERE course_id = $course_id AND instructor_id = $instructor_id");
        if ($check->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }

        $update = $conn->query("UPDATE Courses SET course_name = '$course_name', description = '$description' WHERE course_id = $course_id AND instructor_id = $instructor_id");
        
        if ($update) {
            echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update course']);
        }
        exit();
    }
}

// Get total counts
$total_courses = $conn->query("SELECT COUNT(*) as count FROM Courses WHERE instructor_id = $instructor_id")->fetch_assoc()['count'];
$total_students = $conn->query("SELECT COUNT(DISTINCT e.student_id) as count FROM Enrollments e 
                               INNER JOIN Courses c ON e.course_id = c.course_id 
                               WHERE c.instructor_id = $instructor_id")->fetch_assoc()['count'];

// Get courses for filter dropdown
$courses_list = $conn->query("SELECT course_id, course_name FROM Courses WHERE instructor_id = $instructor_id ORDER BY course_name");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/instructor_dashboard.css">
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
                <div class="instructor-greeting">
                    <i class="fas fa-chalkboard-user"></i>
                    <div class="greeting-text">
                        <p class="greeting-label">Welcome</p>
                        <h2>Instructor: <?php echo $instructor_name; ?></h2>
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
            <h3 class="nav-title"><i class="fas fa-tasks"></i> Quick Actions</h3>
            <div class="nav-grid">
                <a href="add_assignment.php" class="nav-card nav-primary">
                    <div class="nav-icon">
                        <i class="fas fa-file-pen"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Add Assignment</h4>
                        <p>Create new assignment</p>
                    </div>
                    <i class="fas fa-arrow-right nav-arrow"></i>
                </a>

                <a href="grade.php" class="nav-card nav-success">
                    <div class="nav-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Grade Students</h4>
                        <p>Submit grades and feedback</p>
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
            <p class="table-hint"><i class="fas fa-hand-pointer"></i> Click any course row to edit details or view assignments.</p>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-book"></i> Course Name</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-users"></i> Students</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="courses_tbody">
                        <tr><td colspan="4" class="no-data">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Students Section -->
        <section class="data-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    <h3>Enrolled Students</h3>
                    <span class="count-badge"><?php echo $total_students; ?></span>
                </div>
                <div class="filter-controls">
                    <select id="course_filter" class="filter-select">
                        <option value="all">All Students</option>
                        <?php while($course = $courses_list->fetch_assoc()) { ?>
                            <option value="<?php echo $course['course_id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Name</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-calendar"></i> Enrolled</th>
                        </tr>
                    </thead>
                    <tbody id="students_tbody">
                        <tr><td colspan="3" class="no-data">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Main Content -->
        <main class="main-content">
            <div class="welcome-section">
                <h3><i class="fas fa-info-circle"></i> Dashboard Info</h3>
                <p>Manage your courses, view enrolled students, add assignments, and grade your students. Use the filters to find specific students from your courses. Click on any course row to edit its details or view its assignments.</p>
            </div>
        </main>
    </div>

    <!-- ===== COURSE MODAL ===== -->
    <div id="courseModal" class="modal-overlay" style="display:none;">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>

            <!-- Tabs -->
            <div class="modal-tabs">
                <button class="modal-tab active" id="tab-edit" onclick="switchTab('edit')">
                    <i class="fas fa-pen"></i> Edit Course
                </button>
                <button class="modal-tab" id="tab-assignments" onclick="switchTab('assignments')">
                    <i class="fas fa-file-alt"></i> Assignments
                </button>
            </div>

            <!-- Edit Tab -->
            <div id="panel-edit" class="modal-panel">
                <h3 class="modal-title">Edit Course Details</h3>
                <input type="hidden" id="edit_course_id">
                <div class="form-group">
                    <label for="edit_course_name">Course Name</label>
                    <input type="text" id="edit_course_name" class="form-input" placeholder="Enter course name">
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" class="form-textarea" rows="4" placeholder="Enter course description"></textarea>
                </div>
                <div id="edit_message" class="edit-message" style="display:none;"></div>
                <div class="modal-actions">
                    <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button class="btn-save" onclick="saveCourse()">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>

            <!-- Assignments Tab -->
            <div id="panel-assignments" class="modal-panel" style="display:none;">
                <h3 class="modal-title">Assignments</h3>
                <div id="assignments_list">
                    <p class="no-data">Loading assignments...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentCourseFilter = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            updateDate();
            loadCourses();
            loadStudents();

            document.getElementById('course_filter').addEventListener('change', function(e) {
                currentCourseFilter = e.target.value;
                loadStudents();
            });

            // Close modal on overlay click
            document.getElementById('courseModal').addEventListener('click', function(e) {
                if (e.target === this) closeModal();
            });
        });

        function updateDate() {
            const dateElement = document.getElementById('current-date');
            const today = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateElement.textContent = today.toLocaleDateString('en-US', options);
        }

        function loadCourses() {
            const formData = new FormData();
            formData.append('action', 'get_all_courses');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('courses_tbody');
                tbody.innerHTML = '';

                if (data.courses.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="no-data">No courses assigned</td></tr>';
                    return;
                }

                data.courses.forEach((course) => {
                    const description = course.description ? course.description.substring(0, 60) + (course.description.length > 60 ? '...' : '') : 'No description';
                    
                    const row = document.createElement('tr');
                    row.className = 'table-row clickable-row';
                    row.innerHTML = `
                        <td class="cell-name">
                            <div class="course-avatar"><i class="fas fa-book"></i></div>
                            <span>${escapeHtml(course.course_name)}</span>
                        </td>
                        <td class="cell-description">${escapeHtml(description)}</td>
                        <td class="cell-count">
                            <span class="count-badge-small">${course.student_count}</span>
                        </td>
                        <td class="cell-actions">
                            <button class="btn-row-action btn-edit-course" onclick="openModal(event, ${course.course_id}, '${escapeJs(course.course_name)}', '${escapeJs(course.description || '')}')">
                                <i class="fas fa-pen"></i> Edit
                            </button>
                            <button class="btn-row-action btn-view-assignments" onclick="openModalAssignments(event, ${course.course_id}, '${escapeJs(course.course_name)}')">
                                <i class="fas fa-file-alt"></i> Assignments
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error loading courses:', error));
        }

        function loadStudents() {
            const filter = currentCourseFilter;
            const formData = new FormData();
            formData.append('action', 'get_students');
            formData.append('course_filter', filter);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('students_tbody');
                tbody.innerHTML = '';

                if (data.students.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="no-data">No students found</td></tr>';
                    return;
                }

                data.students.forEach((student) => {
                    const date = new Date(student.created_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });

                    const row = document.createElement('tr');
                    row.className = 'table-row';
                    row.innerHTML = `
                        <td class="cell-name">
                            <div class="student-avatar"><i class="fas fa-user-graduate"></i></div>
                            <span>${escapeHtml(student.name)}</span>
                        </td>
                        <td class="cell-email">${escapeHtml(student.email)}</td>
                        <td class="cell-date">${date}</td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error loading students:', error));
        }

        // ---- MODAL FUNCTIONS ----

        function openModal(e, courseId, courseName, description) {
            e.stopPropagation();
            document.getElementById('edit_course_id').value = courseId;
            document.getElementById('edit_course_name').value = courseName;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_message').style.display = 'none';
            switchTab('edit');
            document.getElementById('courseModal').style.display = 'flex';
        }

        function openModalAssignments(e, courseId, courseName) {
            e.stopPropagation();
            document.getElementById('edit_course_id').value = courseId;
            document.getElementById('edit_course_name').value = courseName;
            document.getElementById('edit_description').value = '';
            switchTab('assignments');
            loadAssignments(courseId);
            document.getElementById('courseModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('courseModal').style.display = 'none';
        }

        function switchTab(tab) {
            document.getElementById('panel-edit').style.display = tab === 'edit' ? 'block' : 'none';
            document.getElementById('panel-assignments').style.display = tab === 'assignments' ? 'block' : 'none';
            document.getElementById('tab-edit').classList.toggle('active', tab === 'edit');
            document.getElementById('tab-assignments').classList.toggle('active', tab === 'assignments');

            if (tab === 'assignments') {
                const courseId = document.getElementById('edit_course_id').value;
                loadAssignments(courseId);
            }
        }

        function loadAssignments(courseId) {
            const container = document.getElementById('assignments_list');
            container.innerHTML = '<p class="no-data">Loading...</p>';

            const formData = new FormData();
            formData.append('action', 'get_assignments');
            formData.append('course_id', courseId);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (!data.assignments || data.assignments.length === 0) {
                    container.innerHTML = '<p class="no-data">No assignments for this course yet.</p>';
                    return;
                }

                container.innerHTML = data.assignments.map(a => {
                    const due = a.due_date ? new Date(a.due_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'No due date';
                    const isPast = a.due_date && new Date(a.due_date) < new Date();
                    return `
                        <div class="assignment-card">
                            <div class="assignment-icon"><i class="fas fa-file-pen"></i></div>
                            <div class="assignment-info">
                                <h4>${escapeHtml(a.title)}</h4>
                                <p>${a.description ? escapeHtml(a.description.substring(0, 80)) + (a.description.length > 80 ? '...' : '') : 'No description'}</p>
                                <span class="due-badge ${isPast ? 'due-past' : 'due-upcoming'}">
                                    <i class="fas fa-clock"></i> Due: ${due}
                                </span>
                            </div>
                        </div>
                    `;
                }).join('');
            })
            .catch(() => {
                container.innerHTML = '<p class="no-data">Failed to load assignments.</p>';
            });
        }

        function saveCourse() {
            const courseId = document.getElementById('edit_course_id').value;
            const courseName = document.getElementById('edit_course_name').value.trim();
            const description = document.getElementById('edit_description').value.trim();
            const msgEl = document.getElementById('edit_message');

            if (!courseName) {
                showEditMessage('Course name cannot be empty.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_course');
            formData.append('course_id', courseId);
            formData.append('course_name', courseName);
            formData.append('description', description);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showEditMessage('Course updated successfully!', 'success');
                    loadCourses(); // Refresh the table
                    setTimeout(closeModal, 1200);
                } else {
                    showEditMessage(data.message || 'Update failed.', 'error');
                }
            })
            .catch(() => showEditMessage('An error occurred.', 'error'));
        }

        function showEditMessage(msg, type) {
            const el = document.getElementById('edit_message');
            el.textContent = msg;
            el.className = 'edit-message ' + type;
            el.style.display = 'block';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function escapeJs(text) {
            if (!text) return '';
            return String(text).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '');
        }
    </script>
</body>
</html>