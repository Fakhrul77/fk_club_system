<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT c.clubName FROM club_membership cm JOIN club c ON cm.club_id = c.club_id WHERE cm.user_id = ? AND cm.status = 'Active'");
$stmt->execute([$user_id]);
$userClubs = $stmt->fetchAll();

$update_success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $phone = $_POST['phone'] ?? '';
    $programme = $_POST['programme'] ?? '';
    $year = $_POST['year'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE users SET phone = ?, programme = ?, yearsOfStud = ? WHERE user_id = ?");
    $stmt->execute([$phone, $programme, $year, $user_id]);
    $update_success = "Profile updated successfully!";
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - FK Club System</title>
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
        
        .profile-container { display: grid; grid-template-columns: 300px 1fr; gap: 25px; }
        .profile-sidebar { background: white; border-radius: 20px; padding: 25px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .profile-avatar { width: 120px; height: 120px; background: var(--umpsa-blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .profile-avatar i { font-size: 60px; color: white; }
        .profile-name { font-size: 18px; font-weight: bold; color: var(--umpsa-blue); margin-top: 10px; }
        .profile-info { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .info-section { margin-bottom: 25px; }
        .info-section h5 { color: var(--umpsa-blue); margin-bottom: 15px; border-bottom: 2px solid var(--umpsa-gold); display: inline-block; padding-bottom: 5px; }
        .info-row { display: flex; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 10px; align-items: center; }
        .info-label { width: 130px; font-weight: 600; color: #555; }
        .info-value { flex: 1; color: #333; }
        .info-value input, .info-value select { width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 8px; }
        .club-tag { background: var(--umpsa-light-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; display: inline-block; margin: 3px; }
        .action-buttons { margin-top: 25px; display: flex; gap: 15px; }
        .btn-save { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        .btn-change-password { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        
        /* Logout Modal */
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
            .profile-container { grid-template-columns: 1fr; }
            .info-row { flex-direction: column; align-items: flex-start; gap: 10px; }
            .info-label { width: 100%; }
        }
    </style>
</head>
<body>
    
    <!-- ========== DYNAMIC SIDEBAR BASED ON ROLE ========== -->
    <div class="sidebar">
    <div class="sidebar-header">
        <img src="../../assets/images/logo.png" alt="Logo" style="width: 50px; height: auto; margin-bottom: 10px;">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <?php if ($user_role == 1): // ADMIN SIDEBAR ?>
            <a href="dashboard_admin.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="manage_users.php">
                <i class="fas fa-users"></i> <span>Manage Users</span>
            </a>
            <a href="../module2/club_redirect.php">
                <i class="fas fa-building"></i> <span>Manage Clubs</span>
            </a>
            <a href="../module3/manage_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Events</span>
            </a>
            <a href="../module4/attendance_dashboard.php">
                <i class="fas fa-chart-bar"></i> <span>Attendance</span>
            </a>
            <a href="profile.php" class="active">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
            
        <?php elseif ($user_role == 2): // COMMITTEE SIDEBAR ?>
            <a href="dashboard_committee.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module2/club_dashboard_committee.php">
                <i class="fas fa-building"></i> <span>My Club</span>
            </a>
            <a href="../module3/manage_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Events</span>
            </a>
            <a href="../module3/create_event.php">
                <i class="fas fa-calendar-plus"></i> <span>Create Event</span>
            </a>
            <a href="../module4/attendance_management.php">
                <i class="fas fa-qrcode"></i> <span>Record Attendance</span>
            </a>
            <a href="../module4/attendance_dashboard.php">
                <i class="fas fa-chart-bar"></i> <span>Attendance</span>
            </a>
            <a href="profile.php" class="active">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
            
        <?php else: // STUDENT SIDEBAR ?>
            <a href="dashboard_student.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module2/club_dashboard_student.php">
                <i class="fas fa-building"></i> <span>Browse Clubs</span>
            </a>
            <a href="../module3/browse_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Browse Events</span>
            </a>
            <a href="../module3/my_registrations.php">
                <i class="fas fa-list"></i> <span>My Registrations</span>
            </a>
            <a href="../module4/my_points_recognition.php">
                <i class="fas fa-star"></i> <span>My Points</span>
            </a>
            <a href="profile.php" class="active">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($user['name']); ?>
            <span class="badge-role">
                <?php 
                    if ($user_role == 1) echo "Administrator";
                    elseif ($user_role == 2) echo "Committee";
                    else echo "Student";
                ?>
            </span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
            <p class="text-muted"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user['studentId'] ?? 'Staff'); ?></p>
            <hr>
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
        </div>

        <div class="profile-info">
            <?php if ($update_success): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $update_success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="info-section">
                    <h5><i class="fas fa-user"></i> Personal Information</h5>
                    <div class="info-row">
                        <div class="info-label">ID:</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['studentId'] ?? 'Not set'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Full Name:</div>
                        <div class="info-value"><input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>"></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Phone:</div>
                        <div class="info-value"><input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>
                    </div>
                </div>

                <?php if ($user_role != 1): ?>
                <div class="info-section">
                    <h5><i class="fas fa-graduation-cap"></i> Academic Information</h5>
                    <div class="info-row">
                        <div class="info-label">Programme:</div>
                        <div class="info-value">
                            <select name="programme">
                                <option value="">Select</option>
                                <option value="Computer Science" <?php echo ($user['programme'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Information Technology" <?php echo ($user['programme'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Software Engineering" <?php echo ($user['programme'] == 'Software Engineering') ? 'selected' : ''; ?>>Software Engineering</option>
                                <option value="Data Science" <?php echo ($user['programme'] == 'Data Science') ? 'selected' : ''; ?>>Data Science</option>
                            </select>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Year of Study:</div>
                        <div class="info-value">
                            <select name="year">
                                <option value="">Select</option>
                                <option value="1" <?php echo ($user['yearsOfStud'] == 1) ? 'selected' : ''; ?>>Year 1</option>
                                <option value="2" <?php echo ($user['yearsOfStud'] == 2) ? 'selected' : ''; ?>>Year 2</option>
                                <option value="3" <?php echo ($user['yearsOfStud'] == 3) ? 'selected' : ''; ?>>Year 3</option>
                                <option value="4" <?php echo ($user['yearsOfStud'] == 4) ? 'selected' : ''; ?>>Year 4</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-section">
                    <h5><i class="fas fa-building"></i> My Clubs</h5>
                    <?php if (empty($userClubs)): ?>
                        <p class="text-muted">No clubs joined yet.</p>
                    <?php else: ?>
                        <?php foreach ($userClubs as $club): ?>
                            <span class="club-tag"><?php echo htmlspecialchars($club['clubName']); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <button type="submit" name="update_profile" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                    <button type="button" class="btn-change-password" onclick="showPasswordModal()"><i class="fas fa-key"></i> Change Password</button>
                    <?php if ($user_role == 1): ?>
                        <a href="dashboard_admin.php" class="btn-cancel"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    <?php elseif ($user_role == 2): ?>
                        <a href="dashboard_committee.php" class="btn-cancel"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    <?php else: ?>
                        <a href="dashboard_student.php" class="btn-cancel"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="passwordModal" class="modal-overlay">
    <div class="modal-content">
        <h4><i class="fas fa-lock"></i> Change Password</h4>
        <form method="POST">
            <input type="password" name="current_password" placeholder="Current Password" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <div class="modal-buttons">
                <button type="submit" name="change_password" class="modal-btn-confirm">Update Password</button>
                <button type="button" class="modal-btn-cancel" onclick="closePasswordModal()">Cancel</button>
            </div>
        </form>
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
    function showPasswordModal() {
        document.getElementById('passwordModal').style.display = 'flex';
    }
    function closePasswordModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }
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
        const logoutModal = document.getElementById('logoutModal');
        const passwordModal = document.getElementById('passwordModal');
        if (event.target == logoutModal) logoutModal.style.display = 'none';
        if (event.target == passwordModal) passwordModal.style.display = 'none';
    };
</script>
</body>
</html>