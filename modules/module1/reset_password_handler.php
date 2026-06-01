<?php
session_start();
require_once '../../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $email = $_POST['email'];
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password
    if (strlen($new_password) < 6) {
        $_SESSION['reset_error'] = "Password must be at least 6 characters.";
        header("Location: forgot_password.php?token=" . urlencode($token) . "&email=" . urlencode($email));
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("Location: forgot_password.php?token=" . urlencode($token) . "&email=" . urlencode($email));
        exit();
    }
    
    // Verify token is still valid
    $stmt = $pdo->prepare("
        SELECT * FROM password_resets 
        WHERE email = ? AND token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$email, $token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $_SESSION['reset_error'] = "Invalid or expired reset link.";
        header("Location: forgot_password.php");
        exit();
    }
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update user's password
    $stmt = $pdo->prepare("UPDATE users SET passwordHash = ? WHERE email = ?");
    $stmt->execute([$hashed_password, $email]);
    
    // Delete the used reset token
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
    
    $_SESSION['reset_success'] = "Password has been reset successfully! Please login with your new password.";
    header("Location: login.php");
    exit();
} else {
    // If someone tries to access this file directly
    header("Location: forgot_password.php");
    exit();
}
?>