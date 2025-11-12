<?php
// reset_admin.php - Use this to reset admin password
include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Create or update admin user
$username = 'admin';
$password = 'admin123';
$email = 'admin@company.com';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if admin exists
$query = "SELECT id FROM admin_users WHERE username = :username";
$stmt = $db->prepare($query);
$stmt->bindParam(':username', $username);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    // Update existing admin
    $query = "UPDATE admin_users SET password = :password, email = :email WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':email', $email);
    
    if ($stmt->execute()) {
        echo "Admin password updated successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Error updating admin password.";
    }
} else {
    // Create new admin
    $query = "INSERT INTO admin_users (username, password, email) VALUES (:username, :password, :email)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':email', $email);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Error creating admin user.";
    }
}

echo "<br><a href='admin_login.php'>Go to Login</a>";
?>