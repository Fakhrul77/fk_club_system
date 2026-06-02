<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in and is committee or admin
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

// Handle event deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $event_id = (int)$_POST['delete_event_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get all registration_ids for this event
        $stmt = $pdo->prepare("SELECT registration_id FROM event_registration WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $registrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete attendance records for each registration
        if (!empty($registrations)) {
            $placeholders = implode(',', array_fill(0, count($registrations), '?'));
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE registration_id IN ($placeholders)");
            $stmt->execute($registrations);
        }
        
        // Delete event registrations
        $pdo->prepare("DELETE FROM event_registration WHERE event_id = ?")->execute([$event_id]);
        
        // Delete waiting list
        $pdo->prepare("DELETE FROM waiting_list WHERE event_id = ?")->execute([$event_id]);
        
        // Delete event
        $pdo->prepare("DELETE FROM event WHERE event_id = ?")->execute([$event_id]);
        
        $pdo->commit();
        header("Location: manage_events.php?msg=deleted");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: manage_events.php?msg=error");
        exit();
    }
}
// Handle event status update
if (isset($_GET['status']) && isset($_GET['event_id'])) {
    $status = $_GET['status'];
    $event_id = (int)$_GET['event_id'];
    $stmt = $pdo->prepare("UPDATE event SET status = ? WHERE event_id = ?");
    $stmt->execute([$status, $event_id]);
    header("Location: manage_events.php");
    exit();
}


// Get events based on role
if ($user_role == 1) {
    $stmt = $pdo->query("
        SELECT e.*, c.clubName, u.name as creator_name 
        FROM event e 
        JOIN club c ON e.club_id = c.club_id 
        LEFT JOIN users u ON e.created_by = u.user_id 
        ORDER BY e.event_date DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT e.*, c.clubName, u.name as creator_name 
        FROM event e 
        JOIN club c ON e.club_id = c.club_id 
        LEFT JOIN users u ON e.created_by = u.user_id 
        WHERE e.club_id = ?
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$club_id]);
}
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - FK Club System</title>
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

