<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: ../module1/login.php");
    exit();
}

$delete_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($delete_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM club WHERE club_id = ?");
        $stmt->execute([$delete_id]);
        header("Location: club_dashboard_admin.php?msg=deleted");
        exit();
    } catch(PDOException $e) {
        header("Location: club_dashboard_admin.php?msg=error");
        exit();
    }
}
header("Location: club_dashboard_admin.php");
exit();
?>