<?php
session_start();
include_once 'config/database.php';
include_once 'includes/functions.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$alerts = [];
$manualFormDefaults = [
    'employee_id' => '',
    'date' => date('Y-m-d'),
    'time' => date('H:i')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attendance') {
    $attendance_id = isset($_POST['attendance_id']) ? (int)$_POST['attendance_id'] : 0;

    if ($attendance_id <= 0) {
        $alerts[] = ['type' => 'error', 'message' => 'Invalid attendance record selected for deletion.'];
    } else {
        $attendanceRecord = get_attendance_record($db, $attendance_id);
        if (!$attendanceRecord) {
            $alerts[] = ['type' => 'error', 'message' => 'Attendance record not found or already deleted.'];
        } else {
            if (delete_attendance_record($db, $attendance_id)) {
                $employeeName = $attendanceRecord['full_name'] ?? $attendanceRecord['employee_id'];
                log_activity($db, 'ATTENDANCE_DELETE', "Deleted attendance record #{$attendance_id} for {$employeeName}");
                $alerts[] = ['type' => 'success', 'message' => 'Attendance record deleted successfully.'];
            } else {
                $alerts[] = ['type' => 'error', 'message' => 'Unable to delete attendance record. Please try again.'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_attendance_form'])) {
    $employee_id = sanitize_input($_POST['manual_employee_id'] ?? '');
    $attendance_date = sanitize_input($_POST['manual_date'] ?? $manualFormDefaults['date']);
    $attendance_time = sanitize_input($_POST['manual_time'] ?? $manualFormDefaults['time']);

    $manualFormDefaults['employee_id'] = $employee_id;
    $manualFormDefaults['date'] = $attendance_date;
    $manualFormDefaults['time'] = $attendance_time;

    if ($employee_id === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Employee is required.'];
    } else {
        $employee = get_employee($db, $employee_id);

        if (!$employee) {
            $alerts[] = ['type' => 'error', 'message' => 'Employee not found. Please check the employee ID.'];
        } else {
            $dateTime = DateTime::createFromFormat('Y-m-d H:i', $attendance_date . ' ' . $attendance_time);

            if (!$dateTime) {
                $alerts[] = ['type' => 'error', 'message' => 'Invalid date or time provided.'];
            } else {
                $attendanceDateString = $dateTime->format('Y-m-d');
                $checkInTimestamp = $dateTime->format('Y-m-d H:i:s');

                if (has_attendance_on_date($db, $employee_id, $attendanceDateString)) {
                    $alerts[] = ['type' => 'error', 'message' => 'Attendance already recorded for this employee on the selected date.'];
                } else {
                    if (mark_attendance($db, $employee_id, $checkInTimestamp)) {
                        log_activity($db, 'ATTENDANCE_MANUAL', "Manual attendance recorded for {$employee['full_name']} ({$employee_id}) on {$attendanceDateString}");
                        $alerts[] = ['type' => 'success', 'message' => "Attendance recorded for {$employee['full_name']} ({$employee_id})."];
                        $manualFormDefaults = [
                            'employee_id' => '',
                            'date' => date('Y-m-d'),
                            'time' => date('H:i')
                        ];
                    } else {
                        $alerts[] = ['type' => 'error', 'message' => 'Unable to record attendance. Please try again.'];
                    }
                }
            }
        }
    }
}

$stats = get_attendance_stats($db);
$attendance_records = get_todays_attendance($db);
$department_stats = get_department_attendance($db);
$employees = get_all_employees($db);

$defaultExportStart = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-01');
$defaultExportEnd = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : date('Y-m-d');
?>

<?php include_once 'includes/header.php'; ?>

<h1 class="page-title">Admin Panel 
    <small style="font-size: 0.6em; color: var(--gray);">
        Welcome, <?php echo $_SESSION['admin_username']; ?>! 
        <a href="logout.php" style="color: var(--danger); margin-left: 10px;">Logout</a>
    </small>
</h1>

<?php if (!empty($alerts)): ?>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert alert-<?php echo $alert['type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($alert['message']); ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <h2>Today's Attendance Overview</h2>
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['present_today']; ?>/<?php echo $stats['total_employees']; ?></div>
            <div class="stat-label">Present Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['attendance_rate_today']; ?>%</div>
            <div class="stat-label">Today's Rate</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['attendance_rate_week']; ?>%</div>
            <div class="stat-label">This Week</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['attendance_rate_month']; ?>%</div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
</div>

<div class="card">
    <h2>Department-wise Attendance</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Total Employees</th>
                    <th>Present Today</th>
                    <th>Attendance Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($department_stats) > 0): ?>
                    <?php foreach ($department_stats as $dept): ?>
                        <tr>
                            <td><?php echo strtoupper($dept['department']); ?></td>
                            <td><?php echo $dept['total_employees']; ?></td>
                            <td><?php echo $dept['present_today']; ?></td>
                            <td>
                                <?php 
                                $rate = $dept['total_employees'] > 0 ? 
                                    round(($dept['present_today'] / $dept['total_employees']) * 100, 1) : 0;
                                echo $rate . '%';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">No department data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Today's Attendance Records - <?php echo date('F j, Y'); ?></h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Check-in Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($attendance_records) > 0): ?>
                    <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['department']); ?></td>
                            <td><?php echo htmlspecialchars($record['position']); ?></td>
                            <td><?php echo format_time($record['check_in']); ?></td>
                            <td class="status-<?php echo $record['status']; ?>">
                                <?php echo ucfirst($record['status']); ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this attendance record?');">
                                    <input type="hidden" name="action" value="delete_attendance">
                                    <input type="hidden" name="attendance_id" value="<?php echo (int)$record['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No attendance records for today</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Quick Actions</h2>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <a href="register.php" class="btn">Register New Employee</a>
        <a href="attendance.php" class="btn btn-success">Mark Attendance</a>
        <a href="employee_management.php" class="btn">Manage Employees</a>
    </div>
</div>

<div class="card">
    <h2>Manual Attendance Entry</h2>
    <form method="POST" class="form-grid">
        <input type="hidden" name="manual_attendance_form" value="1">
        <div class="form-group">
            <label for="manual_employee_id">Employee</label>
            <select id="manual_employee_id" name="manual_employee_id" required>
                <option value="">Select Employee</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo htmlspecialchars($employee['employee_id']); ?>" <?php echo $manualFormDefaults['employee_id'] === $employee['employee_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($employee['full_name']); ?> (<?php echo htmlspecialchars($employee['employee_id']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="manual_date">Date</label>
            <input type="date" id="manual_date" name="manual_date" value="<?php echo htmlspecialchars($manualFormDefaults['date']); ?>" required>
        </div>
        <div class="form-group">
            <label for="manual_time">Check-in Time</label>
            <input type="time" id="manual_time" name="manual_time" value="<?php echo htmlspecialchars($manualFormDefaults['time']); ?>" required>
        </div>
        <div class="form-group" style="align-self: flex-end;">
            <button type="submit" class="btn btn-success">Record Attendance</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Attendance Export</h2>
    <form id="export-form" action="export_attendance.php" method="GET" class="form-grid">
        <div class="form-group">
            <label for="start_date">Start Date</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($defaultExportStart); ?>" required>
        </div>
        <div class="form-group">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($defaultExportEnd); ?>" required>
        </div>
        <div class="form-group" style="align-self: flex-end;">
            <button type="submit" class="btn btn-danger">Download CSV</button>
        </div>
    </form>
</div>

<?php include_once 'includes/footer.php'; ?>