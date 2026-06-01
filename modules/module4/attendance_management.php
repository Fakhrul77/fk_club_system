<?php
session_start();
require_once '../../includes/db_connection.php';
require_once '../../includes/field_map.php';
require_once 'helpers.php';

// Check if logged in and is committee member or student
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [2,3])) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$error = '';
$success = '';
$scanned_students = [];

// Load events depending on role (use resolved column names)
$matrixCol = resolveColumn($pdo, 'users', ['matrix_number','matrix','matrix_no','matric_number','student_matrix']);
$eventDateCol = resolveColumn($pdo, 'event', ['eventDate','event_date','event_date_time','eventdate']);
$eventTitleCol = resolveColumn($pdo, 'event', ['eventTitle','event_title','title']);
$eventVenueCol = resolveColumn($pdo, 'event', ['eventVenue','venue','location','event_venue']);

if ($user_role == 2) {
    // Committee member: find their club and events for that club
    $stmt = $pdo->prepare("SELECT c.*, cc.committee_id FROM club_committee cc JOIN club c ON cc.club_id = c.club_id WHERE cc.user_id = ? AND cc.status = 'Active'");
    $stmt->execute([$user_id]);
    $club_info = $stmt->fetch();
    $club_id = $club_info['club_id'] ?? null;

    $sql = "SELECT e.*, c.clubName FROM event e JOIN club c ON e.club_id = c.club_id WHERE e.club_id = ? AND e.status IN ('ONGOING', 'Check-in Open') ORDER BY e.`$eventDateCol` DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$club_id]);
    $events = $stmt->fetchAll();
    // normalize event title/date keys
    foreach ($events as &$ev) {
        $ev['eventTitle'] = $ev[$eventTitleCol] ?? ($ev['eventTitle'] ?? '');
        $ev['eventDate'] = $ev[$eventDateCol] ?? ($ev['event_date'] ?? ($ev['eventDate'] ?? ''));
    }
    unset($ev);

} else {
    // Student: show events where the student is registered and check-in is open
    $sql = "SELECT e.*, c.clubName, er.registration_id FROM event_registration er JOIN event e ON er.event_id = e.event_id JOIN club c ON e.club_id = c.club_id WHERE er.user_id = ? AND e.status IN ('ONGOING', 'Check-in Open') AND er.registrationStatus = 'Confirmed' ORDER BY e.`$eventDateCol` DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll();
    foreach ($events as &$ev) {
        $ev['eventTitle'] = $ev[$eventTitleCol] ?? ($ev['eventTitle'] ?? '');
        $ev['eventDate'] = $ev[$eventDateCol] ?? ($ev['event_date'] ?? ($ev['eventDate'] ?? ''));
    }
    unset($ev);
}

$event_id = $_GET['event_id'] ?? null;
$registered_students = [];

    if ($event_id) {
    // Get registered students for this event
        if ($user_role == 3) {
            // student: only fetch their own registration
            $sql = "SELECT er.*, u.* FROM event_registration er JOIN users u ON er.user_id = u.user_id WHERE er.event_id = ? AND er.registrationStatus = 'Confirmed' AND er.user_id = ? ORDER BY u.name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$event_id, $user_id]);
        } else {
            $sql = "SELECT er.*, u.* FROM event_registration er JOIN users u ON er.user_id = u.user_id WHERE er.event_id = ? AND er.registrationStatus = 'Confirmed' ORDER BY u.name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$event_id]);
        }
    $registered_students = $stmt->fetchAll();

    // Normalize matrix_number in PHP (avoid relying on a possibly non-existent column in SQL)
    foreach ($registered_students as &$rs) {
        if (!isset($rs['matrix_number'])) {
            $rs['matrix_number'] = ($matrixCol && isset($rs[$matrixCol])) ? $rs[$matrixCol] : ($rs['matrix_number'] ?? '');
        }
    }
    unset($rs);
    
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM event WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $current_event = $stmt->fetch();

    // Map canonical keys for templates
    $current_event['eventTitle'] = $current_event[$eventTitleCol] ?? ($current_event['eventTitle'] ?? '');
    $current_event['eventDate'] = $current_event[$eventDateCol] ?? ($current_event['event_date'] ?? ($current_event['eventDate'] ?? ''));
    $current_event['eventVenue'] = $current_event[$eventVenueCol] ?? ($current_event['eventVenue'] ?? '');
}

