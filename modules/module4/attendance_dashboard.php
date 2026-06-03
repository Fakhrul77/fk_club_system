<?php
session_start();
require_once '../../includes/db_connection.php';
require_once '../../includes/field_map.php';
require_once '../../includes/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle delete (admin and committee only)
$delete_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attendance') {
    if (in_array($user_role, [1, 2])) {
        $del_id = (int)($_POST['attendance_id'] ?? 0);
        if ($del_id > 0) {
            $stmt = $pdo->prepare(
                "SELECT a.event_id, er.user_id"
              . " FROM attendance a"
              . " JOIN event_registration er ON a.registration_id = er.registration_id"
              . " WHERE a.attendance_id = ?"
            );
            $stmt->execute([$del_id]);
            $att_row = $stmt->fetch();

            $stmt = $pdo->prepare("DELETE FROM attendance WHERE attendance_id = ?");
            $stmt->execute([$del_id]);

            if ($stmt->rowCount() > 0) {
                $delete_msg = 'success';
                if ($att_row) {
                    $calculator = new PointsCalculator($pdo);
                    $check = $pdo->prepare(
                        "SELECT COUNT(*) FROM attendance a"
                      . " JOIN event_registration er ON a.registration_id = er.registration_id"
                      . " WHERE er.user_id = ? AND a.event_id = ? AND a.attendanceStatus = 'Present'"
                    );
                    $check->execute([$att_row['user_id'], $att_row['event_id']]);
                    if ((int)$check->fetchColumn() === 0) {
                        $calculator->removePoints($att_row['user_id'], $att_row['event_id']);
                    }
                    $new_total = $calculator->getTotalPoints($att_row['user_id']);
                    $recognizer = new RecognitionLevelDeterminer($pdo);
                    $recognizer->updateRecognitionLevel($att_row['user_id'], $new_total);
                }
            } else {
                $delete_msg = 'notfound';
            }
        }
    }
    $qs = http_build_query(array_filter([
        'event'   => $_GET['event']   ?? '',
        'date'    => $_GET['date']    ?? '',
        'club'    => $_GET['club']    ?? '',
        'student' => $_GET['student'] ?? '',
        'deleted' => $delete_msg,
    ]));
    header("Location: attendance_dashboard.php" . ($qs ? "?$qs" : ''));
    exit();
}

if (isset($_GET['deleted'])) {
    $delete_msg = $_GET['deleted'];
}

$matrixCol = resolveColumn($pdo, 'users', ['matrix_number','matrix','matrix_no','matric_number','student_matrix']);
$eventDateCol = resolveColumn($pdo, 'event', ['eventDate','event_date','event_date_time','eventdate']);
$eventTitleCol = resolveColumn($pdo, 'event', ['eventTitle','event_title','title']);

$filter_event   = $_GET['event']   ?? null;
$filter_date    = $_GET['date']    ?? null;
$filter_club    = $_GET['club']    ?? null;
$filter_student = $_GET['student'] ?? null;

$events_list = [];
$clubs_list  = [];
$attendance_records = [];

