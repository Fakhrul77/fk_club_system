<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../module1/login.php");
    exit();
}

$user_role = $_SESSION['user_role'];
if ($user_role == 1) header("Location: club_dashboard_admin.php");
elseif ($user_role == 2) header("Location: club_dashboard_committee.php");
else header("Location: club_dashboard_student.php");
exit();
?>
