<?php
// functions.php - Common functions for the attendance system

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    if (!isset($data)) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Extract base64 binary image data from a data URL or raw base64 string
 */
function extract_base64_image_data($face_data) {
    if (!$face_data) {
        return null;
    }

    if (strpos($face_data, 'base64,') !== false) {
        $parts = explode('base64,', $face_data, 2);
        $face_data = $parts[1];
    }

    $face_data = str_replace(' ', '+', $face_data);
    $binary = base64_decode($face_data);

    return $binary !== false ? $binary : null;
}

/**
 * Generate a simple grayscale signature for a captured face frame
 */
function generate_face_signature($face_data) {
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }

    $binary = extract_base64_image_data($face_data);
    if (!$binary) {
        return null;
    }

    $source = @imagecreatefromstring($binary);
    if (!$source) {
        return null;
    }

    $signatureSize = 16;
    $resized = imagecreatetruecolor($signatureSize, $signatureSize);
    imagecopyresampled(
        $resized,
        $source,
        0,
        0,
        0,
        0,
        $signatureSize,
        $signatureSize,
        imagesx($source),
        imagesy($source)
    );

    $signature = [];
    for ($y = 0; $y < $signatureSize; $y++) {
        for ($x = 0; $x < $signatureSize; $x++) {
            $rgb = imagecolorat($resized, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $gray = (int)round(($r + $g + $b) / 3);
            $signature[] = $gray;
        }
    }

    imagedestroy($source);
    imagedestroy($resized);

    return $signature;
}

/**
 * Generate a hash (SHA-256) for the captured face frame
 */
function generate_face_hash($face_data) {
    $binary = extract_base64_image_data($face_data);
    if (!$binary) {
        return null;
    }

    return hash('sha256', $binary);
}

/**
 * Package face data and signature/hash for storage.
 */
function build_face_storage_payload($face_data) {
    $signature = null;
    $hash = generate_face_hash($face_data);
    $storage = $face_data;

    if (function_exists('imagecreatefromstring')) {
        $signature = generate_face_signature($face_data);

        if (!$signature && !$hash) {
            return null;
        }

        $storage = json_encode([
            'image' => $face_data,
            'signature' => $signature,
            'hash' => $hash
        ], JSON_UNESCAPED_SLASHES);
    } elseif (!$hash) {
        return null;
    }

    return [
        'storage' => $storage,
        'signature' => $signature,
        'hash' => $hash
    ];
}

/**
 * Parse stored face payload, returning image, signature and hash.
 */
function parse_face_payload($face_data) {
    $result = [
        'image' => null,
        'signature' => null,
        'hash' => null,
        'needs_update' => false
    ];

    if (!$face_data) {
        return $result;
    }

    $decoded = json_decode($face_data, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['image'])) {
        $result['image'] = $decoded['image'];
        if (isset($decoded['signature']) && is_array($decoded['signature'])) {
            $result['signature'] = $decoded['signature'];
        } elseif (function_exists('imagecreatefromstring')) {
            $result['signature'] = generate_face_signature($decoded['image']);
            if ($result['signature']) {
                $result['needs_update'] = true;
            }
        }

        if (!empty($decoded['hash'])) {
            $result['hash'] = $decoded['hash'];
        } else {
            $result['hash'] = generate_face_hash($decoded['image']);
            if ($result['hash'] && function_exists('imagecreatefromstring')) {
                $result['needs_update'] = true;
            }
        }
    } else {
        $result['image'] = $face_data;
        $result['hash'] = generate_face_hash($face_data);
        if (function_exists('imagecreatefromstring')) {
            $signature = generate_face_signature($face_data);
            if ($signature) {
                $result['signature'] = $signature;
            }
        }
        if ($result['signature'] || $result['hash']) {
            $result['needs_update'] = true;
        }
    }

    return $result;
}

/**
 * Compare two face signatures and return the mean absolute difference.
 */
