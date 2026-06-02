<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: ../module1/login.php");
    exit();
}

$club_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$club_id) header("Location: club_dashboard_admin.php");

$stmt = $pdo->prepare("SELECT * FROM club WHERE club_id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch();
if (!$club) header("Location: club_dashboard_admin.php");

$positions = $pdo->query("SELECT * FROM committee_position ORDER BY position_id")->fetchAll();

$currentCommittee = $pdo->prepare("SELECT cc.*, u.name, u.email, u.studentId, cp.positionName FROM club_committee cc JOIN users u ON cc.user_id = u.user_id LEFT JOIN committee_position cp ON cc.position_id = cp.position_id WHERE cc.club_id = ? AND cc.status = 'Active' ORDER BY cp.position_id");
$currentCommittee->execute([$club_id]);
$committeeList = $currentCommittee->fetchAll();

$availableUsers = $pdo->prepare("
    SELECT u.user_id, u.name, u.email, u.studentId, u.role_id 
    FROM users u 
    WHERE u.status = 'Active' 
    AND (u.role_id = 2 OR u.role_id = 3) 
    AND u.user_id NOT IN (
        SELECT user_id FROM club_committee WHERE status = 'Active'
    )
    ORDER BY u.name
");
$availableUsers->execute([]);
$usersList = $availableUsers->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_committee'])) {
    $user_id = (int)$_POST['user_id'];
    $position_id = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    
    if ($user_id) {
        // Check if user is already a committee member in ANY club
        $checkAnyClub = $pdo->prepare("
            SELECT c.clubName 
            FROM club_committee cc 
            JOIN club c ON cc.club_id = c.club_id 
            WHERE cc.user_id = ? AND cc.status = 'Active'
        ");
        $checkAnyClub->execute([$user_id]);
        $existingCommittee = $checkAnyClub->fetch();
        
        if ($existingCommittee) {
            // User is already a committee member elsewhere
            $error_msg = urlencode("This student is already a committee member of " . $existingCommittee['clubName'] . ". They cannot be added to another club's committee.");
header("Location: committee_assign.php?id=$club_id&error=$error_msg");
exit();
        }
        
        // Check if user is already in THIS club's committee
        $check = $pdo->prepare("SELECT * FROM club_committee WHERE club_id = ? AND user_id = ?");
        $check->execute([$club_id, $user_id]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO club_committee (club_id, user_id, position_id, assignedDate, status) VALUES (?, ?, ?, CURDATE(), 'Active')");
            $stmt->execute([$club_id, $user_id, $position_id]);
            $updateRole = $pdo->prepare("UPDATE users SET role_id = 2 WHERE user_id = ? AND role_id = 3");
            $updateRole->execute([$user_id]);
            header("Location: committee_assign.php?id=$club_id&msg=added");
            exit();
        } else {
            $error = "This student is already a committee member of this club.";
            header("Location: committee_assign.php?id=$club_id&error=" . urlencode($error));
            exit();
        }
    }
}

// REMOVE COMMITTEE MEMBER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_committee_id'])) {
    $committee_id = (int)$_POST['remove_committee_id'];
    
    // Get the user_id and club_id BEFORE deleting
    $stmt = $pdo->prepare("SELECT user_id, club_id FROM club_committee WHERE committee_id = ?");
    $stmt->execute([$committee_id]);
    $user = $stmt->fetch();
    $user_id = $user['user_id'] ?? null;
    $user_club_id = $user['club_id'] ?? null;
    
    if ($user_id) {
        // Check if user is in any OTHER active committee (excluding this one)
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM club_committee 
            WHERE user_id = ? AND committee_id != ? AND status = 'Active'
        ");
        $check->execute([$user_id, $committee_id]);
        $otherCommittees = $check->fetchColumn();
        
        // Delete the committee record
        $stmt = $pdo->prepare("DELETE FROM club_committee WHERE committee_id = ?");
        $stmt->execute([$committee_id]);
        
        // If no other committees, revert role back to Student (role_id = 3)
        if ($otherCommittees == 0) {
            $updateRole = $pdo->prepare("UPDATE users SET role_id = 3 WHERE user_id = ?");
            $updateRole->execute([$user_id]);
        }
    } else {
        // Just delete if no user found
        $stmt = $pdo->prepare("DELETE FROM club_committee WHERE committee_id = ?");
        $stmt->execute([$committee_id]);
    }
    
    header("Location: committee_assign.php?id=$club_id&msg=removed");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_position'])) {
    $committee_id = (int)$_POST['committee_id'];
    $position_id = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    $stmt = $pdo->prepare("UPDATE club_committee SET position_id = ? WHERE committee_id = ? AND club_id = ?");
    $stmt->execute([$position_id, $committee_id, $club_id]);
    header("Location: committee_assign.php?id=$club_id&msg=updated");
    exit();
}

// At the top with other message handling
$error_message = '';
if (isset($_GET['error'])) {
    $error_message = '<div class="alert alert-danger">❌ ' . htmlspecialchars(urldecode($_GET['error'])) . '</div>';
}


$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'added') $message = '<div class="alert alert-success">✅ Committee member added!</div>';
    if ($_GET['msg'] == 'removed') $message = '<div class="alert alert-success">✅ Committee member removed!</div>';
    if ($_GET['msg'] == 'updated') $message = '<div class="alert alert-success">✅ Position updated!</div>';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Committee - <?php echo htmlspecialchars($club['clubName']); ?></title>
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

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
    z-index: 2000;
}

.modal-content {
    background: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    width: 350px;
}

