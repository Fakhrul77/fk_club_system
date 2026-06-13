<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: ../module1/login.php");
    exit();
}

// Fetch statistics
$totalClubs = $pdo->query("SELECT COUNT(*) FROM club")->fetchColumn();
$activeClubs = $pdo->query("SELECT COUNT(*) FROM club WHERE status = 'Active'")->fetchColumn();
$inactiveClubs = $pdo->query("SELECT COUNT(*) FROM club WHERE status = 'Inactive'")->fetchColumn();
$totalStudentsInClubs = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM club_membership WHERE status = 'Active'")->fetchColumn();

// Get club distribution for chart
$clubDistribution = $pdo->query("
    SELECT c.clubName, COUNT(cm.user_id) as member_count
    FROM club c
    LEFT JOIN club_membership cm ON c.club_id = cm.club_id AND cm.status = 'Active'
    WHERE c.status = 'Active'
    GROUP BY c.club_id
    HAVING member_count > 0
    ORDER BY member_count DESC
")->fetchAll();

// Get all clubs
$clubs = $pdo->query("
    SELECT c.*, 
           COUNT(DISTINCT cm.user_id) as member_count,
           COUNT(DISTINCT e.event_id) as event_count,
           COUNT(DISTINCT cc.user_id) as committee_count
    FROM club c
    LEFT JOIN club_membership cm ON c.club_id = cm.club_id AND cm.status = 'Active'
    LEFT JOIN event e ON c.club_id = e.club_id
    LEFT JOIN club_committee cc ON c.club_id = cc.club_id AND cc.status = 'Active'
    GROUP BY c.club_id
    ORDER BY c.club_id DESC
")->fetchAll();

$success_message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'created') $success_message = '<div class="alert alert-success">✅ Club created successfully!</div>';
    if ($_GET['msg'] == 'updated') $success_message = '<div class="alert alert-success">✅ Club updated successfully!</div>';
    if ($_GET['msg'] == 'deleted') $success_message = '<div class="alert alert-success">✅ Club deleted successfully!</div>';
    if ($_GET['msg'] == 'committee_added') $success_message = '<div class="alert alert-success">✅ Committee member added!</div>';
    if ($_GET['msg'] == 'committee_removed') $success_message = '<div class="alert alert-success">✅ Committee member removed!</div>';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Club Management - FK Club System</title>
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

/* Modal Overlay */
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

.modal-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
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
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        .logout-btn:hover { background: #c82333; color: white; }
        
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
        .club-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .club-card:hover { transform: translateY(-3px); }
        .status-active { background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-pending { background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-inactive { background: #f8d7da; color: #721c24; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .btn-create { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; }
        .btn-create:hover { background: #e5a600; color: var(--umpsa-dark-blue); }
        .committee-tag { background: var(--umpsa-light-blue); padding: 2px 8px; border-radius: 15px; display: inline-block; margin: 2px; font-size: 11px; }
        
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

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="../../assets/images/logo.png" alt="Logo" style="width: 50px; height: auto; margin-bottom: 10px;">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <a href="../module1/dashboard_admin.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard_admin.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module1/manage_users.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php' || basename($_SERVER['PHP_SELF']) == 'add_edit_user.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        <a href="../module2/club_redirect.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'club_dashboard_admin.php' || basename($_SERVER['PHP_SELF']) == 'club_edit.php' || basename($_SERVER['PHP_SELF']) == 'club_create.php' || basename($_SERVER['PHP_SELF']) == 'committee_assign.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-building"></i> <span>Manage Clubs</span>
        </a>
        
        <a href="../module3/event_dashboard.php">
           <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
        </a>

        <a href="../module3/manage_events.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_events.php' || basename($_SERVER['PHP_SELF']) == 'create_event.php' || basename($_SERVER['PHP_SELF']) == 'edit_event.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-calendar-alt"></i> <span>Events</span>
        </a>
        <a href="../module4/attendance_dashboard.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_dashboard.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-chart-bar"></i> <span>Attendance</span>
        </a>
        <a href="../module4/generate_report.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'generate_report.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-file-alt"></i> <span>Reports</span>
        </a>
        <a href="../module1/profile.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-user"></i> <span>Profile</span>
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
            <span class="badge-role">Administrator</span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h2 class="mb-4" style="color: var(--umpsa-blue);"><i class="fas fa-tachometer-alt"></i> Club Management Dashboard</h2>

    <?php echo $success_message; ?>

    <!-- Statistics Cards -->
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-info"><h3><?php echo $totalClubs; ?></h3><p>Total Clubs</p></div><div class="stat-icon"><i class="fas fa-building"></i></div></div>
        <div class="stat-card"><div class="stat-info"><h3><?php echo $activeClubs; ?></h3><p>Active Clubs</p></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
        <div class="stat-card"><div class="stat-info"><h3><?php echo $inactiveClubs; ?></h3><p>Inactive Clubs</p></div><div class="stat-icon"><i class="fas fa-ban"></i></div></div>
        <div class="stat-card"><div class="stat-info"><h3><?php echo $totalStudentsInClubs; ?></h3><p>Students in Clubs</p></div><div class="stat-icon"><i class="fas fa-users"></i></div></div>
    </div>

    <!-- Charts -->
    <div class="charts-row">
        <div class="chart-card">
            <h5><i class="fas fa-chart-pie"></i> Student Distribution Across Clubs</h5>
            <canvas id="distributionChart"></canvas>
        </div>
        <div class="chart-card">
            <h5><i class="fas fa-chart-bar"></i> Club Status Overview</h5>
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <!-- Create Club Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-list"></i> All Clubs</h4>
        <a href="club_create.php" class="btn-create"><i class="fas fa-plus-circle"></i> Create New Club</a>
    </div>

    <!-- Clubs List -->
    <div class="row">
        <?php foreach ($clubs as $club): 
            $statusClass = $club['status'] == 'Active' ? 'status-active' : ($club['status'] == 'Pending' ? 'status-pending' : 'status-inactive');
        ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="club-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><h5><?php echo htmlspecialchars($club['clubName']); ?></h5><span class="<?php echo $statusClass; ?>"><?php echo $club['status']; ?></span></div>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($club['clubCategory'] ?? 'General'); ?></span>
                    </div>
                    <hr>
                    <div class="row text-center mb-2">
                        <div class="col-4"><small><i class="fas fa-users"></i> <?php echo $club['member_count']; ?> members</small></div>
                        <div class="col-4"><small><i class="fas fa-calendar"></i> <?php echo $club['event_count']; ?> events</small></div>
                        <div class="col-4"><small><i class="fas fa-user-tie"></i> <?php echo $club['committee_count']; ?> committee</small></div>
                    </div>
                    <div class="btn-group w-100 mt-2">
                        <a href="club_view.php?id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a>
                        <a href="club_edit.php?id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</a>
                        <a href="committee_assign.php?id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-user-tie"></i> Committee</a>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="openStatusModal(<?php echo $club['club_id']; ?>, '<?php echo htmlspecialchars($club['clubName']); ?>')">
    <i class="fas fa-power-off"></i>
</button>
<button type="button" class="btn btn-sm btn-danger" onclick="openDeleteClubModal(<?php echo $club['club_id']; ?>, '<?php echo htmlspecialchars($club['clubName']); ?>')">
    <i class="fas fa-trash"></i>
</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($clubs)): ?>
            <div class="col-12"><div class="alert alert-info">No clubs found. <a href="club_create.php">Create your first club!</a></div></div>
        <?php endif; ?>
    </div>
</div>

<!-- Change Status Confirmation Modal -->
<div id="statusModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-sync-alt" style="font-size: 50px; color: #FDB813;"></i>
        <h4>Change Club Status</h4>
        <p id="statusMessage">Are you sure you want to change the status of this club?</p>
        <div class="modal-buttons">
            <button id="confirmStatusBtn" class="modal-btn-confirm">Yes, Change Status</button>
            <button id="cancelStatusBtn" class="modal-btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<!-- Delete Club Confirmation Modal -->
<div id="deleteClubModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-trash-alt" style="font-size: 50px; color: #dc3545;"></i>
        <h4>Delete Club</h4>
        <p id="deleteClubMessage">⚠️ WARNING: This will permanently delete the club and all related data (members, events, etc.). This action cannot be undone!</p>
        <div class="modal-buttons">
            <button id="confirmDeleteClubBtn" class="modal-btn-confirm">Yes, Delete Club</button>
            <button id="cancelDeleteClubBtn" class="modal-btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<?php include_once '../../includes/logout_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const distLabels = <?php echo json_encode(array_column($clubDistribution, 'clubName')); ?>;
const distData = <?php echo json_encode(array_column($clubDistribution, 'member_count')); ?>;
const colors = ['#003B5C', '#FDB813', '#28A745', '#17A2B8', '#6C757D', '#DC3545', '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4'];
if (distLabels.length > 0) {
    new Chart(document.getElementById('distributionChart'), {
        type: 'pie',
        data: { labels: distLabels, datasets: [{ data: distData, backgroundColor: colors.slice(0, distLabels.length), borderWidth: 2, borderColor: 'white' }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
    });
}
new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: { labels: ['Active', 'Pending', 'Inactive'], datasets: [{ label: 'Number of Clubs', data: [<?php echo $activeClubs; ?>, <?php echo $totalClubs - $activeClubs - $inactiveClubs; ?>, <?php echo $inactiveClubs; ?>], backgroundColor: ['#28A745', '#FDB813', '#DC3545'], borderRadius: 8 }] },
    options: { responsive: true, scales: { y: { beginAtZero: true, stepSize: 1 } } }
});
</script>

<script>
// Status Modal Variables
let statusClubId = null;
let statusClubName = '';

// Delete Club Modal Variables
let deleteClubId = null;
let deleteClubName = '';

// Open Status Modal
function openStatusModal(clubId, clubName) {
    statusClubId = clubId;
    statusClubName = clubName;
    document.getElementById('statusMessage').innerHTML = `Are you sure you want to change the status of <strong>${clubName}</strong>?`;
    document.getElementById('statusModal').style.display = 'flex';
}

// Confirm Status Change
document.getElementById('confirmStatusBtn').addEventListener('click', function() {
    if (statusClubId) {
        window.location.href = `club_toggle_status.php?id=${statusClubId}`;
    }
});

// Cancel Status Change
document.getElementById('cancelStatusBtn').addEventListener('click', function() {
    document.getElementById('statusModal').style.display = 'none';
    statusClubId = null;
});

// Open Delete Club Modal
function openDeleteClubModal(clubId, clubName) {
    deleteClubId = clubId;
    deleteClubName = clubName;
    document.getElementById('deleteClubMessage').innerHTML = `⚠️ WARNING: You are about to permanently delete <strong>${clubName}</strong>.<br>This will delete all club members, events, and related data. This action cannot be undone!`;
    document.getElementById('deleteClubModal').style.display = 'flex';
}

// Confirm Delete Club
document.getElementById('confirmDeleteClubBtn').addEventListener('click', function() {
    if (deleteClubId) {
        window.location.href = `club_delete.php?id=${deleteClubId}`;
    }
});

// Cancel Delete Club
document.getElementById('cancelDeleteClubBtn').addEventListener('click', function() {
    document.getElementById('deleteClubModal').style.display = 'none';
    deleteClubId = null;
});

// Close modals when clicking outside
window.onclick = function(event) {
    const statusModal = document.getElementById('statusModal');
    const deleteClubModal = document.getElementById('deleteClubModal');
    
    if (event.target == statusModal) {
        statusModal.style.display = 'none';
        statusClubId = null;
    }
    if (event.target == deleteClubModal) {
        deleteClubModal.style.display = 'none';
        deleteClubId = null;
    }
}
</script>

</body>
</html>