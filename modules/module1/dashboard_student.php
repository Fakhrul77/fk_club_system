<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle cancel registration
if (isset($_GET['cancel_event'])) {
    $event_id = (int)$_GET['cancel_event'];
    
    // Check if user has active registration
    $stmt = $pdo->prepare("SELECT registration_id FROM event_registration WHERE user_id = ? AND event_id = ? AND status = 'Confirmed'");
    $stmt->execute([$user_id, $event_id]);
    $registration = $stmt->fetch();
    
    if ($registration) {
        // Update registration status to Cancelled
        $pdo->prepare("UPDATE event_registration SET status = 'Cancelled', cancellation_date = NOW() WHERE registration_id = ?")
            ->execute([$registration['registration_id']]);
        
        // Update event participant count
        $pdo->prepare("UPDATE event SET current_participant = current_participant - 1 WHERE event_id = ?")
            ->execute([$event_id]);
        
        // Promote from waiting list if any
        $waiting_stmt = $pdo->prepare("SELECT user_id, waiting_id FROM waiting_list WHERE event_id = ? ORDER BY position ASC LIMIT 1");
        $waiting_stmt->execute([$event_id]);
        $waiting_user = $waiting_stmt->fetch();
        
        if ($waiting_user) {
            $pdo->prepare("DELETE FROM waiting_list WHERE waiting_id = ?")->execute([$waiting_user['waiting_id']]);
            $pdo->prepare("INSERT INTO event_registration (user_id, event_id, registration_date, status) VALUES (?, ?, NOW(), 'Confirmed')")
                ->execute([$waiting_user['user_id'], $event_id]);
            $pdo->prepare("UPDATE event SET current_participant = current_participant + 1 WHERE event_id = ?")->execute([$event_id]);
        }
    }
    header("Location: dashboard_student.php?msg=cancelled");
    exit();
}

// Check for messages
$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'cancelled') {
        $message = '<div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> Registration cancelled successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
}

