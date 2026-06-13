<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 1 && $_SESSION['user_role'] != 2)) {
    header("Location: ../module1/login.php");
    exit();
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if ($event_id <= 0) {
    header("Location: manage_events.php");
    exit();
}

// Get event details
$stmt = $pdo->prepare("SELECT current_participant, max_participant FROM event WHERE event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: manage_events.php");
    exit();
}

$available_spots = $event['max_participant'] - $event['current_participant'];
$promoted_count = 0;

if ($available_spots > 0) {
    $limit = (int)$available_spots;
    
    $waiting_stmt = $pdo->prepare("
        SELECT waiting_id, user_id, position FROM waiting_list 
        WHERE event_id = ? 
        ORDER BY position ASC 
        LIMIT $limit
    ");
    $waiting_stmt->execute([$event_id]);
    $waiting_users = $waiting_stmt->fetchAll();
    
    $promoted_positions = [];  // ← NEW: Track promoted positions
    
    foreach ($waiting_users as $waiting_user) {
        $promoted_positions[] = $waiting_user['position'];  // ← NEW: Store position
        
        $pdo->prepare("DELETE FROM waiting_list WHERE waiting_id = ?")->execute([$waiting_user['waiting_id']]);
        $pdo->prepare("INSERT INTO event_registration (user_id, event_id, registration_date, status) VALUES (?, ?, NOW(), 'Confirmed')")->execute([$waiting_user['user_id'], $event_id]);
        $pdo->prepare("UPDATE event SET current_participant = current_participant + 1 WHERE event_id = ?")->execute([$event_id]);
        $promoted_count++;
    }
    
    // ← NEW: Reorder remaining waiting list positions
    if (!empty($promoted_positions)) {
        $pdo->prepare("SET @pos = 0")->execute();
        $pdo->prepare("UPDATE waiting_list SET position = (@pos := @pos + 1) WHERE event_id = ? ORDER BY position ASC")
            ->execute([$event_id]);
    }
}

// Redirect back
header("Location: view_event.php?id=$event_id&msg=promoted&count=$promoted_count");
exit();
?>