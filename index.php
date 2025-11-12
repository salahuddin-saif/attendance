<?php
include_once 'config/database.php';
include_once 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Get today's stats
$today = date('Y-m-d');
$query = "SELECT COUNT(*) as total_employees FROM employees";
$stmt = $db->prepare($query);
$stmt->execute();
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['total_employees'];

$query = "SELECT COUNT(DISTINCT employee_id) as present_today FROM attendance WHERE DATE(check_in) = :today";
$stmt = $db->prepare($query);
$stmt->bindParam(':today', $today);
$stmt->execute();
$present_today = $stmt->fetch(PDO::FETCH_ASSOC)['present_today'];

$absent_today = $total_employees - $present_today;
$attendance_rate = $total_employees > 0 ? round(($present_today / $total_employees) * 100, 1) : 0;
?>

<h1 class="page-title">Welcome to FaceAuth</h1>
<div class="card">
    <h2>Employee Attendance System with Face Recognition</h2>
    <p>This system allows employees to register with facial recognition and mark their attendance using face verification.</p>
    
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_employees; ?></div>
            <div class="stat-label">Total Employees</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $present_today; ?></div>
            <div class="stat-label">Present Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $absent_today; ?></div>
            <div class="stat-label">Absent Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
            <div class="stat-label">Attendance Rate</div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>