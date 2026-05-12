<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in and is committee
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// DEBUG - Check what user is logged in
$debug = false; // Set to true to see debug info

// Get committee member's club information - IMPROVED QUERY
$stmt = $pdo->prepare("
    SELECT c.*, cp.positionName, cc.assignedDate, cc.committee_id
    FROM club_committee cc
    JOIN club c ON cc.club_id = c.club_id
    JOIN committee_position cp ON cc.position_id = cp.position_id
    WHERE cc.user_id = ? AND cc.status = 'Active'
");
$stmt->execute([$user_id]);
$club_info = $stmt->fetch();

$club_id = $club_info['club_id'] ?? null;
$club_name = $club_info['clubName'] ?? 'No Club Assigned';
$position = $club_info['positionName'] ?? 'Committee Member';

// Get club statistics - FIXED QUERIES
if ($club_id) {
    // Count total members in this club (from club_membership table)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Active'");
    $stmt->execute([$club_id]);
    $totalMembers = $stmt->fetchColumn();
    
    // Count upcoming events for this club
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event WHERE club_id = ? AND status = 'UPCOMING'");
    $stmt->execute([$club_id]);
    $upcomingEvents = $stmt->fetchColumn();
    
    // Count pending applications (if status column exists, else show 0)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Pending'");
    $stmt->execute([$club_id]);
    $pendingApps = $stmt->fetchColumn();
    
    // Get total points awarded by this club
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ap.pointsEarned), 0) 
        FROM activity_points ap
        JOIN event e ON ap.event_id = e.event_id
        WHERE e.club_id = ?
    ");
    $stmt->execute([$club_id]);
    $totalPoints = $stmt->fetchColumn();
} else {
    $totalMembers = 0;
    $upcomingEvents = 0;
    $pendingApps = 0;
    $totalPoints = 0;
}

// DEBUG INFO - Remove this after fixing
if ($debug) {
    echo "<pre>";
    echo "User ID: " . $user_id . "\n";
    echo "Club ID: " . ($club_id ?? 'NULL') . "\n";
    echo "Club Name: " . $club_name . "\n";
    echo "Total Members: " . $totalMembers . "\n";
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Committee Dashboard - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --umpsa-blue: #003B5C; --umpsa-gold: #FDB813; --umpsa-dark-blue: #002147; --umpsa-light-blue: #E8F0F8; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--umpsa-light-blue); }
        .sidebar {
            position: fixed; top: 0; left: 0; height: 100%; width: 260px; background: var(--umpsa-dark-blue);
            color: white; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { margin: 0; font-size: 18px; }
        .sidebar-header p { font-size: 11px; opacity: 0.7; margin-top: 5px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: block; padding: 12px 25px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s; font-size: 14px;
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
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: white; border-radius: 16px; padding: 20px; display: flex;
            align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-info h3 { font-size: 28px; font-weight: bold; color: var(--umpsa-blue); margin-bottom: 5px; }
        .stat-info p { color: #666; margin: 0; font-size: 13px; }
        .stat-icon { width: 50px; height: 50px; background: rgba(0,59,92,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 28px; color: var(--umpsa-blue); }
        .welcome-card {
            background: linear-gradient(135deg, var(--umpsa-blue) 0%, var(--umpsa-dark-blue) 100%);
            color: white; border-radius: 16px; padding: 25px; margin-bottom: 25px;
        }
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
        <h4>🏛️ FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <a href="#" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="#"><i class="fas fa-building"></i> <span>My Club</span></a>
        <a href="#"><i class="fas fa-calendar-plus"></i> <span>Create Event</span></a>
        <a href="#"><i class="fas fa-qrcode"></i> <span>Record Attendance</span></a>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Committee Member'); ?>
            <span class="badge-role">Committee</span>
            <?php if ($club_id): ?>
                <span class="badge-role" style="background: var(--umpsa-blue); color: white;"><?php echo htmlspecialchars($club_name); ?></span>
            <?php endif; ?>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <?php if (!$club_id): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            You are not assigned to any club yet. Please contact the administrator.
        </div>
    <?php endif; ?>

    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($club_name); ?> Committee</h3>
                <p class="mb-0">Position: <strong><?php echo htmlspecialchars($position); ?></strong></p>
                <p>Manage your club activities, events, and member applications from this dashboard.</p>
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-users" style="font-size: 60px; opacity: 0.3;"></i>
            </div>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalMembers; ?></h3>
                <p>Club Members</p>
            </div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $upcomingEvents; ?></h3>
                <p>Upcoming Events</p>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $pendingApps; ?></h3>
                <p>Pending Applications</p>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo number_format($totalPoints); ?></h3>
                <p>Total Points Awarded</p>
            </div>
            <div class="stat-icon"><i class="fas fa-star"></i></div>
        </div>
    </div>
</div>

</body>
</html>