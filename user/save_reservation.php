<?php
session_start();
include('../connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['student_id'])) {
    
    // We get these from the FormData sent by JavaScript
    $student_no = mysqli_real_escape_string($conn, $_POST['student_no']); 
    $room       = mysqli_real_escape_string($conn, $_POST['classroom']);
    $date       = mysqli_real_escape_string($conn, $_POST['date']);
    $start      = mysqli_real_escape_string($conn, $_POST['start']);
    $end        = mysqli_real_escape_string($conn, $_POST['end']);
    $purpose    = mysqli_real_escape_string($conn, $_POST['purpose']);

    // Matching your DB image: student_number, room_name, reservation_date, etc.
    // We removed 'reference_no' because it wasn't in your table image.
    $sql = "INSERT INTO reservations (student_number, room_name, reservation_date, start_time, end_time, purpose, status) 
            VALUES ('$student_no', '$room', '$date', '$start', '$end', '$purpose', 'Pending')";

    if (mysqli_query($conn, $sql)) {
        echo json_encode(["status" => "success"]);
    } else {
        // This will help you debug if something is still wrong
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Unauthorized or Invalid Request"]);
}
?>