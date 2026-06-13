<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's active registrations (NOT cancelled)
$stmt = $pdo->prepare("SELECT event_id FROM event_registration WHERE user_id = ? AND status IN ('Registered', 'Confirmed', 'Attended')");
$stmt->execute([$user_id]);
$registeredEvents = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get user's waiting list entries
$stmt = $pdo->prepare("SELECT event_id FROM waiting_list WHERE user_id = ?");
$stmt->execute([$user_id]);
$waitingEvents = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all upcoming events
$stmt = $pdo->query("
    SELECT e.*, c.clubName 
    FROM event e 
    JOIN club c ON e.club_id = c.club_id 
    WHERE e.status = 'UPCOMING' AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
");
$events = $stmt->fetchAll();

// Handle AJAX registration request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_register'])) {
    $event_id = (int)$_POST['event_id'];
    
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM event WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found.']);
        exit();
    }
    
    // Check if already actively registered
    $check = $pdo->prepare("SELECT * FROM event_registration WHERE user_id = ? AND event_id = ? AND status = 'Confirmed'");
    $check->execute([$user_id, $event_id]);
    
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are already registered for this event.']);
        exit();
    }
    
    // Check if already on waiting list
    $checkWaiting = $pdo->prepare("SELECT * FROM waiting_list WHERE user_id = ? AND event_id = ?");
    $checkWaiting->execute([$user_id, $event_id]);
    
    if ($checkWaiting->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You are already on the waiting list for this event.', 'already_waiting' => true]);
        exit();
    }
    
    // Check if there's a cancelled registration
    $checkCancelled = $pdo->prepare("SELECT registration_id FROM event_registration WHERE user_id = ? AND event_id = ? AND status = 'Cancelled'");
    $checkCancelled->execute([$user_id, $event_id]);
    $cancelledReg = $checkCancelled->fetch();
    
    if ($cancelledReg) {
        // Delete the cancelled registration
        $pdo->prepare("DELETE FROM event_registration WHERE registration_id = ?")
            ->execute([$cancelledReg['registration_id']]);
        // Now treat as new registration
    }
    
    // Check capacity
    if ($event['current_participant'] < $event['max_participant']) {
        // Register student directly
        $stmt = $pdo->prepare("
            INSERT INTO event_registration (user_id, event_id, registration_date, status)
            VALUES (?, ?, NOW(), 'Confirmed')
        ");
        $stmt->execute([$user_id, $event_id]);
        
        $pdo->prepare("UPDATE event SET current_participant = current_participant + 1 WHERE event_id = ?")
            ->execute([$event_id]);
        
        echo json_encode(['success' => true, 'message' => 'Successfully registered for the event!', 'type' => 'registered']);
        exit();
    } else {
        // Event is full - add to waiting list (if not already there)
        $waiting_stmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) as max_pos FROM waiting_list WHERE event_id = ?");
        $waiting_stmt->execute([$event_id]);
        $max_pos = $waiting_stmt->fetchColumn();
        $new_position = $max_pos + 1;
        
        $stmt = $pdo->prepare("
            INSERT INTO waiting_list (user_id, event_id, position, joined_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $event_id, $new_position]);
        
        echo json_encode(['success' => true, 'message' => 'Event is full! You have been added to the waiting list (Position: ' . $new_position . ')', 'type' => 'waiting']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Events - FK Club System</title>
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
        
        .event-card {
            background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s;
        }
        .event-card:hover { transform: translateY(-3px); }
        .event-title { font-size: 20px; font-weight: bold; color: var(--umpsa-blue); margin-bottom: 10px; }
        .event-club { color: var(--umpsa-gold); font-size: 14px; margin-bottom: 15px; }
        .event-detail { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px; }
        .event-detail span { background: var(--umpsa-light-blue); padding: 5px 12px; border-radius: 20px; font-size: 13px; }
        .event-desc { color: #666; margin-bottom: 15px; line-height: 1.5; }
        .btn-register { background: var(--umpsa-blue); color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; }
        .btn-register:hover { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); }
        .btn-registered { background: #28a745; color: white; padding: 10px 25px; border-radius: 8px; display: inline-block; cursor: default; }
        .btn-waiting { background: #ffc107; color: #333; padding: 10px 25px; border-radius: 8px; display: inline-block; cursor: default; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h4, .sidebar-menu a span { display: none; }
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
        <a href="../module1/dashboard_student.php">
            <i class="fas fa-home"></i> <span>Dashboard</span>
        </a>
        <a href="../module2/club_dashboard_student.php">
            <i class="fas fa-building"></i> <span>Browse Clubs</span>
        </a>
        <a href="../module3/browse_events.php" class="active">
            <i class="fas fa-calendar-alt"></i> <span>Browse Events</span>
        </a>
        <a href="../module3/my_registrations.php">
            <i class="fas fa-list"></i> <span>My Registrations</span>
        </a>
        <a href="../module4/my_points_recognition.php">
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

    <h3 class="mb-4" style="color: var(--umpsa-blue);"><i class="fas fa-calendar-alt"></i> Upcoming Events</h3>

    <div id="alertMessage"></div>

    <?php if (empty($events)): ?>
        <div class="event-card text-center">
            <p class="text-muted">No upcoming events at the moment. Check back later!</p>
        </div>
    <?php else: ?>
        <?php foreach ($events as $event): 
            $isRegistered = in_array($event['event_id'], $registeredEvents);
            $isOnWaitingList = in_array($event['event_id'], $waitingEvents);
            $isFull = $event['current_participant'] >= $event['max_participant'];
        ?>
        <div class="event-card" id="event_<?php echo $event['event_id']; ?>">
            <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
            <div class="event-club"><i class="fas fa-building"></i> <?php echo htmlspecialchars($event['clubName']); ?></div>
            
            <div class="event-detail">
                <span><i class="fas fa-calendar-day"></i> <?php echo date('d M Y', strtotime($event['event_date'])); ?></span>
                <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?></span>
                <span><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($event['venue']); ?></span>
                <span><i class="fas fa-users"></i> <?php echo $event['current_participant']; ?>/<?php echo $event['max_participant']; ?> spots</span>
                <span><i class="fas fa-star"></i> <?php echo $event['points_awarded']; ?> points</span>
            </div>
            
            <div class="event-desc"><?php echo nl2br(htmlspecialchars(substr($event['event_description'], 0, 200))); ?></div>
            
            <div class="event-action">
                <?php if ($isRegistered): ?>
                    <div class="btn-registered"><i class="fas fa-check-circle"></i> Already Registered</div>
                <?php elseif ($isOnWaitingList): ?>
                    <div class="btn-waiting"><i class="fas fa-hourglass-half"></i> On Waiting List</div>
                <?php elseif ($isFull): ?>
                    <button class="btn-register" onclick="registerEvent(<?php echo $event['event_id']; ?>, this)" style="background: #ffc107; color: #333;">
                        <i class="fas fa-hourglass-half"></i> Join Waiting List
                    </button>
                <?php else: ?>
                    <button class="btn-register" onclick="registerEvent(<?php echo $event['event_id']; ?>, this)">
                        <i class="fas fa-check"></i> Register Now
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Register Confirmation Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden;">
            <div style="background: var(--umpsa-dark-blue); color: white; padding: 15px;">
                <h5 style="margin: 0; font-weight: 600;">
                    <i class="fas fa-calendar-check" style="color: var(--umpsa-gold);"></i>
                    Confirm Registration
                </h5>
            </div>
            <div style="padding: 20px; font-size: 14px; color: #333;">
                <p id="registerModalText" style="margin-bottom: 10px;"></p>
                <div style="background: #e8f0f8; padding: 10px; border-radius: 8px; font-size: 13px;">
                    Please ensure you are available for the event schedule.
                </div>
            </div>
            <div style="padding: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmRegisterBtn"
                        style="background: var(--umpsa-blue); border: none;">Confirm</button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/logout_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedEventId = null;
let selectedButton = null;

function registerEvent(eventId, buttonElement) {
    selectedEventId = eventId;
    selectedButton = buttonElement;

    const isWaitingList = buttonElement.innerText.includes('Waiting List');

    const message = isWaitingList
        ? 'This event is currently full. Would you like to join the waiting list?'
        : 'Are you sure you want to register for this event?';

    document.getElementById('registerModalText').innerHTML = message;

    const modal = new bootstrap.Modal(document.getElementById('registerModal'));
    modal.show();
}

document.getElementById('confirmRegisterBtn').addEventListener('click', function () {
    if (!selectedEventId) return;

    const buttonElement = selectedButton;
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('browse_events.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_register=1&event_id=' + selectedEventId
    })
    .then(response => response.json())
    .then(data => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
        modal.hide();

       if (data.success) {
    console.log('DEBUG - Message:', data.message);  // ← ADD THIS
    console.log('DEBUG - Type:', data.type);        // ← ADD THIS
    showAlert('success', data.message);
    const actionDiv = buttonElement.parentElement;
    
    // Use the 'type' field from PHP instead of message string
    if (data.type === 'waiting') {
        console.log('DEBUG - Setting waiting button');
        actionDiv.innerHTML = '<div class="btn-waiting"><i class="fas fa-hourglass-half"></i> On Waiting List</div>';
    } else if (data.type === 'registered') {
        console.log('DEBUG - Setting registered button');
        actionDiv.innerHTML = '<div class="btn-registered"><i class="fas fa-check-circle"></i> Already Registered</div>';
    } else {
        console.log('DEBUG - Setting default registered button');
        actionDiv.innerHTML = '<div class="btn-registered"><i class="fas fa-check-circle"></i> Already Registered</div>';
    }
} else {
            showAlert('error', data.message);
            buttonElement.disabled = false;
            if (buttonElement.innerText.includes('Waiting List')) {
                buttonElement.innerHTML = '<i class="fas fa-hourglass-half"></i> Join Waiting List';
            } else {
                buttonElement.innerHTML = '<i class="fas fa-check"></i> Register Now';
            }
        }
    })
    .catch(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
        modal.hide();
        showAlert('error', 'An error occurred. Please try again.');
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<i class="fas fa-check"></i> Register Now';
    });
});

function showAlert(type, message) {
    const alertDiv = document.getElementById('alertMessage');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

    alertDiv.innerHTML = '<div class="' + alertClass + '">' +
        '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' +
        message +
        '<button type="button" class="btn-close float-end" onclick="this.parentElement.remove()"></button>' +
        '</div>';

    setTimeout(() => {
        if (alertDiv.firstChild) alertDiv.firstChild.remove();
    }, 5000);
}
</script>

</body>
</html>