<?php
session_start();
/** @var mysqli $conn */
include('../connect.php');
header('Content-Type: application/json');

$account_no = $_SESSION['account_number'] ?? ''; 
$room    = $_POST['room']    ?? '';
$date    = $_POST['date']    ?? '';
$purpose = $_POST['purpose'] ?? '';
$start   = !empty($_POST['start']) ? date("H:i:s", strtotime($_POST['start'])) : '';
$end     = !empty($_POST['end'])   ? date("H:i:s", strtotime($_POST['end'])) : '';

if (!$account_no) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired.']);
    exit;
}

$dayOfWeek = date("l", strtotime($date));

// 1. Check for Regular Class Conflicts (unless cancelled)
$sched_sql = "SELECT * FROM class_schedules 
              WHERE room_name = ? AND day_of_week = ? 
              AND (? < end_time AND ? > start_time)
              AND id NOT IN (SELECT schedule_id FROM cancelled_classes WHERE cancelled_date = ?)";

$sched_stmt = mysqli_prepare($conn, $sched_sql);
mysqli_stmt_bind_param($sched_stmt, "sssss", $room, $dayOfWeek, $start, $end, $date);
mysqli_stmt_execute($sched_stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($sched_stmt)) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Slot occupied by a regular class schedule.']);
    exit;
}

// 2. Check for Accepted Reservations
$check_sql = "SELECT * FROM reservations 
              WHERE room_name = ? AND reservation_date = ? 
              AND status = 'Accepted' AND (? < end_time AND ? > start_time)";

$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ssss", $room, $date, $start, $end);
mysqli_stmt_execute($check_stmt);
$res_result = mysqli_stmt_get_result($check_stmt);

// 3. Finalize Status (Accept if clear, Pending if occupied by another reservation)
$final_status = (mysqli_num_rows($res_result) > 0) ? 'Pending' : 'Accepted';

$sql = "INSERT INTO reservations (id_number, room_name, reservation_date, start_time, end_time, purpose, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssss", $account_no, $room, $date, $start, $end, $purpose, $final_status);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status' => 'success',
        'message' => ($final_status === 'Accepted') ? 'Reservation Confirmed!' : 'Request is now Pending.'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>