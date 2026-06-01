<?php
$host = '127.0.0.1';
$port = '3306';
$dbname = 'fk_club_system';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT 'Connected!' as status, NOW() as time");
    $result = $stmt->fetch();
    
    echo "✅ " . $result['status'] . "<br>";
    echo "Server time: " . $result['time'];
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>