.modal-buttons {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 10px;
}

.modal-btn-confirm {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
}

.modal-btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
}

        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .welcome-text { font-size: 16px; font-weight: 500; }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        
        .committee-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-back { background: #6c757d; color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .position-badge { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header h4, .sidebar-header p, .sidebar-menu a span { display: none; } .main-content { margin-left: 70px; } }
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
        <a href="../module1/dashboard_admin.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard_admin.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module1/manage_users.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php' || basename($_SERVER['PHP_SELF']) == 'add_edit_user.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        <a href="../module2/club_redirect.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'club_dashboard_admin.php' || basename($_SERVER['PHP_SELF']) == 'club_edit.php' || basename($_SERVER['PHP_SELF']) == 'club_create.php' || basename($_SERVER['PHP_SELF']) == 'committee_assign.php') ? 'class="active"' : ''; ?>>
            <i class="fas fa-building"></i> <span>Manage Clubs</span>
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

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text"><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?><span class="badge-role">Administrator</span></div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="club_dashboard_admin.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Clubs</a>
        <h4 class="text-dark">👥 <?php echo htmlspecialchars($club['clubName']); ?> Committee</h4>
    </div>

    <div class="header" style="background: linear-gradient(135deg, var(--umpsa-blue), var(--umpsa-dark-blue)); color: white; padding: 20px; border-radius: 15px; margin-bottom: 25px;">
        <h2><i class="fas fa-user-tie"></i> Manage Committee Members</h2>
        <p class="mb-0">Assign or remove committee members for <?php echo htmlspecialchars($club['clubName']); ?></p>
    </div>

    <?php echo $message; ?>
    <?php echo $error_message; ?>

    <div class="row">
        <div class="col-md-7">
            <div class="committee-card">
                <h5><i class="fas fa-users"></i> Current Committee Members <span class="badge bg-secondary"><?php echo count($committeeList); ?></span></h5>
                <?php if (empty($committeeList)): ?>
                    <div class="alert alert-info">No committee members assigned yet.</div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table table-hover">
                        <thead><tr><th>#</th><th>Name / Student ID</th><th>Position</th><th>Assigned Date</th><th>Actions</th></tr></thead>
                        <tbody><?php $counter=1; foreach ($committeeList as $cm): ?>
                            <tr><td><?php echo $counter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($cm['name']); ?></strong><br><small><?php echo htmlspecialchars($cm['studentId']); ?></small></td>
                            <td><?php if ($cm['positionName']): ?><span class="position-badge"><?php echo htmlspecialchars($cm['positionName']); ?></span><?php else: ?><span class="text-muted">No position</span><?php endif; ?>
                                <form method="POST" class="mt-2"><input type="hidden" name="committee_id" value="<?php echo $cm['committee_id']; ?>"><div class="input-group input-group-sm"><select name="position_id" class="form-select form-select-sm"><option value="">-- Change --</option><?php foreach ($positions as $pos): ?><option value="<?php echo $pos['position_id']; ?>" <?php echo ($cm['position_id'] == $pos['position_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pos['positionName']); ?></option><?php endforeach; ?></select><button type="submit" name="update_position" class="btn btn-sm btn-outline-primary">Update</button></div></form>
                            </td>
                            <td><?php echo date('d M Y', strtotime($cm['assignedDate'])); ?></td>
                            <td>
    <button type="button"
        class="btn btn-sm btn-danger"
        onclick="openRemoveModal(<?php echo $cm['committee_id']; ?>)">
        <i class="fas fa-trash"></i> Remove
    </button>
</td>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-5">
            <div class="committee-card">
                <h5><i class="fas fa-user-plus"></i> Add Committee Member</h5>
                <form method="POST">
                    <div class="mb-3"><label>Select Student</label><select name="user_id" class="form-control" required><option value="">-- Select Student --</option><?php foreach ($usersList as $user): ?><option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['studentId']); ?>)</option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label>Position (Optional)</label><select name="position_id" class="form-control"><option value="">-- Select Position --</option><?php foreach ($positions as $pos): ?><option value="<?php echo $pos['position_id']; ?>"><?php echo htmlspecialchars($pos['positionName']); ?></option><?php endforeach; ?></select></div>
                    <button type="submit" name="add_committee" class="btn btn-primary w-100"><i class="fas fa-plus"></i> Add to Committee</button>
                </form>
                <?php if (empty($usersList)): ?><div class="alert alert-warning mt-3">No available users to add.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="removeCommitteeForm">
    <input type="hidden" name="remove_committee_id" id="remove_committee_id">
</form>

<div id="removeModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-exclamation-triangle" style="font-size:50px;color:#dc3545;"></i>

        <h4>Remove Committee Member?</h4>
        <p>This will deactivate the member from the committee list.</p>

        <div class="modal-buttons">
            <button class="modal-btn-confirm" onclick="confirmRemove()">Remove</button>
            <button class="modal-btn-cancel" onclick="closeRemoveModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
let selectedCommitteeId = null;

function openRemoveModal(id) {
    selectedCommitteeId = id;
    document.getElementById('removeModal').style.display = 'flex';
}

function closeRemoveModal() {
    document.getElementById('removeModal').style.display = 'none';
    selectedCommitteeId = null;
}

function confirmRemove() {
    if (!selectedCommitteeId) return;
    
    // Set the hidden input value
    document.getElementById('remove_committee_id').value = selectedCommitteeId;
    
    // Submit the form directly
    document.getElementById('removeCommitteeForm').submit();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>