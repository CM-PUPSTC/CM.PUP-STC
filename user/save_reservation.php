<?php
session_start();
include('../connect.php');
header('Content-Type: application/json');

$room    = $_POST['room']    ?? '';
$date    = $_POST['date']    ?? '';
$purpose = $_POST['purpose'] ?? '';
$start   = !empty($_POST['start']) ? date("H:i:s", strtotime($_POST['start'])) : '';
$end     = !empty($_POST['end'])   ? date("H:i:s", strtotime($_POST['end'])) : '';
$account_no = $_SESSION['account_number'] ?? '';

if (!$account_no) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired.']);
    exit;
}

// --- STEP 1: CHECK FOR LIVE CONFLICTS ---
// We check if someone is ALREADY 'Accepted' for this time
$check_sql = "SELECT * FROM reservations 
              WHERE room_name = ? 
              AND reservation_date = ? 
              AND status = 'Accepted'
              AND (? < end_time AND ? > start_time)";

$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ssss", $room, $date, $start, $end);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);

// --- STEP 2: DECIDE THE STATUS ---
// If rows > 0, someone is already there, so this new request is 'Pending' (Queue)
// If rows == 0, the room is free, so this request is 'Accepted' (Live)
$final_status = (mysqli_num_rows($result) > 0) ? 'Pending' : 'Accepted';

// --- STEP 3: INSERT INTO DATABASE ---
$sql = "INSERT INTO reservations (account_number, room_name, reservation_date, start_time, end_time, purpose, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
// Note: Added a 7th "s" for the $final_status
mysqli_stmt_bind_param($stmt, "sssssss", $account_no, $room, $date, $start, $end, $purpose, $final_status);

if (mysqli_stmt_execute($stmt)) {
    $msg = ($final_status === 'Accepted') ? 'Reservation Confirmed!' : 'Room is occupied. You are now in the FCFS Queue.';
    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'reservation_status' => $final_status
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
