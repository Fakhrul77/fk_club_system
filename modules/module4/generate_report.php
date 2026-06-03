<?php
session_start();
require_once '../../includes/db_connection.php';
require_once '../../includes/field_map.php';
require_once '../../includes/helpers.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$eventDateCol = resolveColumn($pdo, 'event', ['eventDate','event_date','event_date_time','eventdate']);
$eventTitleCol = resolveColumn($pdo, 'event', ['eventTitle','event_title','title']);
$matrixCol = resolveColumn($pdo, 'users', ['matrix_number','matrix','matrix_no','matric_number','student_matrix']);
$eventVenueCol = resolveColumn($pdo, 'event', ['eventVenue','venue','location','event_venue']);

$stmt = $pdo->prepare(
    "SELECT DISTINCT e.event_id, e.`$eventTitleCol` AS eventTitle, c.clubName, c.club_id"
  . " FROM event e JOIN club c ON e.club_id = c.club_id"
  . " ORDER BY e.`$eventDateCol` DESC"
);
$stmt->execute();
$events_list = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT club_id, clubName FROM club WHERE status = 'Active' ORDER BY clubName");
$stmt->execute();
$clubs_list = $stmt->fetchAll();

$report_data      = [];
$report_preview   = false;
$error            = '';
$show_checkin     = true;
$show_points      = true;
$show_recognition = true;

