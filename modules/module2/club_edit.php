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

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $club_name = trim($_POST['club_name']);
    $club_category = trim($_POST['category']);
    $club_description = trim($_POST['description']);
    $advisor_name = trim($_POST['advisor_name']);
    $advisor_email = trim($_POST['advisor_email']);
    $status = $_POST['status'];
    
    if (empty($club_name) || empty($club_category)) {
        $error = "Club name and category are required!";
    } else {
        $check = $pdo->prepare("SELECT club_id FROM club WHERE clubName = ? AND club_id != ?");
        $check->execute([$club_name, $club_id]);
        if ($check->fetch()) {
            $error = "Another club already has this name!";
        } else {
            $stmt = $pdo->prepare("UPDATE club SET clubName = ?, clubDescription = ?, clubCategory = ?, status = ?, advisorName = ?, advisorEmail = ? WHERE club_id = ?");
            $stmt->execute([$club_name, $club_description, $club_category, $status, $advisor_name, $advisor_email, $club_id]);
            header("Location: club_dashboard_admin.php?msg=updated");
            exit();
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Club - FK Club System</title>
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
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        
        .form-container { max-width: 700px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-header { text-align: center; margin-bottom: 25px; }
        .form-header h2 { color: var(--umpsa-blue); }
        .form-header i { font-size: 50px; color: var(--umpsa-gold); }
        .btn-submit { background: var(--umpsa-blue); color: white; padding: 12px 30px; border: none; border-radius: 8px; width: 100%; }
        .btn-submit:hover { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); }
        .btn-cancel { background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 8px; text-decoration: none; display: inline-block; text-align: center; }
        
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

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text"><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?><span class="badge-role">Administrator</span></div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="form-container">
        <div class="form-header"><i class="fas fa-edit"></i><h2>Edit Club</h2><p class="text-muted">Update club information</p></div>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3"><label>Club Name</label><input type="text" name="club_name" class="form-control" value="<?php echo htmlspecialchars($club['clubName']); ?>" required></div>
                <div class="col-md-6 mb-3"><label>Category</label><select name="category" class="form-control" required><option value="Academic" <?php echo $club['clubCategory'] == 'Academic' ? 'selected' : ''; ?>>Academic</option><option value="Sports" <?php echo $club['clubCategory'] == 'Sports' ? 'selected' : ''; ?>>Sports</option><option value="Cultural" <?php echo $club['clubCategory'] == 'Cultural' ? 'selected' : ''; ?>>Cultural</option><option value="Technical" <?php echo $club['clubCategory'] == 'Technical' ? 'selected' : ''; ?>>Technical</option><option value="Business" <?php echo $club['clubCategory'] == 'Business' ? 'selected' : ''; ?>>Business</option><option value="Creative" <?php echo $club['clubCategory'] == 'Creative' ? 'selected' : ''; ?>>Creative</option></select></div>
                <div class="col-12 mb-3"><label>Description</label><textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($club['clubDescription'] ?? ''); ?></textarea></div>
                <div class="col-md-6 mb-3"><label>Advisor Name</label><input type="text" name="advisor_name" class="form-control" value="<?php echo htmlspecialchars($club['advisorName'] ?? ''); ?>"></div>
                <div class="col-md-6 mb-3"><label>Advisor Email</label><input type="email" name="advisor_email" class="form-control" value="<?php echo htmlspecialchars($club['advisorEmail'] ?? ''); ?>"></div>
                <div class="col-md-6 mb-3"><label>Status</label><select name="status" class="form-control"><option value="Active" <?php echo $club['status'] == 'Active' ? 'selected' : ''; ?>>Active</option><option value="Pending" <?php echo $club['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option><option value="Inactive" <?php echo $club['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
            </div>
            <div class="d-flex gap-3 mt-3"><button type="submit" class="btn-submit">Update Club</button><a href="club_dashboard_admin.php" class="btn-cancel">Cancel</a></div>
        </form>
    </div>
</div>

<?php include_once '../../includes/logout_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>