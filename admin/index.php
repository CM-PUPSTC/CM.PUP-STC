<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
/** @var mysqli $conn */
include('../connect.php');

// --- DATABASE HANDLERS ---

// Delete Faculty Profile or Student Representative Account
if (isset($_POST['delete_user'])) {
    $delete_id = mysqli_real_escape_string($conn, $_POST['delete_id']);
    $role_type = mysqli_real_escape_string($conn, $_POST['role_type']);

    $conn->query("DELETE FROM users WHERE id_number = '$delete_id'");

    if ($role_type === 'professor') {
        header("Location: index.php?msg=ProfDeleted");
    } else {
        header("Location: index.php?msg=RepDeleted");
    }
    exit();
}

// Add New Room (FIXED: Handles dynamic inputs and default image assignment)
if (isset($_POST['add_room'])) {
    $new_room   = mysqli_real_escape_string($conn, $_POST['room_name']);
    $location   = mysqli_real_escape_string($conn, $_POST['location']);
    $room_type  = mysqli_real_escape_string($conn, $_POST['room_type']);

    // Automatically fall back to PUPLogo.png so student dashboard images never break
    $default_img = "PUPLogo.png";

    $conn->query("INSERT IGNORE INTO classrooms (room_name, location, room_type, image_url, max_capacity) 
                  VALUES ('$new_room', '$location', '$room_type', '$default_img', 40)");

    header("Location: index.php?msg=RoomAdded");
    exit();
}

// Add New Professor
if (isset($_POST['add_prof'])) {
    $new_prof = mysqli_real_escape_string($conn, $_POST['prof_name']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);

    // Automatically hashes 'prof123'
    $temp_pass = password_hash('prof123', PASSWORD_DEFAULT);
    $prof_id = "PROF-" . date('Y') . "-" . rand(1000, 9999);

    // Saving department inside the section_name column
    $conn->query("INSERT IGNORE INTO users (id_number, name, password, role, section_name) 
                  VALUES ('$prof_id', '$new_prof', '$temp_pass', 'professor', '$department')");
    header("Location: index.php?msg=ProfAdded&id=" . $prof_id);
    exit();
}

// Add New Student Representative
if (isset($_POST['add_stud_rep'])) {
    $rep_name = mysqli_real_escape_string($conn, $_POST['rep_name']);
    $section_name = mysqli_real_escape_string($conn, $_POST['section_name']);

    // Automatically hashes 'student123'
    $temp_pass = password_hash('student123', PASSWORD_DEFAULT);
    $rep_id = "REP-" . date('Y') . "-" . rand(1000, 9999);

    // Saving student representative inside users table with 'student' role type
    $conn->query("INSERT IGNORE INTO users (id_number, name, password, role, section_name) 
                  VALUES ('$rep_id', '$rep_name', '$temp_pass', 'student', '$section_name')");
    header("Location: index.php?msg=RepAdded&id=" . $rep_id);
    exit();
}

// Administrative Cancellation Handler (Schedules & Reservations)
if (isset($_POST['admin_cancel_action'])) {
    $action_type = mysqli_real_escape_string($conn, $_POST['action_type']);
    $target_id = mysqli_real_escape_string($conn, $_POST['target_id']);

    if ($action_type === 'Reservation') {
        // Rejects the accepted booking and frees the room
        $conn->query("UPDATE reservations SET status = 'Rejected' WHERE id = '$target_id'");
        header("Location: index.php?msg=ReservationCancelled");
        exit();
    } elseif ($action_type === 'Schedule') {
        // Suspends a single instance day of a recurring class schedule
        $cancel_date = mysqli_real_escape_string($conn, $_POST['selected_cancel_date']);
        if (!empty($cancel_date)) {
            $conn->query("INSERT IGNORE INTO cancelled_classes (schedule_id, cancelled_date) VALUES ('$target_id', '$cancel_date')");
            header("Location: index.php?msg=ClassSuspended");
            exit();
        }
    }
}

// --- Strictly Enforced First-Come, First-Served Reservation Approval Handler ---
if (isset($_POST['update_reservation_status'])) {
    $reservation_id = mysqli_real_escape_string($conn, $_POST['reservation_id']);

    // Read the button value clicked ('Accepted' or 'Rejected')
    $new_status = mysqli_real_escape_string($conn, $_POST['update_reservation_status']);

    if ($new_status === 'Accepted') {
        // 1. Fetch the room name, date, start time, and end time of the reservation Engr. Liza is approving
        $current_res_query = $conn->query("SELECT room_name, reservation_date, start_time, end_time, created_at FROM reservations WHERE id = '$reservation_id' LIMIT 1");
        $current_res = $current_res_query->fetch_assoc();

        if ($current_res) {
            $room_name = mysqli_real_escape_string($conn, $current_res['room_name']);
            $res_date  = mysqli_real_escape_string($conn, $current_res['reservation_date']);
            $start_t   = mysqli_real_escape_string($conn, $current_res['start_time']);
            $end_t     = mysqli_real_escape_string($conn, $current_res['end_time']);
            $created_at = $current_res['created_at'];

            // 2. FIRST-COME, FIRST-SERVED QUEUE CHECK: 
            // Scan for any OLDER pending requests for the EXACT same room
            $check_older_query = $conn->query("
                SELECT id FROM reservations 
                WHERE room_name = '$room_name' 
                AND status = 'Pending' 
                AND created_at < '$created_at' 
                AND id != '$reservation_id'
                LIMIT 1
            ");

            if ($check_older_query->num_rows > 0) {
                // Block Engr. Liza from bypassing queue priority order!
                header("Location: index.php?msg=BlockFirstComeFirstServe");
                exit();
            }

            // 3. SECURE TRANSACTION: Begin database link to avoid race-conditions/double booking
            $conn->begin_transaction();

            try {
                // A: Accept the targeted reservation request
                $conn->query("UPDATE reservations SET status = 'Accepted' WHERE id = '$reservation_id'");

                // B: AUTO-DECLINE OVERLAPPING ENTRIES: 
                // Find all OTHER pending requests for this exact room/date that overlap in time and auto-decline them
                $conn->query("
                    UPDATE reservations 
                    SET status = 'Declined' 
                    WHERE room_name = '$room_name' 
                    AND reservation_date = '$res_date' 
                    AND status = 'Pending'
                    AND id != '$reservation_id'
                    AND ('$start_t' < end_time AND '$end_t' > start_time)
                ");

                // Commit the changes to the database cleanly
                $conn->commit();
                header("Location: index.php?msg=ReservationApproved");
                exit();
            } catch (Exception $e) {
                // Roll back if anything crashes
                $conn->rollback();
                header("Location: index.php?msg=DatabaseError");
                exit();
            }
        }
    } else {
        // If Engr. Liza clicked "Decline", simply change this single status to 'Rejected'
        $conn->query("UPDATE reservations SET status = 'Rejected' WHERE id = '$reservation_id'");
        header("Location: index.php?msg=ReservationRejected");
        exit();
    }
}

// --- DATA FETCHING (OPTIMIZED FOR DISCRETE ACTIONS) ---
$query = "
    SELECT r.id, TRIM(r.room_name) AS room_name, r.reservation_date AS event_date, r.start_time, r.end_time, 
           'RESERVED' as subject_code, 
           r.purpose as subject_name, 
           IFNULL(u.section_name, 'Booking') as section_name, 
           IFNULL(u.name, 'Unknown User') as professor_name, 
           'Reservation' as type, r.id as schedule_id, NULL as is_cancelled_date
    FROM reservations r
    LEFT JOIN users u ON r.id_number = u.id_number
    WHERE r.status = 'Accepted'
    
    UNION ALL
    
    SELECT cs.id, TRIM(cs.room_name) AS room_name, cs.day_of_week AS event_date, cs.start_time, cs.end_time, 
           cs.subject_code, cs.subject_name, cs.section_name, cs.professor_name, 'Schedule' as type,
           cs.id as schedule_id, cc.cancelled_date as is_cancelled_date
    FROM class_schedules cs
    LEFT JOIN cancelled_classes cc ON cs.id = cc.schedule_id
";
$result = mysqli_query($conn, $query);

$calendar_events = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Skip adding explicitly suspended calendar blocks
    if ($row['type'] === 'Schedule' && !empty($row['is_cancelled_date'])) {
        continue;
    }

    $color = '#800000'; // Default Maroon
    $room = strtolower($row['room_name']);
    if (strpos($room, 'lab') !== false) {
        $color = '#28a745';
    } elseif (strpos($room, 'gym') !== false) {
        $color = '#fd7e14';
    } elseif (strpos($room, 'multimedia') !== false) {
        $color = '#007bff';
    }

    $event = [
        'id'    => $row['id'],
        'title' => $row['room_name'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'display' => 'block',
        'extendedProps' => [
            'id'           => $row['id'],
            'subject'      => $row['subject_code'],
            'subject_name' => $row['subject_name'],
            'section'      => $row['section_name'],
            'professor'    => $row['professor_name'],
            'type'         => $row['type'],
            'schedule_id'  => $row['schedule_id']
        ]
    ];

    if ($row['type'] === 'Schedule') {
        $days_map = ['Sunday' => [0], 'Monday' => [1], 'Tuesday' => [2], 'Wednesday' => [3], 'Thursday' => [4], 'Friday' => [5], 'Saturday' => [6]];
        $day_name = ucfirst(strtolower($row['event_date']));
        if (isset($days_map[$day_name])) {
            $event['daysOfWeek'] = $days_map[$day_name];
            $event['startTime'] = $row['start_time'];
            $event['endTime'] = $row['end_time'];
        }
    } else {
        $event['start'] = $row['event_date'] . 'T' . $row['start_time'];
        $event['end'] = $row['event_date'] . 'T' . $row['end_time'];
    }
    $calendar_events[] = $event;
}

$rooms_res = $conn->query("SELECT room_name FROM classrooms ORDER BY room_name ASC");
$rooms_array = [];
while ($r = $rooms_res->fetch_assoc()) {
    $rooms_array[] = $r['room_name'];
}

$professors_res = $conn->query("SELECT id_number, name, section_name FROM users WHERE role = 'professor' ORDER BY name ASC");

// Fetch Student Representatives (Filtered by 'student' layout matched with database context)
$student_reps_res = $conn->query("SELECT id_number, name, section_name FROM users WHERE role = 'student' ORDER BY name ASC");

// Fetch Pending Reservations Queue Sorted strictly oldest first
$pending_requests = $conn->query("
    SELECT r.*, u.name as applicant_name, u.section_name as applicant_section
    FROM reservations r
    LEFT JOIN users u ON r.id_number = u.id_number
    WHERE r.status = 'Pending'
    ORDER BY r.created_at ASC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PUP-STC CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../img/PUPLogo.png">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <style>
        :root {
            --pup-maroon: #800000;
            --pup-gold: #FFD700;
            --sidebar-bg: #ffffff;
        }

        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .top-navbar {
            background: var(--pup-maroon);
            color: white;
            padding: 10px 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .sidebar-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            background: var(--sidebar-bg);
        }

        .section-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
            font-weight: 700;
            margin-bottom: 15px;
            display: block;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .active-room-box {
            background: #f8f9fa;
            border-left: 4px solid var(--pup-maroon);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
        }

        .btn-mgmt {
            border-radius: 8px;
            padding: 10px;
            font-weight: 500;
            transition: all 0.2s;
            text-align: left;
            display: flex;
            align-items: center;
            width: 100%;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
            background: white;
            color: #333;
        }

        .btn-mgmt i {
            width: 25px;
            color: var(--pup-maroon);
        }

        .btn-mgmt:hover {
            background: #fff5f5;
            border-color: var(--pup-maroon);
            transform: translateY(-2px);
        }

        .btn-upload {
            background: var(--pup-maroon);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: 0.3s;
        }

        .btn-upload:hover {
            background: #600000;
            box-shadow: 0 4px 10px rgba(128, 0, 0, 0.3);
        }

        .calendar-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .fc-toolbar-title {
            color: var(--pup-maroon) !important;
            font-weight: 700 !important;
        }

        .room-selector-btn {
            background: white;
            border: 2px solid var(--pup-maroon);
            color: var(--pup-maroon);
            font-weight: bold;
            border-radius: 8px;
            padding: 6px 15px;
        }

        .fc-event {
            cursor: pointer !important;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .fc-event:hover {
            transform: scale(1.015);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        @media (max-width: 767px) {
            .calendar-container {
                padding: 10px;
            }

            .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }

            .fc-button {
                padding: 8px 16px !important;
            }
        }

        .fc-col-header-cell-cushion {
            text-decoration: none !important;
            color: var(--pup-maroon) !important;
            text-transform: uppercase;
            font-weight: 700;
        }

        .fc .fc-button-group>.fc-button:empty {
            display: none !important;
        }

        .fc-toolbar-chunk {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 767px) {
            .fc-toolbar {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                gap: 8px;
            }

            .fc-toolbar-chunk:empty {
                display: none !important;
            }
        }

        /* RESPONSIVE DIRECTORY TABLES */
        @media (max-width: 767px) {
            .table-responsive thead {
                display: none;
            }

            .table-responsive table,
            .table-responsive tbody,
            .table-responsive tr,
            .table-responsive td {
                display: block;
                width: 100%;
            }

            .table-responsive tr {
                background: #ffffff;
                border: 1px solid #e0e0e0;
                border-radius: 10px;
                padding: 12px;
                margin-bottom: 12px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            }

            .table-responsive td {
                text-align: left;
                padding: 6px 4px !important;
                border: none !important;
            }

            .table-responsive td::before {
                content: attr(data-label);
                float: left;
                font-weight: 700;
                text-transform: uppercase;
                font-size: 0.75rem;
                color: #6c757d;
                width: 40%;
            }

            .table-responsive td>div,
            .table-responsive td>code,
            .table-responsive td>span,
            .table-responsive td>form {
                display: inline-block;
                width: 60%;
            }

            .table-responsive td.text-center {
                text-align: left !important;
            }

            .table-responsive td.text-center::before {
                content: attr(data-label);
            }
        }
    </style>
</head>

<body>

    <nav class="top-navbar d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <img src="../img/PUPLogo.png" alt="Logo" width="40" height="40" class="me-3">
            <div>
                <h5 class="fw-bold m-0">PUP-STC</h5>
                <small class="opacity-75">Classroom Management System</small>
            </div>
        </div>
        <div class="dropdown">
            <button class="btn text-white dropdown-toggle border-0" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle me-2"></i>Admin
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </nav>

    <?php if (isset($_GET['msg'])): ?>
        <div class="container-fluid px-4 mb-3">
            <?php if ($_GET['msg'] === 'ProfAdded' && isset($_GET['id'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-success border-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i><strong>Professor Registered!</strong> Account generated with ID: <code class="bg-dark text-white px-2 py-0.5 rounded"><?php echo htmlspecialchars($_GET['id']); ?></code> (Default Pass: <code>prof123</code>).
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['msg'] === 'RepAdded' && isset($_GET['id'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-success border-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i><strong>Student Representative Registered!</strong> Account generated with ID: <code class="bg-dark text-white px-2 py-0.5 rounded"><?php echo htmlspecialchars($_GET['id']); ?></code> (Default Pass: <code>student123</code>).
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['msg'] === 'ProfDeleted'): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-danger border-4" role="alert">
                    <i class="fas fa-trash-alt me-2"></i><strong>Faculty Profile Removed!</strong> The professor account has been deleted from the system.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['msg'] === 'RepDeleted'): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-danger border-4" role="alert">
                    <i class="fas fa-trash-alt me-2"></i><strong>Representative Removed!</strong> The student representative account has been deleted for the new school year.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['msg'] === 'ClassSuspended'): ?>
                <div class="alert alert-warning alert-dismissible fade show shadow-sm border-start border-warning border-4" role="alert">
                    <i class="fas fa-ban me-2"></i><strong>Schedule Cancelled!</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['msg'] === 'ReservationCancelled'): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-danger border-4" role="alert">
                    <i class="fas fa-times-circle me-2"></i><strong>Booking Discarded!</strong> The targeted room reservation has been rejected.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['msg'] === 'BlockFirstComeFirstServe'): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-danger border-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><strong>Approval Blocked!</strong> There is an older pending reservation request for this room layout. You must accept or decline that request first to preserve the First-Come, First-Served requirement.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['msg'] === 'ReservationApproved'): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-success border-4" role="alert">
                    <i class="fas fa-check me-2"></i><strong>Reservation Confirmed!</strong> The request has been cleanly accepted and plotted.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($_GET['msg'] === 'ReservationRejected'): ?>
                <div class="alert alert-secondary alert-dismissible fade show shadow-sm border-start border-secondary border-4" role="alert">
                    <i class="fas fa-times me-2"></i><strong>Reservation Declined!</strong> The booking request was discarded.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-lg-3 col-xl-2">
                <div class="sidebar-card p-3 mb-4">
                    <span class="section-label">Current View</span>
                    <div class="active-room-box">
                        <small class="text-muted d-block">Target Room:</small>
                        <strong id="activeRoomName" class="fs-6">Computer Lab 1</strong>
                    </div>

                    <button class="btn-upload mb-4" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-file-csv me-2"></i>Upload Schedule
                    </button>

                    <span class="section-label">Management</span>
                    <button class="btn-mgmt" data-bs-toggle="modal" data-bs-target="#roomModal">
                        <i class="fas fa-door-open"></i> Add New Room
                    </button>
                    <button class="btn-mgmt" data-bs-toggle="modal" data-bs-target="#profModal">
                        <i class="fas fa-user-tie"></i> Add Professor
                    </button>
                    <button class="btn-mgmt" data-bs-toggle="modal" data-bs-target="#studRepModal">
                        <i class="fas fa-user-graduate"></i> Add Student Rep.
                    </button>
                </div>
            </div>

            <div class="col-lg-9 col-xl-10">
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>

                <div class="card border-0 shadow-sm mt-4" style="border-radius: 15px;">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold m-0 text-dark"><i class="fas fa-clipboard-check me-2" style="color: var(--pup-maroon);"></i>Pending Reservations Queue</h5>
                        <span class="badge bg-danger text-white fw-semibold px-3 py-2"><?php echo $pending_requests->num_rows; ?> Needs Review</span>
                    </div>
                    <div class="table-responsive px-4 pb-4">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light small text-uppercase fw-bold text-muted">
                                <tr>
                                    <th>Room</th>
                                    <th>Applicant Profile</th>
                                    <th>Time Frame Request</th>
                                    <th>Submitted On</th>
                                    <th>Purpose State</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pending_requests->num_rows > 0): ?>
                                    <?php while ($req = $pending_requests->fetch_assoc()): ?>
                                        <tr>
                                            <td data-label="Room Targeted">
                                                <span class="fw-bold text-dark"><i class="fas fa-door-closed me-2 text-secondary"></i><?php echo htmlspecialchars($req['room_name']); ?></span>
                                            </td>
                                            <td data-label="Applicant Profile">
                                                <div class="fw-bold text-secondary"><?php echo htmlspecialchars($req['applicant_name'] ?? 'Unknown User'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($req['applicant_section'] ?? 'N/A'); ?></small>
                                            </td>
                                            <td data-label="Time Frame Request">
                                                <div class="small fw-semibold text-dark"><i class="far fa-calendar-alt me-1 text-muted"></i> <?php echo date('M d, Y', strtotime($req['reservation_date'])); ?></div>
                                                <div class="small text-muted"><i class="far fa-clock me-1 text-muted"></i> <?php echo date('h:i A', strtotime($req['start_time'])) . ' - ' . date('h:i A', strtotime($req['end_time'])); ?></div>
                                            </td>
                                            <td data-label="Submitted On">
                                                <span class="badge bg-light text-dark border"><i class="fas fa-hourglass-start me-1 text-warning"></i> <?php echo date('M d, Y - h:i A', strtotime($req['created_at'])); ?></span>
                                            </td>
                                            <td data-label="Purpose State">
                                                <span class="small text-muted" title="<?php echo htmlspecialchars($req['purpose']); ?>"><?php echo htmlspecialchars(substr($req['purpose'], 0, 30)) . (strlen($req['purpose']) > 30 ? '...' : ''); ?></span>
                                            </td>
                                            <td class="text-center" data-label="Status">
                                                <form method="POST" class="d-inline-flex gap-2">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" name="update_reservation_status" value="Accepted" class="btn btn-sm btn-success px-3 rounded-pill fw-semibold">
                                                        <i class="fas fa-check me-1"></i> Accept
                                                    </button>
                                                    <button type="submit" name="update_reservation_status" value="Rejected" class="btn btn-sm btn-outline-danger px-3 rounded-pill fw-semibold">
                                                        <i class="fas fa-times me-1"></i> Decline
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted small"><i class="fas fa-inbox d-block mb-2 fs-4"></i> No pending room allocation tracks waiting for review.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mt-4" style="border-radius: 15px;">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold m-0 text-dark"><i class="fas fa-user-tie me-2" style="color: var(--pup-maroon);"></i>Registered Faculty</h5>
                        <span class="badge bg-light text-dark border fw-semibold"><?php echo $professors_res->num_rows; ?> Total Professors</span>
                    </div>
                    <div class="table-responsive px-4 pb-4">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light small text-uppercase fw-bold text-muted">
                                <tr>
                                    <th>Professor Name</th>
                                    <th>Generated Account ID</th>
                                    <th>Department</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($professors_res->num_rows > 0): ?>
                                    <?php while ($prof = $professors_res->fetch_assoc()): ?>
                                        <tr>
                                            <td data-label="Professor Name">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px; background: #fff5f5;">
                                                        <i class="fas fa-user text-danger small"></i>
                                                    </div>
                                                    <span class="fw-bold text-secondary small"><?php echo htmlspecialchars($prof['name']); ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Generated Account ID">
                                                <code class="fw-bold text-dark bg-light px-2 py-1 rounded border"><?php echo htmlspecialchars($prof['id_number']); ?></code>
                                            </td>
                                            <td data-label="Department">
                                                <span class="badge bg-light text-secondary border small"><?php echo htmlspecialchars($prof['section_name'] ?? 'General Faculty'); ?></span>
                                            </td>
                                            <td class="text-center" data-label="Status">
                                                <div class="d-flex align-items-center justify-content-center gap-3">
                                                    <span class="small text-success fw-bold"><i class="fas fa-circle me-1 small" style="font-size: 0.5rem;"></i> Active</span>

                                                    <form method="POST" class="delete-user-form d-inline">
                                                        <input type="hidden" name="delete_id" value="<?php echo $prof['id_number']; ?>">
                                                        <input type="hidden" name="role_type" value="professor">
                                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0 trigger-delete-btn" data-message="Are you sure you want to remove the professor profile account for <?php echo htmlspecialchars($prof['name']); ?>?" title="Delete Professor">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">No professor accounts found in the system.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Registered Student Representatives Panel -->
                <div class="card border-0 shadow-sm mt-4 mb-5" style="border-radius: 15px;">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold m-0 text-dark"><i class="fas fa-user-graduate me-2" style="color: var(--pup-maroon);"></i>Registered Student Representatives</h5>
                        <span class="badge bg-light text-dark border fw-semibold"><?php echo $student_reps_res->num_rows; ?> Total Reps</span>
                    </div>
                    <div class="table-responsive px-4 pb-4">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light small text-uppercase fw-bold text-muted">
                                <tr>
                                    <th>Representative Name</th>
                                    <th>Generated Account ID</th>
                                    <th>Course</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($student_reps_res->num_rows > 0): ?>
                                    <?php while ($rep = $student_reps_res->fetch_assoc()): ?>
                                        <tr>
                                            <td data-label="Representative Name">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px; background: #fff5f5;">
                                                        <i class="fas fa-graduation-cap text-danger small"></i>
                                                    </div>
                                                    <span class="fw-bold text-secondary small"><?php echo htmlspecialchars($rep['name']); ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Generated Account ID">
                                                <code class="fw-bold text-dark bg-light px-2 py-1 rounded border"><?php echo htmlspecialchars($rep['id_number']); ?></code>
                                            </td>
                                            <td data-label="Course">
                                                <span class="badge bg-light text-secondary border small"><?php echo htmlspecialchars($rep['section_name'] ?? 'General Student'); ?></span>
                                            </td>
                                            <td class="text-center" data-label="Status">
                                                <div class="d-flex align-items-center justify-content-center gap-3">
                                                    <span class="small text-success fw-bold"><i class="fas fa-circle me-1 small" style="font-size: 0.5rem;"></i> Active</span>

                                                    <!-- Integrated Modal Deletion Form Button Structure -->
                                                    <form method="POST" class="delete-user-form d-inline">
                                                        <input type="hidden" name="delete_id" value="<?php echo $rep['id_number']; ?>">
                                                        <input type="hidden" name="role_type" value="student">
                                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0 trigger-delete-btn" data-message="Are you sure you want to remove the student representative account for <?php echo htmlspecialchars($rep['name']); ?>?" title="Delete Representative">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">No student representative accounts found in the system.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal fade" id="roomModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Register New Room</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Room Name / Number</label>
                                        <input type="text" name="room_name" class="form-control" placeholder="e.g., NB 101" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Location Structure</label>
                                        <input type="text" name="location" class="form-control" placeholder="e.g., Main Building, 1st Floor" required>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label fw-bold">Room Type Classification</label>
                                        <select name="room_type" class="form-select" required>
                                            <option value="Classroom">Classroom Block</option>
                                            <option value="lab">Laboratory Unit (lab)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer border-0">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_room" class="btn btn-danger px-4">Create Room</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="profModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add Professor Profile</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="" method="POST">
                                <div class="modal-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Full Name</label>
                                        <input type="text" name="prof_name" class="form-control" placeholder="e.g., Dr. Juan Dela Cruz" required>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label fw-bold">Department</label>
                                        <select name="department" class="form-select" required>
                                            <option value="IT Department">Information Technology</option>
                                            <option value="Engineering Department">Engineering</option>
                                            <option value="Education Department">Education</option>
                                            <option value="Business Department">Business</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer border-0">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_prof" class="btn btn-danger px-4">Save Profile</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="studRepModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-user-graduate me-2"></i>Add Student Representative</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="" method="POST">
                                <div class="modal-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Full Name</label>
                                        <input type="text" name="rep_name" class="form-control" placeholder="e.g., Juan Carlo" required>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label fw-bold">Section / Course Group Block</label>
                                        <input type="text" name="section_name" class="form-control" placeholder="e.g., DIT 3-1" required>
                                    </div>
                                </div>
                                <div class="modal-footer border-0">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_stud_rep" class="btn btn-danger px-4">Save Representative</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="uploadModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header">
                                <h5 class="modal-title">Upload Schedule</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="./upload_handler.php" method="POST" enctype="multipart/form-data">
                                <div class="modal-body p-4">
                                    <input type="hidden" name="room_name" id="hiddenRoomInput">
                                    <div class="p-3 bg-light rounded mb-3">
                                        <small class="text-muted">Targeting:</small><br>
                                        <strong id="modalRoomTarget" class="text-dark"></strong>
                                    </div>
                                    <label class="form-label fw-bold">Select CSV File</label>
                                    <input type="file" name="schedule_csv" class="form-control" accept=".csv" required>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-danger px-4">Process Upload</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="adminActionModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header bg-dark text-white">
                                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Manage Cancellation</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body p-4">
                                    <input type="hidden" name="action_type" id="modalActionType">
                                    <input type="hidden" name="target_id" id="modalTargetId">

                                    <p class="mb-2">You are cancelling this <strong id="infoBlockType" class="text-uppercase text-danger"></strong></p>

                                    <div class="p-3 bg-light rounded border mb-3">
                                        <div class="small fw-bold mb-1 text-dark" id="infoSubject"></div>
                                        <div class="text-muted small" id="infoProfessor"></div>
                                        <div class="text-muted small" id="infoSection"></div>
                                    </div>

                                    <div id="classDateInputGroup" style="display: none;">
                                        <label class="form-label fw-bold text-dark">Specify Effective Cancellation Date:</label>
                                        <input type="date" name="selected_cancel_date" id="selected_cancel_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                        <small class="text-muted d-block mt-1">This room instance slot will open up exclusively for this calendar date.</small>
                                    </div>

                                    <div id="reservationNoticeGroup" style="display: none;">
                                        <p class="text-muted small mb-0">Proceeding will reject this booking reservation track and clear the space layout completely.</p>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 bg-light">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="admin_cancel_action" class="btn btn-danger px-4">Confirm Cancellation</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="customConfirmModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-shadow="none" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px; background: #fff5f5;">
                                    <i class="fas fa-trash-alt text-danger fs-3"></i>
                                </div>
                                <p class="m-0 fw-semibold text-dark fs-5" id="confirmModalMessage">Are you sure you want to proceed?</p>
                                <small class="text-muted d-block mt-2">This action completely removes the profile record from the database storage framework and cannot be undone.</small>
                            </div>
                            <div class="modal-footer bg-light border-0 justify-content-center gap-2">
                                <button type="button" class="btn btn-secondary px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="confirmModalSubmitBtn" class="btn btn-danger px-4 rounded-pill fw-semibold">Delete Record</button>
                            </div>
                        </div>
                    </div>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var calendarEl = document.getElementById('calendar');
                        const allEvents = <?php echo json_encode($calendar_events); ?>;
                        const roomsList = <?php echo json_encode($rooms_array); ?>;
                        const isMobile = window.innerWidth < 768;

                        // Delete System Setup Variables
                        let activeDeleteForm = null;
                        const confirmModalEl = document.getElementById('customConfirmModal');
                        const confirmModal = new bootstrap.Modal(confirmModalEl);
                        const modalMessageText = document.getElementById('confirmModalMessage');
                        const modalSubmitBtn = document.getElementById('confirmModalSubmitBtn');

                        // Intercept custom trash clicks for a modern popup display framework
                        document.querySelectorAll('.trigger-delete-btn').forEach(button => {
                            button.addEventListener('click', function() {
                                activeDeleteForm = this.closest('.delete-user-form');
                                const warningMessage = this.getAttribute('data-message') || "Are you sure you want to completely proceed?";
                                modalMessageText.innerText = warningMessage;
                                confirmModal.show();
                            });
                        });

                        // Handle submission click framework inside modal instance
                        modalSubmitBtn.addEventListener('click', function() {
                            if (activeDeleteForm) {
                                const hiddenSubmitField = document.createElement('input');
                                hiddenSubmitField.type = 'hidden';
                                hiddenSubmitField.name = 'delete_user';
                                hiddenSubmitField.value = '1';
                                activeDeleteForm.appendChild(hiddenSubmitField);
                                activeDeleteForm.submit();
                            }
                        });

                        function injectRoomButton() {
                            let roomOptions = "";
                            roomsList.forEach(r => {
                                roomOptions += `<li><a class="dropdown-item" href="#" onclick="updateTargetRoom('${r}')">${r}</a></li>`;
                            });

                            const toolbarLeft = document.querySelector('.fc-toolbar-chunk:first-child');
                            if (toolbarLeft) {
                                toolbarLeft.innerHTML = `
                <div class="dropdown">
                    <button class="room-selector-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown">
                        <i class="fas fa-exchange-alt me-2"></i>Change Room
                    </button>
                    <ul class="dropdown-menu shadow border-0">${roomOptions}</ul>
                </div>`;
                            }
                        }

                        var calendar = new FullCalendar.Calendar(calendarEl, {
                            initialView: isMobile ? 'timeGridDay' : 'timeGridWeek',
                            slotMinTime: '07:30:00',
                            slotMaxTime: '22:00:00',
                            allDaySlot: false,
                            height: 'auto',
                            stickyHeaderDates: true,

                            eventContent: function(arg) {
                                let subject = arg.event.extendedProps.subject || '';
                                let section = arg.event.extendedProps.section || '';
                                let professor = arg.event.extendedProps.professor || '';
                                let durationHours = 2.0;

                                if (arg.event.start && arg.event.end) {
                                    durationHours = (arg.event.end - arg.event.start) / (1000 * 60 * 60);
                                } else {
                                    let startTimeStr = arg.event.startStr || '';
                                    let endTimeStr = arg.event.endStr || '';

                                    if (startTimeStr && endTimeStr) {
                                        let startParts = startTimeStr.split(':').map(Number);
                                        let endParts = endTimeStr.split(':').map(Number);

                                        if (startParts.length >= 2 && endParts.length >= 2) {
                                            let startDecimal = startParts[0] + (startParts[1] / 60);
                                            let endDecimal = endParts[0] + (endParts[1] / 60);
                                            durationHours = endDecimal - startDecimal;
                                        }
                                    }
                                }

                                let timeSize = '1.3em',
                                    subjectSize = '1.4em',
                                    sectionSize = '1.2em',
                                    profSize = '1.1em';
                                let showProfessor = true;

                                if (durationHours <= 1.1) {
                                    timeSize = '0.8em';
                                    subjectSize = '0.85em';
                                    sectionSize = '0.8em';
                                    showProfessor = false;
                                } else if (durationHours > 1.1 && durationHours <= 1.5) {
                                    timeSize = '0.95em';
                                    subjectSize = '1.05em';
                                    sectionSize = '0.85em';
                                    profSize = '0.8em';
                                } else if (durationHours > 1.5 && durationHours <= 2.5) {
                                    timeSize = '1.15em';
                                    subjectSize = '1.2em';
                                    sectionSize = '1.0em';
                                    profSize = '0.9em';
                                }

                                let profDisplay = (professor !== 'N/A' && professor !== '' && showProfessor) ?
                                    `<div style="font-size: ${profSize}; font-style: italic; opacity: 0.85; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; margin-top: 1px;"><i class="fas fa-user-tie me-1"></i>${professor}</div>` : '';

                                return {
                                    html: `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; text-align: center; padding: 1px 2px; overflow: hidden; line-height: 1.1;">
                <div style="font-size: ${timeSize}; font-weight: bold; margin-bottom: 1px;">${arg.timeText}</div>
                <div style="font-size: ${subjectSize}; font-weight: 800; text-transform: uppercase; max-width: 100%; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; margin-bottom: 1px;">${subject}</div>
                <div style="font-size: ${sectionSize}; opacity: 0.9; font-weight: 600;">${section}</div>
                ${profDisplay} 
            </div>`
                                };
                            },

                            headerToolbar: {
                                left: 'roomSelectorBtn',
                                center: 'title',
                                right: isMobile ? 'prev,next' : ''
                            },

                            eventClick: function(info) {
                                const props = info.event.extendedProps;

                                document.getElementById('modalActionType').value = props.type;
                                document.getElementById('modalTargetId').value = props.schedule_id;

                                document.getElementById('infoBlockType').innerText = props.type;
                                document.getElementById('infoSubject').innerText = (props.subject ? props.subject : '') + ' - ' + (props.subject_name ? props.subject_name : '');
                                document.getElementById('infoProfessor').innerText = "Faculty: " + props.professor;
                                document.getElementById('infoSection').innerText = "Allocated Group: " + props.section;

                                const dateGroup = document.getElementById('classDateInputGroup');
                                const noticeGroup = document.getElementById('reservationNoticeGroup');
                                const dateInput = document.getElementById('selected_cancel_date');

                                if (props.type === 'Schedule') {
                                    dateGroup.style.display = 'block';
                                    noticeGroup.style.display = 'none';
                                    dateInput.required = true;
                                } else {
                                    dateGroup.style.display = 'none';
                                    noticeGroup.style.display = 'block';
                                    dateInput.required = false;
                                }

                                var myModal = new bootstrap.Modal(document.getElementById('adminActionActionModal'));
                                myModal.show();
                            },

                            datesSet: function() {
                                injectRoomButton();
                            },

                            windowResize: function(arg) {
                                if (window.innerWidth < 768) {
                                    calendar.changeView('timeGridDay');
                                    calendar.setOption('headerToolbar', {
                                        left: 'roomSelectorBtn',
                                        center: 'title',
                                        right: 'prev,next'
                                    });
                                } else {
                                    calendar.changeView('timeGridWeek');
                                    calendar.setOption('headerToolbar', {
                                        left: 'roomSelectorBtn',
                                        center: 'title',
                                        right: ''
                                    });
                                }
                                setTimeout(injectRoomButton, 50);
                            },

                            dayHeaderContent: function(arg) {
                                return arg.date.toLocaleDateString('en-US', {
                                    weekday: 'long'
                                });
                            },

                            titleFormat: function() {
                                return 'SEMESTER SCHEDULE';
                            },

                            events: function(info, successCallback) {
                                const activeRoomEl = document.getElementById('activeRoomName');
                                if (!activeRoomEl) return successCallback([]);

                                const activeRoom = activeRoomEl.innerText.trim().toLowerCase();
                                const filtered = allEvents.filter(e => e.title.trim().toLowerCase() === activeRoom);
                                successCallback(filtered);
                            }
                        });

                        calendar.render();
                        window.currentCalendar = calendar;

                        const urlParams = new URLSearchParams(window.location.search);
                        const targetRoomParam = urlParams.get('room_id');

                        if (targetRoomParam) {
                            updateTargetRoom(targetRoomParam);
                        } else if (roomsList.length > 0) {
                            updateTargetRoom(roomsList[0]);
                        }
                    });

                    function updateTargetRoom(room) {
                        const cleanRoom = room.trim();
                        const nameDisplay = document.getElementById('activeRoomName');
                        const modalDisplay = document.getElementById('modalRoomTarget');
                        const hiddenInput = document.getElementById('hiddenRoomInput');

                        if (nameDisplay) nameDisplay.innerText = cleanRoom;
                        if (modalDisplay) modalDisplay.innerText = cleanRoom;
                        if (hiddenInput) hiddenInput.value = cleanRoom;

                        if (window.currentCalendar) {
                            window.currentCalendar.refetchEvents();
                        }
                    }
                </script>
</body>

</html>