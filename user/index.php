<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['account_id'])) {
    header("Location: ../login.php");
    exit();
}

/** @var mysqli $conn */
include('../connect.php');

$user_id = $_SESSION['account_id'];
$account_no = $_SESSION['account_number'];
$clean_id = mysqli_real_escape_string($conn, $user_id);

$user_result = mysqli_query($conn, "SELECT * FROM users WHERE id = '$clean_id' LIMIT 1");
$user_data = mysqli_fetch_assoc($user_result);
$section_name = $user_data['section_name'] ?? "Guest User";

// 1. Fetch the User's Weekly Schedule
$master_sched_query = "SELECT * FROM class_schedules WHERE section_name = '$section_name' ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time ASC";
$master_result = mysqli_query($conn, $master_sched_query);
$master_schedules = [];
while ($row = mysqli_fetch_assoc($master_result)) {
    $master_schedules[] = $row;
}

// 2. Fetch ALL Cancelled Classes to pass to JavaScript
$cancel_query = "SELECT * FROM cancelled_classes";
$cancel_result = mysqli_query($conn, $cancel_query);
$all_cancellations = [];
while ($row = mysqli_fetch_assoc($cancel_result)) {
    $all_cancellations[] = $row;
}

// 3. Fetch Occupied Slots (Accepted Reservations)
$occ_query = "SELECT room_name, reservation_date, start_time, end_time FROM reservations WHERE status = 'Accepted'";
$occ_result = mysqli_query($conn, $occ_query);
$db_occupied = [];
while ($row = mysqli_fetch_assoc($occ_result)) {
    $key = $row['reservation_date'] . "_" . $row['room_name'];
    $db_occupied[$key][] = [
        'start' => substr($row['start_time'], 0, 5),
        'end' => substr($row['end_time'], 0, 5)
    ];
}

// 4. Fetch User's Personal Reservation History
$my_res_query = "SELECT * FROM reservations WHERE id_number = '$account_no' ORDER BY created_at DESC";
$my_res_result = mysqli_query($conn, $my_res_query);
$history = [];
while ($row = mysqli_fetch_assoc($my_res_result)) {
    $history[] = [
        'classroom' => $row['room_name'],
        'refNo'     => $row['id'],
        'purpose'   => $row['purpose'],
        'status'    => $row['status'],
        'dateTime'  => date("M d", strtotime($row['reservation_date'])) . " (" .
            date("h:i A", strtotime($row['start_time'])) . " - " .
            date("h:i A", strtotime($row['end_time'])) . ")"
    ];
}

// 5. Fetch Classroom List
$class_query = "SELECT * FROM classrooms ORDER BY room_name ASC";
$class_result = mysqli_query($conn, $class_query);
$classroom_list = [];
while ($row = mysqli_fetch_assoc($class_result)) {
    $classroom_list[] = [
        "name" => $row['room_name'],
        "loc" => $row['location'],
        "cat" => $row['room_type'] ?? "General",
        "cap" => ($row['max_capacity'] ?? "40") . " pax",
        "img" => "../img/" . $row['image_url']
    ];
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PUPSTC CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pup-maroon: #800000;
            --pup-gold: #FFD700;
            --teal-primary: #008080;
        }

        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', sans-serif;
            overflow-x: hidden;
        }

        .top-navbar {
            background: var(--pup-maroon);
            color: white;
            padding: 10px 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .main-content {
            padding: 1.5rem;
        }

        .search-container,
        .schedule-container,
        .classroom-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }

        .filter-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: block;
        }

        .classroom-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .status-pill {
            display: inline-block;
            width: 100px;
            padding: 6px 0;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 4px;
            text-align: center;
        }

        .btn-schedule {
            background-color: var(--teal-primary);
            color: white;
            font-weight: 600;
            border-radius: 50px;
            white-space: nowrap;
        }

        .occupied-slot-item {
            font-size: 0.85rem;
            padding: 8px;
            background: #fff5f5;
            color: #c62828;
            border-left: 3px solid #c62828;
            border-radius: 4px;
            margin-bottom: 8px;
        }
    </style>
</head>

