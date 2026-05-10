<?php
session_start();
require_once __DIR__ . '/db_connection.php';

define('SITE_NAME', 'FK Student Club & Event Management System');
define('SITE_URL', 'http://localhost/fk_club_system/');

date_default_timezone_set('Asia/Kuala_Lumpur');

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}
?>