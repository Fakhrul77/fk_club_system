<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 1 && $_SESSION['user_role'] != 2)) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get club filter
$club_filter = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// Get clubs for filter
$clubs = $pdo->query("SELECT club_id, clubName FROM club WHERE status = 'Active'")->fetchAll();

// ========== STATISTICS ==========
$totalEvents = $pdo->query("SELECT COUNT(*) FROM event")->fetchColumn();
$totalRegistrations = $pdo->query("SELECT COUNT(*) FROM event_registration")->fetchColumn();
$avgParticipants = round($pdo->query("SELECT AVG(current_participant) FROM event")->fetchColumn(), 1);
$upcomingEvents = $pdo->query("SELECT COUNT(*) FROM event WHERE event_date >= CURDATE() AND status = 'UPCOMING'")->fetchColumn();

// ========== CHART DATA ==========
$clubData = $pdo->query("
    SELECT c.clubName, COUNT(e.event_id) as event_count 
    FROM club c 
    LEFT JOIN event e ON c.club_id = e.club_id 
    WHERE c.status = 'Active'
    GROUP BY c.club_id 
    ORDER BY event_count DESC 
    LIMIT 6
")->fetchAll();

$statusData = $pdo->query("SELECT status, COUNT(*) as count FROM event GROUP BY status")->fetchAll();

$monthlyData = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b') as month, COUNT(*) as count
    FROM event
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%b')
    ORDER BY MIN(created_at)
")->fetchAll();

$joinReport = $pdo->query("
    SELECT 
        e.event_id,
        e.event_title,
        c.clubName,
        e.event_date,
        CASE 
            WHEN e.event_date < CURDATE() THEN 'COMPLETED'
            WHEN e.event_date = CURDATE() THEN 'ONGOING'
            ELSE 'UPCOMING'
        END as status,
        e.current_participant,
        e.max_participant,
        e.points_awarded,
        COUNT(DISTINCT er.registration_id) as registered_count,
        CASE 
            WHEN e.current_participant >= e.max_participant THEN 'Full'
            WHEN e.event_date < CURDATE() THEN 'Passed'
            ELSE 'Available'
        END as availability
    FROM event e
    JOIN club c ON e.club_id = c.club_id
    LEFT JOIN event_registration er ON e.event_id = er.event_id AND er.status IN ('Confirmed', 'Attended')
    GROUP BY e.event_id
    ORDER BY e.event_date DESC
    LIMIT 10
");

$popularEvents = $pdo->query("
    SELECT e.event_title, c.clubName, COUNT(er.registration_id) as participant_count
    FROM event e
    JOIN club c ON e.club_id = c.club_id
    JOIN event_registration er ON e.event_id = er.event_id
    WHERE er.status IN ('Confirmed', 'Attended')
    GROUP BY e.event_id
    ORDER BY participant_count DESC
    LIMIT 5
")->fetchAll();

$trendData = $pdo->query("
    SELECT DATE_FORMAT(event_date, '%Y-%m') as month,
           COUNT(*) as events_count,
           SUM(current_participant) as total_participants
    FROM event
    WHERE event_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(event_date, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

$totalCap = $pdo->query("SELECT SUM(max_participant) FROM event")->fetchColumn();
$totalFilled = $pdo->query("SELECT SUM(current_participant) FROM event")->fetchColumn();
$fillRate = $totalCap > 0 ? round(($totalFilled / $totalCap) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Dashboard - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-info h3 { font-size: 28px; font-weight: bold; color: var(--umpsa-blue); margin-bottom: 5px; }
        .stat-info p { color: #666; margin: 0; font-size: 13px; }
        .stat-icon { width: 50px; height: 50px; background: rgba(0,59,92,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 28px; color: var(--umpsa-blue); }
        
        .chart-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .chart-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .chart-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        .charts-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        
        .table-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .table-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        
        .status-UPCOMING { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-ONGOING { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-COMPLETED { background: #d1ecf1; color: #0c5460; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-CANCELLED { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        
        .btn-view-event {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-view-event:hover {
            background: #138496;
            color: white;
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
            .charts-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ========== DYNAMIC SIDEBAR ========== -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="../../assets/images/logo.png" alt="Logo" style="width: 50px; height: auto; margin-bottom: 10px;">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <?php if ($user_role == 1): // ADMIN SIDEBAR ?>
            <a href="../module1/dashboard_admin.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module1/manage_users.php">
                <i class="fas fa-users"></i> <span>Manage Users</span>
            </a>
            <a href="../module2/club_dashboard_admin.php">
                <i class="fas fa-building"></i> <span>Manage Clubs</span>
            </a>

            <a href="event_dashboard.php" class="active">
                <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
            </a>

            <a href="manage_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Events</span>
            </a>
            
            <a href="../module4/attendance_dashboard.php">
                <i class="fas fa-chart-bar"></i> <span>Attendance</span>
            </a>
            <a href="../module4/generate_report.php">
                <i class="fas fa-file-alt"></i> <span>Reports</span>
            </a>
            <a href="../module1/profile.php">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
            
        <?php else: // COMMITTEE SIDEBAR ?>
            <a href="../module1/dashboard_committee.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module2/club_dashboard_committee.php">
                <i class="fas fa-building"></i> <span>My Club</span>
            </a>
            <a href="../module3/event_dashboard.php" class="active">
             <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
            </a>
            <a href="manage_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Manage Events</span>
            </a>
            <a href="../module4/attendance_management.php">
                <i class="fas fa-qrcode"></i> <span>Record Attendance</span>
            </a>
            <a href="../module4/attendance_dashboard.php">
                <i class="fas fa-chart-bar"></i> <span>Attendance Dashboard</span>
            </a>
            <a href="../module4/generate_report.php">
                <i class="fas fa-file-alt"></i> <span>Reports</span>
            </a>
            <a href="../module1/profile.php">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
            <span class="badge-role"><?php echo $user_role == 1 ? 'Administrator' : 'Committee'; ?></span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h2 class="mb-4" style="color: var(--umpsa-blue);"><i class="fas fa-chart-line"></i> Event Management Dashboard</h2>

    <!-- Statistics Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $totalEvents; ?></h3><p>Total Events</p></div>
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $totalRegistrations; ?></h3><p>Total Registrations</p></div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $avgParticipants; ?></h3><p>Avg Participants/Event</p></div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $upcomingEvents; ?></h3><p>Upcoming Events</p></div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="charts-row">
        <div class="chart-card">
            <h5><i class="fas fa-chart-bar"></i> Events by Club</h5>
            <canvas id="clubChart" style="height: 250px;"></canvas>
        </div>
        <div class="chart-card">
            <h5><i class="fas fa-chart-pie"></i> Events by Status</h5>
            <canvas id="statusChart" style="height: 250px;"></canvas>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="charts-row">
        <div class="chart-card">
            <h5><i class="fas fa-chart-line"></i> Monthly Event Trend</h5>
            <canvas id="trendChart" style="height: 250px;"></canvas>
        </div>
        <div class="chart-card">
            <h5><i class="fas fa-trophy"></i> Most Popular Events</h5>
            <canvas id="popularChart" style="height: 250px;"></canvas>
        </div>
    </div>

    <!-- JOIN TABLE REPORT -->
<div class="table-card">
    <h5><i class="fas fa-table"></i> Events Summary</h5>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Club</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Capacity</th>
                    <th>Registered</th>
                    <th>Points</th>
                    <th>Availability</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($joinReport as $event): ?>
                <tr>
                    <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                    <td><?php echo htmlspecialchars($event['clubName']); ?></td>
                    <td><?php echo date('d M Y', strtotime($event['event_date'])); ?></td>
                    <td><span class="status-<?php echo $event['status']; ?>"><?php echo $event['status']; ?></span></td>
                    <td><?php echo $event['current_participant']; ?>/<?php echo $event['max_participant']; ?></td>
                    <td><?php echo $event['registered_count']; ?></td>
                    <td><?php echo $event['points_awarded']; ?></td>
                    <td>
                        <?php if ($event['availability'] == 'Full'): ?>
                            <span class="text-danger">Full</span>
                        <?php elseif ($event['availability'] == 'Passed'): ?>
                            <span class="text-muted">Passed</span>
                        <?php else: ?>
                            <span class="text-success">Available</span>
                        <?php endif; ?>
                        </td>
                    <td>
                        <!-- ADDED return parameter -->
                        <a href="view_event.php?id=<?php echo $event['event_id']; ?>&return=event_dashboard.php" class="btn-view-event">
                            <i class="fas fa-eye"></i> View
                        </a>
                        </td>
                        </tr>
                <?php endforeach; ?>
            </tbody>
                        </table>
    </div>
</div>

    <!-- COUNT() / SUM() Report -->
    <div class="table-card">
        <h5><i class="fas fa-calculator"></i> Event Statistics</h5>
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card text-center p-3">
                    <h6>Total Registrations</h6>
                    <h3><?php echo $totalRegistrations; ?></h3>
                    <small class="text-muted">across all events</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center p-3">
                    <h6>Total Capacity</h6>
                    <h3><?php echo $totalFilled; ?> / <?php echo $totalCap; ?></h3>
                    <small class="text-muted">filled / total capacity</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center p-3">
                    <h6>Average Fill Rate</h6>
                    <h3><?php echo $fillRate; ?>%</h3>
                    <small class="text-muted">overall attendance rate</small>
                </div>
            </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Chart 1: Events by Club
    new Chart(document.getElementById('clubChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($clubData, 'clubName')); ?>,
            datasets: [{
                label: 'Number of Events',
                data: <?php echo json_encode(array_column($clubData, 'event_count')); ?>,
                backgroundColor: '#003B5C',
                borderRadius: 8
            }]
        },
        options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    // Chart 2: Events by Status
    new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($statusData, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($statusData, 'count')); ?>,
                backgroundColor: ['#28A745', '#FFC107', '#17A2B8', '#DC3545']
            }]
        },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
    });

    // Chart 3: Monthly Trend
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($trendData, 'month')); ?>,
            datasets: [
                {
                    label: 'Events Created',
                    data: <?php echo json_encode(array_column($trendData, 'events_count')); ?>,
                    borderColor: '#003B5C',
                    backgroundColor: 'rgba(0,59,92,0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Total Participants',
                    data: <?php echo json_encode(array_column($trendData, 'total_participants')); ?>,
                    borderColor: '#FDB813',
                    backgroundColor: 'rgba(253,184,19,0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
    });

    // Chart 4: Popular Events
    new Chart(document.getElementById('popularChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($popularEvents, 'event_title')); ?>,
            datasets: [{
                label: 'Number of Participants',
                data: <?php echo json_encode(array_column($popularEvents, 'participant_count')); ?>,
                backgroundColor: '#FDB813',
                borderRadius: 8
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: true,
            indexAxis: 'y',
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Logout functionality
    function showLogoutConfirm() {
        document.getElementById('logoutModal').style.display = 'flex';
    }
    
    document.getElementById('confirmLogout').onclick = function() {
        window.location.href = '../../logout.php';
    };
    
    document.getElementById('cancelLogout').onclick = function() {
        document.getElementById('logoutModal').style.display = 'none';
    };
    
    window.onclick = function(event) {
        const modal = document.getElementById('logoutModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };
</script>

</body>
</html>
<?php $pdo = null; ?>