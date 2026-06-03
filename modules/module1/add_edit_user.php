<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: login.php");
    exit();
}

// Get user ID from URL (for edit mode)
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($user_id > 0);

// Get user data if editing
$user_data = null;
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    
    if (!$user_data) {
        header("Location: manage_users.php");
        exit();
    }
}

// Get roles for dropdown
$roles = $pdo->query("SELECT * FROM user_role")->fetchAll();

// Get clubs for dropdown
$clubs = $pdo->query("SELECT * FROM club WHERE status = 'Active'")->fetchAll();

// Get committee positions for dropdown
$positions = $pdo->query("SELECT * FROM committee_position")->fetchAll();

// Handle form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $programme = trim($_POST['programme'] ?? '');
    $year = !empty($_POST['year']) ? (int)$_POST['year'] : null;
    $role_id = (int)$_POST['role_id'];
    $club_id = !empty($_POST['club_id']) ? (int)$_POST['club_id'] : null;
    $position_id = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    $status = $_POST['status'];
    
    // Determine which ID to use based on role
    $id_value = '';
    if ($role_id == 1) { // Admin
        $id_value = $staff_id;
    } else { // Student or Committee
        $id_value = $student_id;
    }
    
    // Validation
    if (empty($name) || empty($email) || empty($role_id)) {
        $error = "Please fill in all required fields.";
    } elseif ($role_id == 1 && empty($staff_id)) {
        $error = "Please enter Staff ID for Administrator.";
    } elseif (($role_id == 2 || $role_id == 3) && empty($student_id)) {
        $error = "Please enter Student ID for Student/Committee.";
    } elseif ($role_id == 2 && empty($position_id)) {
        $error = "Please select a position for Committee Member.";
    } else {
        try {
            if ($is_edit) {
                // Update existing user
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET studentId = ?, name = ?, email = ?, phone = ?, 
                        programme = ?, yearsOfStud = ?, role_id = ?, status = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$id_value, $name, $email, $phone, $programme, $year, $role_id, $status, $user_id]);
                
                // Update club and position assignment if committee
                if ($role_id == 2) {
                    $check = $pdo->prepare("SELECT * FROM club_committee WHERE user_id = ?");
                    $check->execute([$user_id]);
                    if ($check->fetch()) {
                        $update = $pdo->prepare("UPDATE club_committee SET club_id = ?, position_id = ? WHERE user_id = ?");
                        $update->execute([$club_id, $position_id, $user_id]);
                    } else if ($club_id) {
                        $insert = $pdo->prepare("INSERT INTO club_committee (user_id, club_id, position_id, assignedDate, status) VALUES (?, ?, ?, CURDATE(), 'Active')");
                        $insert->execute([$user_id, $club_id, $position_id]);
                    }
                } else {
                    $pdo->prepare("DELETE FROM club_committee WHERE user_id = ?")->execute([$user_id]);
                }
                
                // Redirect after update
                header("Location: manage_users.php?msg=updated");
                exit();
            } else {
                // Create new user
                $check = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $error = "Email already exists!";
                } else {
                    $temp_password = 'password123';
                    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (studentId, name, email, phone, programme, yearsOfStud, role_id, status, passwordHash, emailVerified, createdAt)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmt->execute([$id_value, $name, $email, $phone, $programme, $year, $role_id, $status, $hashed_password]);
                    
                    $new_user_id = $pdo->lastInsertId();
                    
                    if ($role_id == 2 && $club_id && $position_id) {
                        $insert = $pdo->prepare("INSERT INTO club_committee (user_id, club_id, position_id, assignedDate, status) VALUES (?, ?, ?, CURDATE(), 'Active')");
                        $insert->execute([$new_user_id, $club_id, $position_id]);
                    }
                    
                    header("Location: manage_users.php?msg=added");
                    exit();
                }
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get user's club and position if committee
$user_club_id = null;
$user_position_id = null;
if ($is_edit && $user_data && $user_data['role_id'] == 2) {
    $stmt = $pdo->prepare("SELECT club_id, position_id FROM club_committee WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_committee = $stmt->fetch();
    $user_club_id = $user_committee['club_id'] ?? null;
    $user_position_id = $user_committee['position_id'] ?? null;
}

// Determine which ID to display
$display_student_id = '';
$display_staff_id = '';
if ($user_data) {
    if ($user_data['role_id'] == 1) {
        $display_staff_id = $user_data['studentId'] ?? '';
    } else {
        $display_student_id = $user_data['studentId'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit User' : 'Add New User'; ?> - FK Club System</title>
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
        .logout-btn:hover { background: #c82333; }
        
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
        }
        .form-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--umpsa-gold);
        }
        .form-header h3 { color: var(--umpsa-blue); margin: 0; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group label i { color: var(--umpsa-gold); margin-right: 5px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .full-width { grid-column: span 2; }
        .radio-group { display: flex; gap: 20px; align-items: center; padding-top: 8px; }
        .radio-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; margin: 0; }
        .radio-group input { width: auto; margin: 0; }
        .form-actions { display: flex; gap: 15px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .btn-save { background: #28a745; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; }
        .btn-save:hover { background: #218838; }
        .btn-reset { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; }
        .btn-reset:hover { background: #e5a600; }
        .btn-cancel { background: #6c757d; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
        .btn-cancel:hover { background: #5a6268; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .required:after { content: " *"; color: red; }
        .info-text { font-size: 12px; color: #666; margin-top: 5px; }
        
        .student-field { display: block; }
        .admin-field { display: none; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-header p, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
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
        <a href="../module1/dashboard_admin.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="../module1/manage_users.php" class="active"><i class="fas fa-users"></i> <span>Manage Users</span></a>
        <a href="../module2/club_redirect.php"><i class="fas fa-building"></i> <span>Manage Clubs</span></a>
        <a href="../module3/event_dashboard.php">
           <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
        </a>
        <a href="../module3/manage_events.php"><i class="fas fa-calendar-alt"></i> <span>Events</span></a>
        <a href="../module4/attendance_dashboard.php"><i class="fas fa-chart-bar"></i> <span>Attendance</span></a>
        <a href="../module4/generate_report.php"><i class="fas fa-file-alt"></i> <span>Reports</span></a>
        <a href="../module1/profile.php"><i class="fas fa-user"></i> <span>Profile</span></a>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
            <span class="badge-role">Administrator</span>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-user-<?php echo $is_edit ? 'edit' : 'plus'; ?>"></i> <?php echo $is_edit ? 'Edit User' : 'Add New User'; ?></h3>
            <p class="text-muted mt-2">Fill in the user details below</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid">
                <!-- STUDENT ID FIELD -->
                <div class="form-group student-field" id="studentIdField">
                    <label><i class="fas fa-id-card"></i> Student ID</label>
                    <input type="text" name="student_id" value="<?php echo htmlspecialchars($display_student_id); ?>" placeholder="e.g., CS23001">
                    <div class="info-text">For students and committee members</div>
                </div>
                
                <!-- STAFF ID FIELD -->
                <div class="form-group admin-field" id="staffIdField">
                    <label><i class="fas fa-id-badge"></i> Staff ID</label>
                    <input type="text" name="staff_id" value="<?php echo htmlspecialchars($display_staff_id); ?>" placeholder="e.g., FK001">
                    <div class="info-text">For administrator/staff only</div>
                </div>
                
                <!-- Full Name -->
                <div class="form-group">
                    <label class="required"><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" placeholder="Enter full name" required>
                </div>
                
                <!-- Email -->
                <div class="form-group">
                    <label class="required"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="Enter email address" required>
                </div>
                
                <!-- Phone -->
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" placeholder="e.g., 012-3456789">
                </div>
                
                <!-- Programme -->
                <div class="form-group student-field" id="programmeField">
                    <label><i class="fas fa-graduation-cap"></i> Programme</label>
                    <select name="programme">
                        <option value="">Select Programme</option>
                        <option value="Computer Science" <?php echo (($user_data['programme'] ?? '') == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                        <option value="Information Technology" <?php echo (($user_data['programme'] ?? '') == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                        <option value="Software Engineering" <?php echo (($user_data['programme'] ?? '') == 'Software Engineering') ? 'selected' : ''; ?>>Software Engineering</option>
                        <option value="Data Science" <?php echo (($user_data['programme'] ?? '') == 'Data Science') ? 'selected' : ''; ?>>Data Science</option>
                    </select>
                </div>
                
                <!-- Year of Study -->
                <div class="form-group student-field" id="yearField">
                    <label><i class="fas fa-calendar"></i> Year of Study</label>
                    <select name="year">
                        <option value="">Select Year</option>
                        <option value="1" <?php echo (($user_data['yearsOfStud'] ?? '') == 1) ? 'selected' : ''; ?>>Year 1</option>
                        <option value="2" <?php echo (($user_data['yearsOfStud'] ?? '') == 2) ? 'selected' : ''; ?>>Year 2</option>
                        <option value="3" <?php echo (($user_data['yearsOfStud'] ?? '') == 3) ? 'selected' : ''; ?>>Year 3</option>
                        <option value="4" <?php echo (($user_data['yearsOfStud'] ?? '') == 4) ? 'selected' : ''; ?>>Year 4</option>
                    </select>
                </div>
                
                <!-- Role -->
                <div class="form-group">
                    <label class="required"><i class="fas fa-tag"></i> Role</label>
                    <select name="role_id" id="role_select" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>" <?php echo (($user_data['role_id'] ?? '') == $role['role_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['roleName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Club Field -->
                <div class="form-group" id="club_field" style="display: none;">
                    <label><i class="fas fa-building"></i> Assign Club</label>
                    <select name="club_id">
                        <option value="">Select Club</option>
                        <?php foreach ($clubs as $club): ?>
                            <option value="<?php echo $club['club_id']; ?>" <?php echo (($user_club_id ?? '') == $club['club_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($club['clubName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Position Field -->
                <div class="form-group" id="position_field" style="display: none;">
                    <label><i class="fas fa-user-tie"></i> Committee Position</label>
                    <select name="position_id">
                        <option value="">Select Position</option>
                        <?php foreach ($positions as $position): ?>
                            <option value="<?php echo $position['position_id']; ?>" <?php echo (($user_position_id ?? '') == $position['position_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($position['positionName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Status -->
                <div class="form-group full-width">
                    <label><i class="fas fa-power-off"></i> Account Status</label>
                    <div class="radio-group">
                        <label><input type="radio" name="status" value="Active" <?php echo (($user_data['status'] ?? 'Active') == 'Active') ? 'checked' : ''; ?>> Active</label>
                        <label><input type="radio" name="status" value="Inactive" <?php echo (($user_data['status'] ?? '') == 'Inactive') ? 'checked' : ''; ?>> Inactive</label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> <?php echo $is_edit ? 'Update User' : 'Save User'; ?></button>
                <?php if ($is_edit): ?>
                    <button type="button" class="btn-reset" onclick="alert('Reset password email sent to user')"><i class="fas fa-key"></i> Reset Password</button>
                <?php endif; ?>
                <a href="manage_users.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
        
        <?php if (!$is_edit): ?>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i> New users will get temporary password: <strong>password123</strong>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleFields() {
        const roleSelect = document.getElementById('role_select');
        const selectedRole = roleSelect.options[roleSelect.selectedIndex].text;
        
        const studentFields = document.querySelectorAll('.student-field');
        const adminFields = document.querySelectorAll('.admin-field');
        const clubField = document.getElementById('club_field');
        const positionField = document.getElementById('position_field');
        
        if (selectedRole === 'Administrator') {
            studentFields.forEach(field => { field.style.display = 'none'; });
            adminFields.forEach(field => { field.style.display = 'block'; });
            clubField.style.display = 'none';
            positionField.style.display = 'none';
        } 
        else if (selectedRole === 'Club Committee') {
            studentFields.forEach(field => { field.style.display = 'block'; });
            adminFields.forEach(field => { field.style.display = 'none'; });
            clubField.style.display = 'block';
            positionField.style.display = 'block';
        }
        else {
            studentFields.forEach(field => { field.style.display = 'block'; });
            adminFields.forEach(field => { field.style.display = 'none'; });
            clubField.style.display = 'none';
            positionField.style.display = 'none';
        }
    }
    
    toggleFields();
    document.getElementById('role_select').addEventListener('change', toggleFields);
</script>

</body>
</html>
<?php 
$pdo = null;
?>