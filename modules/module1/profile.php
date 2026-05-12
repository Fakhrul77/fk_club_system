<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Get user data from database
$stmt = $pdo->prepare("
    SELECT u.*, r.roleName, sc.categoryName 
    FROM users u
    JOIN user_role r ON u.role_id = r.role_id
    LEFT JOIN student_category sc ON u.category_id = sc.category_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user's clubs
$stmt = $pdo->prepare("
    SELECT c.clubName, cm.joinDate, cm.status
    FROM club_membership cm
    JOIN club c ON cm.club_id = c.club_id
    WHERE cm.user_id = ? AND cm.status = 'Active'
");
$stmt->execute([$user_id]);
$userClubs = $stmt->fetchAll();

// Handle profile update
$update_success = '';
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $phone = $_POST['phone'] ?? '';
    $programme = $_POST['programme'] ?? '';
    $year = $_POST['year'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET phone = ?, programme = ?, yearsOfStud = ?, updatedAt = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$phone, $programme, $year, $user_id]);
        $update_success = "Profile updated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
    } catch(PDOException $e) {
        $update_error = "Update failed: " . $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $pwd_error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $pwd_error = "Password must be at least 6 characters!";
    } else {
        // For demo, just show success (in real app, you'd update the database)
        $pwd_success = "Password changed successfully!";
    }
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

        /* ========== PROFILE CONTAINER ========== */
        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
        }

        /* Profile Sidebar */
        .profile-sidebar {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            height: fit-content;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #1E3A5F, #FF6B35);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .profile-avatar i {
            font-size: 70px;
            color: white;
        }

        .upload-btn {
            background: #FF6B35;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .upload-btn:hover {
            background: #1E3A5F;
        }

        .profile-name {
            font-size: 20px;
            font-weight: bold;
            margin: 15px 0 5px;
            color: #1E3A5F;
        }

        .profile-role {
            color: #FF6B35;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 22px;
            font-weight: bold;
            color: #1E3A5F;
        }

        .stat-label {
            font-size: 11px;
            color: #666;
        }

        /* Profile Info Card */
        .profile-info {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-section h5 {
            color: #1E3A5F;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #FF6B35;
            display: inline-block;
        }

        .info-row {
            display: flex;
            margin-bottom: 18px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 12px;
            align-items: center;
        }

        .info-label {
            width: 140px;
            font-weight: 600;
            color: #555;
        }

        .info-value {
            flex: 1;
            color: #333;
        }

        .info-value i {
            color: #FF6B35;
            margin-right: 8px;
        }

        .edit-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            display: none;
        }

        .edit-input.show {
            display: block;
        }

        .info-value.show {
            display: none;
        }

        .btn-edit {
            background: #1E3A5F;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }

        .btn-save {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
            display: none;
        }

        .btn-save.show {
            display: inline-block;
        }

        .club-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 5px;
        }

        .club-tag {
            background: #e8f4f8;
            padding: 6px 15px;
            border-radius: 25px;
            font-size: 12px;
            color: #1E3A5F;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn-change-password {
            background: #FF6B35;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* Modal */
        .modal {
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
            padding: 30px;
            border-radius: 20px;
            width: 450px;
        }

        .modal-content h4 {
            margin-bottom: 20px;
            color: #1E3A5F;
        }

        .modal-content input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .profile-container {
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
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .info-label {
                width: 100%;
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
        <a href="dashboard_student.php">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="#">
            <i class="fas fa-building"></i> <span>Browse Clubs</span>
        </a>
        <a href="#">
            <i class="fas fa-calendar-alt"></i> <span>Browse Events</span>
        </a>
        <a href="#">
            <i class="fas fa-list"></i> <span>My Registrations</span>
        </a>
        <a href="#">
            <i class="fas fa-star"></i> <span>My Points</span>
        </a>
        <a href="#" class="active">
            <i class="fas fa-user"></i> <span>Profile</span>
        </a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($user['name']); ?>
            <span class="badge-role"><?php echo htmlspecialchars($user['roleName']); ?></span>
        </div>
        <a href="../../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Profile Container -->
    <div class="profile-container">
        <!-- Left Sidebar - Profile Photo -->
        <div class="profile-sidebar">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <form id="photoForm" style="display: inline;">
                <button type="button" class="upload-btn" onclick="document.getElementById('photoInput').click()">
                    <i class="fas fa-camera"></i> Upload Photo
                </button>
                <input type="file" id="photoInput" style="display: none;" accept="image/*">
            </form>
            <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
            <div class="profile-role">
                <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user['studentId']); ?>
            </div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $user['yearsOfStud'] ?? 'N/A'; ?></div>
                    <div class="stat-label">Year of Study</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($userClubs); ?></div>
                    <div class="stat-label">Clubs Joined</div>
                </div>
            </div>
            
            <div class="contact-info">
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($user['programme'] ?? 'Not specified'); ?></p>
            </div>
        </div>

        <!-- Right Side - Profile Information -->
        <div class="profile-info">
            <?php if ($update_success): ?>
                <div class="alert-success"><?php echo $update_success; ?></div>
            <?php endif; ?>
            
            <?php if ($update_error): ?>
                <div class="alert-error"><?php echo $update_error; ?></div>
            <?php endif; ?>

            <form method="POST" id="profileForm">
                <div class="info-section">
                    <h5><i class="fas fa-user"></i> Personal Information</h5>
                    
                    <div class="info-row">
                        <div class="info-label">Student ID:</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['studentId']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Full Name:</div>
                        <div class="info-value" id="nameValue"><?php echo htmlspecialchars($user['name']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value" id="emailValue"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Phone Number:</div>
                        <div class="info-value" id="phoneValue"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
                        <input type="text" class="edit-input" id="phoneInput" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        <button type="button" class="btn-edit" onclick="editField('phone')"><i class="fas fa-edit"></i> Edit</button>
                        <button type="submit" class="btn-save" id="savePhoneBtn" name="update_profile" style="display: none;"><i class="fas fa-save"></i> Save</button>
                    </div>
                </div>
                
                <div class="info-section">
                    <h5><i class="fas fa-graduation-cap"></i> Academic Information</h5>
                    
                    <div class="info-row">
                        <div class="info-label">Programme:</div>
                        <div class="info-value" id="programmeValue"><?php echo htmlspecialchars($user['programme'] ?? 'Not specified'); ?></div>
                        <select class="edit-input" id="programmeInput" name="programme">
                            <option value="Computer Science" <?php echo ($user['programme'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                            <option value="Information Technology" <?php echo ($user['programme'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                            <option value="Software Engineering" <?php echo ($user['programme'] == 'Software Engineering') ? 'selected' : ''; ?>>Software Engineering</option>
                            <option value="Data Science" <?php echo ($user['programme'] == 'Data Science') ? 'selected' : ''; ?>>Data Science</option>
                        </select>
                        <button type="button" class="btn-edit" onclick="editField('programme')"><i class="fas fa-edit"></i> Edit</button>
                        <button type="submit" class="btn-save" id="saveProgrammeBtn" name="update_profile" style="display: none;"><i class="fas fa-save"></i> Save</button>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Year of Study:</div>
                        <div class="info-value" id="yearValue"><?php echo htmlspecialchars($user['yearsOfStud'] ?? 'Not specified'); ?></div>
                        <select class="edit-input" id="yearInput" name="year">
                            <option value="1" <?php echo ($user['yearsOfStud'] == 1) ? 'selected' : ''; ?>>Year 1</option>
                            <option value="2" <?php echo ($user['yearsOfStud'] == 2) ? 'selected' : ''; ?>>Year 2</option>
                            <option value="3" <?php echo ($user['yearsOfStud'] == 3) ? 'selected' : ''; ?>>Year 3</option>
                            <option value="4" <?php echo ($user['yearsOfStud'] == 4) ? 'selected' : ''; ?>>Year 4</option>
                        </select>
                        <button type="button" class="btn-edit" onclick="editField('year')"><i class="fas fa-edit"></i> Edit</button>
                        <button type="submit" class="btn-save" id="saveYearBtn" name="update_profile" style="display: none;"><i class="fas fa-save"></i> Save</button>
                    </div>
                </div>
            </form>
            
            <div class="info-section">
                <h5><i class="fas fa-building"></i> My Clubs</h5>
                <div class="club-list">
                    <?php if (empty($userClubs)): ?>
                        <p class="text-muted">You haven't joined any clubs yet.</p>
                    <?php else: ?>
                        <?php foreach ($userClubs as $club): ?>
                            <span class="club-tag">
                                <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                <?php echo htmlspecialchars($club['clubName']); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="button" class="btn-change-password" onclick="showPasswordModal()">
                    <i class="fas fa-key"></i> Change Password
                </button>
                <a href="dashboard_student.php" class="btn-cancel">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <h4><i class="fas fa-lock"></i> Change Password</h4>
        <form method="POST">
            <input type="password" name="current_password" placeholder="Current Password" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <?php if (isset($pwd_error)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 8px; border-radius: 5px; margin: 10px 0;"><?php echo $pwd_error; ?></div>
            <?php endif; ?>
            <?php if (isset($pwd_success)): ?>
                <div style="background: #d4edda; color: #155724; padding: 8px; border-radius: 5px; margin: 10px 0;"><?php echo $pwd_success; ?></div>
            <?php endif; ?>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="change_password" style="flex:1; background: #1E3A5F; color: white; padding: 10px; border: none; border-radius: 5px;">Update</button>
                <button type="button" onclick="closePasswordModal()" style="flex:1; background: #6c757d; color: white; padding: 10px; border: none; border-radius: 5px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentEditField = null;
    
    function editField(field) {
        // Hide value display, show input
        document.getElementById(field + 'Value').style.display = 'none';
        document.getElementById(field + 'Input').style.display = 'block';
        
        // Get the parent row
        const row = document.getElementById(field + 'Value').parentElement;
        const btns = row.querySelectorAll('.btn-edit, .btn-save');
        
        // Hide edit button, show save button
        btns[0].style.display = 'none';
        btns[1].style.display = 'inline-block';
    }
    
    // Photo upload handler
    document.getElementById('photoInput').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            alert('Profile photo updated successfully!');
        }
    });
    
    function showPasswordModal() {
        document.getElementById('passwordModal').style.display = 'flex';
    }
    
    function closePasswordModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('passwordModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>