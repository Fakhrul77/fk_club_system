<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle cancellation
if (isset($_GET['cancel'])) {
    $event_id = (int)$_GET['cancel'];
    
    $stmt = $pdo->prepare("SELECT * FROM event_registration WHERE user_id = ? AND event_id = ? AND status NOT IN ('Attended', 'NoShow')");
    $stmt->execute([$user_id, $event_id]);
    
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE event_registration SET status = 'Cancelled', cancellation_date = NOW() WHERE user_id = ? AND event_id = ?")
            ->execute([$user_id, $event_id]);
        
        // Update event participant count
        $pdo->prepare("UPDATE event SET current_participant = current_participant - 1 WHERE event_id = ?")
            ->execute([$event_id]);
        
        // Promote from waiting list
        $waiting_stmt = $pdo->prepare("SELECT user_id FROM waiting_list WHERE event_id = ? ORDER BY position ASC LIMIT 1");
        $waiting_stmt->execute([$event_id]);
        $waiting_user = $waiting_stmt->fetch();
        
        if ($waiting_user) {
            $pdo->prepare("DELETE FROM waiting_list WHERE event_id = ? AND user_id = ?")->execute([$event_id, $waiting_user['user_id']]);
            $pdo->prepare("INSERT INTO event_registration (user_id, event_id, registration_date, status) VALUES (?, ?, NOW(), 'Confirmed')")
                ->execute([$waiting_user['user_id'], $event_id]);
            $pdo->prepare("UPDATE event SET current_participant = current_participant + 1 WHERE event_id = ?")->execute([$event_id]);
        }
    }
    header("Location: my_registrations.php");
    exit();
}

// Get user's registrations
$stmt = $pdo->prepare("
    SELECT er.*, e.event_title, e.event_date, e.event_time, e.venue, e.status as event_status, c.clubName, e.points_awarded
    FROM event_registration er
    JOIN event e ON er.event_id = e.event_id
    JOIN club c ON e.club_id = c.club_id
    WHERE er.user_id = ?
    ORDER BY e.event_date DESC
");
$stmt->execute([$user_id]);
$registrations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Registrations - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --umpsa-blue: #003B5C; --umpsa-gold: #FDB813; --umpsa-dark-blue: #002147; --umpsa-light-blue: #E8F0F8; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--umpsa-light-blue); overflow-x: hidden; }
        
        .sidebar {
            position: fixed; top: 0; left: 0; height: 100%; width: 260px;
            background: var(--umpsa-dark-blue); color: white; z-index: 1000;
        }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { margin: 0; font-size: 18px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: block; padding: 12px 25px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s; font-size: 14px;
        }
        .sidebar-header p {
         font-size: 11px;
         opacity: 0.7;
         margin-top: 5px;
         }

        .sidebar-menu a:hover { background: rgba(253,184,19,0.2); color: white; }
        .sidebar-menu a i { margin-right: 10px; width: 20px; }
        .sidebar-menu a.active { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); }
        
        .main-content { margin-left: 260px; padding: 20px; }
        
        .top-nav {
            background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .welcome-text { font-size: 16px; font-weight: 500; }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        
        .table-card {
            background: white; border-radius: 16px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .status-registered { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-cancelled { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-attended { background: #d1ecf1; color: #0c5460; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .action-btn { background: none; border: none; cursor: pointer; margin: 0 5px; color: #dc3545; font-size: 16px; }
        .action-btn:hover { color: #c82333; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="../../assets/images/logo.png" alt="Logo" style="width: 50px; height: auto; margin-bottom: 10px;">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <a href="../module1/dashboard_student.php">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module2/club_dashboard_student.php">
            <i class="fas fa-building"></i> <span>Browse Clubs</span>
        </a>
        <a href="../module3/browse_events.php">
            <i class="fas fa-calendar-alt"></i> <span>Browse Events</span>
        </a>
        <a href="../module3/my_registrations.php" class="active">
            <i class="fas fa-list"></i> <span>My Registrations</span>
        </a>
        <a href="../module4/my_points_recognition.php">
            <i class="fas fa-star"></i> <span>My Points</span>
        </a>
        <a href="../module1/profile.php">
            <i class="fas fa-user"></i> <span>Profile</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            <span class="badge-role">Student</span>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="table-card">
        <h3><i class="fas fa-list-alt"></i> My Event Registrations</h3>
        
        <div class="table-responsive mt-3">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Event</th><th>Club</th><th>Date</th><th>Venue</th><th>Points</th><th>Status</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reg['event_title']); ?></td>
                        <td><?php echo htmlspecialchars($reg['clubName']); ?></td>
                        <td><?php echo date('d M Y, h:i A', strtotime($reg['event_date'])); ?></td>
                        <td><?php echo htmlspecialchars($reg['venue']); ?></td>
                        <td><?php echo $reg['status'] == 'Attended' ? $reg['points_awarded'] : '-'; ?></td>
                        <td>
                            <span class="status-<?php echo strtolower($reg['status']); ?>">
                                <?php echo $reg['status']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($reg['status'] == 'Confirmed' && strtotime($reg['event_date']) > time()): ?>
                                <button class="action-btn" onclick="confirmCancel(<?php echo $reg['event_id']; ?>)" title="Cancel Registration">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmCancel(eventId) {
    if(confirm('Are you sure you want to cancel your registration for this event?')) {
        window.location.href = '?cancel=' + eventId;
    }
}
</script>
</body>
</html>