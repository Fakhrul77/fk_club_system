<?php
session_start();
session_destroy();
header("Location: modules/module1/login.php");
exit();
?>