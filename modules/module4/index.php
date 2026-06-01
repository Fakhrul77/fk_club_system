<?php
/**
 * Module 4 - Manage Attendance & Reports
 * Main Entry Point and Navigation Hub
 */

session_start();
require_once '../../includes/db_connection.php';
require_once '../../includes/field_map.php';
require_once '../../includes/helpers.php';
// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Redirect based on user role
if ($user_role == 1) {
    // Admin - go to attendance dashboard
    header("Location: attendance_dashboard.php");
} elseif ($user_role == 2) {
    // Committee Member - go to attendance management
    header("Location: attendance_management.php");
} elseif ($user_role == 3) {
    // Student - go to points & recognition
    header("Location: my_points_recognition.php");
} else {
    header("Location: ../module1/login.php");
}
exit();
?>
