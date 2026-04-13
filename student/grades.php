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
SELECT a.title, g.marks, GetGradeStatus(g.marks) as status
FROM Grades g
JOIN Submissions s ON g.submission_id = s.submission_id
JOIN Assignments a ON s.assignment_id = a.assignment_id
WHERE s.student_id = ?
ORDER BY a.title ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Calculate average marks
$avg_query = "SELECT AVG(g.marks) as average FROM Grades g
              JOIN Submissions s ON g.submission_id = s.submission_id
              WHERE s.student_id = ?";
$avg_stmt = $conn->prepare($avg_query);
$avg_stmt->bind_param("i", $student_id);
$avg_stmt->execute();
$avg_result = $avg_stmt->get_result();
$avg_row = $avg_result->fetch_assoc();
$average = $avg_row['average'] ? round($avg_row['average'], 2) : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/student_grades.css">
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
                <h2><i class="fas fa-star"></i> My Grades</h2>
            </div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a class="nav-link nav-logout" href="../login.php?logout=1">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </header>

        <!-- Main Content -->
        <main class="main">
            <?php if ($result->num_rows > 0) { ?>
                <!-- Summary Section -->
                <div class="summary-section">
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="summary-content">
                            <p class="summary-label">Average Score</p>
                            <h3 class="summary-value"><?php echo $average; ?>/100</h3>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-list-check"></i>
                        </div>
                        <div class="summary-content">
                            <p class="summary-label">Assignments Graded</p>
                            <h3 class="summary-value"><?php echo $result->num_rows; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Grades Table -->
                <div class="grades-section">
                    <div class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>Assignment Grades</h3>
                    </div>

                    <div class="table-wrapper">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-file-alt"></i> Assignment Title</th>
                                    <th><i class="fas fa-pen-fancy"></i> Marks</th>
                                    <th><i class="fas fa-badge"></i> Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()) { 
                                    $marks = intval($row['marks']);
                                    $status = htmlspecialchars($row['status']);
                                    
                                    // Determine status badge color
                                    $statusClass = 'status-excellent';
                                    if ($marks < 50) {
                                        $statusClass = 'status-poor';
                                    } elseif ($marks < 65) {
                                        $statusClass = 'status-fair';
                                    } elseif ($marks < 80) {
                                        $statusClass = 'status-good';
                                    }
                                ?>
                                    <tr class="grade-row">
                                        <td class="cell-title">
                                            <i class="fas fa-file-alt"></i>
                                            <span><?php echo htmlspecialchars($row['title']); ?></span>
                                        </td>
                                        <td class="cell-marks">
                                            <div class="marks-box">
                                                <span class="marks-value"><?php echo $marks; ?></span>
                                                <span class="marks-unit">/100</span>
                                            </div>
                                        </td>
                                        <td class="cell-status">
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grade Legend -->
                <div class="legend-section">
                    <h4><i class="fas fa-info-circle"></i> Grade Legend</h4>
                    <div class="legend-grid">
                        <div class="legend-item">
                            <span class="legend-color excellent"></span>
                            <span class="legend-text">Excellent (80+)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color good"></span>
                            <span class="legend-text">Good (65-79)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color fair"></span>
                            <span class="legend-text">Fair (50-64)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color poor"></span>
                            <span class="legend-text">Poor (<50)</span>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Grades Yet</h3>
                    <p>You don't have any grades available at the moment. Check back soon after your assignments are graded.</p>
                    <a href="dashboard.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            <?php } ?>
        </main>
    </div>
</body>
</html>