if ($user_role == 1 || $user_role == 2) {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT e.event_id, e.`$eventTitleCol` AS eventTitle, c.clubName"
      . " FROM event e JOIN club c ON e.club_id = c.club_id"
      . " WHERE e.status IN ('COMPLETED','ONGOING','UPCOMING')"
      . " ORDER BY e.`$eventDateCol` DESC"
    );
    $stmt->execute();
    $events_list = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT club_id, clubName FROM club WHERE status = 'Active' ORDER BY clubName");
    $stmt->execute();
    $clubs_list = $stmt->fetchAll();

    $query = "SELECT a.*, er.*, u.*, e.`$eventTitleCol` AS eventTitle, e.`$eventDateCol` AS eventDate, c.clubName"
           . " FROM attendance a"
           . " JOIN event_registration er ON a.registration_id = er.registration_id"
           . " JOIN users u ON er.user_id = u.user_id"
           . " JOIN event e ON a.event_id = e.event_id"
           . " JOIN club c ON e.club_id = c.club_id"
           . " WHERE 1=1";
    $params = [];

    if ($filter_event) { $query .= " AND e.event_id = ?"; $params[] = $filter_event; }
    if ($filter_date)  { $query .= " AND DATE(e.`$eventDateCol`) = ?"; $params[] = $filter_date; }
    if ($filter_club)  { $query .= " AND c.club_id = ?"; $params[] = $filter_club; }
    if ($filter_student) {
    $query .= " AND (u.name LIKE ? OR u.studentId LIKE ?)";
    $params[] = "%$filter_student%";
    $params[] = "%$filter_student%";
}
    $query .= " ORDER BY e.`$eventDateCol` DESC, u.name ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();

    foreach ($attendance_records as &$ar) {
        if (!isset($ar['matrix_number']))
            $ar['matrix_number'] = ($matrixCol && isset($ar[$matrixCol])) ? $ar[$matrixCol] : '';
    }
    unset($ar);

} else {
    $query = "SELECT a.*, er.*, u.*, e.`$eventTitleCol` AS eventTitle, e.`$eventDateCol` AS eventDate, c.clubName"
           . " FROM attendance a"
           . " JOIN event_registration er ON a.registration_id = er.registration_id"
           . " JOIN users u ON er.user_id = u.user_id"
           . " JOIN event e ON a.event_id = e.event_id"
           . " JOIN club c ON e.club_id = c.club_id"
           . " WHERE u.user_id = ?"
           . " ORDER BY e.`$eventDateCol` DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $attendance_records = $stmt->fetchAll();

    foreach ($attendance_records as &$ar) {
        if (!isset($ar['matrix_number']))
            $ar['matrix_number'] = ($matrixCol && isset($ar[$matrixCol])) ? $ar[$matrixCol] : '';
    }
    unset($ar);
}

// Stats
$total_present = 0;
$total_absent  = 0;
$total_excused = 0;
foreach ($attendance_records as $r) {
    if ($r['attendanceStatus'] == 'Present')      $total_present++;
    elseif ($r['attendanceStatus'] == 'Absent')   $total_absent++;
    elseif ($r['attendanceStatus'] == 'Excused')  $total_excused++;
}
$total_records = count($attendance_records);
$att_rate = $total_records > 0 ? round(($total_present / $total_records) * 100) : 0;

