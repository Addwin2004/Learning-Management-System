<?php
$conn = new mysqli("localhost", "root", "", "lms");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>