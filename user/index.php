<?php
// 1. START SESSION
session_start();

// 2. PREVENT CACHING (Important for security)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 3. THE SECURITY GATE (Check login immediately)
if (!isset($_SESSION['account_id'])) {
    header("Location: ../login.php");
    exit();
}

// 4. DATABASE CONNECTION (Must be before any queries!)
include('../connect.php');

// 5. DEFINE USER DATA
$user_id = $_SESSION['account_id'];
$clean_id = mysqli_real_escape_string($conn, $user_id);

// 6. FETCH USER PROFILE
$user_result = mysqli_query($conn, "SELECT * FROM users WHERE id = '$clean_id' LIMIT 1");
$user_data = mysqli_fetch_assoc($user_result);
$section_name = $user_data['section_name'] ?? "Guest User";
// index.php
$account_no = $_SESSION['account_number'];
$query = "SELECT * FROM reservations WHERE account_number = '$account_no' ORDER BY created_at DESC";
// This ensures they see Pending, Accepted, and Cancelled ones.

// 7. FETCH ALL ACCEPTED RESERVATIONS (Occupied Slots)
// Use 'reservation_date' to match your database
$occ_query = "SELECT room_name, reservation_date, start_time, end_time FROM reservations WHERE status = 'Accepted'";
$occ_result = mysqli_query($conn, $occ_query);
$db_occupied = [];

if ($occ_result) {
    while ($row = mysqli_fetch_assoc($occ_result)) {
        // Use the correct column name here too
        $key = $row['reservation_date'] . "_" . $row['room_name'];
        $db_occupied[$key][] = [
            'start' => substr($row['start_time'], 0, 5),
            'end' => substr($row['end_time'], 0, 5)
        ];
    }
}

// 8. FETCH LOGGED-IN USER'S RESERVATION HISTORY
// Change this to include all statuses
$my_res_query = "SELECT * FROM reservations WHERE account_number = '$account_no' ORDER BY created_at DESC";
$my_res_result = mysqli_query($conn, $my_res_query);

// 9. FETCH CLASSROOM LIST
$class_query = "SELECT * FROM classrooms ORDER BY room_name ASC";
$class_result = mysqli_query($conn, $class_query);
$classroom_list = [];

