<?php
session_start();

include_once 'config/database.php';
include_once 'includes/functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$start_date = sanitize_input($_GET['start_date'] ?? date('Y-m-01'));
$end_date = sanitize_input($_GET['end_date'] ?? date('Y-m-d'));

$startDateObj = DateTime::createFromFormat('Y-m-d', $start_date);
$endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);

if (!$startDateObj) {
    $startDateObj = new DateTime(date('Y-m-01'));
}

if (!$endDateObj) {
    $endDateObj = new DateTime();
}

if ($startDateObj > $endDateObj) {
    [$startDateObj, $endDateObj] = [$endDateObj, $startDateObj];
}

$start_date = $startDateObj->format('Y-m-d');
$end_date = $endDateObj->format('Y-m-d');

$attendance_records = get_attendance_by_date_range($db, $start_date, $end_date);
$employees = get_all_employees($db);

$dateRange = [];
$rangeStart = clone $startDateObj;
for ($i = 0; $i < 31; $i++) {
    $dateRange[] = $rangeStart->format('Y-m-d');
    $rangeStart->modify('+1 day');
}
$dateIndexMap = array_flip($dateRange);

$attendanceMap = [];

foreach ($attendance_records as $record) {
    $employeeId = $record['employee_id'];
    $recordDate = date('Y-m-d', strtotime($record['check_in']));
    $rangeIndex = $dateIndexMap[$recordDate] ?? null;

    if ($rangeIndex === null) {
        continue;
    }

    if (!isset($attendanceMap[$employeeId])) {
        $attendanceMap[$employeeId] = [
            'days' => array_fill(0, count($dateRange), ''),
            'last_check_in' => null,
            'present_days' => 0
        ];
    }

    $existingValue = $attendanceMap[$employeeId]['days'][$rangeIndex];
    $checkInTime = date('H:i', strtotime($record['check_in']));

    if ($existingValue === '') {
        $attendanceMap[$employeeId]['days'][$rangeIndex] = $checkInTime;
        $attendanceMap[$employeeId]['present_days']++;
    }

    $currentLast = $attendanceMap[$employeeId]['last_check_in'];
    if (!$currentLast || strtotime($record['check_in']) > strtotime($currentLast)) {
        $attendanceMap[$employeeId]['last_check_in'] = $record['check_in'];
    }
}

header('Content-Type: text/csv; charset=utf-8');
$filename = sprintf('attendance_export_%s_to_%s.csv', $start_date, $end_date);
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

$headers = array_merge(
    ['Employee Name'],
    $dateRange,
    ['Last Check-in', 'Total Days Present']
);

fputcsv($output, $headers);

foreach ($employees as $employee) {
    $employeeId = $employee['employee_id'];
    $fullName = $employee['full_name'];

    $row = [$fullName];

    if (isset($attendanceMap[$employeeId])) {
        $row = array_merge(
            $row,
            $attendanceMap[$employeeId]['days']
        );

        $lastCheckIn = $attendanceMap[$employeeId]['last_check_in']
            ? date('Y-m-d H:i', strtotime($attendanceMap[$employeeId]['last_check_in']))
            : '';

        $row[] = $lastCheckIn;
        $row[] = $attendanceMap[$employeeId]['present_days'];
    } else {
        $row = array_merge($row, array_fill(0, count($dateRange), ''), ['', 0]);
    }

    fputcsv($output, $row);
}

fclose($output);

$count = count($attendance_records);
log_activity($db, 'EXPORT_ATTENDANCE', "Exported {$count} attendance records from {$start_date} to {$end_date}");

exit;

