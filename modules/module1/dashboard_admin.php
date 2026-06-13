<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: login.php");
    exit();
}

// ========== STATISTICS CARDS ==========
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 3 AND status = 'Active'")->fetchColumn();
$totalClubs = $pdo->query("SELECT COUNT(*) FROM club WHERE status = 'Active'")->fetchColumn();
$totalEvents = $pdo->query("SELECT COUNT(*) FROM event WHERE status = 'UPCOMING'")->fetchColumn();
$totalCommittee = $pdo->query("SELECT COUNT(*) FROM club_committee WHERE status = 'Active'")->fetchColumn();
if ($totalCommittee == 0) {
    $totalCommittee = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 2 AND status = 'Active'")->fetchColumn();
}

// ========== RECENT USERS ==========
$recentUsers = $pdo->query("
    SELECT u.*, r.roleName 
    FROM users u 
    JOIN user_role r ON u.role_id = r.role_id 
    ORDER BY u.createdAt DESC 
    LIMIT 10
")->fetchAll();

// ========== DYNAMIC CHART 1: Monthly User Registrations ==========
// Fix: Removed the 'year_month' alias that was causing the syntax error
$monthlyUsers = $pdo->query("
    SELECT 
        DATE_FORMAT(createdAt, '%b') as month,
        COUNT(*) as count
    FROM users 
    WHERE createdAt >= DATE_SUB(NOW(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(createdAt, '%Y-%m')
    ORDER BY createdAt ASC
")->fetchAll();

// Initialize last 6 months with zero values
$chartMonths = [];
$chartRegistrations = [];
for ($i = 5; $i >= 0; $i--) {
    $monthName = date('M', strtotime("-$i months"));
    $chartMonths[] = $monthName;
    $chartRegistrations[$monthName] = 0;
}
foreach ($monthlyUsers as $row) {
    $chartRegistrations[$row['month']] = $row['count'];
}
$chartRegistrations = array_values($chartRegistrations);

// ========== DYNAMIC CHART 2: Club Distribution (Active Members) ==========
$clubDistribution = $pdo->query("
    SELECT 
        c.clubName,
        COUNT(cm.membership_id) as member_count
    FROM club c
    LEFT JOIN club_membership cm ON c.club_id = cm.club_id AND cm.status = 'Active'
    WHERE c.status = 'Active'
    GROUP BY c.club_id
    ORDER BY member_count DESC
    LIMIT 5
")->fetchAll();

$clubLabels = [];
$clubData = [];
$clubColors = ['#003B5C', '#FDB813', '#28A745', '#17A2B8', '#6C757D'];

foreach ($clubDistribution as $index => $club) {
    $clubLabels[] = $club['clubName'];
    $clubData[] = $club['member_count'];
}
// If no clubs exist, show placeholder
if (empty($clubLabels)) {
    $clubLabels = ['No Clubs Yet'];
    $clubData = [1];
}

// ========== DYNAMIC CHART 3: User Role Distribution ==========
$roleDistribution = $pdo->query("
    SELECT 
        r.roleName,
        COUNT(u.user_id) as count
    FROM user_role r
    LEFT JOIN users u ON r.role_id = u.role_id AND u.status = 'Active'
    GROUP BY r.role_id
")->fetchAll();

$roleLabels = [];
$roleData = [];
$roleColors = ['#003B5C', '#FDB813', '#28A745'];

foreach ($roleDistribution as $index => $role) {
    $roleLabels[] = $role['roleName'];
    $roleData[] = $role['count'];
}

// ========== DYNAMIC CHART 4: Event Participation Trend (Last 6 Months) ==========
$eventTrend = $pdo->query("
    SELECT 
        DATE_FORMAT(e.event_date, '%b') as month,
        COUNT(er.registration_id) as participant_count
    FROM event e
    LEFT JOIN event_registration er ON e.event_id = er.event_id AND er.status = 'Confirmed'
    WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
    ORDER BY e.event_date ASC
")->fetchAll();

$trendMonths = [];
$trendData = [];
for ($i = 5; $i >= 0; $i--) {
    $monthName = date('M', strtotime("-$i months"));
    $trendMonths[] = $monthName;
    $trendData[$monthName] = 0;
}
foreach ($eventTrend as $row) {
    $trendData[$row['month']] = $row['participant_count'];
}
$trendData = array_values($trendData);
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
        
        /* ========== MODERN BUTTON STYLES ========== */

/* Manage All Users Button */
.btn-manage {
    background: linear-gradient(135deg, var(--umpsa-blue), var(--umpsa-dark-blue));
    color: white;
    padding: 8px 20px;
    border-radius: 30px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.btn-manage:hover {
    background: linear-gradient(135deg, var(--umpsa-dark-blue), var(--umpsa-blue));
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,59,92,0.3);
    color: white;
}

.btn-manage i {
    font-size: 14px;
}

/* Edit Button */
.btn-edit-modern {
    background: #17a2b8;
    color: white;
    padding: 6px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
}

.btn-edit-modern:hover {
    background: #138496;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(23,162,184,0.3);
    color: white;
}

.btn-edit-modern i {
    font-size: 12px;
}

/* View Button */
.btn-view-modern {
    background: #28a745;
    color: white;
    padding: 6px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
}

.btn-view-modern:hover {
    background: #218838;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(40,167,69,0.3);
    color: white;
}

/* Delete/Deactivate Button */
.btn-danger-modern {
    background: #dc3545;
    color: white;
    padding: 6px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    cursor: pointer;
}

.btn-danger-modern:hover {
    background: #c82333;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(220,53,69,0.3);
}

/* Activate Button */
.btn-success-modern {
    background: #28a745;
    color: white;
    padding: 6px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    cursor: pointer;
}

.btn-success-modern:hover {
    background: #218838;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(40,167,69,0.3);
}

/* Add User Button */
.btn-add-modern {
    background: linear-gradient(135deg, #28a745, #1e7e34);
    color: white;
    padding: 10px 24px;
    border-radius: 30px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.btn-add-modern:hover {
    background: linear-gradient(135deg, #1e7e34, #155724);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40,167,69,0.3);
    color: white;
}

/* Action Buttons Container */
.action-buttons-container {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Table Action Icons (for compact view) */
.action-icon {
    background: none;
    border: none;
    cursor: pointer;
    margin: 0 4px;
    font-size: 16px;
    transition: all 0.2s ease;
    padding: 6px;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.action-icon.edit {
    color: #17a2b8;
}

.action-icon.edit:hover {
    background: rgba(23,162,184,0.1);
    transform: scale(1.1);
}

.action-icon.delete {
    color: #dc3545;
}

.action-icon.delete:hover {
    background: rgba(220,53,69,0.1);
    transform: scale(1.1);
}

.action-icon.activate {
    color: #28a745;
}

.action-icon.activate:hover {
    background: rgba(40,167,69,0.1);
    transform: scale(1.1);
}

.action-icon.deactivate {
    color: #ffc107;
}

.action-icon.deactivate:hover {
    background: rgba(255,193,7,0.1);
    transform: scale(1.1);
}
        .status-active {
         background: #d4edda;
         color: #155724;
         padding: 4px 10px;
         border-radius: 20px;
         font-size: 11px;
         display: inline-block;
         }

        .status-inactive {
         background: #f8d7da;
         color: #721c24;
         padding: 4px 10px;
         border-radius: 20px;
         font-size: 11px;
         display: inline-block;
         }
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
        
        .charts-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .chart-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .chart-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        
        .table-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .table-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        
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
        <a href="../module1/dashboard_admin.php" class="active">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module1/manage_users.php">
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        <a href="../module2/club_redirect.php">
            <i class="fas fa-building"></i> <span>Manage Clubs</span>
        </a>
        <a href="../module3/event_dashboard.php">
               <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
            </a>
        <a href="../module3/manage_events.php">
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

    <!-- Statistics Cards -->
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

    <!-- Charts Row 1 -->
    <div class="charts-row">
        <div class="chart-card">
            <h5><i class="fas fa-chart-line"></i> User Registration (Last 6 Months)</h5>
            <canvas id="userChart"></canvas>
        </div>
        <div class="chart-card">
            <h5><i class="fas fa-chart-pie"></i> Club Distribution (Active Members)</h5>
            <canvas id="clubChart"></canvas>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="charts-row">
        <div class="chart-card">
            <h5><i class="fas fa-chart-pie"></i> User Role Distribution</h5>
            <canvas id="roleChart"></canvas>
        </div>
        <div class="chart-card">
            <h5><i class="fas fa-chart-line"></i> Event Participation Trend (Last 6 Months)</h5>
            <canvas id="trendChart"></canvas>
        </div>
    </div>

   <!-- Recent Users Table with Search -->
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-clock"></i> Recent User Registrations</h5>
        <div class="d-flex gap-2 flex-wrap">
            <div class="input-group" style="width: 260px;">
                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="searchRecentUsers" class="form-control form-control-sm" 
                       placeholder="Search by name, email or ID...">
                <button id="clearRecentSearch" class="btn btn-outline-secondary btn-sm" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <select id="filterRecentRole" class="form-select form-select-sm" style="width: 140px;">
                <option value="all">All Roles</option>
                <option value="Administrator">Administrator</option>
                <option value="Club Committee">Committee</option>
                <option value="Student">Student</option>
            </select>
            <a href="manage_users.php" class="btn-manage">
    <i class="fas fa-users"></i> Manage All Users
</a>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table" id="recentUsersTable">
            <thead>
                <tr>
                    <th><i class="fas fa-calendar"></i> Date</th>
                    <th><i class="fas fa-user"></i> Name</th>
                    <th><i class="fas fa-id-card"></i> Student ID</th>
                    <th><i class="fas fa-tag"></i> Role</th>
                    <th><i class="fas fa-circle"></i> Status</th>
                    <th><i class="fas fa-cog"></i> Actions</th>
                </tr>
            </thead>
            <tbody id="recentUsersBody">
                <?php foreach ($recentUsers as $user): ?>
                <tr class="recent-user-row" 
                    data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>"
                    data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>"
                    data-id="<?php echo strtolower(htmlspecialchars($user['studentId'] ?? '')); ?>"
                    data-role="<?php echo htmlspecialchars($user['roleName']); ?>">
                    <td><?php echo date('d/m/Y', strtotime($user['createdAt'] ?? 'now')); ?></td>
                    <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['studentId'] ?? '-'); ?></td>
                    <td>
                        <span class="badge" style="background: var(--umpsa-light-blue); color: var(--umpsa-blue);">
                            <?php echo htmlspecialchars($user['roleName']); ?>
                        </span>
                    </td>
                    <td>
                      <span class="<?php echo $user['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>">
                              <?php echo $user['status']; ?>
                     </span>
                    </td>
                    <td>
                        <a href="add_edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn-edit-modern">
    <i class="fas fa-edit"></i> Edit
</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div id="noRecentResults" class="text-center py-4" style="display: none;">
        <i class="fas fa-search fa-2x text-muted mb-2 d-block"></i>
        <p class="text-muted mb-0">No users match your search criteria.</p>
        <button class="btn btn-sm btn-link" onclick="clearRecentSearch()">
            <i class="fas fa-undo"></i> Clear Search
        </button>
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
    // Chart 1: User Registration (Bar Chart)
    const userCtx = document.getElementById('userChart').getContext('2d');
    new Chart(userCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartMonths); ?>,
            datasets: [{
                label: 'New Registrations',
                data: <?php echo json_encode($chartRegistrations); ?>,
                backgroundColor: '#FDB813',
                borderRadius: 8,
                borderColor: '#003B5C',
                borderWidth: 1
            }]
        },
        options: { 
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw + ' new user' + (context.raw !== 1 ? 's' : '');
                        }
                    }
                }
            }
        }
    });

    // Chart 2: Club Distribution (Pie Chart)
    const clubCtx = document.getElementById('clubChart').getContext('2d');
    new Chart(clubCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($clubLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($clubData); ?>,
                backgroundColor: <?php echo json_encode(array_slice($clubColors, 0, count($clubLabels))); ?>,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.raw + ' member' + (context.raw !== 1 ? 's' : '');
                        }
                    }
                }
            }
        }
    });

    // Chart 3: User Role Distribution (Doughnut Chart)
    const roleCtx = document.getElementById('roleChart').getContext('2d');
    new Chart(roleCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($roleLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($roleData); ?>,
                backgroundColor: ['#003B5C', '#FDB813', '#28A745'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.raw + ' user' + (context.raw !== 1 ? 's' : '');
                        }
                    }
                }
            }
        }
    });

    // Chart 4: Event Participation Trend (Line Chart)
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trendMonths); ?>,
            datasets: [{
                label: 'Participants',
                data: <?php echo json_encode($trendData); ?>,
                borderColor: '#003B5C',
                backgroundColor: 'rgba(0,59,92,0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#FDB813',
                pointBorderColor: '#003B5C',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw + ' participant' + (context.raw !== 1 ? 's' : '');
                        }
                    }
                }
            }
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

