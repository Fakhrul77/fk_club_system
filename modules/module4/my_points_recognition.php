<?php
session_start();
require_once '../../includes/db_connection.php';
require_once '../../includes/helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// Use your actual column names
$eventTitleCol = 'event_title';
$eventDateCol = 'event_date';

// Attended events (Present only) joined with points
$sql = "SELECT a.*, e.`$eventTitleCol` AS eventTitle, e.`$eventDateCol` AS eventDate,"
     . " c.clubName, COALESCE(ap.pointsEarned, 0) AS pointsEarned"
     . " FROM attendance a"
     . " JOIN event_registration er ON a.registration_id = er.registration_id"
     . " JOIN event e ON a.event_id = e.event_id"
     . " JOIN club c ON e.club_id = c.club_id"
     . " LEFT JOIN activity_points ap ON ap.user_id = er.user_id AND ap.event_id = a.event_id"
     . " WHERE er.user_id = ? AND a.attendanceStatus = 'Present'"
     . " ORDER BY e.`$eventDateCol` ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$attended_events = $stmt->fetchAll();

// Total points
$total_points = 0;
foreach ($attended_events as $r) $total_points += (int)$r['pointsEarned'];

// Recognition levels (Table B)
$levels = [
    ['name' => 'Warning', 'min' => 0,   'max' => 19,   'icon' => '⚠️', 'color' => '#DC3545'],
    ['name' => 'Certificate Eligible', 'min' => 20,  'max' => 49,   'icon' => '📜', 'color' => '#CD7F32'],
    ['name' => 'Active Student Award', 'min' => 50,  'max' => 79,   'icon' => '🏅', 'color' => '#C0C0C0'],
    ['name' => 'Outstanding Participant', 'min' => 80, 'max' => 999, 'icon' => '🏆', 'color' => '#FFD700'],
];

$current_level = $levels[0];
$next_level = null;
foreach ($levels as $l) {
    if ($total_points >= $l['min']) $current_level = $l;
}
foreach ($levels as $l) {
    if ($l['min'] > $total_points) { $next_level = $l; break; }
}

$progress_pct = 0;
if ($next_level) {
    $progress_pct = min(100, round(($total_points / $next_level['min']) * 100));
} else {
    $progress_pct = 100;
}

// Projected from upcoming registrations
$stmt = $pdo->prepare(
    "SELECT e.eventCategory FROM event_registration er"
  . " JOIN event e ON er.event_id = e.event_id"
  . " WHERE er.user_id = ? AND er.status = 'Confirmed'"
  . " AND e.status = 'UPCOMING'"
);
$stmt->execute([$user_id]);
$upcoming = $stmt->fetchAll();

$activity_points_map = [
    'General Event' => 5, 'Workshop' => 10, 'Seminar' => 8,
    'Competition' => 15, 'Volunteer' => 12, 'Leadership' => 20, 
    'Organizing' => 25, 'Default' => 5
];

$projected_extra = 0;
foreach ($upcoming as $ue) {
    $cat = $ue['eventCategory'] ?? 'Default';
    $projected_extra += $activity_points_map[$cat] ?? $activity_points_map['Default'];
}
$projected_total = $total_points + $projected_extra;
$projected_level = $levels[0];
foreach ($levels as $l) {
    if ($projected_total >= $l['min']) $projected_level = $l;
}

// Chart data
$chart_dates = [];
$chart_points = [];
$running = 0;
foreach ($attended_events as $r) {
    $running += (int)$r['pointsEarned'];
    $chart_dates[] = date('d M Y', strtotime($r['eventDate']));
    $chart_points[] = $running;
}

