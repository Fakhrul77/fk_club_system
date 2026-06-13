<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle cancellation with proper waiting list promotion
if (isset($_GET['cancel'])) {
    $event_id = (int)$_GET['cancel'];
    
    try {
        $pdo->beginTransaction();
        
        // Get registration
        $stmt = $pdo->prepare("SELECT registration_id FROM event_registration 
                               WHERE user_id = ? AND event_id = ? AND status = 'Confirmed'");
        $stmt->execute([$user_id, $event_id]);
        $registration = $stmt->fetch();
        
        if ($registration) {
            // Cancel registration
            $pdo->prepare("UPDATE event_registration SET status = 'Cancelled', cancellation_date = NOW() 
                           WHERE registration_id = ?")->execute([$registration['registration_id']]);
            
            // Decrease participant count
            $pdo->prepare("UPDATE event SET current_participant = current_participant - 1 
                           WHERE event_id = ?")->execute([$event_id]);
            
            // Get waiting list - FIXED: No placeholder in LIMIT
            $waiting_stmt = $pdo->prepare("
                SELECT waiting_id, user_id, position FROM waiting_list 
                WHERE event_id = ? ORDER BY position ASC LIMIT 1
            ");
            $waiting_stmt->execute([$event_id]);
            $waiting_user = $waiting_stmt->fetch();
            
            if ($waiting_user) {
                $old_position = $waiting_user['position'];
                
                // Remove from waiting list
                $pdo->prepare("DELETE FROM waiting_list WHERE waiting_id = ?")
                    ->execute([$waiting_user['waiting_id']]);
                
                // Add to registration
                $pdo->prepare("INSERT INTO event_registration (user_id, event_id, registration_date, status) 
                               VALUES (?, ?, NOW(), 'Confirmed')")
                    ->execute([$waiting_user['user_id'], $event_id]);
                
                // Update participant count
                $pdo->prepare("UPDATE event SET current_participant = current_participant + 1 
                               WHERE event_id = ?")->execute([$event_id]);
                
                // FIXED: Reorder remaining waiting list positions
                $pdo->prepare("UPDATE waiting_list SET position = position - 1 
                               WHERE event_id = ? AND position > ?")
                    ->execute([$event_id, $old_position]);
            }
        }
        
        $pdo->commit();
        header("Location: my_registrations.php?msg=cancelled");
        exit();
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error cancelling registration";
        header("Location: my_registrations.php");
        exit();
    }
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
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 260px;
            background: var(--umpsa-dark-blue);
            color: white;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header h4 {
            margin: 10px 0 0 0;
            font-size: 18px;
        }
        .sidebar-header p {
            margin: 5px 0 0 0;
            font-size: 11px;
            opacity: 0.7;
        }
        .sidebar-menu {
            padding: 20px 0;
        }
        .sidebar-menu a {
            display: block;
            padding: 12px 25px;
            margin: 5px 0;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        .sidebar-menu a:hover {
            background: rgba(253,184,19,0.2);
            color: white;
        }
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
        }
        .sidebar-menu a.active {
            background: var(--umpsa-gold);
            color: var(--umpsa-dark-blue);
        }
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
        
        .btn-qr {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
            margin: 0 2px;
        }
        .btn-qr:hover {
            background: #138496;
            color: white;
        }
        
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
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="table-card">
        <h3><i class="fas fa-list-alt"></i> My Event Registrations</h3>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <i class="fas fa-check-circle"></i>
                <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="table-responsive mt-3">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Club</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Points</th>
                        <th>Status</th>
                        <th>Action</th>
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
                            <!-- QR Button -->
                           <a href="../module4/generate_qr.php?event_id=<?php echo $reg['event_id']; ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn-qr">
    <i class="fas fa-qrcode"></i> QR
</a>
                            
                            <!-- Cancel Button -->
                            <?php if ($reg['status'] == 'Confirmed' && strtotime($reg['event_date']) > time()): ?>
                                <button class="action-btn" onclick="openCancelModal(<?php echo $reg['event_id']; ?>)" title="Cancel Registration">
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

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
            <!-- Header -->
            <div style="background: var(--umpsa-dark-blue); color: white; padding: 15px;">
                <h5 style="margin: 0; font-weight: 600;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--umpsa-gold);"></i>
                    Confirm Cancellation
                </h5>
            </div>
            <!-- Body -->
            <div style="padding: 20px; font-size: 14px; color: #333;">
                <p style="margin-bottom: 10px;">
                    You are about to cancel your event registration.
                </p>
                <div style="background: #fff3cd; padding: 10px; border-radius: 8px; font-size: 13px;">
                    ⚠️ Once cancelled, your spot may be given to another student on the waiting list.
                </div>
            </div>
            <!-- Footer -->
            <div style="padding: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Keep Registration
                </button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">
                    Yes, Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/logout_modal.php'; ?>

<script>
let selectedEventId = null;

function openCancelModal(eventId) {
    selectedEventId = eventId;
    const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
    modal.show();
}

document.getElementById('confirmCancelBtn').addEventListener('click', function () {
    if (selectedEventId) {
        window.location.href = '?cancel=' + selectedEventId;
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>