// Chart data
$chart_labels  = [];
$chart_present = [];
$chart_absent  = [];
$event_groups  = [];
foreach ($attendance_records as $r) {
    $key = $r['eventTitle'];
    if (!isset($event_groups[$key])) $event_groups[$key] = ['present'=>0,'absent'=>0];
    if ($r['attendanceStatus'] == 'Present')    $event_groups[$key]['present']++;
    elseif ($r['attendanceStatus'] == 'Absent') $event_groups[$key]['absent']++;
}
$event_groups_slice = array_slice($event_groups, 0, 8, true);
foreach ($event_groups_slice as $label => $counts) {
    $chart_labels[]  = $label;
    $chart_present[] = $counts['present'];
    $chart_absent[]  = $counts['absent'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

/* Modal Overlay */
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
    width: 400px;
    text-align: center;
}

.modal-content i { font-size: 50px; margin-bottom: 15px; }
.modal-content h4 { margin-bottom: 15px; }
.modal-content p { margin-bottom: 20px; color: #666; }

.modal-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.modal-btn-confirm {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    cursor: pointer;
}

.modal-btn-confirm:hover { background: #c82333; }

.modal-btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    cursor: pointer;
}

.modal-btn-cancel:hover { background: #5a6268; }

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
        
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-info h3 { font-size: 28px; font-weight: bold; color: var(--umpsa-blue); margin-bottom: 5px; }
        .stat-info p { color: #666; margin: 0; font-size: 13px; }
        .stat-icon { width: 50px; height: 50px; background: rgba(0,59,92,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 28px; color: var(--umpsa-blue); }
        
        .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .chart-card h5 { color: var(--umpsa-blue); margin-bottom: 20px; font-weight: 600; }
        .chart-card h5 i { color: var(--umpsa-gold); margin-right: 8px; }
        
        .sec-card { background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 24px; overflow: hidden; }
        .sec-head { padding: 14px 20px; background: var(--umpsa-blue); color: white; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .sec-head i { color: var(--umpsa-gold); }
        .sec-body { padding: 20px; }
        
        .form-label { font-weight: 500; font-size: 13px; color: #444; }
        .form-control:focus, .form-select:focus { border-color: var(--umpsa-gold); box-shadow: 0 0 0 3px rgba(253,184,19,.15); }
        .btn-umpsa { background: var(--umpsa-blue); color: white; border: none; padding: 8px 20px; border-radius: 8px; }
        .btn-umpsa:hover { background: var(--umpsa-dark-blue); color: white; }
        
        .att-table thead th { background: var(--umpsa-blue); color: white; font-weight: 600; border: none; padding: 12px 14px; font-size: 13px; }
        .att-table tbody td { padding: 11px 14px; border-bottom: 1px solid #eef; vertical-align: middle; font-size: 13px; }
        .att-table tbody tr:hover { background: var(--umpsa-light-blue); }
        
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
        .modal-buttons { display: flex; gap: 15px; justify-content: center; margin-top: 20px; }
        .modal-btn-confirm { background: #dc3545; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; }
        .modal-btn-cancel { background: #6c757d; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-header p, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="../../assets/images/logo.png" alt="Logo" style="width: 50px; height: auto; margin-bottom: 10px;">
        <h4>FK Club System</h4>
        <p>Faculty of Computing</p>
    </div>
    <div class="sidebar-menu">
        <?php if ($user_role == 1): ?>
            <a href="../module1/dashboard_admin.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module1/manage_users.php">
                <i class="fas fa-users"></i> <span>Manage Users</span>
            </a>
            <a href="../module2/club_dashboard_admin.php">
                <i class="fas fa-building"></i> <span>Manage Clubs</span>
            </a>
            <a href="../module3/manage_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Events</span>
            </a>
            <a href="attendance_dashboard.php" class="active">
                <i class="fas fa-chart-bar"></i> <span>Attendance</span>
            </a>
            <a href="generate_report.php">
                <i class="fas fa-file-alt"></i> <span>Reports</span>
            </a>
            <a href="../module1/profile.php">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
        <?php elseif ($user_role == 2): ?>
            <a href="../module1/dashboard_committee.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module2/club_dashboard_committee.php">
                <i class="fas fa-building"></i> <span>My Club</span>
            </a>
            <a href="../module3/manage_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Manage Events</span>
            </a>
            <a href="attendance_management.php">
                <i class="fas fa-qrcode"></i> <span>Record Attendance</span>
            </a>
            <a href="attendance_dashboard.php" class="active">
                <i class="fas fa-chart-bar"></i> <span>Attendance Dashboard</span>
            </a>
            <a href="generate_report.php">
                <i class="fas fa-file-alt"></i> <span>Reports</span>
            </a>
            <a href="../module1/profile.php">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
        <?php else: ?>
            <a href="../module1/dashboard_student.php">
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
            <a href="my_points_recognition.php">
                <i class="fas fa-star"></i> <span>My Points</span>
            </a>
            <a href="attendance_dashboard.php" class="active">
                <i class="fas fa-chart-line"></i> <span>Attendance History</span>
            </a>
            <a href="../module1/profile.php">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            <span class="badge-role"><?php echo $user_role == 1 ? 'Administrator' : ($user_role == 2 ? 'Committee' : 'Student'); ?></span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h2 class="mb-4" style="color: var(--umpsa-blue);"><i class="fas fa-chart-bar"></i> Attendance Dashboard</h2>

    <?php if ($delete_msg === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> Attendance record deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($delete_msg === 'notfound'): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i> Record not found or already deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $total_present; ?></h3><p>Total Present</p></div>
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: #28a745;"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $total_absent; ?></h3><p>Total Absent</p></div>
            <div class="stat-icon"><i class="fas fa-times-circle" style="color: #dc3545;"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $att_rate; ?>%</h3><p>Attendance Rate</p></div>
            <div class="stat-icon"><i class="fas fa-percent" style="color: var(--umpsa-blue);"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3><?php echo $total_records; ?></h3><p>Total Records</p></div>
            <div class="stat-icon"><i class="fas fa-database" style="color: var(--umpsa-gold);"></i></div>
        </div>
    </div>

    <?php if ($user_role == 1 || $user_role == 2): ?>
    <!-- Filters -->
    <div class="sec-card">
        <div class="sec-head"><i class="fas fa-filter"></i> Filters</div>
        <div class="sec-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Event</label>
                    <select name="event" class="form-select form-select-sm">
                        <option value="">-- All Events --</option>
                        <?php foreach ($events_list as $ev): ?>
                            <option value="<?php echo $ev['event_id']; ?>" <?php echo ($filter_event == $ev['event_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['eventTitle']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Club</label>
                    <select name="club" class="form-select form-select-sm">
                        <option value="">-- All Clubs --</option>
                        <?php foreach ($clubs_list as $cl): ?>
                            <option value="<?php echo $cl['club_id']; ?>" <?php echo ($filter_club == $cl['club_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cl['clubName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Student (Name / Matrix)</label>
                    <input type="text" name="student" class="form-control form-control-sm" placeholder="Search..." value="<?php echo htmlspecialchars($filter_student ?? ''); ?>">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-umpsa btn-sm"><i class="fas fa-search"></i> Apply Filters</button>
                    <a href="attendance_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts -->
    <?php if (!empty($attendance_records)): ?>
    <div class="charts-row">
        <div class="chart-card">
            <h5><i class="fas fa-chart-bar"></i> Attendance Trend by Event</h5>
            <canvas id="trendChart" style="height: 250px;"></canvas>
        </div>
        <div class="chart-card">
            <h5><i class="fas fa-chart-pie"></i> Overall Attendance Distribution</h5>
            <canvas id="distChart" style="height: 250px;"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Attendance Table -->
    <div class="sec-card">
        <div class="sec-head">
            <i class="fas fa-table"></i> Attendance Records
            <?php if ($total_records > 0): ?>
                <span class="ms-auto badge" style="background:var(--umpsa-gold);color:var(--umpsa-dark-blue);"><?php echo $total_records; ?> records</span>
            <?php endif; ?>
        </div>
        <div class="sec-body p-0">
            <?php if (empty($attendance_records)): ?>
                <div class="p-4 text-center">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo ($user_role == 3) ? 'No attendance records yet. Attend events to build your history!' : 'No attendance records match the selected filters.'; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table att-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Event Name</th>
                                <th>Date</th>
                                <th>Club</th>
                                <?php if ($user_role != 3): ?><th>Student Name</th><?php endif; ?>
                                <th>Student ID</th>
                                <th>Status</th>
                                <th>Check-in Time</th>
                                <?php if ($user_role != 3): ?><th>Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $i => $record):
                                if ($record['attendanceStatus'] == 'Present') $sc = 'success';
                                elseif ($record['attendanceStatus'] == 'Absent') $sc = 'danger';
                                else $sc = 'warning';
                            ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($record['eventTitle']); ?></td>
                                <td><?php echo date('d M Y', strtotime($record['eventDate'])); ?></td>
                                <td><?php echo htmlspecialchars($record['clubName']); ?></td>
                                <?php if ($user_role != 3): ?>
                                    <td><?php echo htmlspecialchars($record['name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($record['studentId']); ?></td>
                                <td><span class="badge bg-<?php echo $sc; ?>"><?php echo $record['attendanceStatus']; ?></span></td>
                                <td><?php echo $record['checkInTime'] ? date('H:i', strtotime($record['checkInTime'])) : '—'; ?></td>
                                <?php if ($user_role != 3): ?>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteAttendanceModal(<?php echo $record['attendance_id']; ?>, '<?php echo htmlspecialchars($record['eventTitle']); ?>', '<?php echo htmlspecialchars($record['name']); ?>')" title="Delete record">
    <i class="fas fa-trash-alt"></i>
</button>

<!-- Hidden form for deletion -->
<form id="deleteAttendanceForm" method="POST">
    <input type="hidden" name="action" value="delete_attendance">
    <input type="hidden" name="attendance_id" id="delete_attendance_id">
</form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Logout Modal -->
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

<!-- Delete Attendance Confirmation Modal -->
<div id="deleteAttendanceModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-exclamation-triangle" style="font-size: 50px; color: #dc3545;"></i>
        <h4>Delete Attendance Record</h4>
        <p id="deleteAttendanceMessage">Are you sure you want to delete this attendance record?</p>
        <p class="text-muted small">This will also remove any points awarded for this attendance.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteAttendanceBtn" class="modal-btn-confirm">Yes, Delete Record</button>
            <button id="cancelDeleteAttendanceBtn" class="modal-btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Logout functionality
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
        const modal = document.getElementById('logoutModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };

    <?php if (!empty($attendance_records)): ?>
    // Attendance Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Present',
                    data: <?php echo json_encode($chart_present); ?>,
                    backgroundColor: '#28a745',
                    borderRadius: 6
                },
                {
                    label: 'Absent',
                    data: <?php echo json_encode($chart_absent); ?>,
                    backgroundColor: '#dc3545',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 12 } } } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Distribution Pie Chart
    const distCtx = document.getElementById('distChart').getContext('2d');
    new Chart(distCtx, {
        type: 'pie',
        data: {
            labels: ['Present', 'Absent', 'Excused'],
            datasets: [{
                data: [<?php echo $total_present; ?>, <?php echo $total_absent; ?>, <?php echo $total_excused; ?>],
                backgroundColor: ['#28a745', '#dc3545', '#FDB813'],
                borderWidth: 2, borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 12 } } } }
        }
    });
    <?php endif; ?>
</script>

<script>
// Delete Attendance Modal Variables
let deleteAttendanceId = null;
let deleteEventName = '';
let deleteStudentName = '';

// Open Delete Attendance Modal
function openDeleteAttendanceModal(attendanceId, eventName, studentName) {
    deleteAttendanceId = attendanceId;
    deleteEventName = eventName;
    deleteStudentName = studentName;
    document.getElementById('deleteAttendanceMessage').innerHTML = `Are you sure you want to delete the attendance record for <strong>${studentName}</strong> at event <strong>${eventName}</strong>?`;
    document.getElementById('deleteAttendanceModal').style.display = 'flex';
}

// Confirm Delete Attendance
document.getElementById('confirmDeleteAttendanceBtn').addEventListener('click', function() {
    if (deleteAttendanceId) {
        document.getElementById('delete_attendance_id').value = deleteAttendanceId;
        document.getElementById('deleteAttendanceForm').submit();
    }
});

// Cancel Delete Attendance
document.getElementById('cancelDeleteAttendanceBtn').addEventListener('click', function() {
    document.getElementById('deleteAttendanceModal').style.display = 'none';
    deleteAttendanceId = null;
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deleteAttendanceModal');
    if (event.target == modal) {
        modal.style.display = 'none';
        deleteAttendanceId = null;
    }
}
</script>

</body>
</html>
<?php $pdo = null; ?>