$page_title = "My Points & Recognition";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Points - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --umpsa-blue: #003B5C; --umpsa-gold: #FDB813; --umpsa-dark-blue: #002147; --umpsa-light-blue: #E8F0F8; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--umpsa-light-blue); overflow-x: hidden; }
        
        /* Sidebar */
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
        
        /* Main Content */
        .main-content { margin-left: 260px; padding: 20px; }
        
        /* Top Navbar */
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
        
        /* Points Card */
        .points-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .points-big { font-size: 48px; font-weight: bold; color: var(--umpsa-blue); }
        .badge-icon { font-size: 60px; }
        .progress { height: 10px; border-radius: 10px; background: #e9ecef; }
        .progress-bar { background: var(--umpsa-gold); border-radius: 10px; }
        
        /* Level Pills */
        .level-pills { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .level-pill {
            display: flex; align-items: center; gap: 10px;
            background: white; border-radius: 12px; padding: 12px 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); font-size: 13px;
            border: 2px solid transparent; flex: 1; min-width: 130px;
        }
        .level-pill.active { border-color: var(--umpsa-gold); background: rgba(253,184,19,0.05); }
        .level-pill .pill-icon { font-size: 28px; }
        .level-pill .pill-name { font-weight: 700; color: var(--umpsa-blue); }
        .level-pill .pill-range { color: #888; font-size: 11px; }
        
        /* Projected Card */
        .proj-card {
            background: #fff9e6;
            border-left: 4px solid var(--umpsa-gold);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            font-size: 13px;
        }
        
        /* Section Cards */
        .sec-card { background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 24px; overflow: hidden; }
        .sec-head { padding: 14px 20px; background: var(--umpsa-blue); color: white; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .sec-head i { color: var(--umpsa-gold); }
        .sec-body { padding: 20px; }
        
        /* Tables */
        .pts-table { width: 100%; border-collapse: collapse; }
        .pts-table thead th { background: var(--umpsa-blue); color: white; font-weight: 600; border: none; padding: 12px 14px; font-size: 13px; }
        .pts-table tbody td { padding: 11px 14px; border-bottom: 1px solid #eef; vertical-align: middle; font-size: 13px; }
        .pts-table tbody tr:hover { background: var(--umpsa-light-blue); }
        
        /* Chart */
        .chart-wrap { position: relative; height: 280px; }
        
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
            .level-pills { flex-direction: column; }
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
        <a href="../module4/my_points_recognition.php" class="active">
            <i class="fas fa-star"></i> <span>My Points</span>
        </a>
        <a href="../module1/profile.php">
            <i class="fas fa-user"></i> <span>Profile</span>
        </a>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            <span class="badge-role">Student</span>
        </div>
        <a href="#" class="logout-btn" onclick="showLogoutConfirm()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h2 class="mb-4" style="color: var(--umpsa-blue);"><i class="fas fa-star"></i> My Points & Recognition</h2>

    <!-- Recognition Banner -->
    <div class="points-card">
        <div class="badge-icon"><?php echo $current_level['icon']; ?></div>
        <h3><?php echo $current_level['name']; ?></h3>
        <div class="points-big"><?php echo $total_points; ?> Points</div>
        
        <?php if ($next_level): ?>
            <div class="progress mt-3">
                <div class="progress-bar" style="width: <?php echo $progress_pct; ?>%"></div>
            </div>
            <small class="text-muted mt-2 d-block"><?php echo $next_level['min'] - $total_points; ?> more points to reach <?php echo $next_level['name']; ?>!</small>
        <?php else: ?>
            <div class="progress mt-3">
                <div class="progress-bar bg-success" style="width: 100%"></div>
            </div>
            <small class="text-success mt-2 d-block">🎉 Congratulations! You have reached the highest level! 🎉</small>
        <?php endif; ?>
    </div>

    <!-- Level Thresholds -->
    <div class="level-pills">
        <?php foreach ($levels as $lv): ?>
            <div class="level-pill <?php echo ($current_level['name'] === $lv['name']) ? 'active' : ''; ?>">
                <span class="pill-icon"><?php echo $lv['icon']; ?></span>
                <div>
                    <div class="pill-name"><?php echo $lv['name']; ?></div>
                    <div class="pill-range"><?php echo $lv['min']; ?>–<?php echo $lv['max'] < 999 ? $lv['max'] : '+'; ?> pts</div>
                    <?php if ($current_level['name'] === $lv['name']): ?>
                        <span class="badge bg-success" style="font-size:10px;">Current</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Projected Level -->
    <?php if ($projected_extra > 0): ?>
    <div class="proj-card">
        <i class="fas fa-bolt" style="color:var(--umpsa-gold); margin-right:6px;"></i>
        You have <strong><?php echo count($upcoming); ?></strong> upcoming event(s) worth
        <strong><?php echo $projected_extra; ?> potential points</strong>.
        If you attend all: <strong><?php echo $total_points; ?> + <?php echo $projected_extra; ?> = <?php echo $projected_total; ?> pts</strong>
        → <strong><?php echo $projected_level['icon']; ?> <?php echo $projected_level['name']; ?></strong> level!
    </div>
    <?php endif; ?>

    <!-- Event History -->
    <div class="sec-card">
        <div class="sec-head">
            <i class="fas fa-history"></i> Event History
            <span class="ms-auto badge" style="background:var(--umpsa-gold);color:var(--umpsa-dark-blue);"><?php echo count($attended_events); ?> events</span>
        </div>
        <div class="sec-body p-0">
            <?php if (empty($attended_events)): ?>
                <div class="p-4 text-center">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> No attendance records yet. Attend events to earn points!
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table pts-table mb-0">
                        <thead>
                            <tr><th>#</th><th>Event Name</th><th>Date</th><th>Club</th><th>Points</th></tr>
                        </thead>
                        <tbody>
                            <?php $display = array_reverse($attended_events); foreach ($display as $i => $r): ?>
                            <tr>
                                <td class="text-muted"><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($r['eventTitle']); ?></td>
                                <td><?php echo date('d M Y', strtotime($r['eventDate'])); ?></td>
                                <td><?php echo htmlspecialchars($r['clubName']); ?></td>
                                <td><?php if ($r['pointsEarned'] > 0): ?><span class="badge" style="background:var(--umpsa-gold);color:var(--umpsa-dark-blue);">+<?php echo $r['pointsEarned']; ?> pts</span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Points Progress Chart -->
    <?php if (!empty($attended_events)): ?>
    <div class="sec-card">
        <div class="sec-head"><i class="fas fa-chart-line"></i> Points Progress</div>
        <div class="sec-body">
            <div class="chart-wrap"><canvas id="progressChart"></canvas></div>
        </div>
    </div>
    <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Progress Chart
    <?php if (!empty($attended_events)): ?>
    const ctx = document.getElementById('progressChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_dates); ?>,
            datasets: [{
                label: 'Cumulative Points',
                data: <?php echo json_encode($chart_points); ?>,
                borderColor: '#003B5C',
                backgroundColor: 'rgba(0,59,92,0.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#FDB813',
                pointBorderColor: '#003B5C',
                pointRadius: 5,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxRotation: 30, font: { size: 11 } } },
                y: { beginAtZero: true, ticks: { stepSize: 5 } }
            }
        }
    });
    <?php endif; ?>

    // Logout Functions
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
</script>

</body>
</html>
<?php $pdo = null; ?>