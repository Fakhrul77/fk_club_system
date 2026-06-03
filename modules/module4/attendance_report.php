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

$filter_event   = $_GET['event']   ?? null;
$filter_date    = $_GET['date']    ?? null;
$filter_club    = $_GET['club']    ?? null;
$filter_student = $_GET['student'] ?? null;

$matrixCol     = resolveColumn($pdo, 'users', ['matrix_number','matrix','matrix_no','matric_number','student_matrix']);
$eventDateCol  = resolveColumn($pdo, 'event', ['eventDate','event_date','event_date_time','eventdate']);
$eventTitleCol = resolveColumn($pdo, 'event', ['eventTitle','event_title','title']);

$params = [];

if ($user_role == 1 || $user_role == 2) {
    $query = "SELECT a.*, er.*, u.*, e.`$eventTitleCol` AS eventTitle, e.`$eventDateCol` AS eventDate, c.clubName"
           . " FROM attendance a"
           . " JOIN event_registration er ON a.registration_id = er.registration_id"
           . " JOIN users u ON er.user_id = u.user_id"
           . " JOIN event e ON a.event_id = e.event_id"
           . " JOIN club c ON e.club_id = c.club_id"
           . " WHERE 1=1";

    if ($filter_event) { $query .= " AND e.event_id = ?"; $params[] = $filter_event; }
    if ($filter_date)  { $query .= " AND DATE(e.`$eventDateCol`) = ?"; $params[] = $filter_date; }
    if ($filter_club)  { $query .= " AND c.club_id = ?"; $params[] = $filter_club; }
    if ($filter_student) {
        if ($matrixCol) {
            $query .= " AND (u.name LIKE ? OR u.`$matrixCol` LIKE ?)";
            $params[] = "%$filter_student%";
            $params[] = "%$filter_student%";
        } else {
            $query .= " AND u.name LIKE ?";
            $params[] = "%$filter_student%";
        }
    }
    $query .= " ORDER BY e.`$eventDateCol` DESC, u.name ASC";
} else {
    // Student: own records only
    $query = "SELECT a.*, er.*, u.*, e.`$eventTitleCol` AS eventTitle, e.`$eventDateCol` AS eventDate, c.clubName"
           . " FROM attendance a"
           . " JOIN event_registration er ON a.registration_id = er.registration_id"
           . " JOIN users u ON er.user_id = u.user_id"
           . " JOIN event e ON a.event_id = e.event_id"
           . " JOIN club c ON e.club_id = c.club_id"
           . " WHERE u.user_id = ?"
           . " ORDER BY e.`$eventDateCol` DESC";
    $params[] = $user_id;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

foreach ($records as &$r) {
    if (!isset($r['matrix_number']) || $r['matrix_number'] === '') {
        $r['matrix_number'] = ($matrixCol && isset($r[$matrixCol])) ? $r[$matrixCol] : '';
    }
}
unset($r);

// CSV export
$format = $_GET['format'] ?? 'print';
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Event', 'Date', 'Club', 'Student Name', 'Student ID', 'Status', 'Check-in Time']);
    foreach ($records as $i => $r) {
        fputcsv($out, [
            $i + 1,
            $r['eventTitle']      ?? '',
            $r['eventDate']       ? date('d M Y', strtotime($r['eventDate'])) : '',
            $r['clubName']        ?? '',
            $r['name']            ?? '',
            $r['matrix_number']   ?? '',
            $r['attendanceStatus']?? '',
            $r['checkInTime']     ? date('H:i', strtotime($r['checkInTime'])) : '',
        ]);
    }
    fclose($out);
    exit();
}

// Printable HTML
$present = count(array_filter($records, fn($r) => $r['attendanceStatus'] === 'Present'));
$absent  = count(array_filter($records, fn($r) => $r['attendanceStatus'] === 'Absent'));
$total   = count($records);
$rate    = $total > 0 ? round(($present / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance Report – FK Club System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root { --umpsa-blue:#003B5C; --umpsa-dark-blue:#002147; --umpsa-gold:#FDB813; }
    body { background: #f4f6f9; }
    .report-header { background: var(--umpsa-blue); color: white; padding: 18px 24px; border-radius: 12px 12px 0 0; }
    .report-header h4 { margin: 0; font-size: 18px; }
    .report-header p  { margin: 4px 0 0; font-size: 12px; opacity: .8; }
    .report-wrap { background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08); overflow: hidden; margin: 24px auto; max-width: 1100px; }
    .summary-row { display: flex; gap: 24px; padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #eee; font-size: 13px; flex-wrap: wrap; }
    .summary-row strong { color: var(--umpsa-blue); }
    table thead th { background: var(--umpsa-blue); color: white; padding: 10px 12px; font-size: 13px; border: none; }
    table tbody td { padding: 9px 12px; border-bottom: 1px solid #eef; font-size: 13px; vertical-align: middle; }
    table tbody tr:hover { background: #f0f6fb; }
    .no-print-btn { padding: 12px 20px; display: flex; gap: 10px; border-top: 1px solid #eee; }
    @media print {
        body { background: white; }
        .no-print-btn { display: none !important; }
        .report-wrap { margin: 0; box-shadow: none; }
        table thead th { background: #003B5C !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table tbody tr:nth-child(even) { background: #f5f8fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { margin: 12mm; }
    }
</style>
</head>
<body>
<div class="report-wrap">
    <div class="report-header">
        <h4>FK Club System &mdash; Attendance Report</h4>
        <p>Faculty of Computing, UMPSA &nbsp;|&nbsp; Generated: <?php echo date('d M Y, h:i A'); ?> &nbsp;|&nbsp; By: <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></p>
    </div>
    <div class="summary-row">
        <span><strong>Total Records:</strong> <?php echo $total; ?></span>
        <span><strong>Present:</strong> <span class="badge bg-success"><?php echo $present; ?></span></span>
        <span><strong>Absent:</strong> <span class="badge bg-danger"><?php echo $absent; ?></span></span>
        <span><strong>Attendance Rate:</strong> <span class="badge bg-primary"><?php echo $rate; ?>%</span></span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Club</th>
                    <?php if ($user_role != 3): ?><th>Student Name</th><th>Matrix No.</th><?php endif; ?>
                    <th>Status</th>
                    <th>Check-in Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $i => $r):
                        $sc = match($r['attendanceStatus']) { 'Present'=>'success','Absent'=>'danger',default=>'warning' };
                    ?>
                    <tr>
                        <td class="text-muted"><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($r['eventTitle'] ?? ''); ?></td>
                        <td><?php echo $r['eventDate'] ? date('d M Y', strtotime($r['eventDate'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($r['clubName'] ?? ''); ?></td>
                        <?php if ($user_role != 3): ?>
                            <td><?php echo htmlspecialchars($r['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['studentID'] ?? ''); ?></td>
                        <?php endif; ?>
                        <td><span class="badge bg-<?php echo $sc; ?>"><?php echo htmlspecialchars($r['attendanceStatus'] ?? ''); ?></span></td>
                        <td><?php echo $r['checkInTime'] ? date('H:i', strtotime($r['checkInTime'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="no-print-btn">
        <button class="btn btn-sm" style="background:#003B5C;color:white;" onclick="window.print()">
            <i class="fas fa-print"></i> Print / Save as PDF
        </button>
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>
</body>
</html>
