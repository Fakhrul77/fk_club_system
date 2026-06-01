<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get committee member's club
$stmt = $pdo->prepare("
    SELECT c.*, cp.positionName, cc.assignedDate
    FROM club_committee cc
    JOIN club c ON cc.club_id = c.club_id
    LEFT JOIN committee_position cp ON cc.position_id = cp.position_id
    WHERE cc.user_id = ? AND cc.status = 'Active'
");
$stmt->execute([$user_id]);
$club = $stmt->fetch();

$club_id = $club['club_id'] ?? null;
$club_name = $club['clubName'] ?? 'No Club Assigned';
$position = $club['positionName'] ?? 'Committee Member';

if (!$club_id) {
    $error = "You are not assigned to any club yet. Please contact the administrator.";
}

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE club_id = ? AND status = 'Active'");
$stmt->execute([$club_id]);
$totalMembers = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership_applications WHERE club_id = ? AND status = 'Pending'");
$stmt->execute([$club_id]);
$pendingApps = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_committee WHERE club_id = ? AND status = 'Active'");
$stmt->execute([$club_id]);
$committeeCount = $stmt->fetchColumn();

// Get pending applications with reason and motivation
$applications = $pdo->prepare("
    SELECT a.*, u.name, u.email, u.studentId, u.phone, u.programme, u.yearsOfStud
    FROM club_membership_applications a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.club_id = ? AND a.status = 'Pending'
    ORDER BY a.application_date ASC
");
$applications->execute([$club_id]);
$applicationsList = $applications->fetchAll();

// Get approved members
$members = $pdo->prepare("
    SELECT u.name, u.email, u.studentId, u.phone, cm.joinDate
    FROM club_membership cm
    JOIN users u ON cm.user_id = u.user_id
    WHERE cm.club_id = ? AND cm.status = 'Active'
    ORDER BY cm.joinDate DESC
");
$members->execute([$club_id]);
$membersList = $members->fetchAll();

// Get committee members
$committeeMembers = $pdo->prepare("
    SELECT u.name, u.email, cp.positionName, cc.assignedDate
    FROM club_committee cc
    JOIN users u ON cc.user_id = u.user_id
    LEFT JOIN committee_position cp ON cc.position_id = cp.position_id
    WHERE cc.club_id = ? AND cc.status = 'Active'
    ORDER BY cp.position_id
");
$committeeMembers->execute([$club_id]);
$committeeList = $committeeMembers->fetchAll();

// Handle application actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($action == 'approve') {
        $app = $pdo->prepare("SELECT * FROM club_membership_applications WHERE application_id = ?");
        $app->execute([$application_id]);
        $application = $app->fetch();
        
        if ($application) {
            $checkMember = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE user_id = ? AND status = 'Active'");
            $checkMember->execute([$application['user_id']]);
            $existingMembership = $checkMember->fetchColumn();
            
            if ($existingMembership > 0) {
                $error = "This student is already a member of another club. Cannot approve.";
                header("Location: club_dashboard_committee.php?msg=error&error=already_member");
                exit();
            }
            
            $pdo->beginTransaction();
            
            $update = $pdo->prepare("
                UPDATE club_membership_applications 
                SET status = 'Approved', committee_remarks = ?, reviewed_by = ?, reviewed_date = CURDATE()
                WHERE application_id = ?
            ");
            $update->execute([$remarks, $user_id, $application_id]);
            
            $insert = $pdo->prepare("
                INSERT INTO club_membership (club_id, user_id, joinDate, status, application_id)
                VALUES (?, ?, CURDATE(), 'Active', ?)
            ");
            $insert->execute([$club_id, $application['user_id'], $application_id]);
            
            $pdo->commit();
            header("Location: club_dashboard_committee.php?msg=approved");
            exit();
        }
    } 
    elseif ($action == 'reject') {
        $stmt = $pdo->prepare("
            UPDATE club_membership_applications 
            SET status = 'Rejected', committee_remarks = ?, reviewed_by = ?, reviewed_date = CURDATE()
            WHERE application_id = ?
        ");
        $stmt->execute([$remarks, $user_id, $application_id]);
        header("Location: club_dashboard_committee.php?msg=rejected");
        exit();
    }
}

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'approved') $message = '<div class="alert alert-success">✅ Application approved! Member added to club.</div>';
    if ($_GET['msg'] == 'rejected') $message = '<div class="alert alert-info">❌ Application rejected.</div>';
    if ($_GET['msg'] == 'error' && isset($_GET['error']) && $_GET['error'] == 'already_member') $message = '<div class="alert alert-danger">❌ Cannot approve: Student is already a member of another club.</div>';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Committee Club Dashboard - FK Club System</title>
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
        
        .top-nav { background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .welcome-text { font-size: 16px; font-weight: 500; }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .badge-club { background: var(--umpsa-blue); color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-info h3 { font-size: 28px; font-weight: bold; color: var(--umpsa-blue); margin-bottom: 5px; }
        .stat-icon { width: 50px; height: 50px; background: rgba(0,59,92,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 28px; color: var(--umpsa-blue); }
        
        .application-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .application-card:hover { transform: translateY(-3px); }
        .member-card, .info-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .info-card h5 { color: var(--umpsa-blue); margin-bottom: 15px; border-left: 3px solid var(--umpsa-gold); padding-left: 10px; }
        .status-pending { background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .btn-approve { background: #28a745; color: white; border: none; padding: 5px 12px; border-radius: 20px; margin: 2px; }
        .btn-reject { background: #dc3545; color: white; border: none; padding: 5px 12px; border-radius: 20px; margin: 2px; }
        .committee-tag { background: var(--umpsa-light-blue); padding: 5px 12px; border-radius: 20px; display: inline-block; margin: 3px; }
        .modal-content { border-radius: 15px; }
        .modal-header { background: var(--umpsa-dark-blue); color: white; border-radius: 15px 15px 0 0; }
        .reason-box { background: #f8f9fa; border-left: 3px solid var(--umpsa-gold); padding: 12px; border-radius: 8px; margin: 10px 0; }
        
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
        
        <!-- 4. Create Event -->
        <a href="../module3/create_event.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'create_event.php') ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i> <span>Create Event</span>
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
            <?php if ($club_id): ?><span class="badge-club"><?php echo htmlspecialchars($club_name); ?></span><?php endif; ?>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h2 class="mb-4" style="color: var(--umpsa-blue);"><i class="fas fa-tachometer-alt"></i> <?php echo htmlspecialchars($club_name); ?> Management</h2>

    <?php if (isset($error)): ?><div class="alert alert-warning"><?php echo $error; ?></div><?php endif; ?>
    <?php echo $message; ?>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-info"><h3><?php echo $totalMembers; ?></h3><p>Total Members</p></div><div class="stat-icon"><i class="fas fa-users"></i></div></div>
        <div class="stat-card"><div class="stat-info"><h3><?php echo $pendingApps; ?></h3><p>Pending Applications</p></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
        <div class="stat-card"><div class="stat-info"><h3><?php echo $committeeCount; ?></h3><p>Committee Members</p></div><div class="stat-icon"><i class="fas fa-user-tie"></i></div></div>
    </div>

    <!-- Pending Applications Section with Reason and Motivation -->
    <h4 class="mb-3"><i class="fas fa-file-alt"></i> Pending Applications for Review</h4>
    
    <?php if (empty($applicationsList)): ?>
        <div class="alert alert-info">No pending applications to review.</div>
    <?php else: ?>
        <?php foreach ($applicationsList as $app): ?>
            <div class="application-card">
                <div class="row">
                    <div class="col-md-4">
                        <strong><?php echo htmlspecialchars($app['name']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($app['studentId']); ?></small>
                        <hr>
                        <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($app['email']); ?></small><br>
                        <small><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($app['programme'] ?? 'N/A'); ?>
                        <?php if ($app['yearsOfStud']): ?> - Year <?php echo $app['yearsOfStud']; ?><?php endif; ?></small>
                        <br>
                        <span class="status-pending mt-2 d-inline-block"><i class="fas fa-hourglass-half"></i> Applied: <?php echo date('d M Y', strtotime($app['application_date'])); ?></span>
                    </div>
                    <div class="col-md-5">
                        <div class="reason-box">
                            <strong><i class="fas fa-question-circle"></i> Why they want to join:</strong>
                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($app['reason'] ?? 'No reason provided.')); ?></p>
                        </div>
                        <div class="reason-box mt-2">
                            <strong><i class="fas fa-heart"></i> Motivation / Contribution:</strong>
                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($app['motivation'] ?? 'No motivation provided.')); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3 text-end">
                        <button class="btn-approve" onclick="showApproveModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['name']); ?>')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn-reject" onclick="showRejectModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['name']); ?>')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="info-card">
                <h5><i class="fas fa-info-circle"></i> Club Information</h5>
                <table class="table table-borderless">
                    <tr><td width="120"><strong>Club Name:</strong></td><td><?php echo htmlspecialchars($club['clubName'] ?? $club_name); ?></td></tr>
                    <tr><td><strong>Category:</strong></td><td><?php echo htmlspecialchars($club['clubCategory'] ?? '-'); ?></td></tr>
                    <tr><td><strong>Status:</strong></td><td><?php echo $club['status'] ?? 'Active'; ?></td></tr>
                    <?php if ($club['advisorName']): ?>
                    <tr><td><strong>Advisor:</strong></td><td><?php echo htmlspecialchars($club['advisorName']); ?></td></tr>
                    <?php endif; ?>
                    <tr><td><strong>Total Members:</strong></td><td><?php echo $totalMembers; ?></td></tr>
                </table>
            </div>
            
            <?php if (!empty($club['clubDescription'])): ?>
                <div class="info-card">
                    <h5><i class="fas fa-align-left"></i> Description</h5>
                    <p><?php echo nl2br(htmlspecialchars($club['clubDescription'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <div class="info-card">
                <h5><i class="fas fa-user-tie"></i> Committee Members</h5>
                <?php if (empty($committeeList)): ?>
                    <p class="text-muted">No committee members assigned yet.</p>
                <?php else: ?>
                    <?php foreach ($committeeList as $cm): ?>
                        <div class="committee-tag">
                            <strong><?php echo htmlspecialchars($cm['positionName'] ?? 'Member'); ?></strong><br>
                            <?php echo htmlspecialchars($cm['name']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <h5><i class="fas fa-users"></i> Club Members (<?php echo $totalMembers; ?>)</h5>
                <?php if (empty($membersList)): ?>
                    <p class="text-muted">No members yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Name</th><th>Student ID</th><th>Joined</th></tr></thead>
                            <tbody>
                                <?php foreach ($membersList as $member): ?>
                                    <tr><td><?php echo htmlspecialchars($member['name']); ?></td><td><?php echo htmlspecialchars($member['studentId']); ?></td><td><?php echo date('d M Y', strtotime($member['joinDate'])); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="approve_app_id">
                    <input type="hidden" name="action" value="approve">
                    <p>Approve application for <strong id="approve_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Note: Students can only be a member of ONE club. 
                        If they are already in another club, approval will fail.
                    </div>
                    <label>Remarks (optional):</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Add any remarks..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="reject_app_id">
                    <input type="hidden" name="action" value="reject">
                    <p>Reject application for <strong id="reject_name"></strong>?</p>
                    <label>Reason for rejection (optional):</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Reason for rejection..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showApproveModal(id, name) {
        document.getElementById('approve_app_id').value = id;
        document.getElementById('approve_name').innerText = name;
        new bootstrap.Modal(document.getElementById('approveModal')).show();
    }
    function showRejectModal(id, name) {
        document.getElementById('reject_app_id').value = id;
        document.getElementById('reject_name').innerText = name;
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }
</script>
</body>
</html>