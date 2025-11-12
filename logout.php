<?php
session_start();
include_once 'config/database.php';
include_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if (isset($_SESSION['admin_username'])) {
    log_activity($db, 'ADMIN_LOGOUT', "Admin logged out: " . $_SESSION['admin_username']);
}

session_destroy();
header('Location: admin_login.php');
exit;
?>