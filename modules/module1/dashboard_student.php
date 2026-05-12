<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: login.php");
    exit();
}

// Set default role name if not set
if (!isset($_SESSION['user_role_name'])) {
    $_SESSION['user_role_name'] = 'Student';
}

$user_id = $_SESSION['user_id'];

// Get student information
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// Get total points earned
$stmt = $pdo->prepare("SELECT SUM(pointsEarned) as total FROM activity_points WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalPoints = $stmt->fetchColumn() ?? 0;

// Get total events attended
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND attendanceStatus = 'Present'");
$stmt->execute([$user_id]);
$eventsAttended = $stmt->fetchColumn() ?? 0;

// Get total clubs joined
$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE user_id = ? AND status = 'Active'");
$stmt->execute([$user_id]);
$clubsJoined = $stmt->fetchColumn() ?? 0;

// Get upcoming registered events
$upcomingEvents = $pdo->prepare("
    SELECT e.*, c.clubName 
    FROM event_registration er
    JOIN event e ON er.event_id = e.event_id
    JOIN club c ON e.club_id = c.club_id
    WHERE er.user_id = ? AND e.event_date >= CURDATE() AND er.status = 'Registered'
    ORDER BY e.event_date ASC
    LIMIT 5
");
$upcomingEvents->execute([$user_id]);
$upcomingEventsList = $upcomingEvents->fetchAll();

// Get past events history
$pastEvents = $pdo->prepare("
    SELECT e.*, c.clubName, a.attendanceStatus, ap.pointsEarned
    FROM event_registration er
    JOIN event e ON er.event_id = e.event_id
    JOIN club c ON e.club_id = c.club_id
    LEFT JOIN attendance a ON a.user_id = er.user_id AND a.event_id = e.event_id
    LEFT JOIN activity_points ap ON ap.user_id = er.user_id AND ap.event_id = e.event_id
    WHERE er.user_id = ? AND e.event_date < CURDATE()
    ORDER BY e.event_date DESC
    LIMIT 5
");
$pastEvents->execute([$user_id]);
$pastEventsList = $pastEvents->fetchAll();

// Get joined clubs
$joinedClubs = $pdo->prepare("
    SELECT c.*, cm.joinDate
    FROM club_membership cm
    JOIN club c ON cm.club_id = c.club_id
    WHERE cm.user_id = ? AND cm.status = 'Active'
");
$joinedClubs->execute([$user_id]);
$joinedClubsList = $joinedClubs->fetchAll();

// Determine recognition level based on points
if ($totalPoints >= 80) {
    $recognitionLevel = "Outstanding Participant";
    $recognitionBadge = "🏆";
    $recognitionColor = "#FFD700";
    $nextLevelPoints = 0;
} elseif ($totalPoints >= 50) {
    $recognitionLevel = "Active Student Award";
    $recognitionBadge = "🏅";
    $recognitionColor = "#C0C0C0";
    $nextLevelPoints = 80 - $totalPoints;
} elseif ($totalPoints >= 20) {
    $recognitionLevel = "Certificate Eligible";
    $recognitionBadge = "📜";
    $recognitionColor = "#CD7F32";
    $nextLevelPoints = 50 - $totalPoints;
} else {
    $recognitionLevel = "Warning - Need More Participation";
    $recognitionBadge = "⚠️";
    $recognitionColor = "#DC3545";
    $nextLevelPoints = 20 - $totalPoints;
}

// Calculate progress percentage
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            overflow-x: hidden;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 260px;
            background: #1E3A5F;
            color: white;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .sidebar-header p {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
        }

        .sidebar-menu a.active {
            background: #FF6B35;
            color: white;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }

        /* ========== TOP NAVBAR ========== */
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

        .welcome-text {
            font-size: 16px;
            font-weight: 500;
        }

        .welcome-text i {
            color: #FF6B35;
            margin-right: 8px;
        }

        .badge-role {
            background: #FF6B35;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #c82333;
            color: white;
        }

        /* ========== STAT CARDS ========== */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: bold;
            color: #1E3A5F;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #666;
            margin: 0;
            font-size: 13px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,107,53,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 28px;
            color: #FF6B35;
        }

        /* ========== RECOGNITION CARD ========== */
        .recognition-card {
            background: linear-gradient(135deg, #1E3A5F 0%, #2B4C7E 100%);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            color: white;
        }

        .recognition-badge {
            font-size: 48px;
            text-align: center;
        }

        .progress {
            height: 10px;
            border-radius: 10px;
            background: rgba(255,255,255,0.2);
        }

        .progress-bar {
            background: #FF6B35;
            border-radius: 10px;
        }

        /* ========== TABLES ========== */
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .table-card h5 {
            color: #1E3A5F;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .table-card h5 i {
            color: #FF6B35;
            margin-right: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid #eee;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .status-registered {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }

        .status-attended {
            background: #cce5ff;
            color: #004085;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }

        .club-tag {
            background: #e8f4f8;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin: 3px;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            margin: 0 5px;
            color: #FF6B35;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h4, .sidebar-header p, .sidebar-menu a span {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
            .stat-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- ========== SIDEBAR WITH PROFILE LINK ========== -->
<div class="sidebar">
    <div class="sidebar-header">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard_student.php" class="active">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="#">
            <i class="fas fa-building"></i> <span>Browse Clubs</span>
        </a>
        <a href="#">
            <i class="fas fa-calendar-alt"></i> <span>Browse Events</span>
        </a>
        <a href="#">
            <i class="fas fa-list"></i> <span>My Registrations</span>
        </a>
        <a href="#">
            <i class="fas fa-star"></i> <span>My Points</span>
        </a>
        <a href="profile.php">
            <i class="fas fa-user"></i> <span>Profile</span>
        </a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Student'); ?>
            <span class="badge-role"><?php echo $_SESSION['user_role_name'] ?? 'Student'; ?></span>
        </div>
        <a href="../../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalPoints; ?></h3>
                <p>Total Points</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $recognitionBadge; ?> <?php echo $recognitionLevel; ?></h3>
                <p>Recognition Level</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-trophy"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo count($upcomingEventsList); ?></h3>
                <p>Upcoming Events</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $clubsJoined; ?></h3>
                <p>Clubs Joined</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-building"></i>
            </div>
        </div>
    </div>

    <!-- Recognition Progress Card -->
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
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $progressPercent; ?>%" aria-valuenow="<?php echo $progressPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="mt-1 d-block"><?php echo $nextLevelPoints; ?> more points to next level!</small>
                <?php else: ?>
                    <div class="progress mt-2">
                        <div class="progress-bar" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="mt-1 d-block">🎉 Congratulations! You have reached the highest level!</small>
                <?php endif; ?>
            </div>
            <div class="col-md-3 text-end">
                <a href="#" style="color: white; text-decoration: none;">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- My Clubs Section -->
    <div class="table-card">
        <h5><i class="fas fa-building"></i> My Clubs</h5>
        <?php if (empty($joinedClubsList)): ?>
            <p class="text-muted">You haven't joined any clubs yet. <a href="#">Browse Clubs</a></p>
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
            <p class="text-muted">You haven't registered for any upcoming events. <a href="#">Browse Events</a></p>
        <?php else: ?>
            <table>
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
                            <button class="action-btn" onclick="alert('View QR Code for: <?php echo $event['event_title']; ?>')">
                                <i class="fas fa-qrcode"></i>
                            </button>
                            <button class="action-btn" onclick="alert('Cancel registration for: <?php echo $event['event_title']; ?>')">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Past Events History -->
    <div class="table-card">
        <h5><i class="fas fa-history"></i> My Participation History</h5>
        <?php if (empty($pastEventsList)): ?>
            <p class="text-muted">No past events yet. Start participating!</p>
        <?php else: ?>
            <table>
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
                         </td>
                        <td>
                            <?php if ($event['pointsEarned'] > 0): ?>
                                <strong style="color: #28a745;">+<?php echo $event['pointsEarned']; ?></strong>
                            <?php elseif ($event['pointsEarned'] < 0): ?>
                                <strong style="color: #dc3545;"><?php echo $event['pointsEarned']; ?></strong>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                         </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="view-all-link mt-3">
                <a href="#">View Full History <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Additional JS if needed
</script>

</body>
</html>