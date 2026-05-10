<?php
session_start();

// Database connection - SIMPLE version that works
$host = '127.0.0.1';
$dbname = 'fk_club_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_ok = true;
} catch(PDOException $e) {
    $db_ok = false;
    $db_error = $e->getMessage();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 1) header("Location: dashboard_admin.php");
    elseif ($_SESSION['user_role'] == 2) header("Location: dashboard_committee.php");
    else header("Location: dashboard_student.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    
    if ($db_ok) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'Active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Verify password (using password_verify for bcrypt)
        if ($user && password_verify($pass, $user['passwordHash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role_id'];
            
            // Redirect based on role
            if ($user['role_id'] == 1) header("Location: dashboard_admin.php");
            elseif ($user['role_id'] == 2) header("Location: dashboard_committee.php");
            else header("Location: dashboard_student.php");
            exit();
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Database connection failed";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1E3A5F 0%, #FF6B35 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .login-card h2 {
            text-align: center;
            color: #1E3A5F;
            margin-bottom: 10px;
        }
        .login-card .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
        }
        .btn-login {
            background: #1E3A5F;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
        }
        .btn-login:hover {
            background: #FF6B35;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .demo-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🏛️ FK Club System</h2>
        <div class="subtitle">Student Club & Event Management</div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!$db_ok): ?>
            <div class="error">⚠️ Database Error: <?php echo $db_error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="email" name="email" class="form-control" placeholder="Email Address" required>
            <input type="password" name="password" class="form-control" placeholder="Password" required>
            <button type="submit" class="btn-login">🔐 Login</button>
        </form>
        
        <div class="demo-info">
            <strong>Demo Credentials:</strong><br>
            Admin: admin@fk.umpsa.edu.my / password123<br>
            Student: ahmad@student.umpsa.edu.my / password123
        </div>
    </div>
</body>
</html>