while ($row = mysqli_fetch_assoc($class_result)) {
    $classroom_list[] = [
        "name" => $row['room_name'],
        "loc" => $row['location'],
        "cat" => $row['room_type'],
        "cap" => $row['max_capacity'] . " pax",
        "img" => $row['image_url']
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
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
            /* Prevent horizontal scroll */
        }

        .navbar-pup {
            background-color: var(--pup-maroon);
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .search-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }

        .filter-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: block;
        }

        .classroom-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .classroom-img {
            width: 110px;
            height: 75px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .table thead th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            font-size: 0.8rem;
            border-top: none;
            padding: 15px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .btn-schedule {
            background-color: var(--teal-primary);
            color: white;
            font-weight: 600;
            border: none;
            padding: 8px 20px;
            transition: 0.2s;
            white-space: nowrap;
        }

        .btn-schedule:hover {
            background-color: #006666;
            color: white;
            transform: translateY(-1px);
        }

        .occupied-slot-item {
            font-size: 0.85rem;
            padding: 5px 10px;
            background: #fff5f5;
            color: #c62828;
            border-left: 3px solid #c62828;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .search-container {
                padding: 15px;
            }

            .classroom-img {
                width: 60px;
                height: 45px;
            }

            .navbar-brand {
                font-size: 1rem;
            }

            #filterType {
                width: 80px !important;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-pup py-2 mb-4">
        <div class="container-fluid px-2 px-md-4">
            <a class="navbar-brand text-white fw-bold d-flex align-items-center" href="#">
                <img src="../img/PUPLogo.png" alt="Logo" width="35" height="35" class="me-2 d-inline-block align-top">

                <span class="ms-2 d-none d-sm-inline">PUP-STC Classroom Reservation</span>
                <span class="ms-2 d-inline d-sm-none">PUP-STC</span>

            </a>
            <div class="dropdown">
                <div class="d-flex align-items-center bg-white bg-opacity-10 rounded-pill px-2 px-md-3 py-1 dropdown-toggle"
                    role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">

                    <span class="small me-2 d-none d-md-inline text-white">
                        <?php echo $section_name; ?>
                    </span>

                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($section_name); ?>&background=FFD700&color=800000"
                        class="rounded-circle" width="25" alt="Profile">
                </div>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="profileDropdown">
                    <li class="px-3 py-2">
                        <div class="fw-bold small"><?php echo $section_name; ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo $account_no; ?></div>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li><a class="dropdown-item small text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
                </ul>
            </div>
        </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-5">
        <div class="mb-4">
            <h3 class="fw-bold text-dark mb-1">Find a Room</h3>
            <p class="text-muted small">Browse and reserve campus rooms or laboratories.</p>
        </div>

        <div class="search-container">
            <div class="row g-3">
                <div class="col-12 col-md-4 col-lg-3">
                    <span class="filter-label">Reservation Date</span>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="far fa-calendar-alt"></i></span>
                        <input type="date" class="form-control" id="searchDate"
                            value="<?php echo date('Y-m-d'); ?>" onchange="renderTable()">
                    </div>
                </div>

                <div class="col-12 col-md-8 col-lg-9">
                    <span class="filter-label">Search Classroom</span>
                    <div class="input-group shadow-sm rounded">
                        <select class="form-select border-end-0 flex-grow-0" id="filterType"
                            onchange="handleFilter()"
                            style="width: auto; min-width: 100px;">
                            <option value="All Types" selected>All</option>
                            <option value="Facility">Gym</option>
                            <option value="Laboratory">Lab</option>
                            <option value="Classroom">Room</option>
                        </select>

                        <input type="text" id="filterSearch" class="form-control border-start-0"
                            placeholder="e.g. Lab..." onkeyup="handleFilter()">

                        <button class="btn btn-dark px-3 px-md-4" onclick="handleFilter()">
                            <i class="fas fa-search"></i>
                            <span class="d-none d-sm-inline ms-1">Find</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="classroom-card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="d-none d-md-table-cell">Preview</th>
                            <th>Name & Location</th>
                            <th class="d-none d-sm-table-cell">Category</th>
                            <th class="d-none d-lg-table-cell">Capacity</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="roomTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="container-fluid px-3 px-md-5">
        <div class="mb-3 mt-5 pt-4">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fas fa-tasks me-2 text-primary"></i>My Reservation Status
            </h4>
        </div>

        <div class="classroom-card shadow-sm bg-white mb-5">
            <div class="p-2 p-md-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-uppercase">
                                <th class="ps-3 ps-md-4">Classroom</th>
                                <th class="d-none d-md-table-cell">Purpose</th>
                                <th>Date & Time</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="statusTableBody">
                            <tr id="emptyStatusRow">
                                <td colspan="4" class="text-center py-4 text-muted small italic">No active requests
                                    found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mx-auto" style="max-width: 95%; width: 500px;">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Request Reservation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <div class="mb-3 bg-light p-3 rounded border">
                        <label class="filter-label mb-0">Target Classroom</label>
                        <h5 class="fw-bold text-dark mb-0" id="selectedClassroom">--</h5>
                        <small class="text-muted" id="modalDateDisplay"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-danger">Unavailable Times</label>
                        <div id="occupiedListContainer"></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Start</label>
                            <input type="time" id="startTime" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">End</label>
                            <input type="time" id="endTime" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Purpose</label>
                        <select class="form-select" id="reservationPurpose">
                            <option>Class Lecture</option>
                            <option>Seminar</option>
                            <option>Meeting</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold">Notes (Optional)</label>
                        <textarea class="form-control" id="reservationReason" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link text-muted text-decoration-none"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-dark px-4 fw-bold w-100 w-sm-auto"
                        onclick="submitReservation()">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // 1. DATA: Fetched from your PHP/Database
        var classrooms = <?php echo json_encode($classroom_list); ?>;

        // 2. STATE MANAGEMENT: For the current user session
        // 2. STATE MANAGEMENT: Pass PHP data into JavaScript
        // Locate this part in your script and update the status logic
        var myReservations = <?php
                                $history = [];
                                if ($my_res_result && mysqli_num_rows($my_res_result) > 0) {
                                    mysqli_data_seek($my_res_result, 0);
                                    while ($row = mysqli_fetch_assoc($my_res_result)) {
                                        $history[] = [
                                            'classroom' => $row['room_name'],
                                            'refNo'     => $row['id'],
                                            'purpose'   => $row['purpose'],
                                            'dateTime'  => date("M d", strtotime($row['reservation_date'])) . " (" .
                                                date("h:i A", strtotime($row['start_time'])) . " - " .
                                                date("h:i A", strtotime($row['end_time'])) . ")",
                                            'status'    => $row['status'] // This will now fetch 'Cancelled' if admin deleted it
                                        ];
                                    }
                                }
                                echo json_encode($history);
                                ?>;

        // This replaces the old empty 'occupiedSlots' line you had
        var occupiedSlots = <?php echo json_encode($db_occupied); ?>; // Tracks bookings locally to prevent double-booking

        var tableBody = document.getElementById('roomTableBody');
        var statusTableBody = document.getElementById('statusTableBody');

        /**
         * Renders the main room selection table
         */
        function renderTable(dataToDisplay) {
            var list = dataToDisplay || classrooms;
            var htmlContent = "";
            var selectedDate = document.getElementById('searchDate').value;

            list.forEach(c => {
                var dateKey = selectedDate + "_" + c.name;

                // Check availability status
                let statusBadge = (occupiedSlots[dateKey] && occupiedSlots[dateKey].length > 0) ?
                    `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 small">Limited Slot</span>` :
                    `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Available</span>`;

                htmlContent += `<tr>
                <td class='d-none d-md-table-cell'><img src='${c.img}' class='classroom-img' alt='Room Preview'></td>
                <td>
                    <div class='fw-bold small'>${c.name}</div>
                    <div style='font-size: 0.7rem' class='text-muted'>
                        <i class='fas fa-map-marker-alt me-1'></i>${c.loc}
                    </div>
                </td>
                <td class='d-none d-sm-table-cell'><span class='badge bg-light text-dark border'>${c.cat}</span></td>
                <td class='d-none d-lg-table-cell'>${c.cap}</td>
                <td>${statusBadge}</td>
                <td class='text-center'>
                    <button class='btn btn-sm btn-schedule px-2 px-md-3 rounded-pill' 
                            onclick="prepareModal('${c.name}')" 
                            data-bs-toggle='modal' 
                            data-bs-target='#confirmModal'>
                        Schedule
                    </button>
                </td>
            </tr>`;
            });
            tableBody.innerHTML = htmlContent;
        }

        /**
         * Populates the modal with room-specific details and existing bookings
         */
        function prepareModal(roomName) {
            document.getElementById('selectedClassroom').innerText = roomName;
            var dateVal = document.getElementById('searchDate').value;
            document.getElementById('modalDateDisplay').innerText = "Date: " + (dateVal || "Not selected");

            var container = document.getElementById('occupiedListContainer');
            container.innerHTML = "";

            var dateKey = dateVal + "_" + roomName;
            var existing = occupiedSlots[dateKey] || [];

            if (existing.length === 0) {
                container.innerHTML = "<div class='small text-muted italic'>No current bookings for this date.</div>";
            } else {
                existing.forEach(slot => {
                    container.innerHTML += `<div class="occupied-slot-item"><i class="fas fa-clock me-2"></i>${slot.start} to ${slot.end}</div>`;
                });
            }

            // Reset inputs
            document.getElementById('startTime').value = "";
            document.getElementById('endTime').value = "";
        }

        /**
         * Submits the reservation and triggers the FCFS Queue
         */
        function submitReservation() {
            var classroom = document.getElementById('selectedClassroom').innerText;
            var date = document.getElementById('searchDate').value;
            var start = document.getElementById('startTime').value;
            var end = document.getElementById('endTime').value;
            var purpose = document.getElementById('reservationPurpose').value;

            if (!start || !end) {
                alert("Please select a time.");
                return;
            }

            let formData = new FormData();
            formData.append('room', classroom);
            formData.append('date', date);
            formData.append('start', start);
            formData.append('end', end);
            formData.append('purpose', purpose);

            // IMPORTANT: Ensure save_reservation.php is in the same folder as index.php
            fetch('save_reservation.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text()) // Changed from .json() temporarily to see errors
                .then(data => {
                    const jsonData = JSON.parse(data);
                    if (jsonData.status === 'success') {
                        // Tell them if they got the room or the queue
                        let msg = (jsonData.reservation_status === 'Accepted') ?
                            "Confirmed! Your reservation is live." :
                            "Room occupied.";
                        alert(msg);
                        location.reload();
                    }
                })
                .catch(error => console.error('Fetch Error:', error));
        }
        /**
         * Simulates the system auto-verifying and accepting the request
         */
        function autoProcessQueue() {
            // Find the first pending item in the list
            let nextInLine = myReservations.find(res => res.status === 'Pending');

            if (nextInLine) {
                // After 3 seconds, mark as Accepted
                setTimeout(() => {
                    nextInLine.status = 'Accepted';
                    renderStatusTable();
                    console.log("System Auto-Accepted: " + nextInLine.refNo);
                }, 3000);
            }
        }

        // Inside your function that loops through the reservations
        statusTableBody.innerHTML = myReservations.map((res, index) => {
            let statusHtml = "";
            let rowClass = "";

            // Normalize status for comparison
            let currentStatus = res.status ? res.status.trim().toLowerCase() : "pending";

            if (currentStatus === 'accepted') {
                statusHtml = `<span class="badge bg-success">Confirmed</span>`;
                rowClass = "table-success";
            } else if (currentStatus === 'pending') {
                // This is the FCFS "Waiting Room"
                statusHtml = `<span class="badge bg-info text-dark">In Queue</span>`;
                rowClass = "table-warning";
            } else if (currentStatus === 'cancelled') {
                statusHtml = `<span class="badge bg-danger">Cancelled</span>`;
                rowClass = "table-danger opacity-75";
            }

            return `
        <tr class="align-middle ${rowClass}">
            <td>${res.room_name}</td>
            <td>${res.purpose}</td>
            <td>${res.start_time} - ${res.end_time}</td>
            <td class="text-center">${statusHtml}</td>
        </tr>
    `;
        }).join('');

/**
 * Renders the Status Table for the student with Print Receipt button
 */
function renderStatusTable() {
    if (myReservations.length === 0) {
        statusTableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted small italic">No active requests found.</td></tr>';
        return;
    }

    statusTableBody.innerHTML = myReservations.map((res, index) => {
        let statusHtml = "";
        let rowClass = "";
        let actionHtml = ""; 

        // Normalize status
        let currentStatus = res.status ? res.status.trim().toLowerCase() : "pending";

        if (currentStatus === 'accepted') {
            statusHtml = `<span class="badge bg-success shadow-sm"><i class="fas fa-check-circle me-1"></i>Confirmed</span>`;
            rowClass = "table-success";
            
            // The Button Design placed inside actionHtml
            actionHtml = `<div class="mt-2">
                            <a href="receipt.php?id=${res.refNo}" target="_blank" class="btn btn-sm btn-dark py-1 px-3 shadow-sm border-0" style="font-size: 0.75rem;">
                                <i class="fas fa-file-invoice me-1"></i>Get Receipt
                            </a>
                          </div>`;
        } else if (currentStatus === 'cancelled') {
            statusHtml = `<span class="badge bg-danger">Cancelled</span>`;
            rowClass = "table-danger opacity-75";
        } else if (index === 0) {
            statusHtml = `<span class="badge bg-primary animate-pulse">Auto-Verifying...</span>`;
            rowClass = "table-warning";
        } else {
            statusHtml = `<span class="badge bg-info text-dark">In Queue (#${index + 1})</span>`;
            rowClass = "table-warning";
        }

        return `
            <tr class="align-middle ${rowClass}">
                <td class="ps-3 ps-md-4">
                    <div class="fw-bold text-dark">${res.classroom}</div>
                    <div class="text-muted" style="font-size: 0.7rem;">Ref: #CMS-${res.refNo}</div>
                    <div class="d-block d-md-none">${actionHtml}</div>
                </td>
                <td class="d-none d-md-table-cell">
                    <div class="small text-secondary">${res.purpose}</div>
                    <div class="d-none d-md-block">${actionHtml}</div>
                </td>
                <td class="small">${res.dateTime}</td>
                <td class="text-center">${statusHtml}</td>
            </tr>
        `;
    }).join('');
}

        /**
         * Handles search and category filtering
         */
        function handleFilter() {
            var search = document.getElementById('filterSearch').value.toLowerCase();
            var type = document.getElementById('filterType').value;
            var filtered = classrooms.filter(c =>
                c.name.toLowerCase().includes(search) && (type === "All Types" || c.cat === type)
            );
            renderTable(filtered);
        }

        // Initial Table Loads
        renderTable();
        renderStatusTable(); // Call the function we just fixed
    </script>

    <script>
        // Detect if the page was loaded via the back/forward button
        window.addEventListener("pageshow", function(event) {
            var historyTraversal = event.persisted ||
                (typeof window.performance != "undefined" &&
                    window.performance.navigation.type === 2);
            if (historyTraversal) {
                // Force a reload from the server, which will trigger your PHP session check
                window.location.reload();
            }
        });
    </script>
</body>

</html>