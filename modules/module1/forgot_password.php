<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - FK Club System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-image: url('../../assets/images/FK4.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .forgot-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            text-align: center;
            backdrop-filter: blur(2px);
        }
        .icon {
            font-size: 60px;
            color: #FF6B35;
            margin-bottom: 20px;
        }
        h2 {
            color: #1E3A5F;
            margin-bottom: 15px;
        }
        .desc {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn-send {
            width: 100%;
            padding: 12px;
            background: #1E3A5F;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-send:hover {
            background: #FF6B35;
        }
        .back-link {
            margin-top: 20px;
            display: inline-block;
            color: #FF6B35;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        hr {
            margin: 20px 0;
            border-color: #eee;
        }
    </style>
</head>
<body>
    <div class="forgot-card">
        
        <h2>Forgot Password?</h2>
        <div class="desc">Enter your registered email address and we'll send you a link to reset your password.</div>
        
        <div class="success-message" id="successMsg">
            <i class="fas fa-check-circle"></i> Reset link sent to your email!
        </div>
        
        <form id="forgotForm">
            <input type="email" id="email" class="form-control" placeholder="Email Address" required>
            <button type="submit" class="btn-send">Send Reset Link</button>
        </form>
        
        <hr>
        
        <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
    
    <script>
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('email').value;
            if(email) {
                document.getElementById('successMsg').style.display = 'block';
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 2000);
            }
        });
    </script>
</body>
</html>