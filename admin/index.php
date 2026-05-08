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
// Add New Room (Now pointing to 'classrooms')
if (isset($_POST['add_room'])) {
    $new_room = mysqli_real_escape_string($conn, $_POST['room_name']);
    // Removed location/image for simplicity, but table 'classrooms' requires them or default values
    $conn->query("INSERT IGNORE INTO classrooms (room_name, location) VALUES ('$new_room', 'Main Building')");
    header("Location: index.php?msg=RoomAdded");
    exit();
}

// Add New Professor
// Add New Professor (Now pointing to 'users' with role 'professor')
if (isset($_POST['add_prof'])) {
    $new_prof = mysqli_real_escape_string($conn, $_POST['prof_name']);
    $temp_pass = password_hash('123456', PASSWORD_DEFAULT); // Give them a default password
    $prof_id = "PROF-" . rand(1000, 9999); // Generate a temporary ID number

    $conn->query("INSERT IGNORE INTO users (id_number, name, password, role) 
                  VALUES ('$prof_id', '$new_prof', '$temp_pass', 'professor')");
    header("Location: index.php?msg=ProfAdded");
    exit();
}
// --- DATA FETCHING ---
$query = "
    SELECT id, room_name, reservation_date AS event_date, start_time, end_time, 
           'N/A' as subject_code, 'Reservation' as section_name, 'Reservation' as type 
    FROM reservations 
    WHERE status = 'Accepted'
    UNION
    SELECT id, room_name, day_of_week AS event_date, start_time, end_time, 
           subject_code, section_name, 'Schedule' as type 
    FROM class_schedules
";
$result = mysqli_query($conn, $query);

$calendar_events = [];
while ($row = mysqli_fetch_assoc($result)) {
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
            'subject' => $row['subject_code'],
            'section' => $row['section_name']
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

        /* Mobile-specific adjustments */
        @media (max-width: 767px) {
            .calendar-container {
                padding: 10px;
            }

            /* Center the title and buttons on mobile */
            .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }

            /* Make buttons larger for touch */
            .fc-button {
                padding: 8px 16px !important;
            }
        }

        /* Custom Day Header style (no numbers) */
        .fc-col-header-cell-cushion {
            text-decoration: none !important;
            color: var(--pup-maroon) !important;
            text-transform: uppercase;
            font-weight: 700;
        }

        /* Hide empty button containers that cause those gray blocks */
        .fc .fc-button-group>.fc-button:empty {
            display: none !important;
        }

        .fc-toolbar-chunk {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Ensure the arrows are clean on mobile */
        @media (max-width: 767px) {
            .fc-toolbar {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                gap: 8px;
            }

            /* This fixes the gray blocks under the Change Room button */
            .fc-toolbar-chunk:empty {
                display: none !important;
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

    <div class="container-fluid px-4">
        <div class="row">
            <!-- SIDEBAR -->
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

            <!-- CALENDAR -->
            <div class="col-lg-9 col-xl-10">
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: ADD ROOM -->
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

    <!-- MODAL: ADD PROFESSOR -->
    <div class="modal fade" id="profModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add Professor Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" name="prof_name" class="form-control" placeholder="e.g., Dr. Juan Dela Cruz" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold">Department</label>
                            <select name="department" class="form-select">
                                <option value="IT">Information Technology</option>
                                <option value="Engineering">Engineering</option>
                                <option value="Education">Education</option>
                                <option value="Business">Business</option>
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

    <!-- MODAL: UPLOAD CSV -->
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

            // 1. Create a reusable injection function
            function injectRoomButton() {
                let roomOptions = "";
                roomsList.forEach(r => {
                    roomOptions += `<li><a class="dropdown-item" href="#" onclick="updateTargetRoom('${r}')">${r}</a></li>`;
                });

                // Target the specific chunk where 'roomSelectorBtn' is supposed to be
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

                    return {
                        html: `
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold;">${arg.timeText}</div>
                    <div style="font-size: 1.5em; font-weight: 800; text-transform: uppercase;">${subject}</div>
                    <div style="font-size: 1.5em; opacity: 0.9;">${section}</div>
                </div>
            `
                    };
                },

                headerToolbar: {
                    left: 'roomSelectorBtn', // This acts as our placeholder
                    center: 'title',
                    right: isMobile ? 'prev,next' : ''
                },

                // 2. The SECRET FIX: Use datesSet to re-inject after every navigation/render
                datesSet: function() {
                    injectRoomButton();
                },

                // 3. Consolidated Window Resize (One function only)
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
                    // Small delay to let FullCalendar finish drawing before we inject
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

            // Set initial room
            if (roomsList.length > 0) updateTargetRoom(roomsList[0]);
        });

        function updateTargetRoom(room) {
            const cleanRoom = room.trim(); // Add .trim() here
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