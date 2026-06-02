<?php
session_start();
require_once '../../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header("Location: ../module1/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if student is already a member of any club
$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership WHERE user_id = ? AND status = 'Active'");
$stmt->execute([$user_id]);
$clubsJoined = $stmt->fetchColumn();

// Check if student has any pending application
$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_membership_applications WHERE user_id = ? AND status = 'Pending'");
$stmt->execute([$user_id]);
$pendingApps = $stmt->fetchColumn();

// Get the club the student is currently a member of (if any)
$currentClub = null;
if ($clubsJoined > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, cm.joinDate, cm.status as membership_status
        FROM club_membership cm 
        JOIN club c ON cm.club_id = c.club_id 
        WHERE cm.user_id = ? AND cm.status = 'Active' 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $currentClub = $stmt->fetch();
}

// Get pending application details if any
$pendingApplication = null;
if ($pendingApps > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, c.clubName, c.club_id, c.clubCategory, c.clubDescription
        FROM club_membership_applications a
        JOIN club c ON a.club_id = c.club_id
        WHERE a.user_id = ? AND a.status = 'Pending'
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $pendingApplication = $stmt->fetch();
}

// Check if student has any rejected application
$stmt = $pdo->prepare("
    SELECT a.*, c.clubName, c.club_id, c.clubCategory, c.clubDescription
    FROM club_membership_applications a
    JOIN club c ON a.club_id = c.club_id
    WHERE a.user_id = ? AND a.status = 'Rejected'
    ORDER BY a.application_date DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$rejectedApplication = $stmt->fetch();

// Handle dismiss rejected message
if (isset($_GET['clear_rejected'])) {
    // Option 1: Delete the rejected record
    $stmt = $pdo->prepare("DELETE FROM club_membership_applications WHERE user_id = ? AND status = 'Rejected'");
    $stmt->execute([$user_id]);
    header("Location: club_dashboard_student.php");
    exit();
    
    // OR Option 2: Just mark it as seen by adding a 'seen' column to database
    // $stmt = $pdo->prepare("UPDATE club_membership_applications SET seen = 1 WHERE user_id = ? AND status = 'Rejected'");
}

// Get upcoming events for student's club (if member)
$upcomingEvents = [];
if ($clubsJoined > 0 && $currentClub) {
    $stmt = $pdo->prepare("
        SELECT e.*, c.clubName
        FROM event e
        JOIN club c ON e.club_id = c.club_id
        WHERE e.club_id = ? AND e.event_date >= CURDATE() AND e.status = 'UPCOMING'
        ORDER BY e.event_date ASC
        LIMIT 5
    ");
    $stmt->execute([$currentClub['club_id']]);
    $upcomingEvents = $stmt->fetchAll();
}

// Get committee members of the club (if member)
$committeeMembers = [];
if ($clubsJoined > 0 && $currentClub) {
    $stmt = $pdo->prepare("
        SELECT u.name, cp.positionName
        FROM club_committee cc
        JOIN users u ON cc.user_id = u.user_id
        LEFT JOIN committee_position cp ON cc.position_id = cp.position_id
        WHERE cc.club_id = ? AND cc.status = 'Active'
        ORDER BY cp.position_id
        LIMIT 5
    ");
    $stmt->execute([$currentClub['club_id']]);
    $committeeMembers = $stmt->fetchAll();
}

// Get all active clubs (excluding the one student is already in)
if ($clubsJoined > 0) {
    $clubs = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM club_membership WHERE club_id = c.club_id AND status = 'Active') as member_count,
               (SELECT COUNT(*) FROM event WHERE club_id = c.club_id AND status = 'UPCOMING') as upcoming_events
        FROM club c 
        WHERE c.status = 'Active' AND c.club_id != ?
        ORDER BY c.clubName
    ");
    $clubs->execute([$currentClub['club_id']]);
} else {
    $clubs = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM club_membership WHERE club_id = c.club_id AND status = 'Active') as member_count,
               (SELECT COUNT(*) FROM event WHERE club_id = c.club_id AND status = 'UPCOMING') as upcoming_events
        FROM club c 
        WHERE c.status = 'Active'
        ORDER BY c.clubName
    ");
}
$clubsList = $clubs->fetchAll();

// Handle club application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    $club_id = (int)$_POST['club_id'];
    $reason = trim($_POST['reason']);
    $motivation = trim($_POST['motivation']);
    
    $errors = [];
    if (empty($reason)) $errors[] = "Please provide a reason for joining.";
    if (empty($motivation)) $errors[] = "Please provide your motivation.";
    
    if (empty($errors)) {
        $checkMember = $pdo->prepare("SELECT * FROM club_membership WHERE user_id = ? AND status = 'Active'");
        $checkMember->execute([$user_id]);
        
        if ($checkMember->fetch()) {
            $error = "You are already a member of a club! You can only join one club at a time.";
        } else {
            $checkPending = $pdo->prepare("SELECT * FROM club_membership_applications WHERE user_id = ? AND status = 'Pending'");
            $checkPending->execute([$user_id]);
            
            if ($checkPending->fetch()) {
                $error = "You already have a pending application! Please wait for approval.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO club_membership_applications (club_id, user_id, status, application_date, reason, motivation) 
                    VALUES (?, ?, 'Pending', CURDATE(), ?, ?)
                ");
                $stmt->execute([$club_id, $user_id, $reason, $motivation]);
                header("Location: club_dashboard_student.php?msg=applied");
                exit();
            }
        }
    } else {
        $error = implode("<br>", $errors);
        $selected_club_id = $club_id;
    }
}

