<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: login.php");
    exit();
}

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') {
        $message = '<div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> ✅ User updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
    if ($_GET['msg'] == 'added') {
        $message = '<div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> ✅ User added successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
    if ($_GET['msg'] == 'deleted') {
        $message = '<div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> ✅ User deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
    if ($_GET['msg'] == 'activated') {
        $message = '<div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> ✅ User activated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
    if ($_GET['msg'] == 'deactivated') {
        $message = '<div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> ✅ User deactivated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
}


// Handle user deactivation (soft delete) - with committee cleanup
if (isset($_GET['deactivate'])) {
    $deactivate_id = (int)$_GET['deactivate'];
    
    // Check if user is committee member
    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$deactivate_id]);
    $user = $stmt->fetch();
    
    if ($user && $user['role_id'] == 2) {
        // Remove from club_committee table first
        $pdo->prepare("DELETE FROM club_committee WHERE user_id = ?")->execute([$deactivate_id]);
    }
    
    // Deactivate the user
    $stmt = $pdo->prepare("UPDATE users SET status = 'Inactive' WHERE user_id = ?");
    $stmt->execute([$deactivate_id]);
    
    header("Location: manage_users.php?msg=deactivated");
    exit();
}

// Handle user activation (reactivate)
if (isset($_GET['activate'])) {
    $activate_id = (int)$_GET['activate'];
    $stmt = $pdo->prepare("UPDATE users SET status = 'Active' WHERE user_id = ?");
    $stmt->execute([$activate_id]);
    header("Location: manage_users.php?msg=activated");
    exit();
}

// Handle permanent delete (for inactive users only)
if (isset($_GET['delete_permanent'])) {
    $delete_id = (int)$_GET['delete_permanent'];
    
    // Remove from related tables first
    $pdo->prepare("DELETE FROM club_committee WHERE user_id = ?")->execute([$delete_id]);
    $pdo->prepare("DELETE FROM club_membership WHERE user_id = ?")->execute([$delete_id]);
    $pdo->prepare("DELETE FROM event_registration WHERE user_id = ?")->execute([$delete_id]);
    $pdo->prepare("DELETE FROM attendance WHERE user_id = ?")->execute([$delete_id]);
    $pdo->prepare("DELETE FROM activity_points WHERE user_id = ?")->execute([$delete_id]);
    
    // Finally delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$delete_id]);
    
    header("Location: manage_users.php?msg=deleted");
    exit();
}

// Get all users with role names
$users = $pdo->query("
    SELECT u.*, r.roleName 
    FROM users u 
    JOIN user_role r ON u.role_id = r.role_id 
    ORDER BY u.createdAt DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - FK Club System</title>
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
        
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .btn-add {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .btn-add:hover { background: #218838; color: white; }
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-select, .search-input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; }
        .search-input { flex: 1; max-width: 300px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f8f9fa; font-weight: 600; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .status-active { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-inactive { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .action-btn { background: none; border: none; cursor: pointer; margin: 0 5px; color: #666; font-size: 16px; }
        .action-btn:hover { color: var(--umpsa-gold); }
        .action-btn-delete { color: #dc3545; }
        .action-btn-delete:hover { color: #c82333; }
        .action-btn-activate { color: #28a745; }
        .action-btn-activate:hover { color: #218838; }
        
        /* Custom Confirmation Modal */
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
            width: 400px;
            text-align: center;
        }
        .modal-content i { font-size: 50px; margin-bottom: 15px; }
        .modal-content h4 { margin-bottom: 15px; }
        .modal-content p { margin-bottom: 20px; color: #666; }
        .modal-buttons { display: flex; gap: 15px; justify-content: center; }
        .modal-btn-confirm { background: #dc3545; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; }
        .modal-btn-cancel { background: #6c757d; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; }
        
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header h4, .sidebar-menu a span { display: none; } .main-content { margin-left: 70px; } }
    </style>
</head>
<body>

<!-- Custom Confirmation Modal for Deactivation -->
<div id="deactivateModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-user-slash" style="color: #dc3545;"></i>
        <h4>Confirm Deactivation</h4>
        <p id="deactivateMessage">Are you sure you want to deactivate this user?</p>
        <div class="modal-buttons">
            <button id="confirmDeactivateBtn" class="modal-btn-confirm">Yes, Deactivate</button>
            <button id="cancelDeactivateBtn" class="modal-btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal for Permanent Delete -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-trash-alt" style="color: #dc3545;"></i>
        <h4>Confirm Permanent Delete</h4>
        <p id="deleteMessage">⚠️ WARNING: This will permanently delete all user data. This action cannot be undone!</p>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="modal-btn-confirm">Yes, Delete Permanently</button>
            <button id="cancelDeleteBtn" class="modal-btn-cancel">Cancel</button>
        </div>
    </div>
</div>

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

     <?php echo $message; ?>


    <div class="table-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
            <h3><i class="fas fa-users"></i> Manage Users</h3>
            <a href="add_edit_user.php" class="btn-add"><i class="fas fa-user-plus"></i> Add New User</a>
        </div>
        
        <div class="filter-bar">
            <select class="filter-select" id="roleFilter">
                <option value="all">All Roles</option>
                <option value="Administrator">Administrator</option>
                <option value="Club Committee">Club Committee</option>
                <option value="Student">Student</option>
            </select>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by name, email or ID...">
        </div>

        <div class="table-responsive">
            <table id="usersTable">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Club</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        // Get club name for committee members
                        $club_name = '-';
                        if ($user['role_id'] == 2) {
                            $club_stmt = $pdo->prepare("SELECT c.clubName FROM club_committee cc JOIN club c ON cc.club_id = c.club_id WHERE cc.user_id = ?");
                            $club_stmt->execute([$user['user_id']]);
                            $club = $club_stmt->fetch();
                            $club_name = $club ? $club['clubName'] : '-';
                        }
                    ?>
                    <tr class="user-row" 
                        data-role="<?php echo htmlspecialchars($user['roleName']); ?>" 
                        data-name="<?php echo htmlspecialchars(strtolower($user['name'])); ?>" 
                        data-email="<?php echo htmlspecialchars(strtolower($user['email'])); ?>" 
                        data-id="<?php echo htmlspecialchars(strtolower($user['studentId'] ?? '')); ?>">
                        <td><?php echo htmlspecialchars($user['studentId'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['roleName']); ?></td>
                        <td><?php echo $club_name; ?></td>
                        <td><span class="<?php echo $user['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>"><?php echo $user['status']; ?></span></td>
                        <td>
                            <a href="add_edit_user.php?id=<?php echo $user['user_id']; ?>" class="action-btn" title="Edit User"><i class="fas fa-edit"></i></a>
                            
                            <?php if ($user['status'] == 'Active'): ?>
                                <button class="action-btn action-btn-delete" onclick="showDeactivateModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" title="Deactivate User">
                                    <i class="fas fa-user-slash"></i>
                                </button>
                            <?php else: ?>
                                <a href="?activate=<?php echo $user['user_id']; ?>" class="action-btn action-btn-activate" title="Activate User">
                                    <i class="fas fa-user-check"></i>
                                </a>
                                <button class="action-btn action-btn-delete" onclick="showDeleteModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" title="Permanently Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Deactivation Modal Variables
    let deactivateId = null;
    let deactivateName = '';
    
    // Delete Modal Variables
    let deleteId = null;
    let deleteName = '';
    
    // Show Deactivate Modal
    function showDeactivateModal(id, name) {
        deactivateId = id;
        deactivateName = name;
        document.getElementById('deactivateMessage').innerHTML = `Are you sure you want to deactivate <strong>${name}</strong>?<br>The user can be activated again later.`;
        document.getElementById('deactivateModal').style.display = 'flex';
    }
    
    // Confirm Deactivation
    document.getElementById('confirmDeactivateBtn').addEventListener('click', function() {
        if (deactivateId) {
            window.location.href = `?deactivate=${deactivateId}`;
        }
    });
    
    // Cancel Deactivation
    document.getElementById('cancelDeactivateBtn').addEventListener('click', function() {
        document.getElementById('deactivateModal').style.display = 'none';
        deactivateId = null;
    });
    
    // Show Delete Modal
    function showDeleteModal(id, name) {
        deleteId = id;
        deleteName = name;
        document.getElementById('deleteMessage').innerHTML = `⚠️ WARNING: You are about to permanently delete <strong>${name}</strong>.<br>This action cannot be undone!`;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    
    // Confirm Permanent Delete
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (deleteId) {
            window.location.href = `?delete_permanent=${deleteId}`;
        }
    });
    
    // Cancel Delete
    document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
        document.getElementById('deleteModal').style.display = 'none';
        deleteId = null;
    });
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const deactivateModal = document.getElementById('deactivateModal');
        const deleteModal = document.getElementById('deleteModal');
        if (event.target == deactivateModal) {
            deactivateModal.style.display = 'none';
        }
        if (event.target == deleteModal) {
            deleteModal.style.display = 'none';
        }
    }
    
    // Filter functions
    const roleFilter = document.getElementById('roleFilter');
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('.user-row');
    
    function filterTable() {
        const selectedRole = roleFilter.value;
        const searchTerm = searchInput.value.toLowerCase().trim();
        
        rows.forEach(row => {
            const rowRole = row.getAttribute('data-role');
            const rowName = row.getAttribute('data-name');
            const rowEmail = row.getAttribute('data-email');
            const rowId = row.getAttribute('data-id');
            
            let roleMatch = (selectedRole === 'all') ? true : (rowRole === selectedRole);
            let searchMatch = (searchTerm === '') ? true : (rowName.includes(searchTerm) || rowEmail.includes(searchTerm) || rowId.includes(searchTerm));
            
            row.style.display = (roleMatch && searchMatch) ? '' : 'none';
        });
    }
    
    roleFilter.addEventListener('change', filterTable);
    searchInput.addEventListener('keyup', filterTable);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>
<?php 
$pdo = null;
?>