// Handle QR code scan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'scan_qr') {
    $qr_raw           = $_POST['qr_data']   ?? '';
    $scanned_event_id = (int)($_POST['event_id'] ?? 0);

    $validator  = new QRCodeGenerator($pdo);
    $validation = $validator->validateQRCode($qr_raw);

    if (!$validation['valid']) {
        $error = $validation['error'];
    } elseif ($validation['event_id'] !== $scanned_event_id) {
        $error = "QR code does not belong to this event.";
    } else {
        $registration_id = $validation['registration_id'];

        // Check if attendance already recorded
        $stmt = $pdo->prepare("SELECT attendance_id FROM attendance WHERE registration_id = ? AND event_id = ?");
        $stmt->execute([$registration_id, $scanned_event_id]);

        if ($stmt->rowCount() > 0) {
            $error = "Attendance already recorded for this student.";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO attendance (registration_id, event_id, attendanceStatus, checkInTime) VALUES (?, ?, 'Present', NOW())"
            );
            if ($stmt->execute([$registration_id, $scanned_event_id])) {
                // Get student info for feedback
                $stmt = $pdo->prepare(
                    "SELECT u.*, er.user_id AS reg_user_id FROM event_registration er JOIN users u ON er.user_id = u.user_id WHERE er.registration_id = ?"
                );
                $stmt->execute([$registration_id]);
                $student = $stmt->fetch();
                if ($student) {
                    $student['matrix_number'] = $student['matrix_number'] ?? '';
                    $calculator = new PointsCalculator($pdo);
                    $points     = $calculator->calculateAttendancePoints($student['reg_user_id'], $scanned_event_id);
                    $calculator->recordPoints($student['reg_user_id'], $scanned_event_id, $points);
                    $total      = $calculator->getTotalPoints($student['reg_user_id']);
                    $recognizer = new RecognitionLevelDeterminer($pdo);
                    $recognizer->updateRecognitionLevel($student['reg_user_id'], $total);
                    $success = "Attendance recorded for <strong>" . htmlspecialchars($student['name']) . "</strong>"
                             . " (" . htmlspecialchars($student['matrix_number']) . ")"
                             . " — <strong>{$points} pts</strong> awarded.";
                } else {
                    $success = "Attendance recorded successfully.";
                }
            } else {
                $error = "Failed to record attendance. Please try again.";
            }
        }
    }
}

// Handle manual attendance marking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'manual_mark') {
    $registration_id   = $_POST['registration_id'] ?? null;
    $attendance_status = $_POST['attendance_status'] ?? 'Present';
    $excused_reason    = trim($_POST['excused_reason'] ?? '');
    $scanned_event_id  = $_POST['event_id'] ?? null;

    if ($registration_id) {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE registration_id = ? AND event_id = ?");
        $stmt->execute([$registration_id, $scanned_event_id]);

        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("UPDATE attendance SET attendanceStatus = ?, checkInTime = NOW() WHERE registration_id = ? AND event_id = ?");
            $stmt->execute([$attendance_status, $registration_id, $scanned_event_id]);
            $success = "Attendance updated successfully";
        } else {
            $stmt = $pdo->prepare("INSERT INTO attendance (registration_id, event_id, attendanceStatus, checkInTime) VALUES (?, ?, ?, NOW())");
            if ($stmt->execute([$registration_id, $scanned_event_id, $attendance_status])) {
                $success = "Attendance recorded successfully";
            } else {
                $error = "Failed to record attendance";
            }
        }

        // Trigger points calculation if marked Present
        if (!$error && $attendance_status === 'Present') {
            $stmt = $pdo->prepare("SELECT user_id FROM event_registration WHERE registration_id = ?");
            $stmt->execute([$registration_id]);
            $reg = $stmt->fetch();
            if ($reg) {
                $calculator = new PointsCalculator($pdo);
                $points     = $calculator->calculateAttendancePoints($reg['user_id'], $scanned_event_id);
                $calculator->recordPoints($reg['user_id'], $scanned_event_id, $points);
                $total      = $calculator->getTotalPoints($reg['user_id']);
                $recognizer = new RecognitionLevelDeterminer($pdo);
                $recognizer->updateRecognitionLevel($reg['user_id'], $total);
                $success   .= " — <strong>{$points} pts</strong> awarded.";
            }
        }
    }
}

$page_title = "Attendance Management";
?>
<?php include '../../includes/header.php'; ?>

