<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'instructor') {
    header("Location: ../login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

// ---- AJAX: Serve the submitted file securely ----
if (isset($_GET['action']) && $_GET['action'] === 'view_file' && isset($_GET['submission_id'])) {
    $sub_id = intval($_GET['submission_id']);

    // Verify this submission belongs to a course owned by this instructor
    $stmt = $conn->prepare("
        SELECT s.file_path
        FROM Submissions s
        JOIN Assignments a ON s.assignment_id = a.assignment_id
        JOIN Courses c ON a.course_id = c.course_id
        WHERE s.submission_id = ? AND c.instructor_id = ?
    ");
    $stmt->bind_param("ii", $sub_id, $instructor_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !$row['file_path']) {
        http_response_code(404);
        exit("File not found or access denied.");
    }

    $file_path = __DIR__ . "/../uploads/submissions/" . $row['file_path'];

    if (!file_exists($file_path)) {
        http_response_code(404);
        exit("File does not exist on server.");
    }

    $ext      = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_map = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'zip'  => 'application/zip',
    ];

    $mime        = $mime_map[$ext] ?? 'application/octet-stream';
    $inline_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
    $disposition  = in_array($ext, $inline_types) ? 'inline' : 'attachment';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . basename($row['file_path']) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($file_path);
    exit();
}

// ---- AJAX: Get submission info as JSON ----
if (isset($_GET['action']) && $_GET['action'] === 'get_submission' && isset($_GET['submission_id'])) {
    header('Content-Type: application/json');
    $sub_id = intval($_GET['submission_id']);

    $stmt = $conn->prepare("
        SELECT s.submission_id, s.file_path, s.submission_date,
               u.name AS student_name,
               a.title AS assignment_title
        FROM Submissions s
        JOIN Assignments a ON s.assignment_id = a.assignment_id
        JOIN Courses c ON a.course_id = c.course_id
        JOIN Users u ON s.student_id = u.user_id
        WHERE s.submission_id = ? AND c.instructor_id = ?
    ");
    $stmt->bind_param("ii", $sub_id, $instructor_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['error' => 'Not found']);
        exit();
    }

    $ext          = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
    $previewable  = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt']);
    $is_image     = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);

    echo json_encode([
        'submission_id'    => $row['submission_id'],
        'student_name'     => $row['student_name'],
        'assignment_title' => $row['assignment_title'],
        'file_name'        => basename($row['file_path']),
        'file_ext'         => $ext,
        'submission_date'  => $row['submission_date'],
        'previewable'      => $previewable,
        'is_image'         => $is_image,
    ]);
    exit();
}

// ---- Handle Grade Update (POST) ----
$message      = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sub_id = intval($_POST['submission_id']);
    $marks  = intval($_POST['marks']);

    // Clamp marks between 0 and 100
    $marks = max(0, min(100, $marks));

    $conn->begin_transaction();
    try {
        // Check if a grade row already exists
        $check = $conn->prepare("SELECT grade_id FROM Grades WHERE submission_id = ?");
        $check->bind_param("i", $sub_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;

        if ($exists) {
            $stmt = $conn->prepare("UPDATE Grades SET marks = ? WHERE submission_id = ?");
            $stmt->bind_param("ii", $marks, $sub_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO Grades (submission_id, marks) VALUES (?, ?)");
            $stmt->bind_param("ii", $sub_id, $marks);
        }

        $stmt->execute();
        $conn->commit();
        $message      = "Grade saved successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $message      = "Error occurred while grading. Please try again.";
        $message_type = "error";
    }
}

// ---- Fetch All Submissions for this instructor ----
$query = "
    SELECT s.submission_id,
           u.name        AS student_name,
           a.title       AS assignment_title,
           s.file_path,
           s.submission_date,
           g.marks
    FROM Submissions s
    JOIN Assignments a ON s.assignment_id = a.assignment_id
    JOIN Courses     c ON a.course_id = c.course_id
    JOIN Users       u ON s.student_id = u.user_id
    LEFT JOIN Grades g ON s.submission_id = g.submission_id
    WHERE c.instructor_id = ?
    ORDER BY u.name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submissions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/instructor_grade.css">
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
                <h2><i class="fas fa-star"></i> Grade Submissions</h2>
            </div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="add_assignment.php" class="nav-link">
                    <i class="fas fa-file-pen"></i> Add Assignment
                </a>
                <a class="nav-link nav-logout" href="../login.php?logout=1">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </header>

        <!-- Main Content -->
        <main class="main">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <div class="grading-section">
                <div class="section-header">
                    <h3><i class="fas fa-clipboard-check"></i> Student Submissions</h3>
                    <span class="count-badge"><?php echo $result->num_rows; ?></span>
                </div>

                <?php if ($result->num_rows > 0): ?>
                    <div class="table-wrapper">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user-graduate"></i> Student</th>
                                    <th><i class="fas fa-book"></i> Assignment</th>
                                    <th><i class="fas fa-calendar-alt"></i> Submitted</th>
                                    <th><i class="fas fa-eye"></i> File</th>
                                    <th><i class="fas fa-pen-fancy"></i> Marks</th>
                                    <th><i class="fas fa-save"></i> Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="grade-row">
                                        <td class="cell-student">
                                            <div class="student-avatar">
                                                <i class="fas fa-user-graduate"></i>
                                            </div>
                                            <span><?php echo htmlspecialchars($row['student_name']); ?></span>
                                        </td>
                                        <td class="cell-assignment">
                                            <?php echo htmlspecialchars($row['assignment_title']); ?>
                                        </td>
                                        <td class="cell-date">
                                            <?php
                                                echo $row['submission_date']
                                                    ? date('M j, Y', strtotime($row['submission_date']))
                                                    : '—';
                                            ?>
                                        </td>
                                        <td class="cell-file">
                                            <?php if ($row['file_path']): ?>
                                                <button
                                                    class="btn-view-file"
                                                    onclick="openFileModal(<?php echo $row['submission_id']; ?>)"
                                                    title="View submitted file">
                                                    <i class="fas fa-folder-open"></i>
                                                    <span>View File</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="no-file"><i class="fas fa-minus"></i> No file</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cell-marks">
                                            <form method="POST" class="grade-form">
                                                <input type="hidden" name="submission_id" value="<?php echo $row['submission_id']; ?>">
                                                <div class="marks-input-wrapper">
                                                    <input type="number"
                                                           name="marks"
                                                           class="marks-input"
                                                           value="<?php echo $row['marks'] !== null ? intval($row['marks']) : ''; ?>"
                                                           min="0"
                                                           max="100"
                                                           placeholder="—">
                                                    <span class="marks-unit">/100</span>
                                                </div>
                                        </td>
                                        <td class="cell-action">
                                                <button type="submit" class="btn-update">
                                                    <i class="fas fa-save"></i> Save
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Submissions Found</h3>
                        <p>There are no student submissions for your courses yet. Please check back later or encourage students to submit their assignments.</p>
                        <a href="dashboard.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- ===== FILE VIEWER MODAL ===== -->
    <div id="fileModal" class="file-modal-overlay" style="display:none;" onclick="handleOverlayClick(event)">
        <div class="file-modal-box">
            <!-- Modal Header -->
            <div class="file-modal-header">
                <div class="file-modal-title-group">
                    <div class="file-modal-icon" id="fileModalIcon">
                        <i class="fas fa-file"></i>
                    </div>
                    <div>
                        <h3 id="fileModalTitle">Loading...</h3>
                        <p id="fileModalMeta" class="file-modal-meta"></p>
                    </div>
                </div>
                <button class="file-modal-close" onclick="closeFileModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Student / Assignment Info Strip -->
            <div class="file-modal-info-strip">
                <div class="info-chip">
                    <i class="fas fa-user-graduate"></i>
                    <span id="fileModalStudent">—</span>
                </div>
                <div class="info-chip">
                    <i class="fas fa-book"></i>
                    <span id="fileModalAssignment">—</span>
                </div>
                <div class="info-chip">
                    <i class="fas fa-calendar-check"></i>
                    <span id="fileModalDate">—</span>
                </div>
            </div>

            <!-- Preview Area -->
            <div class="file-modal-preview" id="fileModalPreview">
                <div class="preview-loading" id="previewLoading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading preview...</p>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="file-modal-footer">
                <a id="fileModalDownload" href="#" class="btn-download" target="_blank" download>
                    <i class="fas fa-download"></i> Download File
                </a>
                <a id="fileModalOpen" href="#" class="btn-open-new" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Open in New Tab
                </a>
                <button class="btn-modal-close-footer" onclick="closeFileModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // ---- Auto-hide success alert ----
        document.addEventListener('DOMContentLoaded', function () {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(function () {
                    successAlert.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => successAlert.remove(), 300);
                }, 3000);
            }
        });

        // ---- File Modal ----
        function openFileModal(submissionId) {
            const modal   = document.getElementById('fileModal');
            const preview = document.getElementById('fileModalPreview');

            // Reset state
            document.getElementById('fileModalTitle').textContent      = 'Loading...';
            document.getElementById('fileModalMeta').textContent       = '';
            document.getElementById('fileModalStudent').textContent    = '—';
            document.getElementById('fileModalAssignment').textContent = '—';
            document.getElementById('fileModalDate').textContent       = '—';
            preview.innerHTML = '<div class="preview-loading"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            // Fetch submission metadata
            fetch('grade.php?action=get_submission&submission_id=' + submissionId)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        preview.innerHTML = '<div class="preview-error"><i class="fas fa-exclamation-triangle"></i><p>' + escHtml(data.error) + '</p></div>';
                        return;
                    }

                    // Populate header info
                    document.getElementById('fileModalTitle').textContent      = data.file_name;
                    document.getElementById('fileModalStudent').textContent    = data.student_name;
                    document.getElementById('fileModalAssignment').textContent = data.assignment_title;

                    const subDate = data.submission_date
                        ? new Date(data.submission_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                        : '—';
                    document.getElementById('fileModalDate').textContent = subDate;

                    // File type icon + colour
                    const iconEl = document.getElementById('fileModalIcon');
                    const extMeta = getExtMeta(data.file_ext);
                    iconEl.innerHTML  = '<i class="fas ' + extMeta.icon + '"></i>';
                    iconEl.style.background = extMeta.bg;
                    document.getElementById('fileModalMeta').textContent = data.file_ext.toUpperCase() + ' file';

                    // Build URLs
                    const viewUrl     = 'grade.php?action=view_file&submission_id=' + data.submission_id;
                    const downloadUrl = viewUrl + '&download=1';

                    document.getElementById('fileModalDownload').href = downloadUrl;
                    document.getElementById('fileModalOpen').href     = viewUrl;

                    // Render preview
                    if (data.is_image) {
                        preview.innerHTML = '<img src="' + viewUrl + '" class="preview-image" alt="Submitted file" onload="this.style.opacity=1" onerror="showPreviewError()">';
                    } else if (data.file_ext === 'pdf') {
                        preview.innerHTML = '<iframe src="' + viewUrl + '" class="preview-iframe" title="PDF Preview" onload="document.getElementById(\'previewLoading\')&&document.getElementById(\'previewLoading\').remove()"></iframe>';
                    } else if (data.file_ext === 'txt') {
                        // Fetch and display plain text inline
                        fetch(viewUrl)
                            .then(r => r.text())
                            .then(text => {
                                preview.innerHTML = '<pre class="preview-text">' + escHtml(text) + '</pre>';
                            })
                            .catch(() => showPreviewError());
                    } else {
                        // Non-previewable file — show download prompt
                        preview.innerHTML = `
                            <div class="preview-no-support">
                                <div class="no-preview-icon" style="background:${extMeta.bg}">
                                    <i class="fas ${extMeta.icon}"></i>
                                </div>
                                <h4>Preview not available</h4>
                                <p>This file type (${escHtml(data.file_ext.toUpperCase())}) cannot be previewed in the browser.</p>
                                <a href="${downloadUrl}" class="btn-download-inline" download>
                                    <i class="fas fa-download"></i> Download to open
                                </a>
                            </div>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    preview.innerHTML = '<div class="preview-error"><i class="fas fa-exclamation-triangle"></i><p>Failed to load file information.</p></div>';
                });
        }

        function closeFileModal() {
            document.getElementById('fileModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function handleOverlayClick(e) {
            if (e.target === document.getElementById('fileModal')) closeFileModal();
        }

        function showPreviewError() {
            document.getElementById('fileModalPreview').innerHTML =
                '<div class="preview-error"><i class="fas fa-exclamation-triangle"></i><p>Could not load preview. Try downloading the file.</p></div>';
        }

        function getExtMeta(ext) {
            const map = {
                pdf:  { icon: 'fa-file-pdf',       bg: 'linear-gradient(135deg,#ef4444,#dc2626)' },
                doc:  { icon: 'fa-file-word',       bg: 'linear-gradient(135deg,#3b82f6,#1d4ed8)' },
                docx: { icon: 'fa-file-word',       bg: 'linear-gradient(135deg,#3b82f6,#1d4ed8)' },
                xls:  { icon: 'fa-file-excel',      bg: 'linear-gradient(135deg,#10b981,#059669)' },
                xlsx: { icon: 'fa-file-excel',      bg: 'linear-gradient(135deg,#10b981,#059669)' },
                ppt:  { icon: 'fa-file-powerpoint', bg: 'linear-gradient(135deg,#f97316,#ea580c)' },
                pptx: { icon: 'fa-file-powerpoint', bg: 'linear-gradient(135deg,#f97316,#ea580c)' },
                jpg:  { icon: 'fa-file-image',      bg: 'linear-gradient(135deg,#a855f7,#7c3aed)' },
                jpeg: { icon: 'fa-file-image',      bg: 'linear-gradient(135deg,#a855f7,#7c3aed)' },
                png:  { icon: 'fa-file-image',      bg: 'linear-gradient(135deg,#a855f7,#7c3aed)' },
                gif:  { icon: 'fa-file-image',      bg: 'linear-gradient(135deg,#a855f7,#7c3aed)' },
                txt:  { icon: 'fa-file-lines',      bg: 'linear-gradient(135deg,#6b7280,#4b5563)' },
                zip:  { icon: 'fa-file-zipper',     bg: 'linear-gradient(135deg,#f59e0b,#d97706)' },
            };
            return map[ext] || { icon: 'fa-file', bg: 'linear-gradient(135deg,#6b7280,#4b5563)' };
        }

        function escHtml(text) {
            if (!text) return '';
            return String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        }
    </script>
</body>
</html>