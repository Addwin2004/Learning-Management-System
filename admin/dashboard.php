<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// ---- HANDLE AJAX REQUESTS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];

    // Get Users
    if ($action === 'get_users') {
        $filter = isset($_POST['filter']) ? $_POST['filter'] : 'all';
        $query = "SELECT user_id, name, email, role, created_at FROM Users";
        
        if ($filter != 'all') {
            $filter = $conn->real_escape_string($filter);
            $query .= " WHERE role = '$filter'";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT 10";
        $result = $conn->query($query);
        $users = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
        
        echo json_encode(['users' => $users]);
        exit();
    }

    // Get Courses
    if ($action === 'get_courses') {
        $filter = isset($_POST['filter']) ? $_POST['filter'] : 'all';
        $query = "SELECT c.course_id, c.course_name, c.description, u.name as instructor_name, c.instructor_id FROM Courses c LEFT JOIN Users u ON c.instructor_id = u.user_id";
        
        if ($filter == 'unassigned') {
            $query .= " WHERE c.instructor_id IS NULL";
        }
        
        $query .= " ORDER BY c.course_id DESC LIMIT 10";
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

    // Get Single User
    if ($action === 'get_user') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $query = "SELECT user_id, name, email, role FROM Users WHERE user_id = $user_id";
        $result = $conn->query($query);
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo json_encode($user);
        } else {
            echo json_encode(['error' => 'User not found']);
        }
        exit();
    }

    // Get Single Course
    if ($action === 'get_course') {
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $query = "SELECT course_id, course_name, description, instructor_id FROM Courses WHERE course_id = $course_id";
        $result = $conn->query($query);
        
        if ($result->num_rows > 0) {
            $course = $result->fetch_assoc();
            echo json_encode($course);
        } else {
            echo json_encode(['error' => 'Course not found']);
        }
        exit();
    }

    // Get Instructors
    if ($action === 'get_instructors') {
        $query = "SELECT user_id, name FROM Users WHERE role = 'instructor' ORDER BY name ASC";
        $result = $conn->query($query);
        $instructors = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $instructors[] = $row;
            }
        }
        
        echo json_encode(['instructors' => $instructors]);
        exit();
    }

    // Update User
    if ($action === 'update_user') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $role = isset($_POST['role']) ? $_POST['role'] : '';

        if (!$user_id || !$name || !$email || !$role) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }

        $name = $conn->real_escape_string($name);
        $email = $conn->real_escape_string($email);
        $role = $conn->real_escape_string($role);

        $query = "UPDATE Users SET name = '$name', email = '$email', role = '$role' WHERE user_id = $user_id";

        if ($conn->query($query)) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit();
    }

    // Update Course
    if ($action === 'update_course') {
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $course_name = isset($_POST['course_name']) ? trim($_POST['course_name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $instructor_id = isset($_POST['instructor_id']) && $_POST['instructor_id'] !== '' ? intval($_POST['instructor_id']) : null;

        if (!$course_id || !$course_name) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }

        $course_name = $conn->real_escape_string($course_name);
        $description = $conn->real_escape_string($description);

        $query = "UPDATE Courses SET course_name = '$course_name', description = '$description', instructor_id = " . ($instructor_id ? $instructor_id : 'NULL') . " WHERE course_id = $course_id";

        if ($conn->query($query)) {
            echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit();
    }
}

// ---- REGULAR PAGE LOAD ----
$total_users = $conn->query("SELECT COUNT(*) as count FROM Users")->fetch_assoc()['count'];
$total_courses = $conn->query("SELECT COUNT(*) as count FROM Courses")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
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
                <div class="admin-greeting">
                    <i class="fas fa-crown"></i>
                    <div class="greeting-text">
                        <p class="greeting-label">Welcome Back</p>
                        <h2>Admin: <?php echo $_SESSION['name']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <span class="timestamp">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="current-date"></span>
                </span>
            </div>
        </header>

        <!-- Navigation Section -->
        <nav class="nav-section">
            <h3 class="nav-title"><i class="fas fa-tasks"></i> Admin Actions</h3>
            <div class="nav-grid">
                <a href="add_user.php" class="nav-card nav-success">
                    <div class="nav-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Add User</h4>
                        <p>Create new user account</p>
                    </div>
                    <i class="fas fa-arrow-right nav-arrow"></i>
                </a>

                <a href="create_course.php" class="nav-card nav-info">
                    <div class="nav-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Create Course</h4>
                        <p>Add new course content</p>
                    </div>
                    <i class="fas fa-arrow-right nav-arrow"></i>
                </a>

                <a href="../login.php?logout=1" class="nav-card nav-danger">
                    <div class="nav-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="nav-content">
                        <h4>Logout</h4>
                        <p>End your session</p>
                    </div>
                    <i class="fas fa-arrow-right nav-arrow"></i>
                </a>
            </div>
        </nav>

        <!-- Users Section -->
        <section class="data-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    <h3>System Users</h3>
                    <span class="count-badge"><?php echo $total_users; ?></span>
                </div>
                <div class="filter-controls">
                    <select id="user_filter" class="filter-select">
                        <option value="all">All Users</option>
                        <option value="admin">Admins</option>
                        <option value="instructor">Instructors</option>
                        <option value="student">Students</option>
                    </select>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Name</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-shield-alt"></i> Role</th>
                            <th><i class="fas fa-calendar"></i> Joined</th>
                            <th><i class="fas fa-edit"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody id="users_tbody">
                        <tr><td colspan="5" class="no-data">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Courses Section -->
        <section class="data-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-book-open"></i>
                    <h3>Available Courses</h3>
                    <span class="count-badge"><?php echo $total_courses; ?></span>
                </div>
                <div class="filter-controls">
                    <select id="course_filter" class="filter-select">
                        <option value="all">All Courses</option>
                        <option value="unassigned">Unassigned</option>
                    </select>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-book"></i> Course Name</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-chalkboard-user"></i> Instructor</th>
                            <th><i class="fas fa-edit"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody id="courses_tbody">
                        <tr><td colspan="4" class="no-data">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Main Content -->
        <main class="main-content">
            <div class="welcome-section">
                <h3><i class="fas fa-info-circle"></i> Dashboard Info</h3>
                <p>Showing the most recent 10 users and courses. Use the filters above to view specific categories. Click Edit to modify user or course details.</p>
            </div>
        </main>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button type="button" class="modal-close" onclick="closeUserModal()">&times;</button>
            </div>
            <form id="editUserForm" onsubmit="saveUser(event)">
                <div class="form-group">
                    <label for="edit_name">Full Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="edit_role">Role</label>
                    <select id="edit_role" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="instructor">Instructor</option>
                        <option value="student">Student</option>
                    </select>
                </div>
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeUserModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div id="editCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Course</h2>
                <button type="button" class="modal-close" onclick="closeCourseModal()">&times;</button>
            </div>
            <form id="editCourseForm" onsubmit="saveCourse(event)">
                <div class="form-group">
                    <label for="edit_course_name">Course Name</label>
                    <input type="text" id="edit_course_name" name="course_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_instructor_id">Instructor</label>
                    <select id="edit_instructor_id" name="instructor_id">
                        <option value="">-- Unassigned --</option>
                        <option value="placeholder">Loading...</option>
                    </select>
                </div>
                <input type="hidden" id="edit_course_id" name="course_id">
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeCourseModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentUserFilter = 'all';
        let currentCourseFilter = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            updateDate();
            loadUsers();
            loadCourses();
            loadInstructors();

            document.getElementById('user_filter').addEventListener('change', function(e) {
                currentUserFilter = e.target.value;
                loadUsers();
            });

            document.getElementById('course_filter').addEventListener('change', function(e) {
                currentCourseFilter = e.target.value;
                loadCourses();
            });
        });

        function updateDate() {
            const dateElement = document.getElementById('current-date');
            const today = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateElement.textContent = today.toLocaleDateString('en-US', options);
        }

        function loadUsers() {
            const filter = currentUserFilter;
            const formData = new FormData();
            formData.append('action', 'get_users');
            formData.append('filter', filter);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('users_tbody');
                tbody.innerHTML = '';

                if (data.users.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="no-data">No users found</td></tr>';
                    return;
                }

                data.users.forEach((user) => {
                    const date = new Date(user.created_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });

                    const roleClass = 'role-' + user.role;
                    const row = document.createElement('tr');
                    row.className = 'table-row';
                    row.innerHTML = `
                        <td class="cell-name">
                            <div class="user-avatar"><i class="fas fa-user"></i></div>
                            <span>${escapeHtml(user.name)}</span>
                        </td>
                        <td class="cell-email">${escapeHtml(user.email)}</td>
                        <td class="cell-role">
                            <span class="role-badge ${roleClass}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span>
                        </td>
                        <td class="cell-date">${date}</td>
                        <td class="cell-action">
                            <button class="btn-edit" onclick="openUserModal(${user.user_id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error loading users:', error));
        }

        function loadCourses() {
            const filter = currentCourseFilter;
            const formData = new FormData();
            formData.append('action', 'get_courses');
            formData.append('filter', filter);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('courses_tbody');
                tbody.innerHTML = '';

                if (data.courses.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="no-data">No courses found</td></tr>';
                    return;
                }

                data.courses.forEach((course) => {
                    const instructor = course.instructor_name || 'Unassigned';
                    const instructorClass = course.instructor_id ? '' : 'unassigned';
                    const description = course.description ? course.description.substring(0, 50) + (course.description.length > 50 ? '...' : '') : '';

                    const row = document.createElement('tr');
                    row.className = 'table-row';
                    row.innerHTML = `
                        <td class="cell-name">
                            <div class="course-avatar"><i class="fas fa-book"></i></div>
                            <span>${escapeHtml(course.course_name)}</span>
                        </td>
                        <td class="cell-description">${escapeHtml(description)}</td>
                        <td class="cell-instructor">
                            <span class="instructor-badge ${instructorClass}">${escapeHtml(instructor)}</span>
                        </td>
                        <td class="cell-action">
                            <button class="btn-edit" onclick="openCourseModal(${course.course_id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            })
            .catch(error => console.error('Error loading courses:', error));
        }

        function loadInstructors() {
            const formData = new FormData();
            formData.append('action', 'get_instructors');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('edit_instructor_id');
                select.innerHTML = '<option value="">-- Unassigned --</option>';
                data.instructors.forEach(instructor => {
                    const option = document.createElement('option');
                    option.value = instructor.user_id;
                    option.textContent = instructor.name;
                    select.appendChild(option);
                });
            })
            .catch(error => console.error('Error loading instructors:', error));
        }

        function openUserModal(userId) {
            const formData = new FormData();
            formData.append('action', 'get_user');
            formData.append('user_id', userId);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(user => {
                document.getElementById('edit_user_id').value = user.user_id;
                document.getElementById('edit_name').value = user.name;
                document.getElementById('edit_email').value = user.email;
                document.getElementById('edit_role').value = user.role;
                const modal = document.getElementById('editUserModal');
                modal.classList.add('show');
                modal.style.display = 'flex';
            })
            .catch(error => console.error('Error loading user:', error));
        }

        function closeUserModal() {
            const modal = document.getElementById('editUserModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
        }

        function saveUser(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('editUserForm'));
            formData.append('action', 'update_user');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeUserModal();
                    loadUsers();
                    alert('User updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving user');
            });
        }

        function openCourseModal(courseId) {
            const formData = new FormData();
            formData.append('action', 'get_course');
            formData.append('course_id', courseId);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(course => {
                document.getElementById('edit_course_id').value = course.course_id;
                document.getElementById('edit_course_name').value = course.course_name;
                document.getElementById('edit_description').value = course.description || '';
                document.getElementById('edit_instructor_id').value = course.instructor_id || '';
                const modal = document.getElementById('editCourseModal');
                modal.classList.add('show');
                modal.style.display = 'flex';
            })
            .catch(error => console.error('Error loading course:', error));
        }

        function closeCourseModal() {
            const modal = document.getElementById('editCourseModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
        }

        function saveCourse(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('editCourseForm'));
            formData.append('action', 'update_course');

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeCourseModal();
                    loadCourses();
                    alert('Course updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving course');
            });
        }

        window.onclick = function(event) {
            const userModal = document.getElementById('editUserModal');
            const courseModal = document.getElementById('editCourseModal');
            if (event.target == userModal) {
                closeUserModal();
            }
            if (event.target == courseModal) {
                closeCourseModal();
            }
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