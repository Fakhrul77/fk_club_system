<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT SUM(pointsEarned) as total FROM activity_points WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalPoints = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE user_id = ? AND status = 'Active'");
$stmt->execute([$user_id]);
$clubsJoined = $stmt->fetchColumn() ?? 0;

$upcomingEvents = $pdo->prepare("SELECT e.*, c.clubName FROM event_registration er JOIN event e ON er.event_id = e.event_id JOIN club c ON e.club_id = c.club_id WHERE er.user_id = ? AND e.event_date >= CURDATE() ORDER BY e.event_date ASC LIMIT 5");
$upcomingEvents->execute([$user_id]);
$upcomingEventsList = $upcomingEvents->fetchAll();

$joinedClubs = $pdo->prepare("SELECT c.*, cm.joinDate FROM club_membership cm JOIN club c ON cm.club_id = c.club_id WHERE cm.user_id = ? AND cm.status = 'Active'");
$joinedClubs->execute([$user_id]);
$joinedClubsList = $joinedClubs->fetchAll();

if ($totalPoints >= 80) { $recognitionLevel = "Outstanding Participant"; $recognitionBadge = "🏆"; $nextLevelPoints = 0; }
elseif ($totalPoints >= 50) { $recognitionLevel = "Active Student Award"; $recognitionBadge = "🏅"; $nextLevelPoints = 80 - $totalPoints; }
elseif ($totalPoints >= 20) { $recognitionLevel = "Certificate Eligible"; $recognitionBadge = "📜"; $nextLevelPoints = 50 - $totalPoints; }
else { $recognitionLevel = "Warning - Need More Participation"; $recognitionBadge = "⚠️"; $nextLevelPoints = 20 - $totalPoints; }

$progressPercent = min(100, ($totalPoints / 80) * 100);
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--umpsa-light-blue); }
        .sidebar { position: fixed; top: 0; left: 0; height: 100%; width: 260px; background: var(--umpsa-dark-blue); color: white; z-index: 1000; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { margin: 0; font-size: 18px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a { display: block; padding: 12px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; font-size: 14px; }
        .sidebar-menu a:hover { background: rgba(253,184,19,0.2); color: white; }
        .sidebar-menu a i { margin-right: 10px; width: 20px; }
        .sidebar-menu a.active { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .welcome-text { font-size: 16px; font-weight: 500; }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-info h3 { font-size: 28px; font-weight: bold; color: var(--umpsa-blue); margin-bottom: 5px; }
        .stat-icon { width: 50px; height: 50px; background: rgba(0,59,92,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 28px; color: var(--umpsa-blue); }
        .recognition-card { background: linear-gradient(135deg, var(--umpsa-blue) 0%, var(--umpsa-dark-blue) 100%); border-radius: 16px; padding: 25px; margin-bottom: 25px; color: white; }
        .recognition-badge { font-size: 48px; text-align: center; }
        .progress { height: 10px; border-radius: 10px; background: rgba(255,255,255,0.2); }
        .progress-bar { background: var(--umpsa-gold); border-radius: 10px; }
        .table-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .table-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .table-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .status-registered { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .club-tag { background: var(--umpsa-light-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; display: inline-block; margin: 3px; }
        .action-btn { background: none; border: none; cursor: pointer; color: var(--umpsa-gold); font-size: 16px; margin: 0 5px; }
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header h4, .sidebar-menu a span { display: none; } .main-content { margin-left: 70px; } .stat-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header"><h4>🏛️ FK Club System</h4></div>
    <div class="sidebar-menu">
        <a href="#" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="#"><i class="fas fa-building"></i> <span>Browse Clubs</span></a>
        <a href="#"><i class="fas fa-calendar-alt"></i> <span>Browse Events</span></a>
        <a href="#"><i class="fas fa-list"></i> <span>My Registrations</span></a>
        <a href="#"><i class="fas fa-star"></i> <span>My Points</span></a>
        <a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text"><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?><span class="badge-role">Student</span></div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-info"><h3><?php echo $totalPoints; ?></h3><p>Total Points</p></div><div class="stat-icon"><i class="fas fa-star"></i></div></div>
        <div class="stat-card"><div class="stat-info"><h3><?php echo $recognitionBadge; ?> <?php echo $recognitionLevel; ?></h3><p>Recognition Level</p></div><div class="stat-icon"><i class="fas fa-trophy"></i></div></div>
        <div class="stat-card"><div class="stat-info"><h3><?php echo count($upcomingEventsList); ?></h3><p>Upcoming Events</p></div><div class="stat-icon"><i class="fas fa-calendar-check"></i></div></div>
        <div class="stat-card"><div class="stat-info"><h3><?php echo $clubsJoined; ?></h3><p>Clubs Joined</p></div><div class="stat-icon"><i class="fas fa-building"></i></div></div>
    </div>

    <div class="recognition-card">
        <div class="row align-items-center">
            <div class="col-md-2 text-center"><div class="recognition-badge"><?php echo $recognitionBadge; ?></div></div>
            <div class="col-md-7">
                <h4><?php echo $recognitionLevel; ?></h4>
                <p>You have earned <?php echo $totalPoints; ?> points</p>
                <?php if ($nextLevelPoints > 0): ?>
                    <div class="progress mt-2"><div class="progress-bar" style="width: <?php echo $progressPercent; ?>%"></div></div>
                    <small><?php echo $nextLevelPoints; ?> more points to next level!</small>
                <?php else: ?>
                    <div class="progress mt-2"><div class="progress-bar" style="width: 100%"></div></div>
                    <small>🎉 Congratulations! Highest level reached!</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h5><i class="fas fa-building"></i> My Clubs</h5>
        <?php if (empty($joinedClubsList)): ?>
            <p class="text-muted">You haven't joined any clubs yet.</p>
        <?php else: ?>
            <?php foreach ($joinedClubsList as $club): ?>
                <span class="club-tag"><i class="fas fa-check-circle" style="color: #28a745;"></i> <?php echo htmlspecialchars($club['clubName']); ?> (Joined: <?php echo date('d M Y', strtotime($club['joinDate'])); ?>)</span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="table-card">
        <h5><i class="fas fa-calendar-alt"></i> My Upcoming Events</h5>
        <?php if (empty($upcomingEventsList)): ?>
            <p class="text-muted">No upcoming events registered.</p>
        <?php else: ?>
            <table><thead><tr><th>Event Name</th><th>Club</th><th>Date</th><th>Venue</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody><?php foreach ($upcomingEventsList as $event): ?>
            <tr><td><?php echo htmlspecialchars($event['event_title']); ?></td><td><?php echo htmlspecialchars($event['clubName']); ?></td><td><?php echo date('d M Y', strtotime($event['event_date'])); ?></td><td><?php echo htmlspecialchars($event['venue']); ?></td><td><span class="status-registered">Registered</span></td>
            <td><button class="action-btn" onclick="alert('View QR Code')"><i class="fas fa-qrcode"></i></button> <button class="action-btn" onclick="alert('Cancel Registration')"><i class="fas fa-times-circle"></i></button></td></tr>
            <?php endforeach; ?></tbody></table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>