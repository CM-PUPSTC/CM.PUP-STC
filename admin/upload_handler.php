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

    // 2. Process CSV File Safely using Header Mapping
    if (($handle = fopen($file, "r")) !== FALSE) {
        
        // Grab the very first row to read headers
        $headers = fgetcsv($handle, 1000, ",");
        
        if ($headers !== FALSE) {
            // Trim whitespace from headers and map titles to their numeric array index position
            $header_map = array_flip(array_map('trim', $headers));

            // Clear out the old schedules ONLY for this specific room 
            // right before importing to prevent redundant/duplicate entries!
            $clearOld = $conn->prepare("DELETE FROM class_schedules WHERE room_name = ?");
            $clearOld->bind_param("s", $room_name);
            $clearOld->execute();
            $clearOld->close();

            // CHANGED 1: Added subject_name to the column insert names list, and added an extra "?" placeholder (8 total)
            $stmt = $conn->prepare("INSERT INTO class_schedules (room_name, subject_code, subject_name, day_of_week, start_time, end_time, section_name, professor_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Dynamically fetch indices by checking header titles. Falls back to empty string if missing.
                $subject_code = isset($header_map['subject_code'])   ? trim($data[$header_map['subject_code']]) : '';
                
                // CHANGED 2: Extract the 'subject_name' field column data directly from your uploaded CSV row file
                $subject_name = isset($header_map['subject_name'])   ? trim($data[$header_map['subject_name']]) : '';
                
                $day          = isset($header_map['day_of_week'])    ? trim($data[$header_map['day_of_week']]) : '';
                $start        = isset($header_map['start_time'])     ? trim($data[$header_map['start_time']]) : '00:00:00';
                $end          = isset($header_map['end_time'])       ? trim($data[$header_map['end_time']]) : '00:00:00';
                $section      = isset($header_map['section_name'])   ? trim($data[$header_map['section_name']]) : '';
                $professor    = isset($header_map['professor_name']) ? trim($data[$header_map['professor_name']]) : '';

                // CHANGED 3: Added an extra "s" to the type string definitions ("ssssssss") and bound the $subject_name variable key directly
                $stmt->bind_param("ssssssss", $room_name, $subject_code, $subject_name, $day, $start, $end, $section, $professor);
                $stmt->execute();
            }
            $stmt->close();
        }
        fclose($handle);
    }

    header("Location: index.php?room_id=" . urlencode($room_name) . "&upload=success");
    exit();
}