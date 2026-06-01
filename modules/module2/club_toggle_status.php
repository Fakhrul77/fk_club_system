<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: ../module1/login.php");
    exit();
}

$toggle_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($toggle_id) {
    $stmt = $pdo->prepare("SELECT status FROM club WHERE club_id = ?");
    $stmt->execute([$toggle_id]);
    $status = $stmt->fetchColumn();
    if ($status !== false) {
        $new_status = ($status == 'Active') ? 'Inactive' : 'Active';
        $stmt = $pdo->prepare("UPDATE club SET status = ? WHERE club_id = ?");
        $stmt->execute([$new_status, $toggle_id]);
    }
}
header("Location: club_dashboard_admin.php");
exit();
?>