<body>

    <nav class="top-navbar d-flex justify-content-between align-items-center mb-4 sticky-top">
        <div class="d-flex align-items-center">
            <img src="../img/PUPLogo.png" alt="Logo" width="55" height="55" class="me-3">
            <div class="lh-sm">
                <h4 class="fw-bold m-0">PUP-STC</h4>
                <small class="opacity-75">Classroom Management System</small>
            </div>
        </div>

        <div class="dropdown">
            <div class="d-flex align-items-center bg-white bg-opacity-10 rounded-pill px-2 py-1 dropdown-toggle" role="button" data-bs-toggle="dropdown">
                <span class="small me-2 text-white"><?php echo $section_name; ?></span>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($section_name); ?>&background=FFD700&color=800000" class="rounded-circle" width="28">
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li class="px-3 py-2 text-muted small">ID: <?php echo $account_no; ?></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item small text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid main-content">
        <h4 class="fw-bold text-dark mb-3"><i class="fas fa-calendar-alt me-2 text-warning"></i>Class Schedule</h4>
        <div class="schedule-container p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase">
                            <th class="ps-4 d-none d-sm-table-cell">Preview</th>
                            <th>Room Details</th>
                            <th>Day/Time</th>
                            <th class="d-none d-md-table-cell">Subject</th>
                            <th>Status</th>
                            <th>Professor</th>
                        </tr>
                    </thead>
                    <tbody id="masterSchedTableBody"></tbody>
                </table>
            </div>
        </div>

        <div class="mb-3 mt-5">
            <h3 class="fw-bold text-dark mb-1">Reservations</h3>
            <p class="text-muted small">Book available slots for make-up classes.</p>
        </div>

        <div class="search-container">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <span class="filter-label">Reservation Date</span>
                    <input type="date" class="form-control form-control-sm" id="searchDate" value="<?php echo date('Y-m-d'); ?>" onchange="updateAllTables()">
                </div>
                <div class="col-12 col-md-8">
                    <span class="filter-label">Search Classroom</span>
                    <div class="input-group input-group-sm">
                        <select class="form-select" id="filterType" onchange="handleFilter()" style="max-width: 100px;">
                            <option value="All Types">All</option>
                            <option value="Laboratory">Lab</option>
                            <option value="Classroom">Room</option>
                        </select>
                        <input type="text" id="filterSearch" class="form-control" placeholder="Room name..." onkeyup="handleFilter()">
                    </div>
                </div>
            </div>
        </div>

        <div class="classroom-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-4 d-none d-md-table-cell">Preview</th>
                            <th>Name & Location</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="roomTableBody"></tbody>
                </table>
            </div>
        </div>

        <h4 class="fw-bold text-dark mb-3 mt-5"><i class="fas fa-ticket-alt me-2 text-primary"></i>My Status</h4>
        <div class="classroom-card p-0 overflow-hidden mb-5">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-4">Classroom</th>
                            <th>Date & Time</th>
                            <th class="text-center">Verification</th>
                        </tr>
                    </thead>
                    <tbody id="statusTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Request Reservation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3 bg-light p-3 rounded border">
                        <label class="filter-label">Target Classroom</label>
                        <h5 class="fw-bold text-dark mb-0" id="selectedClassroom">--</h5>
                        <small class="text-muted" id="modalDateDisplay"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-danger">Occupied Slots</label>
                        <div id="occupiedListContainer" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">Start Time</label>
                            <input type="time" id="startTime" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">End Time</label>
                            <input type="time" id="endTime" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Purpose</label>
                        <select class="form-select" id="reservationPurpose">
                            <option>Class</option>
                            <option>Seminar</option>
                            <option>Make-up Class</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-dark px-4 fw-bold" onclick="submitReservation()">Submit Request</button>
                </div>
                <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-sm modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg text-center p-4">
                            <div id="statusIconContainer" class="mb-3">
                                <i id="statusIcon" class="fas fa-exclamation-triangle fa-3x"></i>
                            </div>
                            <h5 id="statusTitle" class="fw-bold mb-2">Notice</h5>
                            <p id="statusMessage" class="text-muted small mb-3"></p>
                            <button type="button" class="btn btn-dark btn-sm w-100" data-bs-dismiss="modal">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data passed from PHP
        const classrooms = <?php echo json_encode($classroom_list); ?>;
        const myReservations = <?php echo json_encode($history); ?>;
        const occupiedSlots = <?php echo json_encode($db_occupied); ?>;
        const masterSchedules = <?php echo json_encode($master_schedules); ?>;
        const cancellations = <?php echo json_encode($all_cancellations); ?>;

        // --- NEW: THE MISSING NOTIFICATION FUNCTION ---
        function showNotify(message, type = 'warning') {
            const titleEl = document.getElementById('statusTitle');
            const msgEl = document.getElementById('statusMessage');
            const iconEl = document.getElementById('statusIcon');
            const modalDiv = document.getElementById('statusModal');
            const modal = new bootstrap.Modal(modalDiv);

            msgEl.innerText = message;

            if (type === 'success') {
                titleEl.innerText = "Success!";
                iconEl.className = "fas fa-check-circle fa-3x text-success";
                modalDiv.addEventListener('hidden.bs.modal', () => location.reload(), {
                    once: true
                });
            } else {
                titleEl.innerText = "Notice";
                iconEl.className = "fas fa-times-circle fa-3x text-danger";
            }
            modal.show();
        }

        const formatTime = (timeStr) => {
            if (!timeStr) return "";
            let [hours, minutes] = timeStr.split(':');
            let modifier = 'AM';
            hours = parseInt(hours);
            if (hours >= 12) {
                modifier = 'PM';
                if (hours > 12) hours -= 12;
            }
            if (hours === 0) hours = 12;
            return `${hours}:${minutes} ${modifier}`;
        };

        function renderMasterSchedule() {
            const selectedDate = document.getElementById('searchDate').value;
            const tbody = document.getElementById('masterSchedTableBody');
            const roomDetails = {};
            classrooms.forEach(c => {
                roomDetails[c.name] = {
                    img: c.img,
                    loc: c.loc
                };
            });

            if (masterSchedules.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No classes found.</td></tr>';
                return;
            }

            const now = new Date();
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const todayName = days[now.getDay()];
            const currentTime = now.getHours().toString().padStart(2, '0') + ":" + now.getMinutes().toString().padStart(2, '0');

            tbody.innerHTML = masterSchedules.map(s => {
                const isCancelled = cancellations.some(c => c.schedule_id == s.id && c.cancelled_date === selectedDate);
                const isToday = (s.day_of_week === todayName && selectedDate === now.toISOString().split('T')[0]);
                const start = s.start_time.substring(0, 5);
                const end = s.end_time.substring(0, 5);
                const isNow = isToday && (currentTime >= start && currentTime <= end);
                const details = roomDetails[s.room_name] || {
                    img: '../img/default.jpg',
                    loc: 'N/A'
                };

                let statusBadge = isCancelled ? '<span class="badge bg-danger small">Cancel</span>' :
                    (isNow ? '<span class="badge bg-success">NOW</span>' : '<span class="badge bg-light text-dark border small">Regular</span>');

                // Clean up empty or unassigned professor records gracefully
                let profName = (s.professor_name && s.professor_name.trim() !== "") ? s.professor_name : "TBA";

                // NEW UPDATED CODE
                return `<tr>
    <td class="ps-4 d-none d-sm-table-cell"><img src="${details.img}" class="classroom-img"></td>
    <td><div class="fw-bold small">${s.room_name}</div><div class="text-muted" style="font-size:0.7rem">${details.loc}</div></td>
    <td class="small"><div class="fw-bold">${formatTime(start)} - ${formatTime(end)}</div><div class="text-muted">${s.day_of_week}</div></td>
    
    <td class="d-none d-md-table-cell">
        <div class="fw-bold text-dark mb-1" style="font-size:0.85rem;">${s.subject_code}</div>
        <div class="text-muted small" style="font-size:0.75rem; max-width: 200px; white-space: normal;">
            ${s.subject_name ? s.subject_name : 'No Description'}
        </div>
    </td>
    
    <td>${statusBadge}</td>
    <td class="small fw-semibold text-secondary"><i class="fas fa-user-tie me-1 text-muted"></i> ${profName}</td>
</tr>`;
            }).join('');
        }

        function renderTable(dataToDisplay) {
            var list = dataToDisplay || classrooms;
            var htmlContent = "";
            var selectedDate = document.getElementById('searchDate').value;
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayName = days[new Date(selectedDate).getDay()];

            list.forEach(c => {
                const dateKey = selectedDate + "_" + c.name;
                let hasManualBooking = (occupiedSlots[dateKey] && occupiedSlots[dateKey].length > 0);
                let hasRegularClass = masterSchedules.some(s =>
                    s.room_name === c.name && s.day_of_week === dayName &&
                    !cancellations.some(can => can.schedule_id == s.id && can.cancelled_date === selectedDate)
                );

                let badge = hasRegularClass ? '<span class="badge bg-light text-secondary border small">Regular</span>' :
                    (hasManualBooking ? '<span class="badge bg-warning bg-opacity-10 text-warning border small">Schedule</span>' :
                        '<span class="badge bg-success bg-opacity-10 text-success border small">Available</span>');

                htmlContent += `<tr>
                <td class='d-none d-md-table-cell ps-4 align-middle'>
                <img src='${c.img}' class='classroom-img'>
                </td>

                <td class="align-middle">
                <div class='fw-bold small'>${c.name}</div>
                <div style='font-size: 0.7rem' class='text-muted'>${c.loc}</div>
                </td>

                <td class="align-middle">
                ${badge}
                </td>

                <td class='text-center align-middle'>
                <button class='btn btn-sm btn-schedule px-3' 
                onclick="prepareModal('${c.name}')" 
                data-bs-toggle='modal' 
                data-bs-target='#confirmModal'>
                Schedule
                </button>
                </td>
                </tr>`;
            });
            document.getElementById('roomTableBody').innerHTML = htmlContent;
        }

        function renderStatusTable() {
            const tbody = document.getElementById('statusTableBody');
            if (myReservations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted small">No records found.</td></tr>';
                return;
            }

            tbody.innerHTML = myReservations.map(res => {
                const status = res.status.toLowerCase();
                let statusHtml = "";
                let actionButtonsHtml = "";

                // Uniform styling rule to force every single badge and button to look identical in size
                const fixedSizeStyle = "width: 100px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 5px 0; text-align: center; border-radius: 4px; letter-spacing: 0.5px;";

                if (status === 'accepted') {
                    statusHtml = `<span class="badge bg-success status-pill" style="${fixedSizeStyle}">Approved</span>`;

                    // Stacks the Slip and Cancel buttons cleanly down the column line
                    actionButtonsHtml = `
                <button class="btn btn-sm btn-primary fw-bold shadow-sm" style="${fixedSizeStyle}" onclick="viewSlip('${res.refNo}')">
                <i></i> Slip
                </button>
                <button class="btn btn-sm btn-danger fw-bold shadow-sm" style="${fixedSizeStyle}" onclick="cancelReservation('${res.refNo}')">
                <i></i> Cancel
                </button>
            `;
                } else if (status === 'pending') {
                    statusHtml = `<span class="badge bg-info text-dark status-pill" style="${fixedSizeStyle}">In Queue</span>`;

                    actionButtonsHtml = `
                <button class="btn btn-sm btn-danger fw-bold shadow-sm" style="${fixedSizeStyle}" onclick="cancelReservation('${res.refNo}')">
                <i class="fas fa-times me-1"></i> Cancel
                </button>
            `;
                } else {
                    statusHtml = `<span class="badge bg-secondary status-pill" style="${fixedSizeStyle}">Cancelled</span>`;
                    actionButtonsHtml = "";
                }

                return `<tr>
                <td class="ps-4">
                <div class="fw-bold text-dark" style="font-size:0.9rem;">${res.classroom}</div>
                <div class="text-muted small" style="font-size:0.75rem;">Ref: #${res.refNo}</div>
                </td>
                <td class="small text-secondary fw-medium">${res.dateTime}</td>
                <td class="text-center">
                <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                    ${statusHtml}
                    ${actionButtonsHtml}
                </div>
                </td>
                </tr>`;
            }).join('');
        }

        // Add this function right at the bottom area of your scripts
        function viewSlip(refNo) {
            // This targets your existing file and passes the unique reference ID through the URL parameter
            window.open(`receipt.php?id=${refNo}`, '_blank');
        }

        function cancelReservation(refNo) {
            if (confirm(`Are you sure you want to cancel reservation request #${refNo}?`)) {
                const formData = new FormData();
                formData.append('refNo', refNo);

                fetch('cancel_reservation.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        showNotify(data.message, data.status);

                        if (data.status === 'success') {
                            // 1. Smoothly update the local data array status
                            const reservation = myReservations.find(res => res.refNo === refNo);
                            if (reservation) {
                                reservation.status = 'cancelled';
                            }

                            // 2. FORCE REMOVE THE STUCK FADE OVERLAY/BACKDROP
                            // This clears Bootstrap's modal backdrop if it gets stuck
                            const backdrops = document.querySelectorAll('.modal-backdrop, .modal-shadow');
                            backdrops.forEach(backdrop => backdrop.remove());

                            // If your overall wrapper body has a class trapping the dark light effect, reset it:
                            document.body.classList.remove('modal-open');
                            document.body.style.overflow = ''; // Restores scrolling if frozen

                            // 3. Re-render only the table rows instantly without a page refresh!
                            renderStatusTable();
                        }
                    })
                    .catch(err => {
                        showNotify("Error processing cancellation request.", "error");
                    });
            }
        }

        function updateAllTables() {
            renderMasterSchedule();
            renderTable();
        }

        function handleFilter() {
            const search = document.getElementById('filterSearch').value.toLowerCase();
            const type = document.getElementById('filterType').value;
            const filtered = classrooms.filter(c => c.name.toLowerCase().includes(search) && (type === "All Types" || c.cat === type));
            renderTable(filtered);
        }

        function prepareModal(roomName) {
            document.getElementById('selectedClassroom').innerText = roomName;
            const dateVal = document.getElementById('searchDate').value;
            document.getElementById('modalDateDisplay').innerText = "Date: " + dateVal;
            const container = document.getElementById('occupiedListContainer');
            const dateKey = dateVal + "_" + roomName;
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayName = days[new Date(dateVal).getDay()];

            const manualBookings = occupiedSlots[dateKey] || [];
            const preScheduled = masterSchedules.filter(s => s.room_name === roomName && s.day_of_week === dayName && !cancellations.some(can => can.schedule_id == s.id && can.cancelled_date === dateVal));

            let html = "";
            manualBookings.forEach(slot => {
                html += `<div class="occupied-slot-item"><strong>${formatTime(slot.start)} - ${formatTime(slot.end)}</strong> (Reserved)</div>`;
            });
            preScheduled.forEach(s => {
                let profName = (s.professor_name && s.professor_name.trim() !== "") ? s.professor_name : "TBA";
                html += `<div class="occupied-slot-item" style="background:#eee; color:#666; border-color:#999"><strong>${formatTime(s.start_time)} - ${formatTime(s.end_time)}</strong> (Class: ${s.subject_code} - ${profName})</div>`;
            });
            container.innerHTML = html || "<div class='small text-muted p-2'>Available all day.</div>";
        }

        function submitReservation() {
            const room = document.getElementById('selectedClassroom').innerText;
            const date = document.getElementById('searchDate').value;
            const start = document.getElementById('startTime').value;
            const end = document.getElementById('endTime').value;
            const purpose = document.getElementById('reservationPurpose').value;

            if (!start || !end) {
                showNotify("Please select start and end times.", "warning");
                return;
            }

            const startMin = parseInt(start.split(':')[0]) * 60 + parseInt(start.split(':')[1]);
            const endMin = parseInt(end.split(':')[0]) * 60 + parseInt(end.split(':')[1]);
            const duration = endMin - startMin;

            if (duration <= 0) {
                showNotify("End time must be after start time.", "error");
                return;
            }
            if (duration < 60) {
                showNotify("Minimum reservation duration is 1 hour.", "warning");
                return;
            }

            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayName = days[new Date(date).getDay()];

            const conflict = masterSchedules.find(s => {
                if (s.room_name !== room || s.day_of_week !== dayName) return false;
                const isCancelled = cancellations.some(c => c.schedule_id == s.id && c.cancelled_date === date);
                if (isCancelled) return false;
                return (start < s.end_time.substring(0, 5) && end > s.start_time.substring(0, 5));
            });

            if (conflict) {
                showNotify(`Conflict! This room has a regular class (${conflict.subject_code}) during that time.`, "error");
                return;
            }

            const formData = new FormData();
            formData.append('room', room);
            formData.append('date', date);
            formData.append('start', start);
            formData.append('end', end);
            formData.append('purpose', purpose);

            fetch('save_reservation.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    showNotify(data.message, data.status);
                })
                .catch(err => {
                    showNotify("Failed to connect to the server.", "error");
                });
        }

        updateAllTables();
        renderStatusTable();
    </script>
</body>

</html>