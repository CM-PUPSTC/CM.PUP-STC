<?php
session_start();
/** @var mysqli $conn */
include('../connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['schedule_csv'])) {
    $room_name = trim($_POST['room_name']);
    $file = $_FILES['schedule_csv']['tmp_name'];

    // 1. Ensure the room exists in the 'classrooms' table
    $checkRoom = $conn->prepare("SELECT room_name FROM classrooms WHERE room_name = ?");
    $checkRoom->bind_param("s", $room_name);
    $checkRoom->execute();
    $result = $checkRoom->get_result();

    if ($result->num_rows === 0) {
        $insertRoom = $conn->prepare("INSERT INTO classrooms (room_name) VALUES (?)");
        $insertRoom->bind_param("s", $room_name);
        $insertRoom->execute();
        $insertRoom->close();
    }
    $checkRoom->close();

    if (($handle = fopen($file, "r")) !== FALSE) {
        fgetcsv($handle); // Skip header row

        // UPDATED: Now we have 7 placeholders (?) for 7 columns
        $stmt = $conn->prepare("INSERT INTO class_schedules (room_name, subject_code, day_of_week, start_time, end_time, section_name, professor_name) VALUES (?, ?, ?, ?, ?, ?, ?)");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $subject_code = trim($data[0]);
            $day          = trim($data[1]);
            $start        = trim($data[2]);
            $end          = trim($data[3]);
            $section      = trim($data[4]); // Column E
            $professor    = trim($data[5]); // Column F (The Professor Name)

            // UPDATED: 7 's' types for 7 variables
            $stmt->bind_param("sssssss", $room_name, $subject_code, $day, $start, $end, $section, $professor);
            $stmt->execute();
        }
        fclose($handle);
        $stmt->close();
    }

    header("Location: index.php?room_id=" . urlencode($room_name) . "&upload=success");
    exit();
}