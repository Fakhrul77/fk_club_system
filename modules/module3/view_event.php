<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../module1/login.php");
    exit();
}

$return_url = isset($_GET['return']) ? $_GET['return'] : 'manage_events.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$event_id) {
    if ($user_role == 1) header("Location: manage_events.php");
    elseif ($user_role == 2) header("Location: manage_events.php");
    else header("Location: browse_events.php");
    exit();
}

// Get event details with club info
$stmt = $pdo->prepare("
    SELECT e.*, c.clubName, c.clubCategory 
    FROM event e 
    JOIN club c ON e.club_id = c.club_id 
    WHERE e.event_id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    if ($user_role == 1) header("Location: manage_events.php");
    elseif ($user_role == 2) header("Location: manage_events.php");
    else header("Location: browse_events.php");
    exit();
}

// Get participant count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN status IN ('Registered', 'Confirmed') THEN 1 ELSE 0 END) as registered
    FROM event_registration 
    WHERE event_id = ? AND status NOT IN ('Cancelled', 'NoShow')
");
$stmt->execute([$event_id]);
$participants = $stmt->fetch();

// Get attendees list with attendance status
$stmt = $pdo->prepare("
    SELECT er.*, u.name, u.studentId, u.email, u.programme, a.attendanceStatus, a.checkInTime
    FROM event_registration er
    JOIN users u ON er.user_id = u.user_id
    LEFT JOIN attendance a ON er.registration_id = a.registration_id
    WHERE er.event_id = ? AND er.status NOT IN ('Cancelled')
    ORDER BY a.attendanceStatus DESC, u.name ASC
");
$stmt->execute([$event_id]);
$attendees = $stmt->fetchAll();

// Count attendance stats
$present_count = 0;
$late_count = 0;
$absent_count = 0;
foreach ($attendees as $a) {
    if ($a['attendanceStatus'] == 'Present') $present_count++;
    elseif ($a['attendanceStatus'] == 'Late') $late_count++;
    elseif ($a['attendanceStatus'] == 'Absent') $absent_count++;
}
$total_attended = $present_count + $late_count;

// Get event status badge class
$status_class = match($event['status']) {
    'UPCOMING' => 'primary',
    'ONGOING' => 'warning',
    'COMPLETED' => 'success',
    'CANCELLED' => 'danger',
    default => 'secondary'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['event_title']); ?> - Event Details</title>
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
            background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .welcome-text { font-size: 16px; font-weight: 500; }
        .badge-role { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-left: 10px; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; }
        .logout-btn:hover { background: #c82333; }

        .event-header {
            background: linear-gradient(135deg, var(--umpsa-blue), var(--umpsa-dark-blue));
            color: white;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
        }
        .event-title { font-size: 28px; font-weight: bold; margin-bottom: 10px; }
        .event-club { font-size: 16px; opacity: 0.9; margin-bottom: 15px; }
        .event-club i { color: var(--umpsa-gold); margin-right: 5px; }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: 100%;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-number { font-size: 32px; font-weight: bold; color: var(--umpsa-blue); }
        .stat-label { color: #666; font-size: 13px; margin-top: 5px; }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .info-card h5 {
            color: var(--umpsa-blue);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--umpsa-gold);
            display: inline-block;
        }
        .info-row {
            display: flex;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            width: 130px;
            font-weight: 600;
            color: #555;
        }
        .info-value { flex: 1; color: #333; }

        .badge-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-upcoming { background: #0d6efd; color: white; }
        .badge-ongoing { background: #ffc107; color: #333; }
        .badge-completed { background: #198754; color: white; }
        .badge-cancelled { background: #dc3545; color: white; }

        .btn-back {
            background: #6c757d;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back:hover { background: #5a6268; color: white; }

        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-header p, .sidebar-menu a span { display: none; }
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
        <?php if ($user_role == 1): // ADMIN SIDEBAR ?>
            <a href="../module1/dashboard_admin.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module1/manage_users.php">
                <i class="fas fa-users"></i> <span>Manage Users</span>
            </a>
            <a href="../module2/club_redirect.php">
                <i class="fas fa-building"></i> <span>Manage Clubs</span>
            </a>
            <a href="../module3/event_dashboard.php">
           <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
        </a>
            <a href="manage_events.php" class="active">
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
            
        <?php elseif ($user_role == 2): // COMMITTEE SIDEBAR ?>
            <a href="../module1/dashboard_committee.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module2/club_dashboard_committee.php">
                <i class="fas fa-building"></i> <span>My Club</span>
            </a>
          <a href="../module3/event_dashboard.php">
           <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
           </a>
            <a href="manage_events.php" class="active">
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
            
        <?php else: // STUDENT SIDEBAR ?>
            <a href="../module1/dashboard_student.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module2/club_dashboard_student.php">
                <i class="fas fa-building"></i> <span>Browse Clubs</span>
            </a>
            <a href="browse_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Browse Events</span>
            </a>
            <a href="my_registrations.php">
                <i class="fas fa-list"></i> <span>My Registrations</span>
            </a>
            <a href="../module4/my_points_recognition.php">
                <i class="fas fa-star"></i> <span>My Points</span>
            </a>
            <a href="../module1/profile.php">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            <span class="badge-role"><?php echo $user_role == 1 ? 'Administrator' : ($user_role == 2 ? 'Committee' : 'Student'); ?></span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Back Button -->
    <div class="mb-3">
        <a href="<?php echo htmlspecialchars($return_url); ?>" class="btn-back">
              <i class="fas fa-arrow-left"></i> Back
         </a>
    </div>

    <?php 
// Get waiting list counts
$waiting_count = $pdo->prepare("SELECT COUNT(*) FROM waiting_list WHERE event_id = ?");
$waiting_count->execute([$event_id]);
$waiting_list_count = $waiting_count->fetchColumn();
?>

<?php if ($waiting_list_count > 0): ?>
    <div class="alert alert-info mt-2">
        <i class="fas fa-hourglass-half"></i> 
        <?php echo $waiting_list_count; ?> student(s) on waiting list. 
        They will be automatically promoted when spots become available.
    </div>
<?php endif; ?>

    <!-- Event Header -->
    <div class="event-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                <div class="event-club">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($event['clubName']); ?>
                    <?php if ($event['clubCategory']): ?>
                        • <i class="fas fa-tag"></i> <?php echo htmlspecialchars($event['clubCategory']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <span class="badge-status badge-<?php echo strtolower($event['status']); ?>">
                    <i class="fas <?php echo $event['status'] == 'UPCOMING' ? 'fa-clock' : ($event['status'] == 'ONGOING' ? 'fa-play' : ($event['status'] == 'COMPLETED' ? 'fa-check' : 'fa-ban')); ?>"></i>
                    <?php echo $event['status']; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $participants['registered']; ?></div>
                <div class="stat-label">Registered</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_attended; ?></div>
                <div class="stat-label">Attended</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $present_count; ?></div>
                <div class="stat-label">Present</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $event['max_participant']; ?></div>
                <div class="stat-label">Max Capacity</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Event Details Column -->
        <div class="col-md-5">
            <div class="info-card">
                <h5><i class="fas fa-info-circle"></i> Event Information</h5>
                <div class="info-row">
                    <div class="info-label">Date:</div>
                    <div class="info-value"><?php echo date('l, d F Y', strtotime($event['event_date'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Time:</div>
                    <div class="info-value"><?php echo date('h:i A', strtotime($event['event_time'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Venue:</div>
                    <div class="info-value"><?php echo htmlspecialchars($event['venue']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Capacity:</div>
                    <div class="info-value"><?php echo $participants['registered']; ?> / <?php echo $event['max_participant']; ?> registered</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Created By:</div>
                    <div class="info-value"><?php echo htmlspecialchars($event['created_by'] ?? 'System'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Created At:</div>
                    <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($event['created_at'])); ?></div>
                </div>
            </div>

            <?php if (!empty($event['event_description'])): ?>
            <div class="info-card">
                <h5><i class="fas fa-align-left"></i> Description</h5>
                <p><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Participants List Column -->
        <div class="col-md-7">
            <div class="info-card">
                <h5><i class="fas fa-users"></i> Participants List</h5>
                
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by name or student ID...">
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearSearch()"><i class="fas fa-times"></i> Clear</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="participantsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Attendance</th>
                                <th>Check-in Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach ($attendees as $attendee): ?>
                            <tr data-name="<?php echo strtolower($attendee['name']); ?>" data-id="<?php echo strtolower($attendee['studentId']); ?>">
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($attendee['name']); ?></td>
                                <td><?php echo htmlspecialchars($attendee['studentId'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($attendee['attendanceStatus'] == 'Present'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle"></i> Present</span>
                                    <?php elseif ($attendee['attendanceStatus'] == 'Late'): ?>
                                        <span class="badge bg-warning"><i class="fas fa-clock"></i> Late</span>
                                    <?php elseif ($attendee['attendanceStatus'] == 'Absent'): ?>
                                        <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Absent</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $attendee['checkInTime'] ? date('h:i A', strtotime($attendee['checkInTime'])) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($attendees)): ?>
                    <p class="text-muted text-center py-3">No participants registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/logout_modal.php'; ?>

<script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let searchTerm = this.value.toLowerCase();
        let rows = document.querySelectorAll('#participantsTable tbody tr');
        
        rows.forEach(row => {
            let name = row.getAttribute('data-name') || '';
            let id = row.getAttribute('data-id') || '';
            if (name.includes(searchTerm) || id.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    function clearSearch() {
        document.getElementById('searchInput').value = '';
        let rows = document.querySelectorAll('#participantsTable tbody tr');
        rows.forEach(row => row.style.display = '');
    }
</script>

</body>
</html>