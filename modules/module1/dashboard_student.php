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
    
    $stmt = $pdo->prepare("SELECT registration_id FROM event_registration WHERE user_id = ? AND event_id = ? AND status = 'Confirmed'");
    $stmt->execute([$user_id, $event_id]);
    $registration = $stmt->fetch();
    
    if ($registration) {
        $pdo->prepare("UPDATE event_registration SET status = 'Cancelled', cancellation_date = NOW() WHERE registration_id = ?")
            ->execute([$registration['registration_id']]);
        $pdo->prepare("UPDATE event SET current_participant = current_participant - 1 WHERE event_id = ?")
            ->execute([$event_id]);
        
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

$message = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'cancelled') {
    $message = '<div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> Registration cancelled successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
}


// Get total points from activity_points (same as my_points_recognition.php)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(pointsEarned), 0) as total FROM activity_points WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalPoints = (int)$stmt->fetchColumn();

// Get clubs joined count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE user_id = ? AND status = 'Active'");
$stmt->execute([$user_id]);
$clubsJoined = $stmt->fetchColumn() ?? 0;

// Get upcoming registered events
$upcomingEvents = $pdo->prepare("
    SELECT e.event_id, e.event_title, e.event_date, e.event_time, e.venue, e.status, 
           c.clubName, er.registration_id, er.status as reg_status,
           DATEDIFF(e.event_date, CURDATE()) as days_left
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

// Get past events history - IMPROVED QUERY
$pastEvents = $pdo->prepare("
    SELECT e.event_id, e.event_title, e.event_date, e.event_time, e.venue,
           c.clubName, a.attendanceStatus, ap.pointsEarned,
           CASE 
               WHEN a.attendanceStatus = 'Present' THEN 'success'
               WHEN a.attendanceStatus = 'Late' THEN 'warning'
               WHEN a.attendanceStatus = 'Absent' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM event_registration er
    JOIN event e ON er.event_id = e.event_id
    JOIN club c ON e.club_id = c.club_id
    LEFT JOIN attendance a ON a.registration_id = er.registration_id
    LEFT JOIN activity_points ap ON ap.user_id = er.user_id AND ap.event_id = e.event_id
    WHERE er.user_id = ? AND e.event_date < CURDATE()
    ORDER BY e.event_date DESC LIMIT 10
");
$pastEvents->execute([$user_id]);
$pastEventsList = $pastEvents->fetchAll();

// Calculate statistics for participation history
$totalEventsAttended = 0;
$totalPointsEarned = 0;
$presentCount = 0;
$lateCount = 0;
$absentCount = 0;

foreach ($pastEventsList as $event) {
    if ($event['attendanceStatus'] == 'Present') {
        $totalEventsAttended++;
        $presentCount++;
    } elseif ($event['attendanceStatus'] == 'Late') {
        $totalEventsAttended++;
        $lateCount++;
    } elseif ($event['attendanceStatus'] == 'Absent') {
        $absentCount++;
    }
    $totalPointsEarned += (int)$event['pointsEarned'];
}

$attendanceRate = count($pastEventsList) > 0 ? round(($totalEventsAttended / count($pastEventsList)) * 100) : 0;

// Determine recognition level
if ($totalPoints >= 80) {
    $recognitionLevel = "Outstanding Participant";
    $recognitionBadge = "🏆";
    $recognitionColor = "#FFD700";
    $nextLevelPoints = 0;
    $progressPercent = 100;
} elseif ($totalPoints >= 50) {
    $recognitionLevel = "Active Student Award";
    $recognitionBadge = "🏅";
    $recognitionColor = "#C0C0C0";
    $nextLevelPoints = 80 - $totalPoints;
    $progressPercent = ($totalPoints / 80) * 100;
} elseif ($totalPoints >= 20) {
    $recognitionLevel = "Certificate Eligible";
    $recognitionBadge = "📜";
    $recognitionColor = "#CD7F32";
    $nextLevelPoints = 50 - $totalPoints;
    $progressPercent = ($totalPoints / 80) * 100;
} else {
    $recognitionLevel = "Warning - Need More Participation";
    $recognitionBadge = "⚠️";
    $recognitionColor = "#DC3545";
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
            background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
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
        
        .section-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .section-header {
            background: var(--umpsa-blue);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 16px;
        }
        .section-header i { color: var(--umpsa-gold); margin-right: 10px; }
        .section-body { padding: 20px; }
        
        .club-tag {
            background: var(--umpsa-light-blue);
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 13px;
            display: inline-block;
            margin: 5px;
        }
        .club-tag i { color: #28a745; margin-right: 5px; }
        
        .event-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.2s;
            border-left: 4px solid var(--umpsa-gold);
        }
        .event-card:hover {
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateX(5px);
        }
        .event-name { font-size: 16px; font-weight: 700; color: var(--umpsa-blue); margin-bottom: 8px; }
        .event-details { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 12px; font-size: 12px; color: #666; }
        .event-details span i { width: 16px; color: var(--umpsa-gold); margin-right: 4px; }
        .event-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .event-badge.upcoming { background: #d4edda; color: #155724; }
        .event-badge.today { background: #fff3cd; color: #856404; }
        .event-actions { display: flex; gap: 10px; margin-top: 10px; }
        .btn-qr-sm {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-cancel-sm {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* IMPROVED PARTICIPATION HISTORY STYLES */
        .history-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .history-stat-card {
            flex: 1;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        .history-stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--umpsa-blue);
        }
        .history-stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th {
            text-align: left;
            padding: 14px 12px;
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e9ecef;
            color: #495057;
        }
        .history-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 13px;
            vertical-align: middle;
        }
        .history-table tr:hover {
            background: var(--umpsa-light-blue);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge i { font-size: 10px; }
        .status-present { background: #d4edda; color: #155724; }
        .status-late { background: #fff3cd; color: #856404; }
        .status-absent { background: #f8d7da; color: #721c24; }
        .status-pending { background: #e2e3e5; color: #383d41; }
        
        .points-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .points-positive { background: #d4edda; color: #155724; }
        .points-negative { background: #f8d7da; color: #721c24; }
        .points-neutral { background: #e2e3e5; color: #383d41; }
        
        .event-title-link {
            color: var(--umpsa-blue);
            text-decoration: none;
            font-weight: 500;
        }
        .event-title-link:hover {
            text-decoration: underline;
            color: var(--umpsa-gold);
        }
        
        .empty-history {
            text-align: center;
            padding: 50px 20px;
        }
        .empty-history i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 15px;
        }
        
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
        .modal-buttons { display: flex; gap: 15px; justify-content: center; }
        .modal-btn-confirm { background: #dc3545; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; }
        .modal-btn-cancel { background: #6c757d; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-header p, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            .history-stats { flex-direction: column; }
            .history-table thead { display: none; }
            .history-table tr { display: block; margin-bottom: 15px; border: 1px solid #e9ecef; border-radius: 12px; }
            .history-table td { display: block; text-align: right; padding: 10px; border: none; }
            .history-table td:before { content: attr(data-label); float: left; font-weight: 600; color: var(--umpsa-blue); }
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
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <?php echo $message; ?>

    <!-- Statistics Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $totalPoints; ?></h3><p>Total Points</p></div>
            <div class="stat-icon"><i class="fas fa-star"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3 style="color: <?php echo $recognitionColor; ?>"><?php echo $recognitionBadge; ?> <?php echo $recognitionLevel; ?></h3><p>Recognition Level</p></div>
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo count($upcomingEventsList); ?></h3><p>Upcoming Events</p></div>
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $clubsJoined; ?></h3><p>Clubs Joined</p></div>
            <div class="stat-icon"><i class="fas fa-building"></i></div>
        </div>
    </div>

    <!-- Recognition Card -->
    <div class="recognition-card">
        <div class="row align-items-center">
            <div class="col-md-2 text-center"><div class="recognition-badge"><?php echo $recognitionBadge; ?></div></div>
            <div class="col-md-7">
                <h4><?php echo $recognitionLevel; ?></h4>
                <p>You have earned <strong><?php echo $totalPoints; ?> points</strong></p>
                <?php if ($nextLevelPoints > 0): ?>
                    <div class="progress mt-2"><div class="progress-bar" style="width: <?php echo $progressPercent; ?>%"></div></div>
                    <small class="mt-1 d-block"><?php echo $nextLevelPoints; ?> more points to next level!</small>
                <?php else: ?>
                    <div class="progress mt-2"><div class="progress-bar" style="width: 100%"></div></div>
                    <small class="mt-1 d-block">🎉 Congratulations! You have reached the highest level! 🎉</small>
                <?php endif; ?>
            </div>
            <div class="col-md-3 text-end">
                <a href="../module4/my_points_recognition.php" style="color: white; text-decoration: none;">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
<!-- My Clubs Section - Shows ONLY ONE club (students can only join one club) -->
<div class="section-card">
    <div class="section-header">
        <i class="fas fa-building"></i> My Club
        <?php if (!empty($joinedClubsList)): ?>
            <span class="ms-auto badge" style="background: #28a745; color: white;">
                <i class="fas fa-check-circle"></i> Active Member
            </span>
        <?php endif; ?>
    </div>
    <div class="section-body">
        <?php if (empty($joinedClubsList)): ?>
            <div class="text-center py-4">
                <i class="fas fa-users-slash" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                <p class="text-muted mb-2">You are not a member of any club yet.</p>
                <p class="text-muted small mb-3">Note: You can only be a member of <strong>ONE</strong> club at a time.</p>
                <a href="../module2/club_dashboard_student.php" class="btn btn-sm btn-primary" style="background: var(--umpsa-blue);">
                    <i class="fas fa-search"></i> Browse Clubs to Join
                </a>
            </div>
        <?php else: 
            // Students can only be in ONE club, so take the first (and only) one
            $myClub = $joinedClubsList[0];
        ?>
            <div style="background: linear-gradient(135deg, var(--umpsa-light-blue) 0%, #fff 100%); border-radius: 12px; padding: 20px;">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div>
                        <h4 style="color: var(--umpsa-blue); margin-bottom: 8px;">
                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                            <?php echo htmlspecialchars($myClub['clubName']); ?>
                        </h4>
                        <p class="text-muted mb-1">
                            <i class="fas fa-calendar-alt"></i> Member since: <?php echo date('d F Y', strtotime($myClub['joinDate'])); ?>
                        </p>
                        <?php if (!empty($myClub['clubCategory'])): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-tag"></i> Category: <?php echo htmlspecialchars($myClub['clubCategory']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="../module2/club_view.php?id=<?php echo $myClub['club_id']; ?>" class="btn btn-sm" style="background: var(--umpsa-blue); color: white;">
                            <i class="fas fa-eye"></i> View Club Details
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-3 mb-0" style="font-size: 13px;">
                <i class="fas fa-info-circle"></i> You are currently a member of <strong><?php echo htmlspecialchars($myClub['clubName']); ?></strong>. 
                To join another club, you must leave your current club first.
            </div>
        <?php endif; ?>
    </div>
</div>

    <!-- Upcoming Events Section -->
    <div class="section-card">
        <div class="section-header"><i class="fas fa-calendar-alt"></i> My Upcoming Events</div>
        <div class="section-body">
            <?php if (empty($upcomingEventsList)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                    <p class="text-muted mb-0">You haven't registered for any upcoming events.</p>
                    <a href="../module3/browse_events.php" class="btn btn-sm btn-primary mt-3" style="background: var(--umpsa-blue);">Browse Events</a>
                </div>
            <?php else: ?>
                <?php foreach ($upcomingEventsList as $event): 
                    $daysLeft = (int)$event['days_left'];
                    $isToday = ($daysLeft == 0);
                    $badgeClass = $isToday ? 'today' : 'upcoming';
                    $badgeText = $isToday ? 'Today!' : "In $daysLeft days";
                ?>
                    <div class="event-card">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div class="event-name"><?php echo htmlspecialchars($event['event_title']); ?></div>
                            <span class="event-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                        </div>
                        <div class="event-details">
                            <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($event['clubName']); ?></span>
                            <span><i class="fas fa-calendar-day"></i> <?php echo date('d M Y', strtotime($event['event_date'])); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?></span>
                            <span><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($event['venue']); ?></span>
                        </div>
                        <div class="event-actions">
                            <a href="../module4/generate_qr.php?event_id=<?php echo $event['event_id']; ?>&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn-qr-sm"><i class="fas fa-qrcode"></i> QR Code</a>
                            <button class="btn-cancel-sm" onclick="openCancelModal(<?php echo $event['event_id']; ?>, '<?php echo htmlspecialchars($event['event_title']); ?>')"><i class="fas fa-times-circle"></i> Cancel</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-end mt-3"><a href="../module3/my_registrations.php" class="small">View All Registrations <i class="fas fa-arrow-right"></i></a></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== IMPROVED PARTICIPATION HISTORY SECTION ========== -->
    <div class="section-card">
        <div class="section-header">
            <i class="fas fa-history"></i> My Participation History
            <?php if (!empty($pastEventsList)): ?>
                <span class="ms-auto badge" style="background: var(--umpsa-gold); color: var(--umpsa-dark-blue);">
                    <?php echo count($pastEventsList); ?> events
                </span>
            <?php endif; ?>
        </div>
        <div class="section-body">
            <?php if (empty($pastEventsList)): ?>
                <div class="empty-history">
                    <i class="fas fa-calendar-alt"></i>
                    <p class="text-muted mb-0">No past events yet. Start participating!</p>
                    <a href="../module3/browse_events.php" class="btn btn-sm btn-primary mt-3" style="background: var(--umpsa-blue);">Browse Events</a>
                </div>
            <?php else: ?>
                <!-- Statistics Summary -->
                <div class="history-stats">
                    <div class="history-stat-card">
                        <div class="history-stat-number"><?php echo count($pastEventsList); ?></div>
                        <div class="history-stat-label">Total Events</div>
                    </div>
                    <div class="history-stat-card">
                        <div class="history-stat-number" style="color: #28a745;"><?php echo $presentCount; ?></div>
                        <div class="history-stat-label">Present</div>
                    </div>
                    <div class="history-stat-card">
                        <div class="history-stat-number" style="color: #ffc107;"><?php echo $lateCount; ?></div>
                        <div class="history-stat-label">Late</div>
                    </div>
                    <div class="history-stat-card">
                        <div class="history-stat-number" style="color: #dc3545;"><?php echo $absentCount; ?></div>
                        <div class="history-stat-label">Absent</div>
                    </div>
                    <div class="history-stat-card">
                        <div class="history-stat-number"><?php echo $attendanceRate; ?>%</div>
                        <div class="history-stat-label">Attendance Rate</div>
                    </div>
                </div>

                <!-- Participation Table -->
                <div class="table-responsive">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Club</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Venue</th>
                                <th>Attendance</th>
                                <th>Points</th>
                            </td>
                        </thead>
                        <tbody>
                            <?php foreach ($pastEventsList as $event): 
                                $statusText = $event['attendanceStatus'] ?? 'Pending';
                                $statusClass = '';
                                $statusIcon = '';
                                if ($statusText == 'Present') {
                                    $statusClass = 'status-present';
                                    $statusIcon = 'fa-check-circle';
                                } elseif ($statusText == 'Late') {
                                    $statusClass = 'status-late';
                                    $statusIcon = 'fa-clock';
                                } elseif ($statusText == 'Absent') {
                                    $statusClass = 'status-absent';
                                    $statusIcon = 'fa-times-circle';
                                } else {
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'fa-question-circle';
                                }
                                
                                $points = (int)$event['pointsEarned'];
                                $pointsClass = $points > 0 ? 'points-positive' : ($points < 0 ? 'points-negative' : 'points-neutral');
                                $pointsIcon = $points > 0 ? 'fa-plus-circle' : ($points < 0 ? 'fa-minus-circle' : 'fa-minus');
                            ?>
                            <tr>
                                <td data-label="Event">
                                    <a href="../module3/view_event.php?id=<?php echo $event['event_id']; ?>&return=dashboard_student.php" class="event-title-link">
                                        <?php echo htmlspecialchars($event['event_title']); ?>
                                    </a>
                                </td>
                                <td data-label="Club"><?php echo htmlspecialchars($event['clubName']); ?></td>
                                <td data-label="Date"><?php echo date('d M Y', strtotime($event['event_date'])); ?></td>
                                <td data-label="Time"><?php echo date('h:i A', strtotime($event['event_time'])); ?></td>
                                <td data-label="Venue"><?php echo htmlspecialchars($event['venue']); ?></td>
                                <td data-label="Attendance">
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <i class="fas <?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td data-label="Points">
                                    <span class="points-badge <?php echo $pointsClass; ?>">
                                        <i class="fas <?php echo $pointsIcon; ?>"></i>
                                        <?php echo $points > 0 ? '+' : ''; ?><?php echo $points; ?> pts
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-end mt-3">
                    <a href="../module4/my_points_recognition.php" class="small">View Full History <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel Registration Modal -->
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

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-sign-out-alt" style="font-size: 50px; color: #dc3545; margin-bottom: 15px;"></i>
        <h4>Confirm Logout</h4>
        <p>Are you sure you want to logout?</p>
        <div class="modal-buttons">
            <button id="confirmLogout" class="modal-btn-confirm">Yes, Logout</button>
            <button id="cancelLogout" class="modal-btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<script>
    // Cancel Registration Modal
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
        if (cancelEventId) window.location.href = '?cancel_event=' + cancelEventId;
    };
    
    document.getElementById('cancelCancelBtn').onclick = closeCancelModal;
    
    // Logout Modal
    function showLogoutConfirm() { document.getElementById('logoutModal').style.display = 'flex'; }
    
    document.getElementById('confirmLogout').onclick = function() { window.location.href = '../../logout.php'; };
    document.getElementById('cancelLogout').onclick = function() { document.getElementById('logoutModal').style.display = 'none'; };
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('cancelModal')) closeCancelModal();
        if (event.target == document.getElementById('logoutModal')) document.getElementById('logoutModal').style.display = 'none';
    };
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $pdo = null; ?>