<?php
// 1. START THE SESSION ENGINE FIRST
session_start();


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Your existing security gate
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. THE SECURITY GATE (Block the Bypass)
// This must happen before ANY other code runs.
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

// 3. DATABASE CONNECTION
include('../connect.php');

// 4. GET THE REAL LOGGED-IN USER ID
// Use the session variable, not a hardcoded "1"
$user_id = $_SESSION['student_id'];

// 5. FETCH USER DATA
// (Use mysqli_real_escape_string for security)
$clean_id = mysqli_real_escape_string($conn, $user_id);
$user_query = "SELECT * FROM users WHERE id = '$clean_id' LIMIT 1";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);

$full_name = $user_data['full_name'] ?? "Guest User";
$student_no = $user_data['student_number'] ?? "0000-00000-ST-0";

// 6. FETCH CLASSROOMS
$query = "SELECT * FROM classrooms ORDER BY room_name ASC";
$result = mysqli_query($conn, $query);
$classroom_list = [];

while ($row = mysqli_fetch_assoc($result)) {
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
        <div class="container-fluid px-3 px-md-4">
            <a class="navbar-brand text-white fw-bold d-flex align-items-center" href="#">
                <img src="../img/PUPLogo.png" alt="Logo" width="35" height="35" class="me-2 d-inline-block align-top">

                <span class="ms-2">PUP-STC Classroom Reservation</span>

            </a>
            <div class="dropdown">
                <div class="d-flex align-items-center bg-white bg-opacity-10 rounded-pill px-2 px-md-3 py-1 dropdown-toggle"
                    role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">

                    <span class="small me-2 d-none d-md-inline text-white">
                        <?php echo $full_name; ?>
                    </span>

                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=FFD700&color=800000"
                        class="rounded-circle" width="25" alt="Profile">
                </div>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="profileDropdown">
                    <li class="px-3 py-2">
                        <div class="fw-bold small"><?php echo $full_name; ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo $student_no; ?></div>
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
                        <input type="date" class="form-control" id="searchDate" value="2026-04-03" onchange="renderTable()">
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
        var myReservations = [];
        var occupiedSlots = {}; // Tracks bookings locally to prevent double-booking

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
                alert("Please select a time range.");
                return;
            }
            if (start >= end) {
                alert("End time must be after Start time.");
                return;
            }

            var dateKey = date + "_" + classroom;
            var existingBookings = occupiedSlots[dateKey] || [];

            // Conflict Check Logic
            var isConflict = existingBookings.some(booked => (start < booked.end && end > booked.start));

            if (isConflict) {
                alert("This time slot is already occupied. Please choose another time.");
                return;
            }

            // Add to Queue with 'Pending' status
            myReservations.push({
                classroom: classroom,
                dateTime: date + " | " + start + " - " + end,
                purpose: purpose,
                queueTime: new Date().getTime(),
                submittedAt: new Date().toLocaleTimeString(),
                status: 'Pending',
                refNo: "REF-" + Math.floor(100000 + Math.random() * 900000)
            });

            // Update local occupied slots
            if (!occupiedSlots[dateKey]) occupiedSlots[dateKey] = [];
            occupiedSlots[dateKey].push({
                start: start,
                end: end
            });

            // Sort by First-Come First-Served
            myReservations.sort((a, b) => a.queueTime - b.queueTime);

            renderTable();
            renderStatusTable();
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();

            // Trigger the Auto-Accept process
            autoProcessQueue();
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

        /**
         * Renders the Status Table with "Processing" and "Receipt" functionality
         */
        function renderStatusTable() {
            if (myReservations.length === 0) return;

            statusTableBody.innerHTML = myReservations.map((res, index) => {
                let statusHtml = "";
                let actionHtml = "";

                if (res.status === 'Accepted') {
                    statusHtml = `<span class="badge bg-success shadow-sm"><i class="fas fa-check-circle me-1"></i>Confirmed</span>`;
                    // Link to a receipt page (you can create receipt.php to handle this)
                    actionHtml = `<a href="receipt.php?ref=${res.refNo}&room=${res.classroom}" target="_blank" class="btn btn-sm btn-dark mt-2 py-1 px-3">
                                <i class="fas fa-file-invoice me-1"></i>Get Receipt
                              </a>`;
                } else if (index === 0) {
                    // Top of the queue but not yet accepted
                    statusHtml = `<span class="badge bg-primary animate-pulse">Auto-Verifying...</span>`;
                } else {
                    // Lower in the queue
                    statusHtml = `<span class="badge bg-secondary">In Queue (#${index + 1})</span>`;
                }

                return `
                    <tr class="small align-middle ${res.status === 'Accepted' ? 'table-success' : ''}">
                        <td class="ps-3 ps-md-4">
                            <div class="fw-bold">${res.classroom}</div>
                            <div class="text-muted" style="font-size: 0.7rem;">ID: ${res.refNo}</div>
                            <div class="d-block d-md-none">${actionHtml}</div> 
                        </td>
                        <td class="d-none d-md-table-cell">
                            ${res.purpose}<br>
                            <div class="d-none d-md-block">${actionHtml}</div>
                        </td>
                        <td>${res.dateTime}</td>
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

        // Initial Table Load
        renderTable();
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