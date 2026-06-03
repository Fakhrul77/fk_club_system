<?php
session_start();
require_once '../../includes/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../module1/login.php");
    exit();
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if ($event_id <= 0) {
    die("Invalid event ID.");
}

// Get return URL (where to go back)
$return_url = isset($_GET['return']) ? $_GET['return'] : '../module3/my_registrations.php';

// Get event details
$stmt = $pdo->prepare("SELECT event_title, event_date, event_time, venue FROM event WHERE event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    die("Event not found.");
}

// Create QR code data
$qr_data = "Event: " . $event['event_title'] . "\n";
$qr_data .= "Date: " . date('d M Y', strtotime($event['event_date'])) . "\n";
$qr_data .= "Time: " . date('h:i A', strtotime($event['event_time'])) . "\n";
$qr_data .= "Venue: " . $event['venue'];

// Use QuickChart API for QR generation
$qr_url = "https://quickchart.io/qr?text=" . urlencode($qr_data) . "&size=250&margin=2";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event QR Code - <?php echo htmlspecialchars($event['event_title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, rgba(0, 59, 92, 0.85), rgba(0, 33, 71, 0.9)), url('../../assets/images/fk4.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .qr-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }

        .qr-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .qr-header {
            background: linear-gradient(135deg, #003B5C, #002147);
            color: white;
            padding: 25px 30px;
            text-align: center;
        }

        .qr-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .qr-header p {
            margin: 8px 0 0;
            opacity: 0.8;
            font-size: 13px;
        }

        .qr-body {
            padding: 35px 30px;
            text-align: center;
        }

        .qr-code-wrapper {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: inline-block;
            width: 100%;
        }

        .qr-code-wrapper img {
            width: 220px;
            height: 220px;
            margin: 0 auto;
            display: block;
        }

        .event-title {
            font-size: 20px;
            font-weight: 700;
            color: #003B5C;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #FDB813;
            display: inline-block;
        }

        .info-grid {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
        }

        .info-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: #E8F0F8;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .info-icon i {
            font-size: 18px;
            color: #003B5C;
        }

        .info-text {
            flex: 1;
        }

        .info-label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #333;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 10px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #003B5C, #002147);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 59, 92, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .footer-note {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .btn-group, .footer-note {
                display: none;
            }
            .qr-card {
                box-shadow: none;
            }
            .qr-header {
                background: #003B5C;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 480px) {
            .qr-body {
                padding: 25px 20px;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="qr-container">
        <div class="qr-card">
            <div class="qr-header">
                <h2><i class="fas fa-qrcode"></i> Event QR Code</h2>
                <p>Scan to verify attendance</p>
            </div>

            <div class="qr-body">
                <div class="event-title">
                    <?php echo htmlspecialchars($event['event_title']); ?>
                </div>

                <div class="qr-code-wrapper">
                    <img src="<?php echo $qr_url; ?>" alt="QR Code" id="qrImage">
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="info-text">
                            <div class="info-label">Date</div>
                            <div class="info-value"><?php echo date('l, d F Y', strtotime($event['event_date'])); ?></div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-text">
                            <div class="info-label">Time</div>
                            <div class="info-value"><?php echo date('h:i A', strtotime($event['event_time'])); ?></div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-location-dot"></i>
                        </div>
                        <div class="info-text">
                            <div class="info-label">Venue</div>
                            <div class="info-value"><?php echo htmlspecialchars($event['venue']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print QR Code
                    </button>
                    <a href="<?php echo htmlspecialchars($return_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <div class="footer-note">
                <i class="fas fa-shield-alt"></i> Present this QR code at the event entrance for verification
            </div>
        </div>
    </div>

    <script>
        // Add loading effect for QR image
        const qrImg = document.getElementById('qrImage');
        if (qrImg) {
            qrImg.addEventListener('load', function() {
                this.style.opacity = '1';
            });
            qrImg.style.opacity = '0';
            qrImg.style.transition = 'opacity 0.3s ease';
        }
    </script>
</body>
</html>