/* Delete Modal */
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
        .main-content { margin-left: 260px; padding: 20px; }
        
        .top-nav {
            background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .welcome-text { font-size: 16px; font-weight: 500; }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        
        .table-card {
            background: white; border-radius: 16px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .btn-add {
            background: #28a745; color: white; padding: 10px 20px; border: none;
            border-radius: 8px; text-decoration: none; display: inline-flex;
            align-items: center; gap: 8px; margin-bottom: 20px;
        }
        .btn-add:hover { background: #218838; color: white; }
        .status-upcoming { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-ongoing { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-completed { background: #d1ecf1; color: #0c5460; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .status-cancelled { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .action-btn { background: none; border: none; cursor: pointer; margin: 0 5px; color: #666; font-size: 16px; }
        .action-btn:hover { color: var(--umpsa-gold); }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
        }
    </style>
</head>
<body>

<!-- ========== DYNAMIC SIDEBAR BASED ON ROLE ========== -->
<?php if ($user_role == 1): // ADMIN SIDEBAR ?>
<div class="sidebar">
    <div class="sidebar-header">
        <img src="../../assets/images/logo.png" alt="Logo" style="width: 50px; height: auto; margin-bottom: 10px;">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <a href="../module1/dashboard_admin.php">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module1/manage_users.php">
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        <a href="../module2/club_dashboard_admin.php">
            <i class="fas fa-building"></i> <span>Manage Clubs</span>
        </a>
        <a href="../module3/manage_events.php" class="active">
            <i class="fas fa-calendar-alt"></i> <span>Events</span>
        </a>
        <a href="../module4/attendance_dashboard.php">
            <i class="fas fa-chart-bar"></i> <span>Attendance</span>
        </a>
        <a href="../module4/generate_report.php">
            <i class="fas fa-file-alt"></i> <span>Reports</span>
        </a>
        <a href="../module1/profile.php">
            <i class="fas fa-user"></i> <span>Profile</span>
        </a>
    </div>
</div>
<?php else: // COMMITTEE SIDEBAR ?>
<div class="sidebar">
    <div class="sidebar-header">
        <img src="../../assets/images/logo.png" alt="Logo" style="width: 50px; height: auto; margin-bottom: 10px;">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <a href="../module1/dashboard_committee.php">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module2/club_dashboard_committee.php">
            <i class="fas fa-building"></i> <span>My Club</span>
        </a>
        <a href="../module3/manage_events.php" class="active">
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
<?php endif; ?>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
            <span class="badge-role"><?php echo $user_role == 1 ? 'Administrator' : 'Committee'; ?></span>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="table-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
            <h3><i class="fas fa-calendar-alt"></i> Manage Events</h3>
            <a href="create_event.php" class="btn-add"><i class="fas fa-plus"></i> Create New Event</a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th><th>Event Title</th><th>Club</th><th>Date</th><th>Venue</th>
                        <th>Capacity</th><th>Registered</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?php echo $event['event_id']; ?></td>
                        <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                        <td><?php echo htmlspecialchars($event['clubName']); ?></td>
                        <td><?php echo date('d M Y', strtotime($event['event_date'])); ?> <?php echo date('h:i A', strtotime($event['event_time'])); ?></td>
                        <td><?php echo htmlspecialchars($event['venue']); ?></td>
                        <td><?php echo $event['max_participant']; ?></td>
                        <td><?php echo $event['current_participant']; ?></td>
                        <td>
                            <span class="status-<?php echo strtolower($event['status']); ?>">
                                <?php echo $event['status']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit_event.php?id=<?php echo $event['event_id']; ?>" class="action-btn" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="view_event.php?id=<?php echo $event['event_id']; ?>" class="action-btn" title="View"><i class="fas fa-eye"></i></a>
                            <button class="action-btn" onclick="changeStatus(<?php echo $event['event_id']; ?>)" title="Change Status"><i class="fas fa-sync-alt"></i></button>
                            <button class="action-btn" type="button"
        onclick="openDeleteModal(<?php echo $event['event_id']; ?>)">
    <i class="fas fa-trash-alt" style="color:#dc3545;"></i>
</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form method="POST" id="deleteEventForm">
    <input type="hidden" name="delete_event_id" id="delete_event_id">
</form>

<form method="GET" id="statusForm">
    <input type="hidden" name="event_id" id="status_event_id">
    <input type="hidden" name="status" id="status_value">
</form>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content">
            <i class="fas fa-exclamation-triangle" style="font-size:50px;color:#dc3545;"></i>
            <h4>Delete Event?</h4>
            <p>This will remove event and all registrations permanently.</p>

            <div class="modal-buttons">
                <button class="modal-btn-confirm" onclick="confirmDelete()">Delete</button>
                <button class="modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="statusModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-sync-alt" style="font-size:50px;color:#003B5C;"></i>

        <h4>Change Event Status</h4>

        <select id="statusSelect" class="form-select" style="margin:15px 0;">
            <option value="">-- Select Status --</option>
            <option value="UPCOMING">Upcoming</option>
            <option value="ONGOING">Ongoing</option>
            <option value="COMPLETED">Completed</option>
            <option value="CANCELLED">Cancelled</option>
        </select>

        <div class="modal-buttons">
            <button class="modal-btn-confirm" onclick="confirmStatusChange()">Save</button>
            <button class="modal-btn-cancel" onclick="closeStatusModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
let selectedStatusEventId = null;

// OPEN STATUS MODAL
function changeStatus(id) {
    selectedStatusEventId = id;
    document.getElementById('statusModal').style.display = 'flex';
}

// CLOSE STATUS MODAL
function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
    selectedStatusEventId = null;
    document.getElementById('statusSelect').value = "";
}

// CONFIRM STATUS UPDATE
function confirmStatusChange() {
    let status = document.getElementById('statusSelect').value;

    if (!status) {
        alert("Please select a status");
        return;
    }

    document.getElementById('status_event_id').value = selectedStatusEventId;
    document.getElementById('status_value').value = status;

    document.getElementById('statusForm').submit();
}

let selectedEventId = null;

function openDeleteModal(id) {
    selectedEventId = id;
    document.getElementById('deleteModal').style.display = 'flex';
}


function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    selectedEventId = null;
}


function confirmDelete() {
    if (!selectedEventId) return;

    document.getElementById('delete_event_id').value = selectedEventId;
    document.getElementById('deleteModal').style.display = 'none';
    document.getElementById('deleteEventForm').submit();
}
</script>
</body>
</html>