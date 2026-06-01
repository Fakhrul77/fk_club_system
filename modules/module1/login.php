<?php
session_start();

$host = '127.0.0.1';
$port = '3306';
$dbname = 'fk_club_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 1) header("Location: dashboard_admin.php");
    elseif ($_SESSION['user_role'] == 2) header("Location: dashboard_committee.php");
    else header("Location: dashboard_student.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password_input = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'Active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        if ($password_input == 'password123') {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role_id'];
            
            if ($user['role_id'] == 1) {
                header("Location: dashboard_admin.php");
            } elseif ($user['role_id'] == 2) {
                header("Location: dashboard_committee.php");
            } else {
                header("Location: dashboard_student.php");
            }
            exit();
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Invalid email or password";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --umpsa-blue: #003B5C;
            --umpsa-gold: #FDB813;
            --umpsa-dark-blue: #002147;
            --umpsa-light-blue: #E8F0F8;
        }
        body {
            background: linear-gradient(135deg, var(--umpsa-blue) 0%, var(--umpsa-dark-blue) 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('../../assets/images/fk4.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .logo-container {
            margin-bottom: 20px;
        }
        .logo {
            max-width: 80px;
            height: auto;
            margin-bottom: 10px;
        }
        h2 {
            text-align: center;
            color: var(--umpsa-blue);
            margin-bottom: 5px;
            font-size: 24px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--umpsa-gold);
            box-shadow: 0 0 0 3px rgba(253,184,19,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--umpsa-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: var(--umpsa-gold);
            color: var(--umpsa-dark-blue);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .forgot-password {
            text-align: right;
            margin-bottom: 20px;
        }
        .forgot-password a {
            color: var(--umpsa-gold);
            text-decoration: none;
            font-size: 13px;
        }
        .forgot-password a:hover {
            text-decoration: underline;
            color: var(--umpsa-blue);
        }
        .demo-box {
            background: var(--umpsa-light-blue);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
        }
        .info-alert {
            background: #d1ecf1;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }
        hr {
            margin: 20px 0;
            border-color: #eee;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <!-- Logo Section -->
        <div class="logo-container">
            <img src="../../assets/images/logo.png" alt="FK Logo" class="logo">
            <h2>FK Club System</h2>
        </div>
        <div class="subtitle">Student Club & Event Management System</div>
        
        <div class="info-alert">
            <i class="fas fa-info-circle"></i>
            <span>New student? Registration is done by Admin only. Contact FK Student Affairs.</span>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="email" name="email" class="form-control" placeholder="Email Address" required>
            <input type="password" name="password" class="form-control" placeholder="Password" required>
            
            <div class="forgot-password">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <hr>
        
        <div class="demo-box">
            <strong>📋 Demo Credentials:</strong><br>
            👑 Admin: admin@fk.umpsa.edu.my / password123<br>
            📋 Committee: sarah@student.umpsa.edu.my / password123<br>
            🎓 Student: ahmad@student.umpsa.edu.my / password123
        </div>
    </div>
</body>
</html>