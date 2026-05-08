<?php
session_start();
/** @var mysqli $conn */
include('../connect.php');

if (isset($_POST['confirm_cancel'])) {
    $schedule_id = $_POST['schedule_id'];
    $cancelled_date = $_POST['cancelled_date'];

    // Insert into your cancelled_classes table
    $query = "INSERT INTO cancelled_classes (schedule_id, cancelled_date) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $schedule_id, $cancelled_date);

    if ($stmt->execute()) {
        // SUCCESS: Redirect back with a message
        header("Location: index.php?msg=Class session successfully cancelled.");
    } else {
        // ERROR: Redirect back with error
        header("Location: index.php?msg=Error: Could not cancel class.");
    }
    exit();
} else {
    // If someone tries to access this file directly without the form
    header("Location: index.php");
    exit();
}
?>