function compare_face_signatures($signatureA, $signatureB) {
    if (!$signatureA || !$signatureB) {
        return null;
    }

    if (count($signatureA) !== count($signatureB)) {
        return null;
    }

    $sum = 0;
    $count = count($signatureA);

    for ($i = 0; $i < $count; $i++) {
        $sum += abs((int)$signatureA[$i] - (int)$signatureB[$i]);
    }

    return $count > 0 ? $sum / $count : null;
}

/**
 * Compare base64 images via similarity percentage
 */
function compare_face_images_similarity($imageA, $imageB) {
    if (!$imageA || !$imageB) {
        return 0;
    }

    similar_text($imageA, $imageB, $percent);
    return $percent;
}

/**
 * Update stored face payload with normalized structure.
 */
function update_employee_face_payload($db, $employee_id, $image, $signature, $hash) {
    try {
        $payload = json_encode([
            'image' => $image,
            'signature' => $signature,
            'hash' => $hash
        ], JSON_UNESCAPED_SLASHES);

        $query = "UPDATE employees SET face_data = :face_data WHERE employee_id = :employee_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':face_data', $payload);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Database error in update_employee_face_payload: " . $e->getMessage());
    }
}

/**
 * Validate admin login
 */
function validate_admin_login($db, $username, $password) {
    try {
        $query = "SELECT * FROM admin_users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $admin['password'])) {
                return $admin;
            }
        }
        return false;
    } catch (PDOException $e) {
        error_log("Database error in validate_admin_login: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if employee exists
 */
function employee_exists($db, $employee_id) {
    try {
        $query = "SELECT id FROM employees WHERE employee_id = :employee_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database error in employee_exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Get employee by ID
 */
function get_employee($db, $employee_id) {
    try {
        $query = "SELECT * FROM employees WHERE employee_id = :employee_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_employee: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all employees
 */
function get_all_employees($db) {
    try {
        $query = "SELECT * FROM employees ORDER BY full_name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_all_employees: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark attendance
 */
function mark_attendance($db, $employee_id, $check_in = null) {
    try {
        if (!$check_in) {
            $check_in = date('Y-m-d H:i:s');
        }
        
        $query = "INSERT INTO attendance (employee_id, check_in, status) VALUES (:employee_id, :check_in, 'present')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':check_in', $check_in);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Database error in mark_attendance: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if employee has already marked attendance today
 */
function has_attendance_today($db, $employee_id) {
    try {
        $today = date('Y-m-d');
        $query = "SELECT id FROM attendance WHERE employee_id = :employee_id AND DATE(check_in) = :today";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':today', $today);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database error in has_attendance_today: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if employee has attendance on a specific date
 */
function has_attendance_on_date($db, $employee_id, $date) {
    try {
        $query = "SELECT id FROM attendance WHERE employee_id = :employee_id AND DATE(check_in) = :selected_date";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':selected_date', $date);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database error in has_attendance_on_date: " . $e->getMessage());
        return false;
    }
}

/**
 * Get single attendance record.
 */
function get_attendance_record($db, $attendance_id) {
    try {
        $query = "SELECT a.*, e.full_name 
                  FROM attendance a 
                  LEFT JOIN employees e ON a.employee_id = e.employee_id 
                  WHERE a.id = :attendance_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':attendance_id', $attendance_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_attendance_record: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete attendance record.
 */
function delete_attendance_record($db, $attendance_id) {
    try {
        $query = "DELETE FROM attendance WHERE id = :attendance_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':attendance_id', $attendance_id, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Database error in delete_attendance_record: " . $e->getMessage());
        return false;
    }
}

/**
 * Get today's attendance records
 */
function get_todays_attendance($db) {
    try {
        $today = date('Y-m-d');
        $query = "SELECT a.*, e.full_name, e.department, e.position 
                  FROM attendance a 
                  JOIN employees e ON a.employee_id = e.employee_id 
                  WHERE DATE(a.check_in) = :today 
                  ORDER BY a.check_in DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':today', $today);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_todays_attendance: " . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance records by date range
 */
function get_attendance_by_date_range($db, $start_date, $end_date) {
    try {
        $query = "SELECT a.*, e.full_name, e.department, e.position 
                  FROM attendance a 
                  JOIN employees e ON a.employee_id = e.employee_id 
                  WHERE DATE(a.check_in) BETWEEN :start_date AND :end_date
                  ORDER BY a.check_in DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_attendance_by_date_range: " . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance statistics
 */
function get_attendance_stats($db) {
    $stats = [
        'total_employees' => 0,
        'present_today' => 0,
        'absent_today' => 0,
        'attendance_rate_today' => 0,
        'present_week' => 0,
        'attendance_rate_week' => 0,
        'present_month' => 0,
        'attendance_rate_month' => 0
    ];
    
    try {
        $today = date('Y-m-d');
        
        // Total employees
        $query = "SELECT COUNT(*) as total FROM employees";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_employees'] = $result['total'] ?? 0;
        
        // Present today
        $query = "SELECT COUNT(DISTINCT employee_id) as present FROM attendance WHERE DATE(check_in) = :today";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':today', $today);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['present_today'] = $result['present'] ?? 0;
        
        // Absent today
        $stats['absent_today'] = $stats['total_employees'] - $stats['present_today'];
        
        // Attendance rate today
        $stats['attendance_rate_today'] = $stats['total_employees'] > 0 ? 
            round(($stats['present_today'] / $stats['total_employees']) * 100, 1) : 0;
        
        // Weekly stats
        $query = "SELECT COUNT(DISTINCT employee_id) as present_week FROM attendance WHERE WEEK(check_in) = WEEK(NOW()) AND YEAR(check_in) = YEAR(NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['present_week'] = $result['present_week'] ?? 0;
        $stats['attendance_rate_week'] = $stats['total_employees'] > 0 ? 
            round(($stats['present_week'] / $stats['total_employees']) * 100, 1) : 0;
        
        // Monthly stats
        $query = "SELECT COUNT(DISTINCT employee_id) as present_month FROM attendance WHERE MONTH(check_in) = MONTH(NOW()) AND YEAR(check_in) = YEAR(NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['present_month'] = $result['present_month'] ?? 0;
        $stats['attendance_rate_month'] = $stats['total_employees'] > 0 ? 
            round(($stats['present_month'] / $stats['total_employees']) * 100, 1) : 0;
        
    } catch (PDOException $e) {
        error_log("Database error in get_attendance_stats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Get department-wise attendance
 */
function get_department_attendance($db) {
    try {
        $today = date('Y-m-d');
        $query = "SELECT e.department, 
                         COUNT(DISTINCT e.id) as total_employees,
                         COUNT(DISTINCT a.employee_id) as present_today
                  FROM employees e
                  LEFT JOIN attendance a ON e.employee_id = a.employee_id AND DATE(a.check_in) = :today
                  GROUP BY e.department
                  ORDER BY e.department";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':today', $today);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_department_attendance: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate attendance report
 */
function generate_attendance_report($db, $start_date, $end_date) {
    try {
        $query = "SELECT e.employee_id, e.full_name, e.department, e.position,
                         COUNT(DISTINCT DATE(a.check_in)) as days_present,
                         COUNT(DISTINCT a.id) as total_checkins,
                         MIN(a.check_in) as first_checkin,
                         MAX(a.check_in) as last_checkin
                  FROM employees e
                  LEFT JOIN attendance a ON e.employee_id = a.employee_id 
                        AND DATE(a.check_in) BETWEEN :start_date AND :end_date
                  GROUP BY e.employee_id, e.full_name, e.department, e.position
                  ORDER BY e.department, e.full_name";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in generate_attendance_report: " . $e->getMessage());
        return [];
    }
}

/**
 * Format time for display
 */
function format_time($time) {
    if (!$time || $time == '0000-00-00 00:00:00') return '-';
    return date('h:i A', strtotime($time));
}

/**
 * Format date for display
 */
function format_date($date) {
    if (!$date || $date == '0000-00-00') return '-';
    return date('M j, Y', strtotime($date));
}

/**
 * Get status badge HTML
 */
function get_status_badge($status) {
    $status_class = '';
    switch ($status) {
        case 'present':
            $status_class = 'status-present';
            break;
        case 'absent':
            $status_class = 'status-absent';
            break;
        case 'late':
            $status_class = 'status-late';
            break;
        default:
            $status_class = 'status-absent';
    }
    
    return '<span class="' . $status_class . '">' . ucfirst($status) . '</span>';
}

/**
 * Check if user is logged in as admin
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Redirect to login if not authenticated
 */
function require_admin_login() {
    if (!is_admin_logged_in()) {
        header('Location: admin_login.php');
        exit;
    }
}

function recognize_face($captured_face_data, $db) {
    try {
        $capturedSignature = generate_face_signature($captured_face_data);
        $capturedHash = generate_face_hash($captured_face_data);

        if (!$capturedSignature && !$capturedHash) {
            return false;
        }

        $query = "SELECT employee_id, full_name, face_data FROM employees WHERE face_data IS NOT NULL";
        $stmt = $db->prepare($query);
        $stmt->execute();

        $bestMatch = null;
        $bestScore = PHP_FLOAT_MAX;
        $matchedBy = null;

        while ($employee = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payload = parse_face_payload($employee['face_data']);

            if ($payload['needs_update'] && ($payload['signature'] || $payload['hash'])) {
                update_employee_face_payload(
                    $db,
                    $employee['employee_id'],
                    $payload['image'],
                    $payload['signature'],
                    $payload['hash']
                );
            }

            $comparisonType = null;
            $score = null;

            if ($capturedSignature && $payload['signature']) {
                $score = compare_face_signatures($capturedSignature, $payload['signature']);
                if ($score !== null) {
                    $comparisonType = 'signature';
                }
            }

            if ($comparisonType === null && $capturedHash && $payload['hash']) {
                if (hash_equals($payload['hash'], $capturedHash)) {
                    $score = 0;
                    $comparisonType = 'hash';
                }
            }

            if ($comparisonType === null && $payload['image']) {
                $similarity = compare_face_images_similarity($captured_face_data, $payload['image']);
                if ($similarity >= 92) {
                    $score = 100 - $similarity; // lower is better for consistency
                    $comparisonType = 'similarity';
                }
            }

            if ($comparisonType === null || $score === null) {
                continue;
            }

            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMatch = [
                    'employee_id' => $employee['employee_id'],
                    'full_name' => $employee['full_name']
                ];
                $matchedBy = $comparisonType;
            }
        }

        if (!$bestMatch) {
            return false;
        }

        if ($matchedBy === 'signature' && $bestScore <= 60) {
            return $bestMatch;
        }

        if ($matchedBy === 'hash' && $bestScore === 0) {
            return $bestMatch;
        }

        if ($matchedBy === 'similarity' && $bestScore <= 12) { // similarity >= 88%
            return $bestMatch;
        }

        return false;
    } catch (PDOException $e) {
        error_log("Database error in recognize_face: " . $e->getMessage());
        return false;
    }
}

/**
 * Log system activity
 */
function log_activity($db, $action, $details) {
    try {
        $query = "INSERT INTO activity_log (action, details, ip_address, user_agent) 
                  VALUES (:action, :details, :ip_address, :user_agent)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Database error in log_activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification (placeholder for email/SMS integration)
 */
function send_notification($employee_id, $message) {
    // In production, implement email or SMS notification
    error_log("Notification for $employee_id: $message");
    return true;
}

/**
 * Create a new admin user (use this if you need to create a new admin)
 */
function create_admin_user($db, $username, $password, $email) {
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO admin_users (username, password, email) VALUES (:username, :password, :email)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':email', $email);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Database error in create_admin_user: " . $e->getMessage());
        return false;
    }
}

/**
 * Check database connection
 */
function check_db_connection($db) {
    try {
        $stmt = $db->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get system settings
 */
function get_system_settings($db) {
    try {
        // This is a placeholder for future settings table
        return [
            'company_name' => 'FaceAuth System',
            'attendance_start_time' => '09:00:00',
            'attendance_end_time' => '17:00:00',
            'late_threshold' => '09:15:00'
        ];
    } catch (Exception $e) {
        error_log("Error getting system settings: " . $e->getMessage());
        return [];
    }
}
?>