<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT c.*, cp.positionName, cc.assignedDate 
    FROM club_committee cc
    JOIN club c ON cc.club_id = c.club_id
    LEFT JOIN committee_position cp ON cc.position_id = cp.position_id
    WHERE cc.user_id = ? AND cc.status = 'Active'
");
$stmt->execute([$user_id]);
$club_info = $stmt->fetch();

$club_id = $club_info['club_id'] ?? null;
$club_name = $club_info['clubName'] ?? 'No Club Assigned';
$position = $club_info['positionName'] ?? 'Committee Member';

// Handle Approve/Reject actions
$action_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    
    if ($action == 'approve') {
        // Get application details
        $stmt = $pdo->prepare("SELECT * FROM club_membership_applications WHERE application_id = ?");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();
        
        if ($application) {
            // Check if student is already a member of any club
            $checkMember = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE user_id = ? AND status = 'Active'");
            $checkMember->execute([$application['user_id']]);
            $existingMembership = $checkMember->fetchColumn();
            
            if ($existingMembership > 0) {
                $action_message = '<div class="alert alert-danger">❌ Cannot approve: Student is already a member of another club.</div>';
            } else {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Update application status
                $update = $pdo->prepare("
                    UPDATE club_membership_applications 
                    SET status = 'Approved', reviewed_by = ?, reviewed_date = CURDATE()
                    WHERE application_id = ?
                ");
                $update->execute([$user_id, $application_id]);
                
                // Add to club_membership
                $insert = $pdo->prepare("
                    INSERT INTO club_membership (club_id, user_id, joinDate, status)
                    VALUES (?, ?, CURDATE(), 'Active')
                ");
                $insert->execute([$club_id, $application['user_id']]);
                
                $pdo->commit();
                $action_message = '<div class="alert alert-success">✅ Application approved! Member added to club.</div>';
            }
        }
    } 
    elseif ($action == 'reject') {
        $rejection_reason = trim($_POST['rejection_reason'] ?? 'No reason provided.');
        
        $stmt = $pdo->prepare("
            UPDATE club_membership_applications 
            SET status = 'Rejected', 
                rejection_reason = ?,
                reviewed_by = ?, 
                reviewed_date = CURDATE()
            WHERE application_id = ?
        ");
        $stmt->execute([$rejection_reason, $user_id, $application_id]);
        
        $action_message = '<div class="alert alert-info">❌ Application rejected.</div>';
    }
    
    // Refresh page to show updated data
    header("Location: dashboard_committee.php?msg=" . urlencode($action_message));
    exit();
}

// Check for message from redirect
if (isset($_GET['msg'])) {
    $action_message = urldecode($_GET['msg']);
}

if ($club_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Active'");
    $stmt->execute([$club_id]);
    $totalMembers = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event WHERE club_id = ? AND status = 'UPCOMING'");
    $stmt->execute([$club_id]);
    $upcomingEvents = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership_applications WHERE club_id = ? AND status = 'Pending'");
    $stmt->execute([$club_id]);
    $pendingApps = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(ap.pointsEarned), 0) FROM activity_points ap JOIN event e ON ap.event_id = e.event_id WHERE e.club_id = ?");
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

// Get pending applications with full details
$recentApps = [];
if ($club_id) {
    $stmt = $pdo->prepare("
        SELECT cm.*, u.name, u.email, u.studentId, u.programme, u.yearsOfStud
        FROM club_membership_applications cm
        JOIN users u ON cm.user_id = u.user_id
        WHERE cm.club_id = ? AND cm.status = 'Pending'
        ORDER BY cm.application_date DESC
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
        :root { 
            --umpsa-blue: #003B5C; 
            --umpsa-gold: #FDB813; 
            --umpsa-dark-blue: #002147; 
            --umpsa-light-blue: #E8F0F8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--umpsa-light-blue);
            overflow-x: hidden; 
        }
        
        /* Sidebar */
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
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }
        
        /* Top Nav */
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
            color: var(--umpsa-gold);
        }
        .badge-role {
            background: var(--umpsa-gold);
            color: var(--umpsa-dark-blue);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        .badge-club {
            background: var(--umpsa-blue);
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
        }
        .logout-btn:hover {
            background: #c82333;
        }
        
        /* Stats Grid */
        .stats-grid {
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
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: var(--umpsa-blue);
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 13px;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(0,59,92,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon i {
            font-size: 24px;
            color: var(--umpsa-blue);
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--umpsa-blue), var(--umpsa-dark-blue));
            color: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .welcome-banner h3 {
            margin-bottom: 8px;
        }
        .welcome-banner h3 i {
            color: var(--umpsa-gold);
            margin-right: 8px;
        }
        
        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .section-title {
            background: var(--umpsa-blue);
            color: white;
            padding: 12px 20px;
            font-weight: 600;
        }
        .section-title i {
            color: var(--umpsa-gold);
            margin-right: 8px;
        }
        .section-content {
            padding: 20px;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #eee;
        }
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            vertical-align: middle;
        }
        .data-table tr:hover {
            background: var(--umpsa-light-blue);
        }
        
        /* Badges */
        .badge-upcoming {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        
        /* Buttons */
        .btn-sm-custom {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        .btn-edit {
            background: #17a2b8;
            color: white;
        }
        .btn-edit:hover {
            background: #138496;
            color: white;
        }
        .btn-qr {
            background: #6c757d;
            color: white;
        }
        .btn-qr:hover {
            background: #5a6268;
            color: white;
        }
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-approve:hover {
            background: #218838;
        }
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-reject:hover {
            background: #c82333;
        }
        .btn-view {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-view:hover {
            background: #138496;
        }
        
        /* Modal Styles */
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
            width: 450px;
            max-width: 90%;
        }
        .modal-header {
            padding: 15px 20px;
            border-radius: 16px 16px 0 0;
            margin: -25px -25px 20px -25px;
        }
        .modal-header.approve {
            background: #28a745;
            color: white;
        }
        .modal-header.reject {
            background: #dc3545;
            color: white;
        }
        .modal-header.info {
            background: var(--umpsa-dark-blue);
            color: white;
        }
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .modal-btn-confirm {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        .modal-btn-confirm.reject {
            background: #dc3545;
        }
        .modal-btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .top-nav {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
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
        <a href="../module1/dashboard_committee.php" class="active">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module2/club_dashboard_committee.php">
            <i class="fas fa-building"></i> <span>My Club</span>
        </a>
        <a href="../module3/event_dashboard.php">
    <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
</a>
        <a href="../module3/manage_events.php">
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
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Committee Member'); ?>
            <span class="badge-role">Committee</span>
            <?php if ($club_id): ?>
                <span class="badge-club"><?php echo htmlspecialchars($club_name); ?></span>
            <?php endif; ?>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($club_name); ?> Committee</h3>
                <p>Position: <strong><?php echo htmlspecialchars($position); ?></strong></p>
                <p class="mb-0">Manage your club activities, events, and member applications from this dashboard.</p>
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-users" style="font-size: 60px; opacity: 0.2;"></i>
            </div>
        </div>
    </div>

    <?php echo $action_message; ?>

    <?php if (!$club_id): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            You are not assigned to any club yet. Please contact the administrator.
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-number"><?php echo $totalMembers; ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-number"><?php echo $upcomingEvents; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-number"><?php echo $pendingApps; ?></div>
                <div class="stat-label">Pending Applications</div>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-number"><?php echo number_format($totalPoints); ?></div>
                <div class="stat-label">Total Points</div>
            </div>
            <div class="stat-icon"><i class="fas fa-star"></i></div>
        </div>
    </div>

    <!-- Upcoming Events -->
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-calendar-alt"></i> Upcoming Events
        </div>
        <div class="section-content">
            <?php if (empty($recentEvents)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No upcoming events scheduled.</p>
                    <a href="../module3/create_event.php" class="btn-edit btn-sm-custom"><i class="fas fa-plus"></i> Create Event</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event</th>
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
                                <td><strong><?php echo htmlspecialchars($event['event_title']); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($event['event_date'])); ?></td>
                                <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                <td><?php echo $event['current_participant']; ?>/<?php echo $event['max_participant']; ?></td>
                                <td><span class="badge-upcoming"><?php echo $event['status']; ?></span></td>
                                <td>
                                    <a href="../module3/edit_event.php?id=<?php echo $event['event_id']; ?>" class="btn-edit btn-sm-custom"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="../module4/attendance_management.php?event_id=<?php echo $event['event_id']; ?>" class="btn-qr btn-sm-custom"><i class="fas fa-qrcode"></i> QR</a>
                            </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                            </table>
                </div>
                <div class="mt-3 text-end">
                    <a href="../module3/manage_events.php" class="btn-link-custom">View All Events <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pending Applications -->
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-user-plus"></i> Pending Applications
        </div>
        <div class="section-content">
            <?php if (empty($recentApps)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending applications to review.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Programme</th>
                                <th>Applied Date</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentApps as $app): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($app['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($app['studentId']); ?></td>
                                <td><?php echo htmlspecialchars($app['programme'] ?? '-'); ?> <?php if($app['yearsOfStud']) echo '(Year '.$app['yearsOfStud'].')'; ?></td>
                                <td><?php echo date('d M Y', strtotime($app['application_date'])); ?></td>
                                <td>
                                    <button class="btn-view" onclick="viewReason(<?php echo $app['application_id']; ?>, '<?php echo addslashes($app['reason']); ?>', '<?php echo addslashes($app['motivation']); ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                            </td>
                                <td>
                                    <button class="btn-approve" onclick="openApproveModal(<?php echo $app['application_id']; ?>, '<?php echo addslashes($app['name']); ?>')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn-reject" onclick="openRejectModal(<?php echo $app['application_id']; ?>, '<?php echo addslashes($app['name']); ?>')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                            </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                            </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden Forms -->
<form id="approveForm" method="POST" style="display: none;">
    <input type="hidden" name="application_id" id="approve_application_id">
    <input type="hidden" name="action" value="approve">
</form>

<form id="rejectForm" method="POST" style="display: none;">
    <input type="hidden" name="application_id" id="reject_application_id">
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="rejection_reason" id="rejection_reason">
</form>

<!-- Approve Confirmation Modal -->
<div id="approveModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header approve">
            <h5><i class="fas fa-check-circle"></i> Approve Application</h5>
            <button type="button" class="btn-close" onclick="closeApproveModal()" style="float:right; background:none; border:none; color:white; font-size:20px;">&times;</button>
        </div>
        <div style="padding: 15px 0;">
            <p id="approveMessage">Are you sure you want to approve this application?</p>
            <div class="alert alert-info mt-2">
                <i class="fas fa-info-circle"></i> The student will be added as a member of <strong><?php echo htmlspecialchars($club_name); ?></strong>.
            </div>
        </div>
        <div class="modal-buttons">
            <button class="modal-btn-cancel" onclick="closeApproveModal()">Cancel</button>
            <button class="modal-btn-confirm" onclick="confirmApprove()">Approve</button>
        </div>
    </div>
</div>

<!-- Reject Confirmation Modal -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header reject">
            <h5><i class="fas fa-times-circle"></i> Reject Application</h5>
            <button type="button" class="btn-close" onclick="closeRejectModal()" style="float:right; background:none; border:none; color:white; font-size:20px;">&times;</button>
        </div>
        <div style="padding: 15px 0;">
            <p id="rejectMessage">Are you sure you want to reject this application?</p>
            <div class="mb-3">
                <label class="form-label fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
                <textarea id="reject_reason_text" class="form-control" rows="3" placeholder="Please provide a reason for rejection (e.g., Club is full, Insufficient motivation, etc.)"></textarea>
                <small class="text-muted">This reason will be visible to the student.</small>
            </div>
        </div>
        <div class="modal-buttons">
            <button class="modal-btn-cancel" onclick="closeRejectModal()">Cancel</button>
            <button class="modal-btn-confirm reject" onclick="confirmReject()">Reject</button>
        </div>
    </div>
</div>

<!-- View Reason Modal -->
<div id="reasonModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header info">
            <h5><i class="fas fa-file-alt"></i> Application Details</h5>
            <button type="button" class="btn-close" onclick="closeReasonModal()" style="float:right; background:none; border:none; color:white; font-size:20px;">&times;</button>
        </div>
        <div style="padding: 15px 0;">
            <div style="margin-bottom: 15px;">
                <strong><i class="fas fa-question-circle"></i> Reason for joining:</strong>
                <p id="reasonText" style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin-top: 5px;"></p>
            </div>
            <div>
                <strong><i class="fas fa-heart"></i> Motivation / Contribution:</strong>
                <p id="motivationText" style="background: #f8f9fa; padding: 10px; border-radius: 8px; margin-top: 5px;"></p>
            </div>
        </div>
        <div class="modal-buttons">
            <button class="modal-btn-cancel" onclick="closeReasonModal()">Close</button>
        </div>
    </div>
</div>

<script>
    let currentApplicationId = null;
    let currentStudentName = '';
    
    // Approve Modal Functions
    function openApproveModal(id, name) {
        currentApplicationId = id;
        currentStudentName = name;
        document.getElementById('approveMessage').innerHTML = `Are you sure you want to approve <strong>${name}</strong>'s application?`;
        document.getElementById('approveModal').style.display = 'flex';
    }
    
    function closeApproveModal() {
        document.getElementById('approveModal').style.display = 'none';
        currentApplicationId = null;
    }
    
    function confirmApprove() {
        if (currentApplicationId) {
            document.getElementById('approve_application_id').value = currentApplicationId;
            document.getElementById('approveForm').submit();
        }
    }
    
    // Reject Modal Functions
    function openRejectModal(id, name) {
        currentApplicationId = id;
        currentStudentName = name;
        document.getElementById('rejectMessage').innerHTML = `Are you sure you want to reject <strong>${name}</strong>'s application?`;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    
    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
        document.getElementById('reject_reason_text').value = '';
        currentApplicationId = null;
    }
    
    function confirmReject() {
        const reason = document.getElementById('reject_reason_text').value;
        if (!reason.trim()) {
            alert('Please provide a reason for rejection.');
            return;
        }
        document.getElementById('reject_application_id').value = currentApplicationId;
        document.getElementById('rejection_reason').value = reason;
        document.getElementById('rejectForm').submit();
    }
    
    // View Reason Modal Functions
    function viewReason(id, reason, motivation) {
        document.getElementById('reasonText').innerHTML = reason || 'No reason provided.';
        document.getElementById('motivationText').innerHTML = motivation || 'No motivation provided.';
        document.getElementById('reasonModal').style.display = 'flex';
    }
    
    function closeReasonModal() {
        document.getElementById('reasonModal').style.display = 'none';
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const approveModal = document.getElementById('approveModal');
        const rejectModal = document.getElementById('rejectModal');
        const reasonModal = document.getElementById('reasonModal');
        
        if (event.target == approveModal) {
            approveModal.style.display = 'none';
        }
        if (event.target == rejectModal) {
            rejectModal.style.display = 'none';
        }
        if (event.target == reasonModal) {
            reasonModal.style.display = 'none';
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $pdo = null; ?>