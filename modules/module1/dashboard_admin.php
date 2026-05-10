<?php
$page_title = 'Admin Dashboard';
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    redirect('login.php');
}

// Get statistics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 3")->fetchColumn();
$totalClubs = $pdo->query("SELECT COUNT(*) FROM club WHERE status = 'Active'")->fetchColumn();
$totalEvents = $pdo->query("SELECT COUNT(*) FROM event WHERE status = 'UPCOMING'")->fetchColumn();
$totalCommittee = $pdo->query("SELECT COUNT(*) FROM club_committee")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { background-color: #1E3A5F; min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; margin: 5px 0; border-radius: 8px; }
        .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.1); color: white; }
        .sidebar .nav-link.active { background-color: #FF6B35; color: white; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card i { font-size: 40px; color: #FF6B35; margin-bottom: 10px; }
        .stat-card h3 { font-size: 28px; font-weight: bold; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar p-0">
                <div class="position-sticky pt-3">
                    <h5 class="text-white text-center py-3">FK Club System</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-building"></i> Manage Clubs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Admin Dashboard</h1>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo $_SESSION['user_name']; ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-users"></i>
                            <h3><?php echo $totalStudents; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-building"></i>
                            <h3><?php echo $totalClubs; ?></h3>
                            <p>Active Clubs</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-calendar-alt"></i>
                            <h3><?php echo $totalEvents; ?></h3>
                            <p>Upcoming Events</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <i class="fas fa-user-tie"></i>
                            <h3><?php echo $totalCommittee; ?></h3>
                            <p>Committee Members</p>
                        </div>
                    </div>
                </div>

                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Welcome to FK Club System! Your database is connected and working.
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>