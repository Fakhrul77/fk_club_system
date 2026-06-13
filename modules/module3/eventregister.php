<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 1 && $_SESSION['user_role'] != 2)) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user's club if committee
$club_id = null;
if ($user_role == 2) {
    $stmt = $pdo->prepare("SELECT club_id FROM club_committee WHERE user_id = ? AND status = 'Active'");
    $stmt->execute([$user_id]);
    $club = $stmt->fetch();
    $club_id = $club['club_id'] ?? null;
}

// Get events for dropdown
if ($user_role == 1) {
    $events = $pdo->query("SELECT event_id, event_title, club_id FROM event ORDER BY event_date DESC")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT event_id, event_title, club_id FROM event WHERE club_id = ? ORDER BY event_date DESC");
    $stmt->execute([$club_id]);
    $events = $stmt->fetchAll();
}

$selected_event = isset($_GET['event_id']) ? (int)$_GET['event_id'] : ($events[0]['event_id'] ?? 0);
$registrations = [];

if ($selected_event) {
    $stmt = $pdo->prepare("
        SELECT er.*, u.name, u.studentId, u.email, u.programme
        FROM event_registration er
        JOIN users u ON er.user_id = u.user_id
        WHERE er.event_id = ?
        ORDER BY er.registration_date DESC
    ");
    $stmt->execute([$selected_event]);
    $registrations = $stmt->fetchAll();
    
    // Get waiting list
    $waiting_stmt = $pdo->prepare("
        SELECT wl.*, u.name, u.studentId
        FROM waiting_list wl
        JOIN users u ON wl.user_id = u.user_id
        WHERE wl.event_id = ?
        ORDER BY wl.position ASC
    ");
    $waiting_stmt->execute([$selected_event]);
    $waiting_list = $waiting_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registrations - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --umpsa-blue: #003B5C; --umpsa-gold: #FDB813; --umpsa-dark-blue: #002147; --umpsa-light-blue: #E8F0F8; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--umpsa-light-blue); }
        .sidebar {
            position: fixed; top: 0; left: 0; height: 100%; width: 260px;
            background: var(--umpsa-dark-blue); color: white; z-index: 1000;
        }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { margin: 0; font-size: 18px; }
        .sidebar-menu { padding: 20px 0; }
        .sidebar-menu a {
            display: block; padding: 12px 25px; color: rgba(255,255,255,0.8);
            text-decoration: none; transition: all 0.3s; font-size: 14px;
        }
        .sidebar-menu a:hover { background: rgba(253,184,19,0.2); color: white; }
        .sidebar-menu a i { margin-right: 10px; width: 20px; }
        .sidebar-menu a.active { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav {
            background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        .table-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-select { padding: 8px 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
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
        <a href="../module1/dashboard_<?php echo $user_role == 1 ? 'admin' : 'committee'; ?>.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="manage_events.php"><i class="fas fa-calendar-alt"></i> <span>Manage Events</span></a>
        <a href="../module2/club_redirect.php"><i class="fas fa-building"></i> <span>Manage Clubs</span></a>
        <a href="create_event.php"><i class="fas fa-plus-circle"></i> <span>Create Event</span></a>
        <a href="event_registrations.php" class="active"><i class="fas fa-list-alt"></i> <span>Registrations</span></a>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            <span class="badge-role"><?php echo $user_role == 1 ? 'Administrator' : 'Committee'; ?></span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="table-card">
        <h3><i class="fas fa-users"></i> Event Registrations</h3>
        
        <form method="GET" class="mt-3 mb-4">
            <label>Select Event:</label>
            <select name="event_id" class="filter-select" onchange="this.form.submit()">
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo $event['event_id']; ?>" <?php echo $selected_event == $event['event_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($event['event_title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <h5>Registered Students (<?php echo count($registrations); ?>)</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th>Student ID</th><th>Name</th><th>Programme</th><th>Registration Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reg['studentId']); ?></td>
                        <td><?php echo htmlspecialchars($reg['name']); ?></td>
                        <td><?php echo htmlspecialchars($reg['programme']); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($reg['registration_date'])); ?></td>
                        <td><?php echo $reg['status']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($waiting_list)): ?>
        <h5 class="mt-4">Waiting List (<?php echo count($waiting_list); ?>)</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th>Position</th><th>Student ID</th><th>Name</th><th>Joined At</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($waiting_list as $wait): ?>
                    <tr>
                        <td><?php echo $wait['position']; ?></td>
                        <td><?php echo htmlspecialchars($wait['studentId']); ?></td>
                        <td><?php echo htmlspecialchars($wait['name']); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($wait['joined_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/logout_modal.php'; ?>

</body>
</html>