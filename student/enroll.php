<?php
session_start();
include("../config/db.php");

$student_id = $_SESSION['user_id'];
$course_id = $_GET['id'];

$stmt = $conn->prepare("CALL EnrollStudent(?, ?)");
$stmt->bind_param("ii", $student_id, $course_id);
$stmt->execute();

header("Location: dashboard.php");
?>