<script>
// Search and Filter for Recent Users Table
function filterRecentUsers() {
    const searchTerm = document.getElementById('searchRecentUsers').value.toLowerCase().trim();
    const roleFilter = document.getElementById('filterRecentRole').value;
    const rows = document.querySelectorAll('#recentUsersBody .recent-user-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const email = row.getAttribute('data-email') || '';
        const id = row.getAttribute('data-id') || '';
        const role = row.getAttribute('data-role') || '';
        
        const matchesSearch = searchTerm === '' || 
                             name.includes(searchTerm) || 
                             email.includes(searchTerm) || 
                             id.includes(searchTerm);
        const matchesRole = roleFilter === 'all' || role === roleFilter;
        
        if (matchesSearch && matchesRole) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    const noResultsDiv = document.getElementById('noRecentResults');
    if (visibleCount === 0 && rows.length > 0) {
        noResultsDiv.style.display = 'block';
    } else {
        noResultsDiv.style.display = 'none';
    }
}

// Clear search function
function clearRecentSearch() {
    document.getElementById('searchRecentUsers').value = '';
    document.getElementById('filterRecentRole').value = 'all';
    filterRecentUsers();
}

// Event listeners
document.getElementById('searchRecentUsers').addEventListener('keyup', filterRecentUsers);
document.getElementById('filterRecentRole').addEventListener('change', filterRecentUsers);
document.getElementById('clearRecentSearch').addEventListener('click', clearRecentSearch);
</script>
</body>
</html>
<?php 
$pdo = null;
?>