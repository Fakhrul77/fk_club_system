<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: ../module1/login.php");
    exit();
}

$committee_id = filter_var($_GET['committee_id'] ?? 0, FILTER_VALIDATE_INT);
$club_id = filter_var($_GET['club_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$committee_id || !$club_id) {
    header("Location: committee_assign.php?id=$club_id&msg=error");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM club_committee WHERE committee_id = ?");
    $stmt->execute([$committee_id]);
    $user = $stmt->fetch();
    $user_id = $user['user_id'] ?? null;
    
    $stmt = $pdo->prepare("DELETE FROM club_committee WHERE committee_id = ? AND club_id = ?");
    $stmt->execute([$committee_id, $club_id]);
    
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_committee WHERE user_id = ? AND status = 'Active'");
        $stmt->execute([$user_id]);
        $other_roles = $stmt->fetchColumn();
        if ($other_roles == 0) {
            $stmt = $pdo->prepare("UPDATE users SET role_id = 3 WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
    }
    header("Location: committee_assign.php?id=$club_id&msg=removed");
    exit();
} catch(PDOException $e) {
    header("Location: committee_assign.php?id=$club_id&msg=error");
    exit();
}
?>