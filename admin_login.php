<?php
session_start();
include_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';

// Simple debug version - remove this in production
if ($_POST) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Debug output
    error_log("Login attempt: username=$username, password=$password");
    
    // Simple authentication for testing
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Invalid username or password!';
        
        // Check what's in the database
        $query = "SELECT * FROM admin_users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Found admin: " . print_r($admin, true));
            error_log("Password verify: " . (password_verify($password, $admin['password']) ? 'true' : 'false'));
        } else {
            error_log("No admin found with username: $username");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - FaceAuth</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i>👤</i> FaceAuth - Admin
                </div>
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="container">
            <div style="max-width: 400px; margin: 0 auto;">
                <h1 class="page-title">Admin Login</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <form method="POST">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="admin" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" value="admin123" required>
                        </div>
                        <button type="submit" class="btn btn-block">Login</button>
                    </form>
                    
                    <div style="margin-top: 1rem; text-align: center; font-size: 0.9rem; color: var(--gray);">
                        Default credentials: admin / admin123
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>