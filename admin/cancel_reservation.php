<?php
session_start();
include('../connect.php');
header('Content-Type: application/json');

if (isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    
    // 1. GET DETAILS of the reservation being cancelled
    $info_query = "SELECT room_name, reservation_date FROM reservations WHERE id = '$id' LIMIT 1";
    $info_result = mysqli_query($conn, $info_query);
    $res_info = mysqli_fetch_assoc($info_result);

    if ($res_info) {
        $room = mysqli_real_escape_string($conn, $res_info['room_name']);
        $date = mysqli_real_escape_string($conn, $res_info['reservation_date']);

        // 2. CANCEL THE TARGET (The Troller)
        $sql_cancel = "UPDATE reservations SET status = 'Cancelled' WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql_cancel)) {
            
            // 3. THE FCFS ENGINE: Find the NEXT person in line
            // We only look for 'Pending' requests for this same room and date.
            $sql_fcfs = "SELECT id FROM reservations 
                         WHERE room_name = '$room' 
                         AND reservation_date = '$date' 
                         AND status = 'Pending' 
                         ORDER BY created_at ASC 
                         LIMIT 1";
            
            $fcfs_result = mysqli_query($conn, $sql_fcfs);
            
            if (mysqli_num_rows($fcfs_result) > 0) {
                $next_in_line = mysqli_fetch_assoc($fcfs_result);
                $next_id = $next_in_line['id'];
                
                // 4. AUTOMATIC PROMOTION
                $promote_sql = "UPDATE reservations SET status = 'Accepted' WHERE id = '$next_id'";
                
                if (mysqli_query($conn, $promote_sql)) {
                    echo json_encode(['status' => 'success', 'message' => 'Troll removed. Next representative in queue has been promoted.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Cancelled troll, but failed to promote next user.']);
                }
            } else {
                echo json_encode(['status' => 'success', 'message' => 'Cancelled successfully. Room is now empty.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error during cancellation.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Reservation not found.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No ID provided.']);
}
exit();