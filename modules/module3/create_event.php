<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if logged in (Admin or Committee)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 1 && $_SESSION['user_role'] != 2)) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user's club if committee
$club_id = null;
$clubs = [];

if ($user_role == 1) { // Admin can see all clubs
    $stmt = $pdo->query("SELECT club_id, clubName FROM club WHERE status = 'Active'");
    $clubs = $stmt->fetchAll();
} else { // Committee gets their assigned club
    $stmt = $pdo->prepare("SELECT club_id FROM club_committee WHERE user_id = ? AND status = 'Active'");
    $stmt->execute([$user_id]);
    $club = $stmt->fetch();
    $club_id = $club['club_id'] ?? null;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    $max_participant = (int)$_POST['max_participant'];
    $selected_club_id = ($user_role == 1) ? (int)$_POST['club_id'] : $club_id;
    
    if (empty($event_title) || empty($event_date) || empty($venue) || $max_participant <= 0) {
        $error = "Please fill in all required fields.";
    } elseif (strtotime($event_date) < strtotime(date('Y-m-d'))) {
        $error = "Event date must be in the future.";
    } elseif ($selected_club_id <= 0) {
        $error = "Please select a valid club.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO event (club_id, event_title, event_description, event_date, event_time, venue, max_participant, current_participant, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'UPCOMING', ?, NOW())
            ");
            $stmt->execute([$selected_club_id, $event_title, $event_description, $event_date, $event_time, $venue, $max_participant, $_SESSION['user_name']]);
            
            $success = "Event created successfully!";
            
            // Redirect after success
            echo "<script>setTimeout(function(){ window.location.href='manage_events.php'; }, 1500);</script>";
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - FK Club System</title>
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
        
        .form-card {
            background: white; border-radius: 20px; padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto;
        }
        .form-header { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--umpsa-gold); }
        .form-header h3 { color: var(--umpsa-blue); margin: 0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group label i { color: var(--umpsa-gold); margin-right: 5px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: var(--umpsa-gold); box-shadow: 0 0 0 3px rgba(253,184,19,0.1);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-save { background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 8px; cursor: pointer; font-size: 16px; }
        .btn-save:hover { background: #218838; }
        .btn-cancel { background: #6c757d; color: white; border: none; padding: 12px 30px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .required:after { content: " *"; color: red; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
       <img src="../../assets/images/logo.png" alt="Logo" style="width: 50px; height: auto; margin-bottom: 10px;">
    <h4>FK Club System</h4>
    <p>Faculty of Computing</p></div>
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
        
    <?php else: // COMMITTEE SIDEBAR ?>
        <a href="../module1/dashboard_committee.php">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module2/club_dashboard_committee.php">
            <i class="fas fa-building"></i> <span>My Club</span>
        </a>
        <a href="manage_events.php" class="active">
            <i class="fas fa-calendar-alt"></i> <span>Manage Events</span>
        </a>
        <a href="../module4/attendance_management.php">
            <i class="fas fa-qrcode"></i> <span>Record Attendance</span>
        </a>
        <a href="../module4/attendance_dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> <span>Attendance Dashboard</span>
        </a>
        <a href="../module4/reports_dashboard.php">
            <i class="fas fa-file-alt"></i> <span>Reports</span>
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
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
            <span class="badge-role"><?php echo $user_role == 1 ? 'Administrator' : 'Committee'; ?></span>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-calendar-plus"></i> Create New Event</h3>
            <p class="text-muted mt-2">Fill in the event details below</p>
        </div>

        <?php if ($success): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?> Redirecting...</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php if ($user_role == 1): ?>
            <div class="form-group">
                <label class="required"><i class="fas fa-building"></i> Select Club</label>
                <select name="club_id" required>
                    <option value="">-- Select Club --</option>
                    <?php foreach ($clubs as $club): ?>
                        <option value="<?php echo $club['club_id']; ?>"><?php echo htmlspecialchars($club['clubName']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="required"><i class="fas fa-heading"></i> Event Title</label>
                <input type="text" name="event_title" placeholder="e.g., Coding Workshop 2024" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Event Description</label>
                <textarea name="event_description" rows="4" placeholder="Describe the event..."></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="required"><i class="fas fa-calendar-day"></i> Event Date</label>
                    <input type="date" name="event_date" required>
                </div>
                <div class="form-group">
                    <label class="required"><i class="fas fa-clock"></i> Event Time</label>
                    <input type="time" name="event_time" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="required"><i class="fas fa-location-dot"></i> Venue</label>
                    <input type="text" name="venue" placeholder="e.g., DK1, Computer Lab, Online (Zoom)" required>
                </div>
                <div class="form-group">
                    <label class="required"><i class="fas fa-users"></i> Maximum Participants</label>
                    <input type="number" name="max_participant" min="1" value="50" required>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Create Event</button>
                <a href="manage_events.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>