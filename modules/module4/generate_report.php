<?php
session_start();
require_once '../../includes/db_connection.php';
require_once '../../includes/field_map.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$eventDateCol  = resolveColumn($pdo, 'event', ['eventDate','event_date','event_date_time','eventdate']);
$eventTitleCol = resolveColumn($pdo, 'event', ['eventTitle','event_title','title']);
$matrixCol     = resolveColumn($pdo, 'users', ['matrix_number','matrix','matrix_no','matric_number','student_matrix']);
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

// Persist POST values for form repopulation
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
        } catch (Exception $e) { /* report_log table optional */ }
    }
}

$page_title = "Generate Attendance Report";
?>
<?php include '../../includes/header.php'; ?>

<style>
    :root { --umpsa-blue:#003B5C; --umpsa-gold:#FDB813; --umpsa-dark-blue:#002147; --umpsa-light-blue:#E8F0F8; }

    .page-title { color: var(--umpsa-blue); font-weight: 700; margin-bottom: 24px; }
    .page-title i { color: var(--umpsa-gold); margin-right: 8px; }

    .sec-card { background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 24px; overflow: hidden; }
    .sec-head { padding: 14px 20px; background: var(--umpsa-blue); color: white; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 8px; }
    .sec-head i { color: var(--umpsa-gold); }
    .sec-body { padding: 20px; }

    .form-label  { font-weight: 500; font-size: 13px; color: #444; }
    .form-control:focus, .form-select:focus { border-color: var(--umpsa-gold); box-shadow: 0 0 0 3px rgba(253,184,19,.15); }

    .btn-umpsa       { background: var(--umpsa-blue); color: white; border: none; }
    .btn-umpsa:hover { background: var(--umpsa-dark-blue); color: white; }
    .btn-gold        { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); border: none; font-weight: 600; }
    .btn-gold:hover  { background: #e0a800; color: var(--umpsa-dark-blue); }

    /* Dynamic filter panels */
    .filter-panel { display: none; }
    .filter-panel.active { display: block; }

    /* Report table */
    .rpt-table thead th { background: var(--umpsa-blue); color: white; font-weight: 600; border: none; padding: 11px 13px; font-size: 13px; }
    .rpt-table tbody td { padding: 10px 13px; border-bottom: 1px solid #eef; vertical-align: middle; font-size: 13px; }
    .rpt-table tbody tr:hover { background: var(--umpsa-light-blue); }

    /* Summary bar */
    .summary-bar { background: var(--umpsa-light-blue); border-radius: 10px; padding: 14px 20px; display: flex; gap: 30px; flex-wrap: wrap; align-items: center; margin-top: 16px; font-size: 13px; }
    .summary-bar strong { color: var(--umpsa-blue); }

    @media print {
        body * { visibility: hidden; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; top: 0; left: 0; width: 100%; padding: 20px; }
        .print-header { display: block !important; }
        .rpt-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .rpt-table thead th { background: #003B5C !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding: 8px; }
        .rpt-table tbody td { padding: 6px 8px; border: 1px solid #ccc; }
        .rpt-table tbody tr:nth-child(even) { background: #f5f8fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .badge { background: none !important; color: #000 !important; font-weight: 600; }
        .summary-bar { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { margin: 15mm; }
    }
</style>

<div class="main-content">
<div class="container-fluid mt-2">

    <h2 class="page-title"><i class="fas fa-file-pdf"></i> Generate Attendance Report</h2>

    <?php if ($error): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="reportForm">

        <!-- ── Row 1: Filters ─────────────────────────────────── -->
        <div class="sec-card">
            <div class="sec-head"><i class="fas fa-filter"></i> Report Filters</div>
            <div class="sec-body">
                <div class="row g-3 align-items-end">
                    <!-- Report type -->
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" id="reportType" class="form-select" onchange="switchFilter()">
                            <option value="by_event"   <?php echo $post_report_type=='by_event'  ?'selected':''; ?>>By Event</option>
                            <option value="by_club"    <?php echo $post_report_type=='by_club'   ?'selected':''; ?>>By Club</option>
                            <option value="by_date"    <?php echo $post_report_type=='by_date'   ?'selected':''; ?>>By Date Range</option>
                            <option value="by_student" <?php echo $post_report_type=='by_student'?'selected':''; ?>>By Student</option>
                        </select>
                    </div>

                    <!-- Dynamic filter panels -->
                    <div class="col-md-6" id="panelWrap">

                        <!-- Event -->
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

                        <!-- Club -->
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

                        <!-- Date range -->
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

                        <!-- Student -->
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

        <!-- ── Row 2: Output Fields ───────────────────────────── -->
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

    </form><!-- end form (generate) -->

    <!-- ── Row 3: Report Preview ──────────────────────────────── -->
    <?php if ($report_preview): ?>
        <?php if (empty($report_data)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> No records match the selected filters.
            </div>
        <?php else:
            $r_present = count(array_filter($report_data, fn($r) => $r['attendanceStatus'] == 'Present'));
            $r_absent  = count(array_filter($report_data, fn($r) => $r['attendanceStatus'] == 'Absent'));
            $r_total   = count($report_data);
            $r_rate    = $r_total > 0 ? round(($r_present / $r_total) * 100) : 0;
        ?>
        <div class="sec-card" id="reportPreviewCard">
            <div class="sec-head">
                <i class="fas fa-table"></i> Report Preview
                <span class="ms-auto badge" style="background:var(--umpsa-gold);color:var(--umpsa-dark-blue);font-size:12px;"><?php echo $r_total; ?> records</span>
            </div>
            <div class="sec-body">
                <div id="printable-area">
                    <!-- Print-only header (hidden on screen) -->
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
                                    <th>Matrix No.</th>
                                    <th>Club</th>
                                    <th>Status</th>
                                    <?php if ($show_checkin):    ?><th>Check-in Time</th><?php endif; ?>
                                    <?php if ($show_points):     ?><th>Points</th><?php endif; ?>
                                    <?php if ($show_recognition):?><th>Recognition</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $i => $row):
                                    $sc = match($row['attendanceStatus']) {
                                        'Present' => 'success', 'Absent' => 'danger', default => 'warning'
                                    };
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
                                    <td><?php echo htmlspecialchars($row['matrix_number']); ?></td>
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

                    <!-- Summary bar -->
                    <div class="summary-bar">
                        <span><strong>Total Records:</strong> <?php echo $r_total; ?></span>
                        <span><strong>Present:</strong> <span class="badge bg-success"><?php echo $r_present; ?></span></span>
                        <span><strong>Absent:</strong> <span class="badge bg-danger"><?php echo $r_absent; ?></span></span>
                        <span><strong>Attendance Rate:</strong> <span class="badge bg-primary"><?php echo $r_rate; ?>%</span></span>
                    </div>
                </div><!-- /printable-area -->
            </div>
        </div>

        <!-- ── Export PDF button (bottom, centered) ───────────── -->
        <div class="text-center mb-4">
            <button onclick="printReport()" class="btn btn-gold px-5 py-2" style="font-size:15px;">
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
</div>

<script>
function switchFilter() {
    const type = document.getElementById('reportType').value;
    document.querySelectorAll('.filter-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('panel_' + type);
    if (panel) panel.classList.add('active');
}
function printReport() { window.print(); }

// Init on load
switchFilter();
</script>

<?php include '../../includes/footer.php'; ?>
