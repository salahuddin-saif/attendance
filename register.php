<?php
include_once 'config/database.php';
include_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$isAjaxRequest = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (
    !empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
);

$responsePayload = [
    'success' => false,
    'message' => '',
    'errors'  => []
];

$flashMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = sanitize_input($_POST['employee_id'] ?? '');
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $department = sanitize_input($_POST['department'] ?? '');
    $position = sanitize_input($_POST['position'] ?? '');
    $face_data = $_POST['face_data'] ?? '';
    $facePayload = null;

    if ($employee_id === '') {
        $responsePayload['errors'][] = 'Employee ID is required.';
    }
    if ($full_name === '') {
        $responsePayload['errors'][] = 'Full name is required.';
    }
    if ($department === '') {
        $responsePayload['errors'][] = 'Department is required.';
    }
    if ($position === '') {
        $responsePayload['errors'][] = 'Position is required.';
    }
    if ($face_data === '') {
        $responsePayload['errors'][] = 'Face capture is required. Please capture your face before submitting.';
    }

    if ($face_data !== '') {
        $facePayload = build_face_storage_payload($face_data);
        if (!$facePayload) {
            $responsePayload['errors'][] = 'Unable to process captured face data. Please retake the photo and try again.';
        }
    }

    if (empty($responsePayload['errors'])) {
        if (employee_exists($db, $employee_id)) {
            $responsePayload['message'] = 'Employee ID already exists.';
        } else {
            try {
                $query = "INSERT INTO employees (employee_id, full_name, department, position, face_data) 
                          VALUES (:employee_id, :full_name, :department, :position, :face_data)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':employee_id', $employee_id);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':department', $department);
                $stmt->bindParam(':position', $position);
                $faceStorage = $facePayload ? $facePayload['storage'] : $face_data;
                $stmt->bindParam(':face_data', $faceStorage);

                if ($stmt->execute()) {
                    log_activity($db, 'EMPLOYEE_REGISTER', "New employee registered: $full_name ($employee_id)");
                    $responsePayload['success'] = true;
                    $responsePayload['message'] = 'Employee registered successfully.';
                } else {
                    $responsePayload['message'] = 'Unable to register employee. Please try again.';
                }
            } catch (PDOException $e) {
                error_log("Database error in employee registration: " . $e->getMessage());
                $responsePayload['message'] = 'A database error occurred. Please contact support.';
            }
        }
    } else {
        $responsePayload['message'] = 'Please fix the highlighted errors and try again.';
    }

    if ($responsePayload['success']) {
        $flashMessage = [
            'type' => 'success',
            'text' => $responsePayload['message']
        ];
    } elseif ($responsePayload['message'] || !empty($responsePayload['errors'])) {
        $errorText = $responsePayload['message'];
        if (!empty($responsePayload['errors'])) {
            $errorText = ($errorText ? $errorText . '<br>' : '') . implode('<br>', $responsePayload['errors']);
        }
        $flashMessage = [
            'type' => 'error',
            'text' => $errorText
        ];
    }

    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode($responsePayload);
        exit;
    }
}

include_once 'includes/header.php';
?>

<h1 class="page-title">Employee Registration</h1>

<?php if ($flashMessage): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type']); ?>">
        <?php echo $flashMessage['text']; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form id="registration-form" method="POST">
        <div class="form-group">
            <label for="employee_id">Employee ID</label>
            <input type="text" id="employee_id" name="employee_id" required>
        </div>
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" required>
        </div>
        <div class="form-group">
            <label for="department">Department</label>
            <select id="department" name="department" required>
                <option value="">Select Department</option>
                <option value="hr">Human Resources</option>
                <option value="it">Information Technology</option>
                <option value="finance">Finance</option>
                <option value="marketing">Marketing</option>
                <option value="operations">Operations</option>
            </select>
        </div>
        <div class="form-group">
            <label for="position">Position</label>
            <input type="text" id="position" name="position" required>
        </div>
        <div class="form-group">
            <label>Face Registration</label>
            <div class="camera-container">
                <video id="video-register" autoplay playsinline></video>
                <canvas id="canvas-register" style="display: none;"></canvas>
            </div>
            <div class="camera-controls">
                <button type="button" id="capture-btn" class="btn">Capture Face</button>
                <button type="button" id="retake-btn" class="btn" style="background: var(--gray); display: none;">Retake</button>
            </div>
        </div>
        <input type="hidden" id="face_data" name="face_data">
        <button type="submit" class="btn btn-block">Register Employee</button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        initializeCamera('video-register');
    });
</script>

<?php include_once 'includes/footer.php'; ?>