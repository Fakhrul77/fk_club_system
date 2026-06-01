<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../module1/login.php");
    exit();
}

$club_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$club_id) {
    $role = $_SESSION['user_role'];
    if ($role == 1) header("Location: club_dashboard_admin.php");
    elseif ($role == 2) header("Location: club_dashboard_committee.php");
    else header("Location: club_dashboard_student.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM club WHERE club_id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch();

if (!$club) {
    $role = $_SESSION['user_role'];
    if ($role == 1) header("Location: club_dashboard_admin.php");
    elseif ($role == 2) header("Location: club_dashboard_committee.php");
    else header("Location: club_dashboard_student.php");
    exit();
}

// Get stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Active'");
$stmt->execute([$club_id]);
$member_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM event WHERE club_id = ?");
$stmt->execute([$club_id]);
$event_count = $stmt->fetchColumn();

// Get committee members
$committee = $pdo->prepare("SELECT u.name, u.email, cp.positionName FROM club_committee cc JOIN users u ON cc.user_id = u.user_id LEFT JOIN committee_position cp ON cc.position_id = cp.position_id WHERE cc.club_id = ? AND cc.status = 'Active' ORDER BY cp.position_id");
$committee->execute([$club_id]);
$committeeMembers = $committee->fetchAll();

// Get upcoming events
$upcomingEvents = $pdo->prepare("SELECT * FROM event WHERE club_id = ? AND event_date >= CURDATE() AND status = 'UPCOMING' ORDER BY event_date ASC LIMIT 5");
$upcomingEvents->execute([$club_id]);
$eventsList = $upcomingEvents->fetchAll();

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$application_status = null;
$can_apply = false;

if ($user_role == 3) {
    $stmt = $pdo->prepare("SELECT status FROM club_membership_applications WHERE club_id = ? AND user_id = ?");
    $stmt->execute([$club_id, $user_id]);
    $app = $stmt->fetch();
    $application_status = $app['status'] ?? null;
    $stmt = $pdo->prepare("SELECT * FROM club_membership WHERE club_id = ? AND user_id = ? AND status = 'Active'");
    $stmt->execute([$club_id, $user_id]);
    $can_apply = !$stmt->fetch() && !$application_status && $club['status'] == 'Active';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['join_club']) && $can_apply) {
    $stmt = $pdo->prepare("INSERT INTO club_membership_applications (club_id, user_id, status, application_date) VALUES (?, ?, 'Pending', CURDATE())");
    $stmt->execute([$club_id, $user_id]);
    header("Location: club_view.php?id=$club_id&msg=applied");
    exit();
}

$message = isset($_GET['msg']) && $_GET['msg'] == 'applied' ? '<div class="alert alert-success">✅ Application submitted successfully!</div>' : '';

if ($user_role == 1) $return_page = "club_dashboard_admin.php";
elseif ($user_role == 2) $return_page = "club_dashboard_committee.php";
else $return_page = "club_dashboard_student.php";

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($club['clubName']); ?> - Club Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --umpsa-blue: #003B5C; --umpsa-gold: #FDB813; --umpsa-dark-blue: #002147; --umpsa-light-blue: #E8F0F8; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--umpsa-light-blue); overflow-x: hidden; }
        
        .sidebar { position: fixed; top: 0; left: 0; height: 100%; width: 260px; background: var(--umpsa-dark-blue); color: white; z-index: 1000; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { margin: 0; font-size: 18px; }
        .sidebar-header p { margin: 5px 0 0; font-size: 11px; opacity: 0.7; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a { display: block; padding: 12px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; font-size: 14px; }
        .sidebar-menu a:hover { background: rgba(253,184,19,0.2); color: white; }
        .sidebar-menu a i { margin-right: 10px; width: 20px; }
        .sidebar-menu a.active { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); }
        
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .welcome-text { font-size: 16px; font-weight: 500; }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        
        .club-header { background: linear-gradient(135deg, var(--umpsa-blue), var(--umpsa-dark-blue)); color: white; border-radius: 20px; padding: 30px; margin-bottom: 30px; }
        .stat-box { background: white; border-radius: 12px; padding: 15px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-number { font-size: 32px; font-weight: bold; color: var(--umpsa-blue); }
        .info-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .info-card h5 { color: var(--umpsa-blue); margin-bottom: 15px; border-left: 3px solid var(--umpsa-gold); padding-left: 10px; }
        .committee-tag { background: var(--umpsa-light-blue); padding: 8px 12px; border-radius: 10px; margin-bottom: 10px; }
        .event-date { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .btn-back { background: #6c757d; color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-join { background: var(--umpsa-blue); color: white; padding: 10px 25px; border-radius: 8px; border: none; font-weight: bold; }
        
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header h4, .sidebar-header p, .sidebar-menu a span { display: none; } .main-content { margin-left: 70px; } }
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
        <?php if ($user_role == 1): ?>
            <a href="../module1/dashboard_admin.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="../module1/manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
            <a href="../module2/club_redirect.php"><i class="fas fa-building"></i> <span>Manage Clubs</span></a>
            <a href="../module3/manage_events.php"><i class="fas fa-calendar-alt"></i> <span>Events</span></a>
            <a href="../module1/profile.php"><i class="fas fa-user"></i> <span>Profile</span></a>
        <?php elseif ($user_role == 2): ?>
            <a href="../module1/dashboard_committee.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="../module2/club_dashboard_committee.php"><i class="fas fa-building"></i> <span>My Club</span></a>
            <a href="../module3/manage_events.php"><i class="fas fa-calendar-alt"></i> <span>Events</span></a>
            <a href="../module3/create_event.php"><i class="fas fa-calendar-plus"></i> <span>Create Event</span></a>
            <a href="../module1/profile.php"><i class="fas fa-user"></i> <span>Profile</span></a>
        <?php else: ?>
            <a href="../module1/dashboard_student.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="../module2/club_dashboard_student.php"><i class="fas fa-building"></i> <span>Browse Clubs</span></a>
            <a href="../module3/browse_events.php"><i class="fas fa-calendar-alt"></i> <span>Browse Events</span></a>
            <a href="../module3/my_registrations.php"><i class="fas fa-list"></i> <span>My Registrations</span></a>
            <a href="../module4/my_points_recognition.php"><i class="fas fa-star"></i> <span>My Points</span></a>
            <a href="../module1/profile.php"><i class="fas fa-user"></i> <span>Profile</span></a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text"><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?><span class="badge-role"><?php echo $user_role == 1 ? 'Admin' : ($user_role == 2 ? 'Committee' : 'Student'); ?></span></div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="mb-3"><a href="<?php echo $return_page; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a></div>
    <?php echo $message; ?>

    <div class="club-header"><div><h1><i class="fas fa-building"></i> <?php echo htmlspecialchars($club['clubName']); ?></h1><p><?php echo htmlspecialchars($club['clubCategory'] ?? 'General'); ?> Club</p></div><div><span class="badge bg-success"><?php echo $club['status']; ?></span></div></div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3"><div class="stat-box"><i class="fas fa-users fa-2x" style="color: var(--umpsa-gold);"></i><div class="stat-number"><?php echo $member_count; ?></div><div>Total Members</div></div></div>
        <div class="col-md-4 mb-3"><div class="stat-box"><i class="fas fa-calendar-alt fa-2x" style="color: var(--umpsa-gold);"></i><div class="stat-number"><?php echo $event_count; ?></div><div>Total Events</div></div></div>
        <div class="col-md-4 mb-3"><div class="stat-box"><i class="fas fa-calendar-plus fa-2x" style="color: var(--umpsa-gold);"></i><div class="stat-number"><?php echo date('d/m/Y', strtotime($club['created_at'])); ?></div><div>Founded</div></div></div>
    </div>

    <?php if ($can_apply): ?>
        <div class="text-center mb-4"><form method="POST"><button type="submit" name="join_club" class="btn-join"><i class="fas fa-hand-paper"></i> Apply to Join This Club</button></form></div>
    <?php elseif ($application_status == 'Pending'): ?>
        <div class="alert alert-warning text-center"><i class="fas fa-hourglass-half"></i> Your application is pending approval.</div>
    <?php elseif ($application_status == 'Approved'): ?>
        <div class="alert alert-success text-center"><i class="fas fa-check-circle"></i> You are a member of this club!</div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="info-card"><h5><i class="fas fa-info-circle"></i> Club Information</h5>
                <table class="table table-borderless">
                    <tr><td width="120"><strong>Club Name:</strong></td><td><?php echo htmlspecialchars($club['clubName']); ?></td></tr>
                    <tr><td><strong>Category:</strong></td><td><?php echo htmlspecialchars($club['clubCategory'] ?? '-'); ?></td></tr>
                    <tr><td><strong>Status:</strong></td><td><?php echo $club['status']; ?></td></tr>
                    <?php if ($club['advisorName']): ?><tr><td><strong>Advisor:</strong></td><td><?php echo htmlspecialchars($club['advisorName']); ?></td></tr><?php endif; ?>
                    <tr><td><strong>Created:</strong></td><td><?php echo date('d/m/Y', strtotime($club['created_at'])); ?></td></tr>
                </table>
            </div>
            <?php if ($club['clubDescription']): ?>
                <div class="info-card"><h5><i class="fas fa-align-left"></i> Description</h5><p><?php echo nl2br(htmlspecialchars($club['clubDescription'])); ?></p></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <div class="info-card"><h5><i class="fas fa-user-tie"></i> Committee Members</h5>
                <?php if (empty($committeeMembers)): ?><p class="text-muted">No committee members assigned yet.</p>
                <?php else: foreach ($committeeMembers as $member): ?>
                    <div class="committee-tag"><strong><?php echo htmlspecialchars($member['positionName'] ?? 'Committee Member'); ?></strong><br><?php echo htmlspecialchars($member['name']); ?></div>
                <?php endforeach; endif; ?>
            </div>
            <?php if (!empty($eventsList)): ?>
                <div class="info-card"><h5><i class="fas fa-calendar-alt"></i> Upcoming Events</h5>
                    <?php foreach ($eventsList as $event): ?>
                        <div class="mb-2 pb-2 border-bottom"><strong><?php echo htmlspecialchars($event['event_title']); ?></strong><br><span class="event-date"><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($event['event_date'])); ?></span> <span class="event-date"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?></span><div class="small text-muted mt-1">📍 <?php echo htmlspecialchars($event['venue'] ?? 'TBA'); ?></div></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>