<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 1 && $_SESSION['user_role'] != 2)) {
    header("Location: ../module1/login.php");
    exit();
}

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$event_id) {
    header("Location: manage_events.php");
    exit();
}

// Get event details
$stmt = $pdo->prepare("SELECT * FROM event WHERE event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: manage_events.php");
    exit();
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
    
    if (empty($event_title) || empty($event_date) || empty($venue) || $max_participant <= 0) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE event SET 
                    event_title = ?, event_description = ?, event_date = ?, 
                    event_time = ?, venue = ?, max_participant = ?
                WHERE event_id = ?
            ");
            $stmt->execute([$event_title, $event_description, $event_date, $event_time, $venue, $max_participant, $event_id]);
            
            $success = "Event updated successfully!";
            
            // Refresh event data
            $stmt = $pdo->prepare("SELECT * FROM event WHERE event_id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch();
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
    <title>Edit Event - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --umpsa-blue: #003B5C; --umpsa-gold: #FDB813; --umpsa-dark-blue: #002147; --umpsa-light-blue: #E8F0F8; }
        body { background: var(--umpsa-light-blue); font-family: 'Segoe UI', sans-serif; }
        .sidebar { position: fixed; top: 0; left: 0; width: 260px; height: 100%; background: var(--umpsa-dark-blue); color: white; }
        .sidebar-header { padding: 20px; text-align: center; }
        .sidebar-menu a { display: block; padding: 12px 25px; color: white; text-decoration: none; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: var(--umpsa-gold); color: var(--umpsa-dark-blue); }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .form-card { background: white; border-radius: 16px; padding: 20px; max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-save { background: #28a745; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .alert-success { background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header h4, .sidebar-menu a span { display: none; } .main-content { margin-left: 70px; } .form-row { grid-template-columns: 1fr; } }
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
        <a href="manage_events.php"><i class="fas fa-calendar-alt"></i> Manage Events</a>
        <a href="../module2/club_redirect.php"><i class="fas fa-building"></i> <span>Manage Clubs</span></a>
        <a href="create_event.php"><i class="fas fa-plus-circle"></i> Create Event</a>
        <a href="event_registrations.php"><i class="fas fa-list-alt"></i> Registrations</a>
        <a href="../module1/profile.php"><i class="fas fa-user"></i> Profile</a>
    </div>
</div>

<div class="main-content">
    <div class="top-nav">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="../../logout.php">Logout</a>
    </div>

    <div class="form-card">
        <h3>Edit Event</h3>
        
        <?php if ($success): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Event Title</label>
                <input type="text" name="event_title" value="<?php echo htmlspecialchars($event['event_title']); ?>" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="event_description" rows="4"><?php echo htmlspecialchars($event['event_description']); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" name="event_date" value="<?php echo $event['event_date']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Event Time</label>
                    <input type="time" name="event_time" value="<?php echo $event['event_time']; ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Venue</label>
                    <input type="text" name="venue" value="<?php echo htmlspecialchars($event['venue']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Max Participants</label>
                    <input type="number" name="max_participant" value="<?php echo $event['max_participant']; ?>" required>
                </div>
            </div>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" class="btn-save">Update Event</button>
                <a href="manage_events.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>