// Get user's total points
$stmt = $pdo->prepare("SELECT SUM(pointsEarned) as total FROM activity_points WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalPoints = $stmt->fetchColumn() ?? 0;

// Get clubs joined count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE user_id = ? AND status = 'Active'");
$stmt->execute([$user_id]);
$clubsJoined = $stmt->fetchColumn() ?? 0;

// Get upcoming registered events
$upcomingEvents = $pdo->prepare("
    SELECT e.*, c.clubName, er.registration_id, er.status as reg_status
    FROM event_registration er
    JOIN event e ON er.event_id = e.event_id
    JOIN club c ON e.club_id = c.club_id
    WHERE er.user_id = ? AND e.event_date >= CURDATE() AND er.status = 'Confirmed'
    ORDER BY e.event_date ASC LIMIT 5
");
$upcomingEvents->execute([$user_id]);
$upcomingEventsList = $upcomingEvents->fetchAll();

// Get clubs joined
$joinedClubs = $pdo->prepare("
    SELECT c.*, cm.joinDate 
    FROM club_membership cm
    JOIN club c ON cm.club_id = c.club_id
    WHERE cm.user_id = ? AND cm.status = 'Active'
");
$joinedClubs->execute([$user_id]);
$joinedClubsList = $joinedClubs->fetchAll();

// Get past events history
$pastEvents = $pdo->prepare("
    SELECT e.*, c.clubName, a.attendanceStatus, ap.pointsEarned
    FROM event_registration er
    JOIN event e ON er.event_id = e.event_id
    JOIN club c ON e.club_id = c.club_id
    LEFT JOIN attendance a ON a.registration_id = er.registration_id
    LEFT JOIN activity_points ap ON ap.user_id = er.user_id AND ap.event_id = e.event_id
    WHERE er.user_id = ? AND e.event_date < CURDATE()
    ORDER BY e.event_date DESC LIMIT 5
");
$pastEvents->execute([$user_id]);
$pastEventsList = $pastEvents->fetchAll();

// Determine recognition level
if ($totalPoints >= 80) {
    $recognitionLevel = "Outstanding Participant";
    $recognitionBadge = "🏆";
    $nextLevelPoints = 0;
    $progressPercent = 100;
} elseif ($totalPoints >= 50) {
    $recognitionLevel = "Active Student Award";
    $recognitionBadge = "🏅";
    $nextLevelPoints = 80 - $totalPoints;
    $progressPercent = ($totalPoints / 80) * 100;
} elseif ($totalPoints >= 20) {
    $recognitionLevel = "Certificate Eligible";
    $recognitionBadge = "📜";
    $nextLevelPoints = 50 - $totalPoints;
    $progressPercent = ($totalPoints / 80) * 100;
} else {
    $recognitionLevel = "Warning - Need More Participation";
    $recognitionBadge = "⚠️";
    $nextLevelPoints = 20 - $totalPoints;
    $progressPercent = ($totalPoints / 80) * 100;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - FK Club System</title>
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
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .welcome-text { font-size: 16px; font-weight: 500; }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; cursor: pointer; }
        .logout-btn:hover { background: #c82333; }
        
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-info h3 { font-size: 28px; font-weight: bold; color: var(--umpsa-blue); margin-bottom: 5px; }
        .stat-info p { color: #666; margin: 0; font-size: 13px; }
        .stat-icon { width: 50px; height: 50px; background: rgba(0,59,92,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 28px; color: var(--umpsa-blue); }
        
        .recognition-card {
            background: linear-gradient(135deg, var(--umpsa-blue) 0%, var(--umpsa-dark-blue) 100%);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            color: white;
        }
        .recognition-badge { font-size: 48px; text-align: center; }
        .progress { height: 10px; border-radius: 10px; background: rgba(255,255,255,0.2); }
        .progress-bar { background: var(--umpsa-gold); border-radius: 10px; }
        
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .table-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .table-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .status-registered { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-attended { background: #cce5ff; color: #004085; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .club-tag {
            background: var(--umpsa-light-blue);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin: 3px;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 25px;
            width: 400px;
            text-align: center;
        }
        .modal-content i { font-size: 50px; margin-bottom: 15px; }
        .modal-content h4 { margin-bottom: 15px; }
        .modal-content p { margin-bottom: 20px; color: #666; }
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .modal-btn-confirm {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
        }
        .modal-btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .btn-cancel {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
        }
        .btn-cancel:hover { background: #c82333; }
        .btn-qr {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-qr:hover { background: #138496; color: white; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-header p, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
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
        <a href="../module1/dashboard_student.php" class="active">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module2/club_dashboard_student.php">
            <i class="fas fa-building"></i> <span>Browse Clubs</span>
        </a>
        <a href="../module3/browse_events.php">
            <i class="fas fa-calendar-alt"></i> <span>Browse Events</span>
        </a>
        <a href="../module3/my_registrations.php">
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
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Student'); ?>
            <span class="badge-role">Student</span>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <?php echo $message; ?>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalPoints; ?></h3>
                <p>Total Points</p>
            </div>
            <div class="stat-icon"><i class="fas fa-star"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $recognitionBadge; ?> <?php echo $recognitionLevel; ?></h3>
                <p>Recognition Level</p>
            </div>
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo count($upcomingEventsList); ?></h3>
                <p>Upcoming Events</p>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $clubsJoined; ?></h3>
                <p>Clubs Joined</p>
            </div>
            <div class="stat-icon"><i class="fas fa-building"></i></div>
        </div>
    </div>

    <div class="recognition-card">
        <div class="row align-items-center">
            <div class="col-md-2 text-center">
                <div class="recognition-badge"><?php echo $recognitionBadge; ?></div>
            </div>
            <div class="col-md-7">
                <h4><?php echo $recognitionLevel; ?></h4>
                <p>You have earned <?php echo $totalPoints; ?> points</p>
                <?php if ($nextLevelPoints > 0): ?>
                    <div class="progress mt-2">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $progressPercent; ?>%"></div>
                    </div>
                    <small class="mt-1 d-block"><?php echo $nextLevelPoints; ?> more points to next level!</small>
                <?php else: ?>
                    <div class="progress mt-2">
                        <div class="progress-bar" role="progressbar" style="width: 100%"></div>
                    </div>
                    <small class="mt-1 d-block">🎉 Congratulations! You have reached the highest level!</small>
                <?php endif; ?>
            </div>
            <div class="col-md-3 text-end">
                <a href="../module4/my_points_recognition.php" style="color: white; text-decoration: none;">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- My Clubs Section -->
    <div class="table-card">
        <h5><i class="fas fa-building"></i> My Clubs</h5>
        <?php if (empty($joinedClubsList)): ?>
            <p class="text-muted">You haven't joined any clubs yet. <a href="../module2/club_dashboard_student.php">Browse Clubs</a></p>
        <?php else: ?>
            <div>
                <?php foreach ($joinedClubsList as $club): ?>
                    <span class="club-tag">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                        <?php echo htmlspecialchars($club['clubName']); ?>
                        (Joined: <?php echo date('d M Y', strtotime($club['joinDate'])); ?>)
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upcoming Registered Events -->
    <div class="table-card">
        <h5><i class="fas fa-calendar-alt"></i> My Upcoming Events</h5>
        <?php if (empty($upcomingEventsList)): ?>
            <p class="text-muted">You haven't registered for any upcoming events. <a href="../module3/browse_events.php">Browse Events</a></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Club</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingEventsList as $event): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                            <td><?php echo htmlspecialchars($event['clubName']); ?></td>
                            <td><?php echo date('d M Y', strtotime($event['event_date'])); ?></td>
                            <td><?php echo htmlspecialchars($event['venue']); ?></td>
                            <td><span class="status-registered">Registered</span></td>
                            <td>
                              <a href="../module4/generate_qr.php?event_id=<?php echo $event['event_id']; ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn-qr">
    <i class="fas fa-qrcode"></i> QR
</a>
                                <button class="btn-cancel" onclick="openCancelModal(<?php echo $event['event_id']; ?>, '<?php echo htmlspecialchars($event['event_title']); ?>')">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </button>
                             </td
                         </tr
                        <?php endforeach; ?>
                    </tbody>
                 </table
            </div>
        <?php endif; ?>
    </div>

    <!-- Past Events History -->
    <div class="table-card">
        <h5><i class="fas fa-history"></i> My Participation History</h5>
        <?php if (empty($pastEventsList)): ?>
            <p class="text-muted">No past events yet. Start participating!</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Club</th>
                            <th>Date</th>
                            <th>Attendance</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pastEventsList as $event): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                            <td><?php echo htmlspecialchars($event['clubName']); ?></td>
                            <td><?php echo date('d M Y', strtotime($event['event_date'])); ?></td>
                            <td>
                                <?php if ($event['attendanceStatus'] == 'Present'): ?>
                                    <span class="status-attended">Present</span>
                                <?php elseif ($event['attendanceStatus'] == 'Late'): ?>
                                    <span class="status-attended">Late</span>
                                <?php else: ?>
                                    <span class="status-attended">Absent</span>
                                <?php endif; ?>
                            </td
                            <td>
                                <?php if ($event['pointsEarned'] > 0): ?>
                                    <strong style="color: #28a745;">+<?php echo $event['pointsEarned']; ?></strong>
                                <?php elseif ($event['pointsEarned'] < 0): ?>
                                    <strong style="color: #dc3545;"><?php echo $event['pointsEarned']; ?></strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td
                         </tr
                        <?php endforeach; ?>
                    </tbody>
                 </table
            </div>
            <div class="view-all-link mt-3">
                <a href="../module4/my_points_recognition.php">View Full History <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Registration Confirmation Modal -->
<div id="cancelModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
        <h4>Cancel Registration</h4>
        <p id="cancelMessage">Are you sure you want to cancel your registration for <strong id="eventName"></strong>?</p>
        <p class="text-muted small">This action cannot be undone. Your spot will be given to the next person on the waiting list.</p>
        <div class="modal-buttons">
            <button id="confirmCancelBtn" class="modal-btn-confirm">Yes, Cancel</button>
            <button id="cancelCancelBtn" class="modal-btn-cancel">No, Keep It</button>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="modal-overlay">
    <div class="modal-content" style="width: 350px;">
        <i class="fas fa-qrcode" style="font-size: 50px; color: var(--umpsa-blue);"></i>
        <h4>Event QR Code</h4>
        <p id="qrEventName"></p>
        <div id="qrCodeImage" style="margin: 20px 0; text-align: center;">
            <img id="qrImg" src="" alt="QR Code" style="width: 200px; height: 200px;">
        </div>
        <p class="text-muted small">Scan this QR code at the event venue for attendance verification.</p>
        <div class="modal-buttons">
            <button class="modal-btn-cancel" onclick="closeQRModal()">Close</button>
        </div>
    </div>
</div>

<script>
    let cancelEventId = null;
    
    function openCancelModal(eventId, eventTitle) {
        cancelEventId = eventId;
        document.getElementById('eventName').innerHTML = eventTitle;
        document.getElementById('cancelModal').style.display = 'flex';
    }
    
    function closeCancelModal() {
        document.getElementById('cancelModal').style.display = 'none';
        cancelEventId = null;
    }
    
    document.getElementById('confirmCancelBtn').onclick = function() {
        if (cancelEventId) {
            window.location.href = '?cancel_event=' + cancelEventId;
        }
    };
    
    document.getElementById('cancelCancelBtn').onclick = function() {
        closeCancelModal();
    };
    
    window.onclick = function(event) {
        const modal = document.getElementById('cancelModal');
        if (event.target == modal) {
            closeCancelModal();
        }
    };
    
    // Logout confirmation
    document.querySelector('.logout-btn').onclick = function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = '../../logout.php';
        }
    };
</script>

<script>
    function showQRModal(eventId, eventTitle) {
        document.getElementById('qrEventName').innerHTML = '<strong>' + eventTitle + '</strong>';
        document.getElementById('qrImg').src = '../../assets/qrcodes/event_' + eventId + '.png?' + new Date().getTime();
        document.getElementById('qrModal').style.display = 'flex';
    }
    
    function closeQRModal() {
        document.getElementById('qrModal').style.display = 'none';
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
$pdo = null;
?>