<?php
session_start();
ini_set('auto_detect_line_endings', true); // Fixes Excel formatting line break issues
error_reporting(E_ALL);
ini_set('display_errors', 1); 

/** @var mysqli $conn */
include('../connect.php');

function removeBOM($str) {
    if (substr($str, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
        $str = substr($str, 3);
    }
    return trim($str);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['schedule_csv'])) {
    $file = $_FILES['schedule_csv']['tmp_name'];
    $redirect_room = trim($_POST['room_name']); // Fallback redirect target

    if (($handle = fopen($file, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        
        if ($headers !== FALSE) {
            $clean_headers = array_map(function($header) {
                return removeBOM(trim($header));
            }, $headers);

            $header_map = array_flip($clean_headers);

            // Double check that crucial headers exist based on your image layout
            if (!isset($header_map['room_name']) || !isset($header_map['subject_code'])) {
                die("Error: Missing crucial CSV columns. Found columns: " . implode(', ', $clean_headers));
            }

            // Optimization: Track cleared rooms during this transaction loop so we don't clear them multiple times
            $cleared_rooms = [];

            $stmt = $conn->prepare("INSERT INTO class_schedules (room_name, subject_code, subject_name, day_of_week, start_time, end_time, section_name, professor_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (empty(array_filter($data))) continue; // Skip blank lines

                // FIX: Trust the room name written in the CSV row data column!
                $csv_room = isset($header_map['room_name']) ? trim($data[$header_map['room_name']]) : '';
                
                if (empty($csv_room)) continue; // Skip rows with missing room tags

                // Keep track of the last room parsed so we redirect to it cleanly
                $redirect_room = $csv_room;

                // Dynamically clear old entries for a room the FIRST time we encounter it in the file
                if (!in_array($csv_room, $cleared_rooms)) {
                    // 1. Ensure room exists in rooms list registry
                    $checkR = $conn->prepare("SELECT room_name FROM classrooms WHERE room_name = ?");
                    $checkR->bind_param("s", $csv_room);
                    $checkR->execute();
                    if ($checkR->get_result()->num_rows === 0) {
                        $insR = $conn->prepare("INSERT INTO classrooms (room_name, location) VALUES (?, 'Main Building')");
                        $insR->bind_param("s", $csv_room);
                        $insR->execute();
                        $insR->close();
                    }
                    $checkR->close();

                    // 2. Clear old schedule data matching this specific room
                    $clearOld = $conn->prepare("DELETE FROM class_schedules WHERE room_name = ?");
                    $clearOld->bind_param("s", $csv_room);
                    $clearOld->execute();
                    $clearOld->close();

                    $cleared_rooms[] = $csv_room;
                }

                // Map data targets explicitly using your actual CSV layout columns
                $subject_code = isset($header_map['subject_code'])   ? trim($data[$header_map['subject_code']]) : '';
                $subject_name = isset($header_map['subject_name'])   ? trim($data[$header_map['subject_name']]) : '';
                $day          = isset($header_map['day_of_week'])    ? trim($data[$header_map['day_of_week']]) : '';
                $start        = isset($header_map['start_time'])     ? trim($data[$header_map['start_time']]) : '00:00:00';
                $end          = isset($header_map['end_time'])       ? trim($data[$header_map['end_time']]) : '00:00:00';
                $section      = isset($header_map['section_name'])   ? trim($data[$header_map['section_name']]) : '';
                $professor    = isset($header_map['professor_name']) ? trim($data[$header_map['professor_name']]) : '';

                $stmt->bind_param("ssssssss", $csv_room, $subject_code, $subject_name, $day, $start, $end, $section, $professor);
                
                if (!$stmt->execute()) {
                    die("Database Insertion Error: " . $stmt->error);
                }
            }
            $stmt->close();
        }
        fclose($handle);
    }

    // Go straight to the dashboard auto-focused on the uploaded room name destination
    header("Location: index.php?room_id=" . urlencode($redirect_room) . "&upload=success");
    exit();
}