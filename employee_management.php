<?php
session_start();

include_once 'config/database.php';
include_once 'includes/functions.php';

require_admin_login();

$database = new Database();
$db = $database->getConnection();

$feedback = null;
$feedbackType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employee') {
    $employee_id = sanitize_input($_POST['employee_id'] ?? '');
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $department = sanitize_input($_POST['department'] ?? '');
    $position = sanitize_input($_POST['position'] ?? '');
    $face_data = $_POST['face_data'] ?? '';

    if ($employee_id === '') {
        $feedback = 'Employee ID is required.';
        $feedbackType = 'error';
    } else {
        $employee = get_employee($db, $employee_id);

        if (!$employee) {
            $feedback = 'Employee not found.';
            $feedbackType = 'error';
        } else {
            $faceStorage = null;

            if ($face_data !== '') {
                $facePayload = build_face_storage_payload($face_data);
                if (!$facePayload) {
                    $feedback = 'Unable to process new face data. Please recapture and try again.';
                    $feedbackType = 'error';
                } else {
                    $faceStorage = $facePayload['storage'];
                }
            }

            if ($feedback === null) {
                try {
                    $queryParts = [
                        'full_name = :full_name',
                        'department = :department',
                        'position = :position'
                    ];

                    if ($faceStorage !== null) {
                        $queryParts[] = 'face_data = :face_data';
                    }

                    $query = "UPDATE employees SET " . implode(', ', $queryParts) . " WHERE employee_id = :employee_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':full_name', $full_name);
                    $stmt->bindParam(':department', $department);
                    $stmt->bindParam(':position', $position);
                    $stmt->bindParam(':employee_id', $employee_id);

                    if ($faceStorage !== null) {
                        $stmt->bindParam(':face_data', $faceStorage);
                    }

                    if ($stmt->execute()) {
                        log_activity($db, 'EMPLOYEE_UPDATE', "Employee updated: {$full_name} ({$employee_id})");
                        $feedback = 'Employee details updated successfully.';
                        $feedbackType = 'success';
                    } else {
                        $feedback = 'Unable to update employee details. Please try again.';
                        $feedbackType = 'error';
                    }
                } catch (PDOException $e) {
                    error_log("Database error updating employee: " . $e->getMessage());
                    $feedback = 'A database error occurred while updating the employee.';
                    $feedbackType = 'error';
                }
            }
        }
    }
}

$employees = get_all_employees($db);

function get_employee_face_image($face_data) {
    $payload = parse_face_payload($face_data);
    return $payload['image'] ?? '';
}

include_once 'includes/header.php';
?>

<h1 class="page-title">Manage Employees</h1>

<?php if ($feedback): ?>
    <div class="alert alert-<?php echo htmlspecialchars($feedbackType); ?>">
        <?php echo htmlspecialchars($feedback); ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Employee Directory</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Registered</th>
                    <th>Face Preview</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($employees) > 0): ?>
                    <?php foreach ($employees as $employee): ?>
                        <?php $faceImage = get_employee_face_image($employee['face_data']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($employee['department'])); ?></td>
                            <td><?php echo htmlspecialchars($employee['position']); ?></td>
                            <td><?php echo htmlspecialchars(format_date(substr($employee['registration_date'] ?? '', 0, 10))); ?></td>
                            <td>
                                <?php if ($faceImage): ?>
                                    <img src="<?php echo htmlspecialchars($faceImage); ?>" alt="Face preview" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                                <?php else: ?>
                                    <span style="color: var(--gray); font-size: 0.9rem;">No face data</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button 
                                    type="button" 
                                    class="btn edit-employee-btn" 
                                    data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                                    data-full-name="<?php echo htmlspecialchars($employee['full_name']); ?>"
                                    data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                    data-position="<?php echo htmlspecialchars($employee['position']); ?>"
                                    data-face-image="<?php echo htmlspecialchars($faceImage, ENT_QUOTES); ?>"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No employees registered yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" id="employee-edit-section">
    <h2>Edit Employee</h2>
    <p id="edit-status-message" style="margin-bottom: 1rem; color: var(--gray);">Select an employee above to edit their details.</p>
    <form method="POST" id="employee-edit-form" class="form-grid">
        <input type="hidden" name="action" value="update_employee">
        <input type="hidden" id="edit_face_data" name="face_data">
        <div class="form-group" style="flex: 1 1 200px;">
            <label for="edit_employee_id">Employee ID</label>
            <input type="text" id="edit_employee_id" name="employee_id" readonly required>
        </div>
        <div class="form-group" style="flex: 1 1 220px;">
            <label for="edit_full_name">Full Name</label>
            <input type="text" id="edit_full_name" name="full_name" required>
        </div>
        <div class="form-group" style="flex: 1 1 200px;">
            <label for="edit_department">Department</label>
            <select id="edit_department" name="department" required>
                <option value="">Select Department</option>
                <option value="hr">Human Resources</option>
                <option value="it">Information Technology</option>
                <option value="finance">Finance</option>
                <option value="marketing">Marketing</option>
                <option value="operations">Operations</option>
            </select>
        </div>
        <div class="form-group" style="flex: 1 1 200px;">
            <label for="edit_position">Position</label>
            <input type="text" id="edit_position" name="position" required>
        </div>
        <div class="form-group" style="flex: 1 1 240px;">
            <label>Face Preview</label>
            <img id="edit-face-preview" alt="Face preview" style="display: none; width: 160px; height: 160px; object-fit: cover; border-radius: 10px; border: 1px solid #ddd;">
        </div>
        <div class="form-group" style="flex: 1 1 280px;">
            <label>Capture New Face Data</label>
            <div class="camera-container" style="max-width: 260px;">
                <video id="video-edit" autoplay playsinline style="display: none;"></video>
                <canvas id="canvas-edit" style="display: none;"></canvas>
            </div>
            <div class="camera-controls">
                <button type="button" id="edit-capture-btn" class="btn">Capture New Face</button>
                <button type="button" id="edit-retake-btn" class="btn" style="background: var(--gray); display: none;">Revert</button>
            </div>
        </div>
        <div class="form-group" style="flex: 1 1 200px; align-self: flex-end;">
            <button type="submit" class="btn btn-success">Save Changes</button>
        </div>
    </form>
</div>

<?php include_once 'includes/footer.php'; ?>