// Handle cancel application
if (isset($_GET['cancel'])) {
    $stmt = $pdo->prepare("DELETE FROM club_membership_applications WHERE user_id = ? AND status = 'Pending'");
    $stmt->execute([$user_id]);
    header("Location: club_dashboard_student.php?msg=cancelled");
    exit();
}

// Handle leave club
if (isset($_GET['leave'])) {
    $stmt = $pdo->prepare("DELETE FROM club_membership WHERE user_id = ? AND status = 'Active'");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("DELETE FROM club_membership_applications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: club_dashboard_student.php?msg=left");
    exit();
}

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'applied') $message = '<div class="alert alert-success">✅ Application submitted successfully! Please wait for committee approval.</div>';
    if ($_GET['msg'] == 'cancelled') $message = '<div class="alert alert-info">📝 Application cancelled. You can now apply to other clubs.</div>';
    if ($_GET['msg'] == 'left') $message = '<div class="alert alert-info">👋 You have left the club. You can now join another club.</div>';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Club Dashboard - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --umpsa-blue: #003B5C; --umpsa-gold: #FDB813; --umpsa-dark-blue: #002147; --umpsa-light-blue: #E8F0F8; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--umpsa-light-blue); overflow-x: hidden; }
        
        /* ========== SIDEBAR - FIXED SPACING ========== */
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
        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }
        
        /* ========== TOP NAVBAR ========== */
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
        .welcome-text {
            font-size: 16px;
            font-weight: 500;
        }
        .badge-role {
            background: var(--umpsa-gold);
            color: var(--umpsa-dark-blue);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        
        /* ========== CLUB CARDS ========== */
        .club-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            height: 100%;
        }
        .club-card:hover {
            transform: translateY(-3px);
        }
        .club-name {
            font-size: 18px;
            font-weight: bold;
            color: var(--umpsa-blue);
        }
        .btn-apply {
            background: var(--umpsa-blue);
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
        }
        .btn-apply:hover {
            background: var(--umpsa-gold);
            color: var(--umpsa-dark-blue);
        }
        .member-badge {
            background: var(--umpsa-gold);
            color: var(--umpsa-dark-blue);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        .info-box {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-leave {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
        }
        .btn-leave:hover {
            background: #c82333;
        }
        .committee-tag {
            background: var(--umpsa-light-blue);
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            margin: 3px;
            font-size: 12px;
        }
        .my-club-header {
            background: linear-gradient(135deg, var(--umpsa-blue), var(--umpsa-dark-blue));
            color: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        /* ========== MODAL ========== */
        .application-modal .modal-content {
            border-radius: 15px;
        }
        .application-modal .modal-header {
            background: var(--umpsa-dark-blue);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h4, .sidebar-header p, .sidebar-menu a span {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>

<!-- ========== SIDEBAR ========== -->
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
        <a href="club_dashboard_student.php" class="active">
            <i class="fas fa-building"></i> <span>Browse Clubs</span>
        </a>
        <a href="../module3/browse_events.php">
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

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">
    <div class="top-nav">
        <div class="welcome-text">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            <span class="badge-role">Student</span>
        </div>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <h2 class="mb-4" style="color: var(--umpsa-blue);"><i class="fas fa-tachometer-alt"></i> Club Management</h2>
    <?php echo $message; if (isset($error)) echo '<div class="alert alert-danger">' . $error . '</div>'; ?>

    <!-- ========== IF STUDENT IS A MEMBER OF A CLUB ========== -->
    <?php if ($clubsJoined > 0 && $currentClub): ?>
        <div class="my-club-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><i class="fas fa-check-circle"></i> My Club</h3>
                    <h1 class="display-5"><?php echo htmlspecialchars($currentClub['clubName']); ?></h1>
                    <p class="mb-2"><?php echo htmlspecialchars($currentClub['clubCategory'] ?? 'General'); ?> Club</p>
                    <p><small>Member since: <?php echo date('d F Y', strtotime($currentClub['joinDate'])); ?></small></p>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-light" onclick="openLeaveClubModal()">
                  <i class="fas fa-sign-out-alt"></i> Leave Club
                   </button>
                </div>
            </div>
        </div>

        <!-- Club Information -->
        <div class="row">
            <div class="col-md-8">
                <div class="club-card">
                    <h5><i class="fas fa-info-circle"></i> About <?php echo htmlspecialchars($currentClub['clubName']); ?></h5>
                    <p><?php echo nl2br(htmlspecialchars($currentClub['clubDescription'] ?? 'No description available.')); ?></p>
                    
                    <?php if ($currentClub['advisorName']): ?>
                        <hr>
                        <p><strong><i class="fas fa-chalkboard-user"></i> Advisor:</strong> <?php echo htmlspecialchars($currentClub['advisorName']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="club-card">
                    <h5><i class="fas fa-user-tie"></i> Committee Members</h5>
                    <?php if (empty($committeeMembers)): ?>
                        <p class="text-muted">No committee members listed.</p>
                    <?php else: ?>
                        <?php foreach ($committeeMembers as $cm): ?>
                            <div class="committee-tag">
                                <strong><?php echo htmlspecialchars($cm['positionName'] ?? 'Member'); ?></strong><br>
                                <?php echo htmlspecialchars($cm['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <?php if (!empty($upcomingEvents)): ?>
            <div class="club-card">
                <h5><i class="fas fa-calendar-alt"></i> Upcoming Events</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr><th>Event Name</th><th>Date</th><th>Time</th><th>Venue</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingEvents as $event): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($event['event_title']); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($event['event_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['venue'] ?? 'TBA'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="alert alert-secondary">
            <i class="fas fa-info-circle"></i> You are currently a member of <strong><?php echo htmlspecialchars($currentClub['clubName']); ?></strong>. 
            To join another club, you must leave your current club first.
        </div>

           <!-- ========== IF STUDENT HAS PENDING APPLICATION ========== -->
    <?php elseif ($pendingApplication): ?>
        <div class="info-box">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5><i class="fas fa-hourglass-half text-warning"></i> Pending Application</h5>
                    <p>You have applied to join <strong><?php echo htmlspecialchars($pendingApplication['clubName']); ?></strong></p>
                    <p><strong>Club Category:</strong> <?php echo htmlspecialchars($pendingApplication['clubCategory'] ?? 'General'); ?></p>
                    <p><strong>Application date:</strong> <?php echo date('d M Y', strtotime($pendingApplication['application_date'])); ?></p>
                    <?php if ($pendingApplication['reason']): ?>
                        <p><strong>Your reason for joining:</strong><br><?php echo nl2br(htmlspecialchars($pendingApplication['reason'])); ?></p>
                    <?php endif; ?>
                    <p class="small text-muted mt-2">Please wait for committee approval. You cannot apply to other clubs while pending.</p>
                </div>
                <button type="button" class="btn btn-danger" onclick="openCancelApplicationModal()">
                    <i class="fas fa-times"></i> Cancel Application
                </button>
            </div>
        </div>

    <!-- ========== IF STUDENT HAS REJECTED APPLICATION ========== -->
    <?php elseif ($rejectedApplication): ?>
        <div class="info-box" style="background: #f8d7da; border-left-color: #dc3545;">
            <div class="d-flex justify-content-between align-items-start">
                <div style="flex: 1;">
                    <h5 style="color: #721c24;"><i class="fas fa-times-circle" style="color: #dc3545;"></i> Application Rejected</h5>
                    <p>Your application to join <strong><?php echo htmlspecialchars($rejectedApplication['clubName']); ?></strong> was not approved.</p>
                    
                    <!-- Show rejection reason -->
                    <div style="background: white; padding: 12px 15px; border-radius: 10px; margin-top: 10px; border-left: 3px solid #dc3545;">
                        <strong><i class="fas fa-info-circle"></i> Reason for rejection:</strong><br>
                        <?php echo nl2br(htmlspecialchars($rejectedApplication['rejection_reason'] ?? 'No specific reason provided.')); ?>
                    </div>
                    
                    <?php if ($rejectedApplication['committee_remarks']): ?>
                        <div style="background: #fff3cd; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 13px;">
                            <i class="fas fa-comment"></i> <strong>Committee notes:</strong> <?php echo nl2br(htmlspecialchars($rejectedApplication['committee_remarks'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <p class="small text-muted mt-3">
                        <i class="fas fa-lightbulb"></i> Tip: You can now apply to other clubs. Learn from this feedback and try again!
                    </p>
                </div>
                <div>
                    <a href="?clear_rejected=1" class="btn btn-outline-danger btn-sm">
                   <i class="fas fa-times"></i> Dismiss
                </a>
                </div>
            </div>
        </div>

    <!-- ========== IF STUDENT HAS NO CLUB, NO PENDING, NO REJECTED ========== -->
    <?php else: ?>
        <div class="info-box">
            <i class="fas fa-info-circle"></i> You are not a member of any club yet. You can only join ONE club. Browse and apply below.
        </div>

        <h4 class="mb-3 mt-3"><i class="fas fa-search"></i> Available Clubs</h4>
        <div class="row">
            <?php foreach ($clubsList as $club): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="club-card">
                        <div class="club-name"><?php echo htmlspecialchars($club['clubName']); ?></div>
                        <div class="text-muted small mb-2">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($club['clubCategory'] ?? 'General'); ?>
                            | <i class="fas fa-users"></i> <?php echo $club['member_count']; ?> members
                        </div>
                        <p class="small text-muted"><?php echo htmlspecialchars(substr($club['clubDescription'] ?? '', 0, 100)); ?>...</p>
                        <button class="btn-apply" onclick="showApplicationForm(<?php echo $club['club_id']; ?>, '<?php echo htmlspecialchars($club['clubName']); ?>')">
                            <i class="fas fa-hand-paper"></i> Apply to Join
                        </button>
                        <a href="club_view.php?id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-outline-secondary ms-2">Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Application Modal -->
<div class="modal fade application-modal" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> <span id="modalClubName"></span> - Membership Application</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="club_id" id="selectedClubId">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Please explain why you want to join this club. 
                        Your application will be reviewed by the club committee.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Why do you want to join this club? <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required 
                                  placeholder="Example: I am passionate about computing and want to improve my skills..."></textarea>
                        <small class="text-muted">Explain your interest in this club's activities.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">What motivates you to contribute to this club? <span class="text-danger">*</span></label>
                        <textarea name="motivation" class="form-control" rows="3" required 
                                  placeholder="Example: I want to organize events, help fellow students, and contribute to club growth..."></textarea>
                        <small class="text-muted">Describe how you plan to actively participate and add value to the club.</small>
                    </div>
                    
                    <div class="alert alert-warning small">
                        <i class="fas fa-exclamation-triangle"></i> Note: You can only be a member of ONE club at a time. 
                        Once approved, you will be added as an official member.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_application" class="btn btn-primary" style="background: var(--umpsa-blue);">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Application Modal -->
<div class="modal fade" id="cancelApplicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden;">

            <!-- Header -->
            <div style="background: var(--umpsa-dark-blue); color: white; padding: 15px;">
                <h5 style="margin: 0; font-weight: 600;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--umpsa-gold);"></i>
                    Confirm Application Cancellation
                </h5>
            </div>

            <!-- Body -->
            <div style="padding: 20px; font-size: 14px; color: #333;">
                <p style="margin-bottom: 10px;">
                    You are about to cancel your club application.
                </p>

                <div style="background: #fff3cd; padding: 10px; border-radius: 8px; font-size: 13px;">
                    ⚠️ If you cancel, you will need to submit a new application to join a club again.
                </div>
            </div>

            <!-- Footer -->
            <div style="padding: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Keep Application
                </button>

                <a href="?cancel=1" class="btn btn-danger">
                    Yes, Cancel
                </a>
            </div>

        </div>
    </div>
</div>

<!-- Leave Club Modal -->
<div class="modal fade" id="leaveClubModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden;">

            <!-- Header -->
            <div style="background: var(--umpsa-dark-blue); color: white; padding: 15px;">
                <h5 style="margin: 0; font-weight: 600;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--umpsa-gold);"></i>
                    Confirm Leave Club
                </h5>
            </div>

            <!-- Body -->
            <div style="padding: 20px; font-size: 14px; color: #333;">
                <p>
                    You are about to leave your current club:
                </p>

                <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; font-weight: 600;">
                    <?php echo htmlspecialchars($currentClub['clubName'] ?? ''); ?>
                </div>

                <div style="margin-top: 12px; background: #fff3cd; padding: 10px; border-radius: 8px; font-size: 13px;">
                    ⚠️ After leaving, you will be able to apply to another club, but you will lose your current membership.
                </div>
            </div>

            <!-- Footer -->
            <div style="padding: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>

                <a href="?leave=1" class="btn btn-danger">
                    Yes, Leave Club
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showApplicationForm(clubId, clubName) {
        document.getElementById('selectedClubId').value = clubId;
        document.getElementById('modalClubName').innerText = clubName;
        new bootstrap.Modal(document.getElementById('applicationModal')).show();
    }
</script>
<script>
function openCancelApplicationModal() {
    const modal = new bootstrap.Modal(document.getElementById('cancelApplicationModal'));
    modal.show();
}
</script>

<script>
function openLeaveClubModal() {
    new bootstrap.Modal(document.getElementById('leaveClubModal')).show();
}
</script>
</body>
</html>