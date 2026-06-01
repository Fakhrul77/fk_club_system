<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../module1/login.php");
    exit();
}

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$event_id) {
    header("Location: manage_events.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT e.*, c.clubName, u.name as creator_name 
    FROM event e 
    JOIN club c ON e.club_id = c.club_id 
    LEFT JOIN users u ON e.created_by = u.user_id 
    WHERE e.event_id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: manage_events.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Details - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --umpsa-blue: #003B5C; --umpsa-gold: #FDB813; --umpsa-dark-blue: #002147; --umpsa-light-blue: #E8F0F8; }
        body { background: var(--umpsa-light-blue); font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 900px; margin: 50px auto; }
        .event-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .event-title { font-size: 28px; font-weight: bold; color: var(--umpsa-blue); }
        .event-club { color: var(--umpsa-gold); font-size: 18px; margin-bottom: 20px; }
        .event-detail { display: flex; gap: 20px; flex-wrap: wrap; margin: 20px 0; }
        .detail-box { background: var(--umpsa-light-blue); padding: 10px 15px; border-radius: 10px; }
        .btn-back { background: #6c757d; color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; }
        .btn-back:hover { background: #5a6268; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="event-card">
            <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
            <div class="event-club"><i class="fas fa-building"></i> <?php echo htmlspecialchars($event['clubName']); ?></div>
            
            <div class="event-detail">
                <div class="detail-box"><i class="fas fa-calendar-day"></i> <?php echo date('d M Y', strtotime($event['event_date'])); ?></div>
                <div class="detail-box"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?></div>
                <div class="detail-box"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($event['venue']); ?></div>
                <div class="detail-box"><i class="fas fa-users"></i> <?php echo $event['current_participant']; ?>/<?php echo $event['max_participant']; ?> participants</div>
            </div>
            
            <div class="mb-4">
                <h5>Description</h5>
                <p><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></p>
            </div>
            
            <div class="mb-4">
                <h5>Status</h5>
                <span class="badge bg-<?php 
                    echo $event['status'] == 'UPCOMING' ? 'success' : ($event['status'] == 'ONGOING' ? 'warning' : 'secondary'); 
                ?>"><?php echo $event['status']; ?></span>
            </div>
            
            <a href="manage_events.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Events</a>
        </div>
    </div>
</body>
</html>