$post_report_type   = 'by_event';
$post_event_id      = '';
$post_club_id       = '';
$post_start_date    = '';
$post_end_date      = '';
$post_student_matrix = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_report_type    = $_POST['report_type']     ?? 'by_event';
    $post_event_id       = $_POST['event_id']        ?? '';
    $post_club_id        = $_POST['club_id']         ?? '';
    $post_start_date     = $_POST['start_date']      ?? '';
    $post_end_date       = $_POST['end_date']        ?? '';
    $post_student_matrix = $_POST['student_matrix']  ?? '';

    $show_checkin     = isset($_POST['field_checkin']);
    $show_points      = isset($_POST['field_points']);
    $show_recognition = isset($_POST['field_recognition']);

    $valid = match($post_report_type) {
        'by_event'   => !empty($post_event_id),
        'by_club'    => !empty($post_club_id),
        'by_date'    => !empty($post_start_date) && !empty($post_end_date),
        'by_student' => !empty($post_student_matrix),
        default      => false
    };

    if (!$valid) {
        $error = "Please fill in the required filter for the selected report type.";
    } else {
        $query = "SELECT a.*, er.*, u.*, e.`$eventTitleCol` AS eventTitle, e.`$eventDateCol` AS eventDate,"
               . " e.`$eventVenueCol` AS eventVenue, c.clubName"
               . " FROM attendance a"
               . " JOIN event_registration er ON a.registration_id = er.registration_id"
               . " JOIN users u ON er.user_id = u.user_id"
               . " JOIN event e ON a.event_id = e.event_id"
               . " JOIN club c ON e.club_id = c.club_id"
               . " WHERE 1=1";
        $params = [];

        if ($post_report_type === 'by_event') {
            $query .= " AND e.event_id = ?"; $params[] = $post_event_id;
        } elseif ($post_report_type === 'by_club') {
            $query .= " AND c.club_id = ?";  $params[] = $post_club_id;
        } elseif ($post_report_type === 'by_date') {
            $query .= " AND DATE(e.`$eventDateCol`) BETWEEN ? AND ?";
            $params[] = $post_start_date; $params[] = $post_end_date;
        } elseif ($post_report_type === 'by_student') {
            if ($matrixCol) {
                $query .= " AND u.`$matrixCol` = ?"; $params[] = $post_student_matrix;
            } else {
                $query .= " AND u.name = ?"; $params[] = $post_student_matrix;
            }
        }
        $query .= " ORDER BY e.`$eventDateCol` DESC, u.name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();

        foreach ($report_data as &$rd) {
            if (!isset($rd['matrix_number']))
                $rd['matrix_number'] = ($matrixCol && isset($rd[$matrixCol])) ? $rd[$matrixCol] : '';
        }
        unset($rd);
        $report_preview = true;

        // Log report generation
        try {
            $ls = $pdo->prepare("INSERT INTO report_log (user_id, report_type, generated_at) VALUES (?, ?, NOW())");
            $ls->execute([$user_id, $post_report_type]);
        } catch (Exception $e) { }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report - FK Club System</title>
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
        .logout-btn { background: #dc3545; color: white; padding: 8px 20px; border-radius: 8px; text-decoration: none; cursor: pointer; }
        .logout-btn:hover { background: #c82333; }
        
        .page-title { color: var(--umpsa-blue); font-weight: 700; margin-bottom: 24px; }
        .page-title i { color: var(--umpsa-gold); margin-right: 8px; }
        
        .sec-card { background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 24px; overflow: hidden; }
        .sec-head { padding: 14px 20px; background: var(--umpsa-blue); color: white; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .sec-head i { color: var(--umpsa-gold); }
        .sec-body { padding: 20px; }
        
        .form-label { font-weight: 500; font-size: 13px; color: #444; }
        .form-control:focus, .form-select:focus { border-color: var(--umpsa-gold); box-shadow: 0 0 0 3px rgba(253,184,19,.15); }
        
        .btn-umpsa { background: var(--umpsa-blue); color: white; border: none; padding: 8px 20px; border-radius: 8px; }
        .btn-umpsa:hover { background: var(--umpsa-dark-blue); color: white; }
        .btn-gold { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); border: none; font-weight: 600; padding: 8px 20px; border-radius: 8px; }
        .btn-gold:hover { background: #e0a800; color: var(--umpsa-dark-blue); }
        
        .filter-panel { display: none; }
        .filter-panel.active { display: block; }
        
        .rpt-table thead th { background: var(--umpsa-blue); color: white; font-weight: 600; border: none; padding: 11px 13px; font-size: 13px; }
        .rpt-table tbody td { padding: 10px 13px; border-bottom: 1px solid #eef; vertical-align: middle; font-size: 13px; }
        .rpt-table tbody tr:hover { background: var(--umpsa-light-blue); }
        
        .summary-bar { background: var(--umpsa-light-blue); border-radius: 10px; padding: 14px 20px; display: flex; gap: 30px; flex-wrap: wrap; align-items: center; margin-top: 16px; font-size: 13px; }
        .summary-bar strong { color: var(--umpsa-blue); }
        
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
        
        @media print {
            .sidebar, .top-nav, .sec-head, .btn-umpsa, .btn-gold, .btn-outline-secondary, .modal-overlay, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .sec-card { box-shadow: none; border: 1px solid #ddd; }
            .rpt-table thead th { background: #003B5C !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .rpt-table tbody tr:nth-child(even) { background: #f5f8fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge { background: none !important; color: #000 !important; font-weight: 600; }
            @page { margin: 15mm; }
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-header p, .sidebar-menu a span { display: none; }
            .main-content { margin-left: 70px; }
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
            <a href="../module3/event_dashboard.php">
               <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
            </a>
            <a href="../module3/manage_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Events</span>
            </a>
            <a href="attendance_dashboard.php">
                <i class="fas fa-chart-bar"></i> <span>Attendance</span>
            </a>
            <a href="generate_report.php" class="active">
                <i class="fas fa-file-alt"></i> <span>Reports</span>
            </a>
            <a href="../module1/profile.php">
                <i class="fas fa-user"></i> <span>Profile</span>
            </a>
        <?php else: ?>
            <a href="../module1/dashboard_committee.php">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <a href="../module2/club_dashboard_committee.php">
                <i class="fas fa-building"></i> <span>My Club</span>
            </a>
            <a href="../module3/event_dashboard.php">
               <i class="fas fa-chart-line"></i> <span>Event Dashboard</span>
            </a>
            <a href="../module3/manage_events.php">
                <i class="fas fa-calendar-alt"></i> <span>Manage Events</span>
            </a>
            <a href="attendance_management.php">
                <i class="fas fa-qrcode"></i> <span>Record Attendance</span>
            </a>
            <a href="attendance_dashboard.php">
                <i class="fas fa-chart-bar"></i> <span>Attendance Dashboard</span>
            </a>
            <a href="generate_report.php" class="active">
                <i class="fas fa-file-alt"></i> <span>Reports</span>
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
            <span class="badge-role"><?php echo $user_role == 1 ? 'Administrator' : 'Committee'; ?></span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h2 class="page-title"><i class="fas fa-file-pdf"></i> Generate Attendance Report</h2>

    <?php if ($error): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="reportForm">
        <!-- Report Filters -->
        <div class="sec-card">
            <div class="sec-head"><i class="fas fa-filter"></i> Report Filters</div>
            <div class="sec-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" id="reportType" class="form-select" onchange="switchFilter()">
                            <option value="by_event"   <?php echo $post_report_type=='by_event'  ?'selected':''; ?>>By Event</option>
                            <option value="by_club"    <?php echo $post_report_type=='by_club'   ?'selected':''; ?>>By Club</option>
                            <option value="by_date"    <?php echo $post_report_type=='by_date'   ?'selected':''; ?>>By Date Range</option>
                            <option value="by_student" <?php echo $post_report_type=='by_student'?'selected':''; ?>>By Student</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="panelWrap">
                        <div id="panel_by_event" class="filter-panel">
                            <label class="form-label">Select Event</label>
                            <select name="event_id" class="form-select">
                                <option value="">-- Choose Event --</option>
                                <?php foreach ($events_list as $ev): ?>
                                    <option value="<?php echo $ev['event_id']; ?>" <?php echo ($post_event_id == $ev['event_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ev['eventTitle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="panel_by_club" class="filter-panel">
                            <label class="form-label">Select Club</label>
                            <select name="club_id" class="form-select">
                                <option value="">-- Choose Club --</option>
                                <?php foreach ($clubs_list as $cl): ?>
                                    <option value="<?php echo $cl['club_id']; ?>" <?php echo ($post_club_id == $cl['club_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cl['clubName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="panel_by_date" class="filter-panel">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($post_start_date); ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($post_end_date); ?>">
                                </div>
                            </div>
                        </div>
                        <div id="panel_by_student" class="filter-panel">
                            <label class="form-label">Student Matrix Number</label>
                            <input type="text" name="student_matrix" class="form-control" placeholder="e.g. CA21088" value="<?php echo htmlspecialchars($post_student_matrix); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-umpsa w-100">
                            <i class="fas fa-search me-1"></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Output Fields -->
        <div class="sec-card">
            <div class="sec-head"><i class="fas fa-columns"></i> Output Fields</div>
            <div class="sec-body">
                <div class="d-flex gap-4 flex-wrap">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" disabled checked id="fStatus">
                        <label class="form-check-label" for="fStatus">Attendance Status <small class="text-muted">(always)</small></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="field_checkin" id="fCheckin" value="1" <?php echo $show_checkin ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="fCheckin">Check-in Time</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="field_points" id="fPoints" value="1" <?php echo $show_points ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="fPoints">Points Earned</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="field_recognition" id="fRec" value="1" <?php echo $show_recognition ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="fRec">Recognition Level</label>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Report Preview -->
    <?php if ($report_preview): ?>
        <?php if (empty($report_data)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> No records match the selected filters.
            </div>
        <?php else:
            $r_present = 0;
            $r_absent = 0;
            foreach ($report_data as $r) {
                if ($r['attendanceStatus'] == 'Present') $r_present++;
                elseif ($r['attendanceStatus'] == 'Absent') $r_absent++;
            }
            $r_total = count($report_data);
            $r_rate = $r_total > 0 ? round(($r_present / $r_total) * 100) : 0;
        ?>
        <div class="sec-card" id="reportPreviewCard">
            <div class="sec-head">
                <i class="fas fa-table"></i> Report Preview
                <span class="ms-auto badge" style="background:var(--umpsa-gold);color:var(--umpsa-dark-blue);"><?php echo $r_total; ?> records</span>
            </div>
            <div class="sec-body">
                <div id="printable-area">
                    <!-- Print Header -->
                    <div class="print-header" style="display:none; text-align:center; border-bottom:2px solid #003B5C; padding-bottom:12px; margin-bottom:16px;">
                        <h3 style="color:#003B5C; margin:0;">FK Club System</h3>
                        <p style="color:#555; margin:4px 0 0;">Faculty of Computing, UMPSA</p>
                        <h4 style="margin:10px 0 4px;">Attendance Report</h4>
                        <p style="font-size:12px; color:#666;">
                            Generated: <?php echo date('d M Y, h:i A'); ?> &nbsp;|&nbsp;
                            By: <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
                        </p>
                    </div>

                    <div class="table-responsive">
                        <table class="table rpt-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Club</th>
                                    <th>Status</th>
                                    <?php if ($show_checkin): ?><th>Check-in Time</th><?php endif; ?>
                                    <?php if ($show_points): ?><th>Points</th><?php endif; ?>
                                    <?php if ($show_recognition): ?><th>Recognition</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $i => $row):
                                    if ($row['attendanceStatus'] == 'Present') $sc = 'success';
                                    elseif ($row['attendanceStatus'] == 'Absent') $sc = 'danger';
                                    else $sc = 'warning';
                                    
                                    $eventPoints = 0;
                                    if ($show_points || $show_recognition) {
                                        $ps = $pdo->prepare("SELECT pointsEarned FROM activity_points WHERE user_id = ? AND event_id = ?");
                                        $ps->execute([$row['user_id'], $row['event_id']]);
                                        $pr = $ps->fetch();
                                        $eventPoints = $pr['pointsEarned'] ?? 0;
                                    }
                                ?>
                                <tr>
                                    <td class="text-muted"><?php echo $i + 1; ?></td>
                                    <td><?php echo htmlspecialchars($row['eventTitle']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['eventDate'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['studentId']); ?></td>
                                    <td><?php echo htmlspecialchars($row['clubName']); ?></td>
                                    <td><span class="badge bg-<?php echo $sc; ?>"><?php echo $row['attendanceStatus']; ?></span></td>
                                    <?php if ($show_checkin): ?>
                                        <td><?php echo $row['checkInTime'] ? date('H:i', strtotime($row['checkInTime'])) : '—'; ?></td>
                                    <?php endif; ?>
                                    <?php if ($show_points): ?>
                                        <td><?php echo $eventPoints; ?> pts</td>
                                    <?php endif; ?>
                                    <?php if ($show_recognition): ?>
                                        <td><?php echo htmlspecialchars($row['recognition_level'] ?? '—'); ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary Bar -->
                    <div class="summary-bar">
                        <span><strong>Total Records:</strong> <?php echo $r_total; ?></span>
                        <span><strong>Present:</strong> <span class="badge bg-success"><?php echo $r_present; ?></span></span>
                        <span><strong>Absent:</strong> <span class="badge bg-danger"><?php echo $r_absent; ?></span></span>
                        <span><strong>Attendance Rate:</strong> <span class="badge bg-primary"><?php echo $r_rate; ?>%</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Button -->
        <div class="text-center mb-4 no-print">
            <button onclick="printReport()" class="btn btn-gold px-5 py-2">
                <i class="fas fa-file-pdf me-2"></i> Export / Print PDF
            </button>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Select filters above and click <strong>Generate Report</strong> to preview the data.
        </div>
    <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function switchFilter() {
        const type = document.getElementById('reportType').value;
        document.querySelectorAll('.filter-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('panel_' + type);
        if (panel) panel.classList.add('active');
    }
    
    function printReport() { window.print(); }
    
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
    
    // Initialize on load
    switchFilter();
</script>

</body>
</html>
<?php $pdo = null; ?>