<style>
:root{--umpsa-blue:#003B5C;--umpsa-gold:#FDB813;--umpsa-dark-blue:#002147;--umpsa-light-blue:#E8F0F8;}
body{background:var(--umpsa-light-blue);}
.page-title{color:var(--umpsa-blue);font-weight:700;}
.page-title i{color:var(--umpsa-gold);margin-right:8px;}
.sec-card{background:#fff;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px;}
.sec-head{background:var(--umpsa-blue);color:#fff;padding:14px 20px;font-weight:600;font-size:15px;}
.sec-head i{color:var(--umpsa-gold);margin-right:8px;}
.sec-body{padding:20px;}
.event-btn{display:block;padding:14px 18px;border:2px solid var(--umpsa-blue);border-radius:10px;color:var(--umpsa-blue);text-decoration:none;transition:.2s;margin-bottom:10px;}
.event-btn:hover,.event-btn.active-event{background:var(--umpsa-blue);color:#fff;}
.event-btn.active-event small{color:rgba(255,255,255,.8)!important;}
.scanner-box{width:100%;height:380px;border:2px dashed #ccc;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#f8f9fa;flex-direction:column;gap:10px;}
.btn-scan{background:var(--umpsa-gold);color:var(--umpsa-dark-blue);font-weight:700;border:none;padding:10px 28px;border-radius:8px;}
.btn-scan:hover{background:#e0a800;}
.btn-mark{background:var(--umpsa-blue);color:#fff;border:none;width:100%;padding:10px;border-radius:8px;font-weight:600;}
.btn-mark:hover{background:var(--umpsa-dark-blue);color:#fff;}
.att-table thead th{background:var(--umpsa-blue);color:#fff;padding:11px 14px;font-weight:600;border:none;}
.att-table tbody td{padding:10px 14px;border-bottom:1px solid #eef;vertical-align:middle;}
.att-table tbody tr:hover{background:var(--umpsa-light-blue);}
.total-bar{background:#fff;border-radius:12px;padding:16px 24px;box-shadow:0 2px 8px rgba(0,0,0,.06);display:flex;align-items:center;gap:16px;}
.total-bar .num{font-size:28px;font-weight:700;color:var(--umpsa-blue);}
.total-bar .lbl{color:#666;font-size:13px;}
</style>

<div class="main-content">
<div class="container-fluid mt-2">

    <h2 class="page-title mb-4"><i class="fas fa-qrcode"></i> Attendance Management</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Event selector -->
    <div class="sec-card">
        <div class="sec-head"><i class="fas fa-calendar-alt"></i> Select Event</div>
        <div class="sec-body">
            <?php if (empty($events)): ?>
                <p class="text-muted mb-0">No ongoing or check-in open events available.</p>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($events as $ev): ?>
                    <div class="col-md-4">
                        <a href="?event_id=<?php echo $ev['event_id']; ?>"
                           class="event-btn <?php echo ($event_id == $ev['event_id']) ? 'active-event' : ''; ?>">
                            <div class="fw-bold"><?php echo htmlspecialchars($ev['eventTitle']); ?></div>
                            <small class="text-muted"><i class="far fa-calendar"></i>
                                <?php echo date('M d, Y  H:i', strtotime($ev['eventDate'])); ?>
                            </small>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($event_id && isset($current_event)): ?>
    <!-- Event info + QR scanner side by side -->
    <div class="row mb-4">
        <!-- Left: event details + manual mark -->
        <div class="col-lg-4">
            <div class="sec-card">
                <div class="sec-head"><i class="fas fa-info-circle"></i> Event Details</div>
                <div class="sec-body">
                    <p><strong>Event:</strong> <?php echo htmlspecialchars($current_event['eventTitle']); ?></p>
                    <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($current_event['eventDate'])); ?></p>
                    <p><strong>Time:</strong> <?php echo date('H:i', strtotime($current_event['eventDate'])); ?></p>
                    <p class="mb-0"><strong>Venue:</strong> <?php echo htmlspecialchars($current_event['eventVenue']); ?></p>
                </div>
            </div>

            <div class="sec-card">
                <div class="sec-head"><i class="fas fa-hand-paper"></i> Manual Mark</div>
                <div class="sec-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="manual_mark">
                        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student</label>
                            <select name="registration_id" class="form-select" required>
                                <option value="">-- Choose student --</option>
                                <?php foreach ($registered_students as $st): ?>
                                    <option value="<?php echo $st['registration_id']; ?>">
                                        <?php echo htmlspecialchars($st['name']); ?> (<?php echo $st['matrix_number']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="attendance_status" class="form-select" required
                                    onchange="document.getElementById('reasonBox').style.display=this.value==='Excused'?'block':'none'">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Excused">Excused</option>
                            </select>
                        </div>
                        <div class="mb-3" id="reasonBox" style="display:none;">
                            <label class="form-label fw-semibold">Reason</label>
                            <input type="text" name="excused_reason" class="form-control" placeholder="e.g. Medical certificate">
                        </div>
                        <button type="submit" class="btn-mark"><i class="fas fa-save"></i> Mark Attendance</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: QR scanner -->
        <div class="col-lg-8">
            <div class="sec-card">
                <div class="sec-head"><i class="fas fa-camera"></i> QR Code Scanner</div>
                <div class="sec-body text-center">
                    <div id="scanner" class="scanner-box">
                        <i class="fas fa-qrcode" style="font-size:48px;color:#ccc;"></i>
                        <p class="text-muted mb-0">QR Code Scanner Area</p>
                        <small class="text-muted">Click Start Scanner to activate camera</small>
                    </div>
                    <button class="btn-scan mt-3" id="startScanner">
                        <i class="fas fa-camera"></i> Start Scanner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Registered Attendees table (full width) -->
    <?php if (!empty($registered_students)):
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE event_id = ? AND attendanceStatus = 'Present'");
        $stmt->execute([$event_id]);
        $present_count = $stmt->fetchColumn();
    ?>
    <div class="sec-card">
        <div class="sec-head"><i class="fas fa-users"></i> Registered Attendees</div>
        <div class="p-0">
            <table class="table att-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Matrix No</th>
                        <th>Attendance Status</th>
                        <th>Check-in Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registered_students as $i => $st):
                        $att = $pdo->prepare("SELECT * FROM attendance WHERE registration_id = ? AND event_id = ?");
                        $att->execute([$st['registration_id'], $event_id]);
                        $att_row = $att->fetch();
                        $status = $att_row['attendanceStatus'] ?? 'Pending';
                        $checkin = $att_row['checkInTime'] ?? null;
                        $sc = match($status){ 'Present'=>'success','Absent'=>'danger','Excused'=>'warning',default=>'secondary' };
                    ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td><?php echo htmlspecialchars($st['name']); ?></td>
                        <td><?php echo htmlspecialchars($st['matrix_number']); ?></td>
                        <td><span class="badge bg-<?php echo $sc; ?>"><?php echo $status; ?></span></td>
                        <td><?php echo $checkin ? date('H:i:s', strtotime($checkin)) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Total Present bar -->
    <div class="total-bar">
        <div>
            <div class="num"><?php echo $present_count; ?></div>
            <div class="lbl">Total Present</div>
        </div>
        <div style="width:1px;height:40px;background:#eee;"></div>
        <div>
            <div class="num" style="color:#dc3545;"><?php echo count($registered_students) - $present_count; ?></div>
            <div class="lbl">Absent / Pending</div>
        </div>
        <div style="width:1px;height:40px;background:#eee;"></div>
        <div>
            <div class="num" style="color:#666;"><?php echo count($registered_students); ?></div>
            <div class="lbl">Total Registered</div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const startBtn = document.getElementById('startScanner');
if (startBtn) startBtn.addEventListener('click', function() {
    const scanner = document.getElementById('scanner');
    const video = document.createElement('video');
    video.style.width = '100%';
    video.style.height = '100%';
    video.style.objectFit = 'cover';
    
    scanner.innerHTML = '';
    scanner.appendChild(video);
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            video.srcObject = stream;
            video.play();
            
            // Scan QR codes
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            function scan() {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, canvas.width, canvas.height);
                    
                    if (code) {
                        handleQRScan(code.data);
                    }
                }
                requestAnimationFrame(scan);
            }
            scan();
        })
        .catch(err => {
            alert('Cannot access camera: ' + err.message);
        });
});

function handleQRScan(qrData) {
    // QR format: registration_id|event_id|matrix_number
    // Send the full raw string to PHP for validation via QRCodeGenerator::validateQRCode()
    const parts = qrData.trim().split('|');
    if (parts.length !== 3) {
        alert('Invalid QR code format. Expected: registration_id|event_id|matrix_number');
        return;
    }

    const eventInput = document.querySelector('input[name="event_id"]');
    const currentEventId = eventInput ? eventInput.value : '';

    fetch('attendance_management.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=scan_qr'
            + '&qr_data='   + encodeURIComponent(qrData.trim())
            + '&event_id='  + encodeURIComponent(currentEventId)
    })
    .then(response => response.text())
    .then(() => { location.reload(); });
}
</script>

<style>
.scanned-list {
    max-height: 300px;
    overflow-y: auto;
}
.scanned-item {
    padding: 10px;
    border-left: 4px solid #28a745;
    margin-bottom: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}
</style>
