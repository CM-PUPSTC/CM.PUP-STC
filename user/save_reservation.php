<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['account_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

/** @var mysqli $conn */
include('../connect.php');

$account_no = $_SESSION['account_number'];

// 1. Grab incoming POST data sent from your index.php form
$room    = mysqli_real_escape_string($conn, $_POST['room']);
$date    = mysqli_real_escape_string($conn, $_POST['date']);
$start   = mysqli_real_escape_string($conn, $_POST['start']);
$end     = mysqli_real_escape_string($conn, $_POST['end']);
$purpose = mysqli_real_escape_string($conn, $_POST['purpose']);

$start_time = date("H:i:s", strtotime($start));
$end_time   = date("H:i:s", strtotime($end));
$day_name   = date('l', strtotime($date));

// 2. Conflict Validation Check (Optional but recommended):
// Make sure it doesn't overlap with a regular class or an already approved booking
$check_accepted_query = "
    SELECT id FROM reservations 
    WHERE room_name = '$room' 
      AND reservation_date = '$date' 
      AND status = 'Accepted'
      AND ('$start_time' < end_time AND '$end_time' > start_time)
    LIMIT 1
";
$accepted_conflict = mysqli_query($conn, $check_accepted_query);

if (mysqli_num_rows($accepted_conflict) > 0) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'This slot is unavailable because it has already been approved for another request.'
    ]);
    exit();
}

// 3. CRITICAL INTEGRATION FIX:
// Explicitly pass 'Pending' as a string into your status field column.
// This guarantees that it hits the admin approval queue first instead of auto-accepting.
$insert_query = "
    INSERT INTO reservations (id_number, room_name, reservation_date, start_time, end_time, purpose, status, created_at) 
    VALUES ('$account_no', '$room', '$date', '$start_time', '$end_time', '$purpose', 'Pending', NOW())
";

if (mysqli_query($conn, $insert_query)) {
    echo json_encode([
        'status' => 'success', 
        'message' => 'Request submitted successfully! Your booking is now in the queue waiting for evaluation.'
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: Unable to process queue entry.'
    ]);
}
?>