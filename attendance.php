<?php
include_once 'config/database.php';
include_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_attendance') {
    $database = new Database();
    $db = $database->getConnection();

    $response = [
        'success' => false,
        'message' => 'Unable to mark attendance.'
    ];

    if (isset($_POST['manual_entry']) && $_POST['manual_entry'] === 'true') {
        $employee_id = sanitize_input($_POST['employee_id'] ?? '');

        if ($employee_id === '') {
            $response['message'] = 'Employee ID is required for manual entry.';
        } else {
            $employee = get_employee($db, $employee_id);
            if (!$employee) {
                $response['message'] = 'Employee not found. Please verify the Employee ID.';
            } elseif (has_attendance_today($db, $employee_id)) {
                $response['message'] = 'Attendance already marked for today!';
            } elseif (mark_attendance($db, $employee_id)) {
                log_activity($db, 'ATTENDANCE_MANUAL', "Manual attendance marked for: {$employee['full_name']} ({$employee_id})");
                $response['success'] = true;
                $response['message'] = "Attendance marked for {$employee['full_name']}";
                $response['time'] = date('Y-m-d H:i:s');
            } else {
                $response['message'] = 'Error marking attendance manually.';
            }
        }
    } else {
        $face_data = $_POST['face_data'] ?? '';

        if ($face_data === '') {
            $response['message'] = 'Capture failed. Please try again.';
        } else {
            $employee = recognize_face($face_data, $db);

            if ($employee) {
                $employee_id = $employee['employee_id'];
                $employee_name = $employee['full_name'];

                if (has_attendance_today($db, $employee_id)) {
                    $response['message'] = 'Attendance already marked for today!';
                } elseif (mark_attendance($db, $employee_id)) {
                    log_activity($db, 'ATTENDANCE_MARKED', "Attendance marked for: $employee_name ($employee_id)");
                    $response['success'] = true;
                    $response['message'] = "Attendance marked for $employee_name";
                    $response['time'] = date('Y-m-d H:i:s');
                } else {
                    $response['message'] = 'Error marking attendance.';
                }
            } else {
                $response['message'] = 'Face not recognized. Please try again or contact administrator.';
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

include_once 'includes/header.php';
?>

<h1 class="page-title">Mark Attendance</h1>
<div class="card">
    <form id="attendance-form">
        <div class="camera-container">
            <video id="video-attendance" autoplay playsinline></video>
            <canvas id="canvas-attendance" style="display: none;"></canvas>
        </div>
        <div class="camera-controls">
            <button type="submit" id="verify-btn" class="btn">Verify & Mark Attendance</button>
        </div>
        <div id="attendance-result" style="margin-top: 1.5rem;"></div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        initializeCamera('video-attendance');
    });
</script>

<?php include_once 'includes/footer.php'; ?>