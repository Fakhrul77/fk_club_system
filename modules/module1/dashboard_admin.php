<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: login.php");
    exit();
}

// Set default role name if not set
if (!isset($_SESSION['user_role_name'])) {
    $_SESSION['user_role_name'] = 'Administrator';
}

// Get statistics from database
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 3")->fetchColumn();
$totalClubs = $pdo->query("SELECT COUNT(*) FROM club WHERE status = 'Active'")->fetchColumn();
$totalEvents = $pdo->query("SELECT COUNT(*) FROM event WHERE status = 'UPCOMING'")->fetchColumn();
$totalCommittee = $pdo->query("SELECT COUNT(*) FROM club_committee")->fetchColumn();

// Get recent users
$recentUsers = $pdo->query("SELECT u.*, r.roleName FROM users u 
                            JOIN user_role r ON u.role_id = r.role_id 
                            ORDER BY u.createdAt DESC LIMIT 5")->fetchAll();
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

        /* ========== RECENT USERS TABLE ========== */
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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

        .status-active {
            background: #d4edda;
            color: #155724;
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

        .view-all-link {
            text-align: right;
            margin-top: 15px;
        }

        .view-all-link a {
            color: #FF6B35;
            text-decoration: none;
            font-size: 13px;
        }

        .view-all-link a:hover {
            text-decoration: underline;
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
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        <a href="#">
            <i class="fas fa-building"></i> <span>Manage Clubs</span>
        </a>
        <a href="#">
            <i class="fas fa-calendar-alt"></i> <span>Events</span>
        </a>
        <a href="#">
            <i class="fas fa-chart-bar"></i> <span>Reports</span>
        </a>
        <a href="#">
            <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
            <span class="badge-role"><?php echo $_SESSION['user_role_name'] ?? 'Administrator'; ?></span>
        </div>
        <a href="../../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Page Title -->
    <h2 class="mb-4" style="color: #1E3A5F;">
        <i class="fas fa-tachometer-alt"></i> Admin Dashboard
    </h2>

    <!-- Statistics Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalStudents; ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalClubs; ?></h3>
                <p>Active Clubs</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-building"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalEvents; ?></h3>
                <p>Upcoming Events</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?php echo $totalCommittee; ?></h3>
                <p>Committee Members</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
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

    <!-- Recent Users Table -->
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
                    <td><span class="status-active">Active</span></td>
                    <td>
                        <button class="action-btn" onclick="alert('Edit user: <?php echo $user['name']; ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn" onclick="alert('Delete user: <?php echo $user['name']; ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="view-all-link">
            <a href="#">View All Users <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</div>

<script>
    // User Registration Chart (Bar Chart)
    const userCtx = document.getElementById('userChart').getContext('2d');
    new Chart(userCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'New Registrations',
                data: [45, 52, 60, 78, 95, 120],
                backgroundColor: '#FF6B35',
                borderRadius: 8,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });

    // Club Distribution Chart (Pie Chart)
    const clubCtx = document.getElementById('clubChart').getContext('2d');
    new Chart(clubCtx, {
        type: 'pie',
        data: {
            labels: ['Computing Club', 'Robotics Club', 'Sports Club', 'Cultural Club', 'Others'],
            datasets: [{
                data: [30, 25, 20, 15, 10],
                backgroundColor: ['#1E3A5F', '#FF6B35', '#28A745', '#17A2B8', '#6C757D'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
</script>

</body>
</html>