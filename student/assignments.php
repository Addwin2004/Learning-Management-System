<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// ---- HANDLE AJAX REQUESTS ----
// BUG FIX: All action handlers must be INSIDE this single outer if-block.
// Previously, get_assignments and get_assignment_details were outside it,
// so they never executed as AJAX — they fell through to the HTML page render.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // ---- Submit Assignment ----
    if ($action === 'submit_assignment') {
        try {
            $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;

            if (!$assignment_id || !isset($_FILES['assignment_file'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit();
            }

            // Verify assignment belongs to a course the student is enrolled in
            $check_query = "SELECT a.assignment_id FROM Assignments a
                           JOIN Courses c ON a.course_id = c.course_id
                           JOIN Enrollments e ON c.course_id = e.course_id
                           WHERE a.assignment_id = ? AND e.student_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ii", $assignment_id, $student_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Assignment not found or not enrolled']);
                exit();
            }

            // Check if already submitted (allow resubmission)
            $submitted_query = "SELECT submission_id, file_path FROM Submissions WHERE assignment_id = ? AND student_id = ?";
            $submitted_stmt = $conn->prepare($submitted_query);
            $submitted_stmt->bind_param("ii", $assignment_id, $student_id);
            $submitted_stmt->execute();
            $submitted_result = $submitted_stmt->get_result();

            $existing_submission_id = null;
            $existing_file_path = null;
            if ($submitted_result->num_rows > 0) {
                $existing_row = $submitted_result->fetch_assoc();
                $existing_submission_id = $existing_row['submission_id'];
                $existing_file_path = $existing_row['file_path'];
            }

            // Validate uploaded file
            $file = $_FILES['assignment_file'];
            $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'png', 'zip'];
            $max_file_size = 10 * 1024 * 1024; // 10MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'File upload error (code ' . $file['error'] . ')']);
                exit();
            }

            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)]);
                exit();
            }

            if ($file['size'] > $max_file_size) {
                echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
                exit();
            }

            // Create upload directory if it doesn't exist
            $upload_dir = __DIR__ . "/../uploads/submissions/";
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
                    exit();
                }
            }

            // Generate unique filename
            $unique_filename = time() . '_' . $student_id . '_' . $assignment_id . '.' . $file_ext;
            $dest_path = $upload_dir . $unique_filename;

            // Move uploaded file to destination
            if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save file. Check server permissions.']);
                exit();
            }

            // Check if assignment is late
            $due_date_stmt = $conn->prepare("SELECT due_date FROM Assignments WHERE assignment_id = ?");
            $due_date_stmt->bind_param("i", $assignment_id);
            $due_date_stmt->execute();
            $due_date_row = $due_date_stmt->get_result()->fetch_assoc();
            $due_date = $due_date_row['due_date'];
            $is_late = ($due_date && strtotime('now') > strtotime($due_date)) ? 1 : 0;

            if ($existing_submission_id) {
                // Resubmission — delete old file first
                if ($existing_file_path) {
                    $old_full_path = $upload_dir . $existing_file_path;
                    if (file_exists($old_full_path)) {
                        unlink($old_full_path);
                    }
                }

                // Update existing submission record
                $update_stmt = $conn->prepare("UPDATE Submissions SET file_path = ?, submission_date = NOW() WHERE submission_id = ?");
                $update_stmt->bind_param("si", $unique_filename, $existing_submission_id);

                if ($update_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => $is_late ? 'Assignment resubmitted! (Late submission)' : 'Assignment resubmitted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error on update: ' . $conn->error]);
                }
            } else {
                // New submission — insert record
                $insert_stmt = $conn->prepare("INSERT INTO Submissions (assignment_id, student_id, file_path, submission_date) VALUES (?, ?, ?, NOW())");
                $insert_stmt->bind_param("iis", $assignment_id, $student_id, $unique_filename);

                if ($insert_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => $is_late ? 'Assignment submitted! (Late submission)' : 'Assignment submitted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error on insert: ' . $conn->error]);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit();
    }

    // ---- Get All Assignments ----
    // BUG FIX: This block was outside the outer POST/action check — moved inside.
    if ($action === 'get_assignments') {
        try {
            $query = "SELECT DISTINCT a.assignment_id, a.title, a.description, a.due_date,
                      c.course_name, c.course_id,
                      CASE WHEN s.submission_id IS NOT NULL THEN 'submitted' ELSE 'pending' END AS status,
                      COALESCE(s.submission_date, NULL) AS submission_date
                      FROM Assignments a
                      JOIN Courses c ON a.course_id = c.course_id
                      JOIN Enrollments e ON c.course_id = e.course_id
                      LEFT JOIN Submissions s ON a.assignment_id = s.assignment_id AND s.student_id = ?
                      WHERE e.student_id = ?
                      ORDER BY a.due_date ASC";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                echo json_encode(['assignments' => [], 'error' => $conn->error]);
                exit();
            }
            $stmt->bind_param("ii", $student_id, $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assignments = [];

            while ($row = $result->fetch_assoc()) {
                $assignments[] = $row;
            }

            echo json_encode(['assignments' => $assignments]);
        } catch (Exception $e) {
            echo json_encode(['assignments' => [], 'error' => $e->getMessage()]);
        }
        exit();
    }

    // ---- Get Assignment Details ----
    // BUG FIX: This block was also outside the outer POST/action check — moved inside.
    if ($action === 'get_assignment_details') {
        try {
            $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;

            if (!$assignment_id) {
                echo json_encode(['error' => 'Invalid assignment ID']);
                exit();
            }

            $query = "SELECT a.assignment_id, a.title, a.description, a.due_date, c.course_name,
                      CASE WHEN s.submission_id IS NOT NULL THEN 'submitted' ELSE 'pending' END AS status,
                      s.submission_id, s.submission_date
                      FROM Assignments a
                      JOIN Courses c ON a.course_id = c.course_id
                      JOIN Enrollments e ON c.course_id = e.course_id
                      LEFT JOIN Submissions s ON a.assignment_id = s.assignment_id AND s.student_id = ?
                      WHERE a.assignment_id = ? AND e.student_id = ?
                      LIMIT 1";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                echo json_encode(['error' => $conn->error]);
                exit();
            }
            $stmt->bind_param("iii", $student_id, $assignment_id, $student_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo json_encode($result->fetch_assoc());
            } else {
                echo json_encode(['error' => 'Assignment not found or not enrolled']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }

    // Unknown action fallback
    echo json_encode(['error' => 'Unknown action']);
    exit();
}

// ---- Page-load counts (non-AJAX) ----
try {
    $pending_stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT a.assignment_id) AS count
         FROM Assignments a
         JOIN Courses c ON a.course_id = c.course_id
         JOIN Enrollments e ON c.course_id = e.course_id
         LEFT JOIN Submissions s ON a.assignment_id = s.assignment_id AND s.student_id = ?
         WHERE e.student_id = ? AND s.submission_id IS NULL"
    );
    $pending_stmt->bind_param("ii", $student_id, $student_id);
    $pending_stmt->execute();
    $pending_count = $pending_stmt->get_result()->fetch_assoc()['count'] ?? 0;

    $submitted_stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT a.assignment_id) AS count
         FROM Assignments a
         JOIN Courses c ON a.course_id = c.course_id
         JOIN Enrollments e ON c.course_id = e.course_id
         JOIN Submissions s ON a.assignment_id = s.assignment_id AND s.student_id = ?
         WHERE e.student_id = ?"
    );
    $submitted_stmt->bind_param("ii", $student_id, $student_id);
    $submitted_stmt->execute();
    $submitted_count = $submitted_stmt->get_result()->fetch_assoc()['count'] ?? 0;
} catch (Exception $e) {
    $pending_count = 0;
    $submitted_count = 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/student_assignments.css">
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
                <h2><i class="fas fa-tasks"></i> My Assignments</h2>
            </div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="../login.php?logout=1" class="nav-link nav-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </header>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-pending">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending</p>
                </div>
            </div>

            <div class="stat-card stat-submitted">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $submitted_count; ?></h3>
                    <p>Submitted</p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="main">
            <div class="assignments-container" id="assignments_container">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading assignments...</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Assignment Details Modal -->
    <div id="assignmentModal" class="modal-overlay">
        <div class="modal-window">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-file-alt"></i>
                    <span id="modal_assignment_title">Assignment</span>
                </h2>
                <button type="button" class="modal-close-btn" onclick="closeAssignmentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="modal-section">
                    <h3>Course</h3>
                    <p id="modal_course_name">-</p>
                </div>

                <div class="modal-section">
                    <h3>Description</h3>
                    <div id="modal_assignment_description" class="description-text">-</div>
                </div>

                <div class="modal-section modal-section-inline">
                    <div class="info-item">
                        <label>Due Date</label>
                        <p id="modal_due_date">-</p>
                    </div>
                    <div class="info-item">
                        <label>Status</label>
                        <p><span id="modal_status" class="status-badge">-</span></p>
                    </div>
                </div>

                <!-- Already Submitted Info -->
                <div id="submission_info" class="modal-section" style="display: none;">
                    <h3>Submission Status</h3>
                    <div class="submission-details">
                        <p><strong>Submitted on:</strong> <span id="modal_submission_date">-</span></p>
                        <p style="color: #10b981; margin-top: 10px;">
                            <i class="fas fa-check-circle"></i> Assignment already submitted
                        </p>
                    </div>
                </div>

                <!-- Upload Form -->
                <form id="submissionForm" class="submission-form" onsubmit="submitAssignment(event)" style="display: none;">
                    <input type="hidden" id="modal_assignment_id" name="assignment_id">

                    <div class="form-group">
                        <label>
                            <i class="fas fa-paperclip"></i>
                            Upload Assignment File <span class="required">*</span>
                        </label>
                        <!-- BUG FIX: file input was hidden with no click trigger on the wrapper.
                             Using a visible label trick: clicking the wrapper triggers the hidden input. -->
                        <div class="file-input-wrapper" id="fileDropZone" onclick="document.getElementById('assignment_file').click()">
                            <input
                                type="file"
                                id="assignment_file"
                                name="assignment_file"
                                required
                                accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx,.jpg,.png,.zip"
                            >
                            <span class="file-input-label" id="fileLabel">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click to upload or drag and drop</span>
                                <small>Allowed: PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP (Max 10MB)</small>
                            </span>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-modal-cancel" onclick="closeAssignmentModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn-modal-submit">
                            <i class="fas fa-paper-plane"></i> Submit Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            loadAllAssignments();
            setupFileDrop();

            // Close modal when clicking outside the window
            document.getElementById('assignmentModal').addEventListener('click', function (e) {
                if (e.target === this) closeAssignmentModal();
            });
        });

        // ---- Load All Assignments ----
        function loadAllAssignments() {
            const container = document.getElementById('assignments_container');
            container.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading assignments...</p></div>';

            const formData = new FormData();
            formData.append('action', 'get_assignments');

            fetch('assignments.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    container.innerHTML = '';

                    if (data.error) {
                        container.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>${escapeHtml(data.error)}</p></div>`;
                        return;
                    }

                    if (!data.assignments || data.assignments.length === 0) {
                        container.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>No Assignments</h3>
                                <p>You don't have any assignments yet. Check back soon!</p>
                            </div>`;
                        return;
                    }

                    const pending   = data.assignments.filter(a => a.status === 'pending');
                    const submitted = data.assignments.filter(a => a.status === 'submitted');

                    if (pending.length > 0) {
                        const section = document.createElement('div');
                        section.className = 'assignments-section';
                        section.innerHTML = '<h3 class="section-title"><i class="fas fa-hourglass-half"></i> Pending Assignments</h3>';
                        const grid = document.createElement('div');
                        grid.className = 'assignments-grid';
                        pending.forEach((a, i) => grid.appendChild(createAssignmentCard(a, i)));
                        section.appendChild(grid);
                        container.appendChild(section);
                    }

                    if (submitted.length > 0) {
                        const section = document.createElement('div');
                        section.className = 'assignments-section';
                        section.innerHTML = '<h3 class="section-title submitted"><i class="fas fa-check-circle"></i> Submitted Assignments</h3>';
                        const grid = document.createElement('div');
                        grid.className = 'assignments-grid';
                        submitted.forEach((a, i) => grid.appendChild(createAssignmentCard(a, i + pending.length)));
                        section.appendChild(grid);
                        container.appendChild(section);
                    }
                })
                .catch(err => {
                    console.error('Load error:', err);
                    container.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Failed to load</h3><p>Please refresh the page.</p></div>`;
                });
        }

        // ---- Build Assignment Card ----
        function createAssignmentCard(assignment, index) {
            const dueDate = new Date(assignment.due_date);
            const today   = new Date();
            today.setHours(0, 0, 0, 0);

            const dueCopy = new Date(dueDate);
            dueCopy.setHours(0, 0, 0, 0);
            const isOverdue = dueCopy < today && assignment.status === 'pending';

            const dueDateStr = dueDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

            let statusClass = 'pending';
            let statusText  = 'Pending';
            if (assignment.status === 'submitted') {
                statusClass = 'submitted';
                statusText  = 'Submitted';
            } else if (isOverdue) {
                statusClass = 'overdue';
                statusText  = 'Overdue';
            }

            const descPreview = assignment.description
                ? escapeHtml(assignment.description.substring(0, 80)) + (assignment.description.length > 80 ? '...' : '')
                : 'No description';

            const card = document.createElement('div');
            card.className = 'assignment-card';
            card.style.animationDelay = (index * 0.08) + 's';
            card.innerHTML = `
                <div class="card-header">
                    <div class="header-content">
                        <h3>${escapeHtml(assignment.title)}</h3>
                        <p class="course-name"><i class="fas fa-book"></i> ${escapeHtml(assignment.course_name)}</p>
                    </div>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
                <div class="card-body">
                    <p class="description">${descPreview}</p>
                </div>
                <div class="card-footer">
                    <div class="due-date">
                        <i class="fas fa-calendar"></i>
                        <span>Due: ${dueDateStr}</span>
                    </div>
                    <button class="btn-view" onclick="openAssignmentModal(${assignment.assignment_id})">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>`;
            return card;
        }

        // ---- Open Modal ----
        function openAssignmentModal(assignmentId) {
            // Reset form and state
            document.getElementById('submissionForm').reset();
            resetFileLabel();
            document.getElementById('submissionForm').style.display = 'none';
            document.getElementById('submission_info').style.display = 'none';
            document.getElementById('modal_assignment_title').textContent = 'Loading...';

            document.getElementById('assignmentModal').classList.add('visible');
            document.body.style.overflow = 'hidden';

            const formData = new FormData();
            formData.append('action', 'get_assignment_details');
            formData.append('assignment_id', assignmentId);

            fetch('assignments.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(assignment => {
                    if (assignment.error) {
                        showAlert('Error: ' + assignment.error, 'error');
                        closeAssignmentModal();
                        return;
                    }

                    document.getElementById('modal_assignment_title').textContent = assignment.title;
                    document.getElementById('modal_course_name').textContent = assignment.course_name;
                    document.getElementById('modal_assignment_description').innerHTML =
                        escapeHtml(assignment.description || 'No description provided.').replace(/\n/g, '<br>');
                    document.getElementById('modal_assignment_id').value = assignment.assignment_id;

                    // Due date
                    const dueDate = new Date(assignment.due_date);
                    document.getElementById('modal_due_date').textContent = dueDate.toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    });

                    // Status badge
                    const statusEl  = document.getElementById('modal_status');
                    const isOverdue = dueDate < new Date() && assignment.status === 'pending';
                    const statusClass = isOverdue ? 'overdue' : assignment.status;
                    const statusText  = isOverdue ? 'Overdue' : (assignment.status === 'submitted' ? 'Submitted' : 'Pending');
                    statusEl.textContent = statusText;
                    statusEl.className   = 'status-badge ' + statusClass;

                    // Show form or submission info
                    if (assignment.status === 'submitted') {
                        document.getElementById('submission_info').style.display = 'block';
                        document.getElementById('submissionForm').style.display  = 'none';

                        const subDate = new Date(assignment.submission_date);
                        document.getElementById('modal_submission_date').textContent = subDate.toLocaleDateString('en-US', {
                            year: 'numeric', month: 'long', day: 'numeric',
                            hour: '2-digit', minute: '2-digit'
                        });
                    } else {
                        document.getElementById('submissionForm').style.display  = 'block';
                        document.getElementById('submission_info').style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error('Modal load error:', err);
                    showAlert('Error loading assignment details', 'error');
                    closeAssignmentModal();
                });
        }

        // ---- Close Modal ----
        function closeAssignmentModal() {
            document.getElementById('assignmentModal').classList.remove('visible');
            document.body.style.overflow = 'auto';
            document.getElementById('submissionForm').reset();
            resetFileLabel();
        }

        // ---- Submit Assignment ----
        function submitAssignment(event) {
            event.preventDefault();

            const fileInput = document.getElementById('assignment_file');
            if (!fileInput.files || fileInput.files.length === 0) {
                showAlert('Please select a file to upload.', 'error');
                return;
            }

            const formData = new FormData(document.getElementById('submissionForm'));
            formData.append('action', 'submit_assignment');

            const submitBtn = document.querySelector('#submissionForm button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            fetch('assignments.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeAssignmentModal();
                        loadAllAssignments();
                        showAlert(data.message || 'Assignment submitted successfully!', 'success');
                    } else {
                        showAlert('Error: ' + (data.message || 'Submission failed'), 'error');
                    }
                })
                .catch(err => {
                    console.error('Submit error:', err);
                    showAlert('Network error. Please try again.', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Assignment';
                });
        }

        // ---- File Drop Zone ----
        function setupFileDrop() {
            const fileInput = document.getElementById('assignment_file');
            const dropZone  = document.getElementById('fileDropZone');

            if (!dropZone || !fileInput) return;

            // Update label when file is chosen via dialog
            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    updateFileLabel(this.files[0].name);
                } else {
                    resetFileLabel();
                }
            });

            dropZone.addEventListener('dragover', e => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));

            dropZone.addEventListener('drop', e => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    updateFileLabel(e.dataTransfer.files[0].name);
                }
            });
        }

        function updateFileLabel(filename) {
            const label = document.getElementById('fileLabel');
            label.innerHTML = `
                <i class="fas fa-file-check" style="color:#10b981"></i>
                <span style="color:#10b981;font-weight:600">${escapeHtml(filename)}</span>
                <small>Click to change file</small>`;
        }

        function resetFileLabel() {
            const label = document.getElementById('fileLabel');
            if (label) {
                label.innerHTML = `
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Click to upload or drag and drop</span>
                    <small>Allowed: PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP (Max 10MB)</small>`;
            }
        }

        // ---- Alert Toast ----
        function showAlert(message, type) {
            // Remove any existing alerts
            document.querySelectorAll('.alert').forEach(a => a.remove());

            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${escapeHtml(message)}</span>`;
            document.body.insertBefore(alert, document.body.firstChild);

            setTimeout(() => {
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            }, 3500);
        }

        // BUG FIX: Added null guard so escapeHtml doesn't crash on null/undefined values
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>