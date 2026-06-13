
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - FK Club System</title>
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
        .forgot-card {
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
        .icon {
            font-size: 50px;
            color: var(--umpsa-gold);
            margin-bottom: 10px;
        }
        h2 {
            color: var(--umpsa-blue);
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
        }
        .btn-send {
            width: 100%;
            padding: 12px;
            background: var(--umpsa-blue);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-send:hover {
            background: var(--umpsa-gold);
            color: var(--umpsa-dark-blue);
        }
        .back-link {
            margin-top: 20px;
            display: inline-block;
            color: var(--umpsa-gold);
            text-decoration: none;
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
        <div class="logo-container">
            <img src="../../assets/images/logo.png" alt="FK Logo" class="logo">
        </div>
        
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