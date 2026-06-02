<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT c.*, cp.positionName 
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

if ($club_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Active'");
    $stmt->execute([$club_id]);
    $totalMembers = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event WHERE club_id = ? AND status = 'UPCOMING'");
    $stmt->execute([$club_id]);
    $upcomingEvents = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Pending'");
    $stmt->execute([$club_id]);
    $pendingApps = $stmt->fetchColumn();
    
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
        .stat-icon { width: 50px; height: 50px; background: rgba(0,59,92,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 28px; color: var(--umpsa-blue); }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--umpsa-blue) 0%, var(--umpsa-dark-blue) 100%);
            color: white; border-radius: 16px; padding: 25px; margin-bottom: 25px;
        }
        
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .table-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .table-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .status-upcoming { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-pending { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .btn-approve { background: #28a745; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; font-size: 11px; margin-right: 5px; }
        .btn-reject { background: #dc3545; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer; font-size: 11px; }
        
        /* Logout Modal */
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
            width: 380px;
            text-align: center;
        }
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
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
        <!-- 1. Dashboard -->
        <a href="../module1/dashboard_committee.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard_committee.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        
        <!-- 2. My Club -->
        <a href="../module2/club_dashboard_committee.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'club_dashboard_committee.php') ? 'active' : ''; ?>">
            <i class="fas fa-building"></i> <span>My Club</span>
        </a>
        
        <!-- 3. Manage Events -->
        <a href="../module3/manage_events.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_events.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> <span>Manage Events</span>
        </a>
        
        <!-- 5. Record Attendance (QR Scanner) -->
        <a href="../module4/attendance_management.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_management.php') ? 'active' : ''; ?>">
            <i class="fas fa-qrcode"></i> <span>Record Attendance</span>
        </a>
        
        <!-- 6. Attendance Dashboard -->
        <a href="../module4/attendance_dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> <span>Attendance Dashboard</span>
        </a>
        
        <!-- 7. Reports -->
        <a href="../module4/generate_report.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'generate_report.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> <span>Reports</span>
        </a>
        
        <!-- 8. Profile -->
        <a href="../module1/profile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> <span>Profile</span>
        </a>
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
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                            <button class="btn btn-sm btn-outline-primary" onclick="alert('Manage event: <?php echo $event['event_title']; ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="alert('Generate QR for: <?php echo $event['event_title']; ?>')">
                                <i class="fas fa-qrcode"></i> QR
                            </button>
                         </td
                     </tr
                    <?php endforeach; ?>
                </tbody>
              </table
        <?php endif; ?>
        <div class="view-all-link mt-3">
            <a href="#" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Create New Event</a>
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
                         </td
                     </tr
                    <?php endforeach; ?>
                </tbody>
              </table
        <?php endif; ?>
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
<?php 
$pdo = null;
?>