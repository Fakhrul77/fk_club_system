<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in and is committee
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: login.php");
    exit();
}

// Set default role name if not set
if (!isset($_SESSION['user_role_name'])) {
    $_SESSION['user_role_name'] = 'Club Committee';
}

// Get the user's ID
$user_id = $_SESSION['user_id'];

// Get committee member's club information
$stmt = $pdo->prepare("
    SELECT c.*, cp.positionName, cc.assignedDate 
    FROM club_committee cc
    JOIN club c ON cc.club_id = c.club_id
    JOIN committee_position cp ON cc.position_id = cp.position_id
    WHERE cc.user_id = ? AND cc.status = 'Active'
");
$stmt->execute([$user_id]);
$club_info = $stmt->fetch();

$club_id = $club_info['club_id'] ?? null;
$club_name = $club_info['clubName'] ?? 'Your Club';
$position = $club_info['positionName'] ?? 'Committee Member';

// Get club statistics
if ($club_id) {
    // Total members in this club
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Active'");
    $stmt->execute([$club_id]);
    $totalMembers = $stmt->fetchColumn();
    
    // Upcoming events for this club
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event WHERE club_id = ? AND status = 'UPCOMING'");
    $stmt->execute([$club_id]);
    $upcomingEvents = $stmt->fetchColumn();
    
    // Total points earned by club members
    $stmt = $pdo->prepare("
        SELECT SUM(ap.pointsEarned) 
        FROM activity_points ap
        JOIN event e ON ap.event_id = e.event_id
        WHERE e.club_id = ?
    ");
    $stmt->execute([$club_id]);
    $totalPoints = $stmt->fetchColumn() ?? 0;
    
    // Pending membership applications
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Pending'");
    $stmt->execute([$club_id]);
    $pendingApps = $stmt->fetchColumn();
} else {
    $totalMembers = 0;
    $upcomingEvents = 0;
    $totalPoints = 0;
    $pendingApps = 0;
}

// Get recent events for this club
$recentEvents = [];
if ($club_id) {
    $stmt = $pdo->prepare("
        SELECT event_id, event_title, event_date, venue, max_participant, current_participant, status
        FROM event 
        WHERE club_id = ? 
        ORDER BY event_date ASC 
        LIMIT 5
    ");
    $stmt->execute([$club_id]);
    $recentEvents = $stmt->fetchAll();
}

// Get recent membership applications
$recentApps = [];
if ($club_id) {
    $stmt = $pdo->prepare("
        SELECT cm.*, u.name, u.email, u.studentId
        FROM club_membership cm
        JOIN users u ON cm.user_id = u.user_id
        WHERE cm.club_id = ? AND cm.status = 'Pending'
        ORDER BY cm.joinDate DESC
        LIMIT 5
    ");
    $stmt->execute([$club_id]);
    $recentApps = $stmt->fetchAll();
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

        .club-badge {
            background: #1E3A5F;
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

        /* ========== CHARTS SECTION ========== */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .chart-card h5 {
            color: #1E3A5F;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .chart-card h5 i {
            color: #FF6B35;
            margin-right: 8px;
        }

        canvas {
            max-height: 250px;
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

        .status-upcoming {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            margin: 0 5px;
            color: #666;
            transition: color 0.2s;
        }

        .action-btn:hover {
            color: #FF6B35;
        }

        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
            margin-right: 5px;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
        }

        .welcome-card {
            background: linear-gradient(135deg, #1E3A5F 0%, #2B4C7E 100%);
            color: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .charts-row {
                grid-template-columns: 1fr;
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

<!-- ========== SIDEBAR ========== -->
<div class="sidebar">
    <div class="sidebar-header">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <a href="#" class="active">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="#">
            <i class="fas fa-building"></i> <span>My Club</span>
        </a>
        <a href="#">
            <i class="fas fa-users"></i> <span>Members</span>
        </a>
        <a href="#">
            <i class="fas fa-calendar-plus"></i> <span>Create Event</span>
        </a>
        <a href="#">
            <i class="fas fa-qrcode"></i> <span>Record Attendance</span>
        </a>
        <a href="#">
            <i class="fas fa-chart-bar"></i> <span>Reports</span>
        </a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Committee'); ?>
            <span class="badge-role"><?php echo $_SESSION['user_role_name'] ?? 'Committee'; ?></span>
            <span class="club-badge"><i class="fas fa-building"></i> <?php echo htmlspecialchars($club_name); ?></span>
        </div>
        <a href="../../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Welcome Card -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($club_name); ?> Committee</h3>
                <p class="mb-0">Position: <strong><?php echo htmlspecialchars($position); ?></strong> | Member since: <?php echo date('d M Y', strtotime($club_info['assignedDate'] ?? 'now')); ?></p>
                <p>Manage your club activities, events, and member applications from this dashboard.</p>
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-users" style="font-size: 60px; opacity: 0.3;"></i>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalMembers; ?></h3>
                <p>Club Members</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $upcomingEvents; ?></h3>
                <p>Upcoming Events</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $pendingApps; ?></h3>
                <p>Pending Applications</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo number_format($totalPoints); ?></h3>
                <p>Total Points Awarded</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-row">
        <div class="chart-card">
            <h5><i class="fas fa-chart-line"></i> Event Participation Rate</h5>
            <canvas id="participationChart"></canvas>
        </div>
        <div class="chart-card">
            <h5><i class="fas fa-chart-pie"></i> Event Status Distribution</h5>
            <canvas id="eventStatusChart"></canvas>
        </div>
    </div>

    <!-- Upcoming Events Table -->
    <div class="table-card">
        <h5><i class="fas fa-calendar-alt"></i> Upcoming Events</h5>
        <?php if (empty($recentEvents)): ?>
            <p class="text-muted">No upcoming events scheduled.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Registration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentEvents as $event): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                        <td><?php echo date('d M Y', strtotime($event['event_date'])); ?></td>
                        <td><?php echo htmlspecialchars($event['venue']); ?></td>
                        <td><?php echo $event['current_participant']; ?>/<?php echo $event['max_participant']; ?></td>
                        <td><span class="status-upcoming"><?php echo $event['status']; ?></span></td>
                        <td>
                            <button class="action-btn" onclick="alert('Manage event: <?php echo $event['event_title']; ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn" onclick="alert('Generate QR for: <?php echo $event['event_title']; ?>')">
                                <i class="fas fa-qrcode"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="view-all-link mt-3">
            <a href="#"><i class="fas fa-plus"></i> Create New Event</a>
        </div>
    </div>

    <!-- Pending Applications Table -->
    <div class="table-card">
        <h5><i class="fas fa-user-plus"></i> Pending Membership Applications</h5>
        <?php if (empty($recentApps)): ?>
            <p class="text-muted">No pending applications.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Email</th>
                        <th>Applied Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentApps as $app): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['name']); ?></td>
                        <td><?php echo htmlspecialchars($app['studentId']); ?></td>
                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                        <td><?php echo date('d M Y', strtotime($app['joinDate'])); ?></td>
                        <td>
                            <button class="btn-approve" onclick="alert('Approve: <?php echo $app['name']; ?>')">Approve</button>
                            <button class="btn-reject" onclick="alert('Reject: <?php echo $app['name']; ?>')">Reject</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    // Participation Rate Chart (Bar Chart)
    const participationCtx = document.getElementById('participationChart').getContext('2d');
    new Chart(participationCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Participants',
                data: [45, 52, 60, 78, 65, <?php echo $totalMembers; ?>],
                backgroundColor: '#FF6B35',
                borderRadius: 8,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });

    // Event Status Chart (Doughnut Chart)
    const eventCtx = document.getElementById('eventStatusChart').getContext('2d');
    new Chart(eventCtx, {
        type: 'doughnut',
        data: {
            labels: ['Upcoming', 'Completed', 'Cancelled'],
            datasets: [{
                data: [<?php echo $upcomingEvents; ?>, 8, 2],
                backgroundColor: ['#28A745', '#1E3A5F', '#DC3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });
</script>

</body>
</html>