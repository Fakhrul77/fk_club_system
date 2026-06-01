<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: login.php");
    exit();
}

$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 3 AND status = 'Active'")->fetchColumn();
$totalClubs = $pdo->query("SELECT COUNT(*) FROM club WHERE status = 'Active'")->fetchColumn();
$totalEvents = $pdo->query("SELECT COUNT(*) FROM event WHERE status = 'UPCOMING'")->fetchColumn();
$totalCommittee = $pdo->query("SELECT COUNT(*) FROM club_committee WHERE status = 'Active'")->fetchColumn();
if ($totalCommittee == 0) {
    $totalCommittee = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 2 AND status = 'Active'")->fetchColumn();
}

$recentUsers = $pdo->query("
    SELECT u.*, r.roleName 
    FROM users u 
    JOIN user_role r ON u.role_id = r.role_id 
    ORDER BY u.createdAt DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FK Club System</title>
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
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { margin: 0; font-size: 18px; }
        .sidebar-header p { font-size: 11px; opacity: 0.7; margin-top: 5px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: block;
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        .sidebar-menu a:hover { background: rgba(253,184,19,0.2); color: white; }
        .sidebar-menu a i { margin-right: 10px; width: 20px; }
        .sidebar-menu a.active { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); }
        
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
        
        .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .chart-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .chart-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        
        .table-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .table-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .status-active { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        
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
            .charts-row { grid-template-columns: 1fr; }
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
        <a href="#" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
        <a href="../module2/club_redirect.php">
        <i class="fas fa-building"></i> <span>Manage Clubs</span></a>
        <a href="../module3/manage_events.php"><i class="fas fa-calendar-alt"></i> <span>Events</span></a>
        <a href="../module4/attendance_dashboard.php">
        <i class="fas fa-chart-bar"></i> <span>Attendance Dashboard</span></a>
        <a href="../module4/generate_report.php">
        <i class="fas fa-file-alt"></i> <span>Report</span></a>
        <a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
            <span class="badge-role">Administrator</span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h2 class="mb-4" style="color: var(--umpsa-blue);"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalStudents; ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalClubs; ?></h3>
                <p>Active Clubs</p>
            </div>
            <div class="stat-icon"><i class="fas fa-building"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalEvents; ?></h3>
                <p>Upcoming Events</p>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalCommittee; ?></h3>
                <p>Committee Members</p>
            </div>
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
        </div>
    </div>

    <div class="charts-row">
        <div class="chart-card">
            <h5><i class="fas fa-chart-line"></i> User Registration (Monthly)</h5>
            <canvas id="userChart"></canvas>
        </div>
        <div class="chart-card">
            <h5><i class="fas fa-chart-pie"></i> Club Distribution</h5>
            <canvas id="clubChart"></canvas>
        </div>
    </div>

    <div class="table-card">
        <h5><i class="fas fa-clock"></i> Recent User Registrations</h5>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentUsers as $user): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($user['createdAt'] ?? 'now')); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['studentId'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($user['roleName']); ?></td>
                    <td><span class="status-active"><?php echo $user['status']; ?></span></td>
                    <td>
                        <a href="add_edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
    new Chart(document.getElementById('userChart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'New Registrations',
                data: [45, 52, 60, 78, 95, 120],
                backgroundColor: '#FDB813',
                borderRadius: 8
            }]
        },
        options: { responsive: true }
    });
    
    new Chart(document.getElementById('clubChart'), {
        type: 'pie',
        data: {
            labels: ['Computing Club', 'Robotics Club', 'Sports Club', 'Cultural Club', 'Others'],
            datasets: [{
                data: [30, 25, 20, 15, 10],
                backgroundColor: ['#003B5C', '#FDB813', '#28A745', '#17A2B8', '#6C757D']
            }]
        },
        options: { responsive: true }
    });
    
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