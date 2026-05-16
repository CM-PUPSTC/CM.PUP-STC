<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
/** @var mysqli $conn */
include('../connect.php');

// --- DATABASE HANDLERS ---

// Add New Room
if (isset($_POST['add_room'])) {
    $new_room = mysqli_real_escape_string($conn, $_POST['room_name']);
    $conn->query("INSERT IGNORE INTO classrooms (room_name, location) VALUES ('$new_room', 'Main Building')");
    header("Location: index.php?msg=RoomAdded");
    exit();
}

// Add New Professor
if (isset($_POST['add_prof'])) {
    $new_prof = mysqli_real_escape_string($conn, $_POST['prof_name']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    
    // CHANGED: Changed '123456' to 'prof123' so it automatically hashes 'prof123' instead
    $temp_pass = password_hash('prof123', PASSWORD_DEFAULT);
    
    $prof_id = "PROF-" . date('Y') . "-" . rand(1000, 9999);

    // Saving department inside the section_name column
    $conn->query("INSERT IGNORE INTO users (id_number, name, password, role, section_name) 
                  VALUES ('$prof_id', '$new_prof', '$temp_pass', 'professor', '$department')");
    header("Location: index.php?msg=ProfAdded&id=" . $prof_id);
    exit();
}

// --- DATA FETCHING ---
// Updated: Added 'Reservation' as subject_name and cs.subject_name to pull your new column row data cleanly!
$query = "
    SELECT id, room_name, reservation_date AS event_date, start_time, end_time, 
           'N/A' as subject_code, 'Reservation' as subject_name, 'Reservation' as section_name, 'N/A' as professor_name, 
           'Reservation' as type, NULL as schedule_id, NULL as is_cancelled_date
    FROM reservations 
    WHERE status = 'Accepted'
    
    UNION ALL
    
    SELECT cs.id, cs.room_name, cs.day_of_week AS event_date, cs.start_time, cs.end_time, 
           cs.subject_code, cs.subject_name, cs.section_name, cs.professor_name, 'Schedule' as type,
           cs.id as schedule_id, cc.cancelled_date as is_cancelled_date
    FROM class_schedules cs
    LEFT JOIN cancelled_classes cc ON cs.id = cc.schedule_id
";
$result = mysqli_query($conn, $query);

$calendar_events = [];
while ($row = mysqli_fetch_assoc($result)) {
    // NEW CHECKPOINT: If this specific row represents a class that was cancelled for a date, 
    // skip adding it to the calendar array so it disappears from the admin dashboard!
    if ($row['type'] === 'Schedule' && !empty($row['is_cancelled_date'])) {
        continue; 
    }

    $color = '#800000';
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
            'subject'      => $row['subject_code'],
            'subject_name' => $row['subject_name'], // <-- CHANGED: Now passing the descriptive subject name out!
            'section'      => $row['section_name'],
            'professor'    => $row['professor_name'],
            'type'         => $row['type'] // Helps your JavaScript differentiate scripts
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

// 1. ADDED FOR STEP B: Fetch professors list to populate our data layout
$professors_res = $conn->query("SELECT id_number, name, section_name FROM users WHERE role = 'professor' ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PUP-STC CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Sidebar Styling */
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

        /* RESPONSIVE FACULTY DIRECTORY */
        @media (max-width: 767px) {

            /* Hide the traditional table header layout on mobile */
            .table-responsive thead {
                display: none;
            }

            /* Force table elements to behave like block cards */
            .table-responsive table,
            .table-responsive tbody,
            .table-responsive tr,
            .table-responsive td {
                display: block;
                width: 100%;
            }

            /* Separate each professor row into an independent card block */
            .table-responsive tr {
                background: #ffffff;
                border: 1px solid #e0e0e0;
                border-radius: 10px;
                padding: 12px;
                margin-bottom: 12px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            }

            /* Remove default padding borders between cells */
            .table-responsive td {
                text-align: left;
                padding: 6px 4px !important;
                border: none !important;
            }

            /* Use data-label attributes to add a clean pseudo-header on the left */
            .table-responsive td::before {
                content: attr(data-label);
                float: left;
                font-weight: 700;
                text-transform: uppercase;
                font-size: 0.75rem;
                color: #6c757d;
                width: 40%;
            }

            /* Align the data contents cleanly to the right of our label */
            .table-responsive td>div,
            .table-responsive td>code,
            .table-responsive td>span {
                display: inline-block;
                width: 60%;
            }

            /* Fix centering for account status badge alignment */
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

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'ProfAdded' && isset($_GET['id'])): ?>
        <div class="container-fluid px-4 mb-3">
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-success border-4" role="alert">
                <i class="fas fa-check-circle me-2"></i><strong>Professor Registered!</strong> Account generated with ID: <code class="bg-dark text-white px-2 py-0.5 rounded"><?php echo htmlspecialchars($_GET['id']); ?></code> (Default Pass: <code>prof123</code>).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
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
                </div>
            </div>

            <div class="col-lg-9 col-xl-10">
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>

                <div class="card border-0 shadow-sm mt-4 mb-5" style="border-radius: 15px;">
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
                                    <th class="text-center">Account Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($professors_res->num_rows > 0): ?>
                                    <?php while ($prof = $professors_res->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px; background: #fff5f5;">
                                                        <i class="fas fa-user text-danger small"></i>
                                                    </div>
                                                    <span class="fw-bold text-secondary small"><?php echo htmlspecialchars($prof['name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="fw-bold text-dark bg-light px-2 py-1 rounded border"><?php echo htmlspecialchars($prof['id_number']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-secondary border small"><?php echo htmlspecialchars($prof['section_name'] ?? 'General Faculty'); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="small text-success fw-bold"><i class="fas fa-circle me-1 small" style="font-size: 0.5rem;"></i> Active</span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">No professor accounts found in the system. Use the sidebar button to add one.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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
                        <label class="form-label fw-bold">Room Name / Number</label>
                        <input type="text" name="room_name" class="form-control" placeholder="e.g., Computer Lab 3" required>
                        <small class="text-muted mt-2 d-block">Ensure the name matches your CSV headers.</small>
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

    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Schedule</h5>
                    <button type="button" class="btn-close btn-close" data-bs-dismiss="modal"></button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            const allEvents = <?php echo json_encode($calendar_events); ?>;
            const roomsList = <?php echo json_encode($rooms_array); ?>;
            const isMobile = window.innerWidth < 768;

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
                slotMinTime: '07:00:00',
                slotMaxTime: '22:00:00',
                allDaySlot: false,
                height: 'auto',
                stickyHeaderDates: true,
                eventContent: function(arg) {
                    let subject = arg.event.extendedProps.subject || '';
                    let section = arg.event.extendedProps.section || '';
                    let professor = arg.event.extendedProps.professor || ''; // <-- Fetch the prof name

                    // If it's a schedule, format a small layout string for the professor
                    let profDisplay = (professor !== 'N/A' && professor !== '') ? `<div style="font-size: 1.1em; font-style: italic; opacity: 0.85;"><i class="fas fa-user-tie me-1"></i>${professor}</div>` : '';

                    return {
                        html: `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; text-align: center; padding: 2px;">
                <div style="font-size: 1.3em; font-weight: bold;">${arg.timeText}</div>
                <div style="font-size: 1.4em; font-weight: 800; text-transform: uppercase;">${subject}</div>
                <div style="font-size: 1.2em; opacity: 0.9;">${section}</div>
                ${profDisplay} 
            </div>
        `
                    };
                },

                headerToolbar: {
                    left: 'roomSelectorBtn',
                    center: 'title',
                    right: isMobile ? 'prev,next' : ''
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

            if (roomsList.length > 0) updateTargetRoom(roomsList[0]);
        });

        function updateTargetRoom(room) {
            const cleanRoom = room.trim();
            const nameDisplay = document.getElementById('activeRoomName');
            const modalDisplay = document.getElementById('modalRoomTarget');
            const hiddenInput = document.getElementById('hiddenRoomInput');

            if (nameDisplay) nameDisplay.innerText = room;
            if (modalDisplay) modalDisplay.innerText = room;
            if (hiddenInput) hiddenInput.value = room;

            if (window.currentCalendar) {
                window.currentCalendar.refetchEvents();
            }
        }
    </script>
</body>

</html>