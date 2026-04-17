<?php
include('../connect.php');

$sql = "SELECT room_name as title, 
               CONCAT(reservation_date, 'T', start_time) as start, 
               CONCAT(reservation_date, 'T', end_time) as end,
               room_type -- assuming you want to color by type
        FROM reservations 
        WHERE status = 'Accepted'";

$result = mysqli_query($conn, $sql);
$events = [];

while($row = mysqli_fetch_assoc($result)) {
    // Logic for colors based on room type
    $color = '#800000'; // Default Maroon
    if($row['room_type'] == 'Laboratory') $color = '#008080';
    
    $events[] = [
        'title' => $row['title'],
        'start' => $row['start'],
        'end' => $row['end'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'display' => 'block'
    ];
}

echo json_encode($events);
?>