<?php
session_start();
/** @var mysqli $conn */
include('../connect.php');

if (!isset($_SESSION['account_id']) || !isset($_POST['refNo'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$refNo = mysqli_real_escape_string($conn, $_POST['refNo']);
// Use the session variable, but remember it maps to 'id_number' in the DB
$account_no = $_SESSION['account_number']; 

// UPDATED: Changed 'account_number' to 'id_number'
$query = "UPDATE reservations SET status = 'Cancelled' 
          WHERE id = '$refNo' AND id_number = '$account_no'";

if (mysqli_query($conn, $query)) {
    if (mysqli_affected_rows($conn) > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        // This usually triggers if the ID or the User ID doesn't match
        echo json_encode(['status' => 'error', 'message' => 'Reservation not found or unauthorized.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
}
?>