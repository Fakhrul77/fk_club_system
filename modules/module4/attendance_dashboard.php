<?php
session_start();
require_once '../../includes/db_connection.php';
require_once '../../includes/field_map.php';
require_once '../../includes/helpers.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle delete (admin and committee only)
$delete_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attendance') {
    if (in_array($user_role, [1, 2])) {
        $del_id = (int)($_POST['attendance_id'] ?? 0);
        if ($del_id > 0) {
            // Get the attendance row before deleting so we can clean up points
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

                // Remove the points awarded for this event and recalculate recognition level
                if ($att_row) {
                    $calculator = new PointsCalculator($pdo);
                    $recognizer = new RecognitionLevelDeterminer($pdo);

                    // Only remove points if the student has no other Present attendance for this event
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
                    $recognizer->updateRecognitionLevel($att_row['user_id'], $new_total);
                }
            } else {
                $delete_msg = 'notfound';
            }
        }
    }
    // Redirect to prevent form resubmit on refresh
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

// Pick up delete feedback from redirect
if (isset($_GET['deleted'])) {
    $delete_msg = $_GET['deleted'];
}

$matrixCol    = resolveColumn($pdo, 'users', ['matrix_number','matrix','matrix_no','matric_number','student_matrix']);
$eventDateCol = resolveColumn($pdo, 'event', ['eventDate','event_date','event_date_time','eventdate']);
$eventTitleCol= resolveColumn($pdo, 'event', ['eventTitle','event_title','title']);

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
      . " WHERE e.status IN ('Completed','ONGOING','Check-in Open')"
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

    if ($filter_event) { $query .= " AND e.event_id = ?";                $params[] = $filter_event; }
    if ($filter_date)  { $query .= " AND DATE(e.`$eventDateCol`) = ?";   $params[] = $filter_date; }
    if ($filter_club)  { $query .= " AND c.club_id = ?";                 $params[] = $filter_club; }
    if ($filter_student) {
        if ($matrixCol) {
            $query .= " AND (u.name LIKE ? OR u.`$matrixCol` LIKE ?)";
            $params[] = "%$filter_student%"; $params[] = "%$filter_student%";
        } else {
            $query .= " AND u.name LIKE ?";
            $params[] = "%$filter_student%";
        }
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

// Chart data: per-event attendance trend (admin/committee) or per-event for student
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
// Limit to 8 events for readability
$event_groups_slice = array_slice($event_groups, 0, 8, true);
foreach ($event_groups_slice as $label => $counts) {
    $chart_labels[]  = $label;
    $chart_present[] = $counts['present'];
    $chart_absent[]  = $counts['absent'];
}

$page_title = "Attendance Dashboard";
?>
<?php include '../../includes/header.php'; ?>

<style>
    :root { --umpsa-blue:#003B5C; --umpsa-gold:#FDB813; --umpsa-dark-blue:#002147; --umpsa-light-blue:#E8F0F8; }

    .page-title { color: var(--umpsa-blue); font-weight: 700; margin-bottom: 24px; }
    .page-title i { color: var(--umpsa-gold); margin-right: 8px; }

    /* Stat cards */
    .stat-card {
        background: white; border-radius: 16px; padding: 22px 20px;
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06); transition: transform .2s; margin-bottom: 20px;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-info h3 { font-size: 34px; font-weight: 700; color: var(--umpsa-blue); margin: 0 0 4px; }
    .stat-info p  { color: #666; margin: 0; font-size: 13px; }
    .stat-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .stat-icon i { font-size: 26px; }
    .icon-green  { background: rgba(40,167,69,.12);  } .icon-green  i { color: #28a745; }
    .icon-red    { background: rgba(220,53,69,.12);  } .icon-red    i { color: #dc3545; }
    .icon-blue   { background: rgba(0,59,92,.10);    } .icon-blue   i { color: var(--umpsa-blue); }

    /* Section cards */
    .sec-card { background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 24px; overflow: hidden; }
    .sec-head {
        padding: 14px 20px; background: var(--umpsa-blue); color: white;
        font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 8px;
    }
    .sec-head i { color: var(--umpsa-gold); }
    .sec-body { padding: 20px; }

    /* Filters */
    .form-label  { font-weight: 500; font-size: 13px; color: #444; }
    .form-control:focus, .form-select:focus { border-color: var(--umpsa-gold); box-shadow: 0 0 0 3px rgba(253,184,19,.15); }

    /* Buttons */
    .btn-umpsa       { background: var(--umpsa-blue); color: white; border: none; }
    .btn-umpsa:hover { background: var(--umpsa-dark-blue); color: white; }
    .btn-gold        { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); border: none; font-weight: 600; }
    .btn-gold:hover  { background: #e0a800; color: var(--umpsa-dark-blue); }

    /* Table */
    .att-table thead th { background: var(--umpsa-blue); color: white; font-weight: 600; border: none; padding: 12px 14px; font-size: 13px; }
    .att-table tbody td { padding: 11px 14px; border-bottom: 1px solid #eef; vertical-align: middle; font-size: 13px; }
    .att-table tbody tr:hover { background: var(--umpsa-light-blue); }

    /* Chart containers */
    .chart-wrap { position: relative; height: 260px; }
</style>

<div class="main-content">
<div class="container-fluid mt-2">

    <h2 class="page-title"><i class="fas fa-chart-bar"></i> Attendance Dashboard</h2>

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

    <!-- ── Stat Cards ─────────────────────────────────────────── -->
    <div class="row">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-info"><h3><?php echo $total_present; ?></h3><p>Total Present</p></div>
                <div class="stat-icon icon-green"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-info"><h3><?php echo $total_absent; ?></h3><p>Total Absent</p></div>
                <div class="stat-icon icon-red"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-info"><h3><?php echo $att_rate; ?>%</h3><p>Attendance Rate</p></div>
                <div class="stat-icon icon-blue"><i class="fas fa-percent"></i></div>
            </div>
        </div>
    </div>

    <?php if ($user_role == 1 || $user_role == 2): ?>
    <!-- ── Filters ────────────────────────────────────────────── -->
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
                    <a href="attendance_management.php" class="btn btn-gold btn-sm ms-auto"><i class="fas fa-qrcode"></i> Open QR Scanner</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Charts ─────────────────────────────────────────────── -->
    <?php if (!empty($attendance_records)): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="sec-card">
                <div class="sec-head"><i class="fas fa-chart-bar"></i> Attendance Trend</div>
                <div class="sec-body">
                    <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="sec-card">
                <div class="sec-head"><i class="fas fa-chart-pie"></i> Attendance Distribution</div>
                <div class="sec-body">
                    <div class="chart-wrap"><canvas id="distChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Attendance Table ───────────────────────────────────── -->
    <div class="sec-card">
        <div class="sec-head"><i class="fas fa-table"></i> Attendance Records
            <?php if ($total_records > 0): ?>
                <span class="ms-auto badge" style="background:var(--umpsa-gold);color:var(--umpsa-dark-blue);font-size:12px;"><?php echo $total_records; ?> records</span>
            <?php endif; ?>
        </div>
        <div class="sec-body p-0">
            <?php if (empty($attendance_records)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo ($user_role == 3)
                            ? 'No attendance records yet. Attend events to build your history!'
                            : 'No attendance records match the selected filters.'; ?>
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
                                <th>Matrix No.</th>
                                <th>Status</th>
                                <th>Check-in Time</th>
                                <?php if ($user_role != 3): ?><th style="width:80px;">Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $i => $record):
                                $sc = match($record['attendanceStatus']) {
                                    'Present' => 'success', 'Absent' => 'danger', default => 'warning'
                                };
                            ?>
                            <tr>
                                <td class="text-muted"><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($record['eventTitle']); ?></td>
                                <td><?php echo date('d M Y', strtotime($record['eventDate'])); ?></td>
                                <td><?php echo htmlspecialchars($record['clubName']); ?></td>
                                <?php if ($user_role != 3): ?>
                                    <td><?php echo htmlspecialchars($record['name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($record['matrix_number']); ?></td>
                                <td><span class="badge bg-<?php echo $sc; ?>"><?php echo $record['attendanceStatus']; ?></span></td>
                                <td><?php echo $record['checkInTime'] ? date('H:i', strtotime($record['checkInTime'])) : '—'; ?></td>
                                <?php if ($user_role != 3): ?>
                                <td>
                                    <form method="POST" action="attendance_dashboard.php?<?php echo htmlspecialchars(http_build_query(array_filter(['event'=>$filter_event,'date'=>$filter_date,'club'=>$filter_club,'student'=>$filter_student]))); ?>" onsubmit="return confirm('Delete this attendance record? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_attendance">
                                        <input type="hidden" name="attendance_id" value="<?php echo $record['attendance_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete record">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
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
</div>

<?php if (!empty($attendance_records)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const BLUE  = '#003B5C';
const GOLD  = '#FDB813';
const RED   = '#dc3545';
const GREEN = '#28a745';

// Bar – Attendance Trend
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Present',
                data: <?php echo json_encode($chart_present); ?>,
                backgroundColor: GREEN,
                borderRadius: 6
            },
            {
                label: 'Absent',
                data: <?php echo json_encode($chart_absent); ?>,
                backgroundColor: RED,
                borderRadius: 6
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 12 } } } },
        scales: {
            x: { ticks: { maxRotation: 30, font: { size: 11 } } },
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Pie – Distribution
const distCtx = document.getElementById('distChart').getContext('2d');
new Chart(distCtx, {
    type: 'pie',
    data: {
        labels: ['Present', 'Absent', 'Excused'],
        datasets: [{
            data: [<?php echo $total_present; ?>, <?php echo $total_absent; ?>, <?php echo $total_excused; ?>],
            backgroundColor: [GREEN, RED, GOLD],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 16 } }
        }
    }
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
