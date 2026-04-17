<?php
session_start();
// Security Check: If not logged in as admin, redirect to login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include('../connect.php');

// Fetch Accepted Reservations
$query = "SELECT id, room_name, reservation_date, start_time, end_time FROM reservations WHERE status = 'Accepted'";
$result = mysqli_query($conn, $query);

$calendar_events = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Default color
    $color = '#800000'; // PUP Maroon

    // Assign colors based on the Room Name
    $room = strtolower($row['room_name']);

    if (strpos($room, 'laboratory') !== false || strpos($room, 'lab') !== false) {
        $color = '#28a745'; // Green for Labs
    } elseif (strpos($room, 'gym') !== false) {
        $color = '#fd7e14'; // Orange for Gym
    } elseif (strpos($room, 'multimedia') !== false) {
        $color = '#007bff'; // Blue for Multimedia
    }

    $calendar_events[] = [
        'id'    => $row['id'],
        'title' => $row['room_name'],
        'start' => $row['reservation_date'] . 'T' . $row['start_time'],
        'end'   => $row['reservation_date'] . 'T' . $row['end_time'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'display' => 'block'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPSTC CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

    <style>
        :root {
            --pup-maroon: #800000;
            --pup-gold: #FFD700;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        .top-navbar {
            background: var(--pup-maroon);
            color: white;
            padding: 12px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .user-profile-link {
            color: white;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            transition: 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-profile-link:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--pup-gold);
        }

        .calendar-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-top: -20px;
        }

        .fc-toolbar-title {
            color: var(--pup-maroon);
            font-weight: 800 !important;
        }

        .fc-button-primary {
            background-color: var(--pup-maroon) !important;
            border: none !important;
        }

        /* --- RESPONSIVE TWEAKS --- */
        @media (max-width: 768px) {
            .top-navbar {
                padding: 10px 15px;
            }

            .calendar-container {
                margin-top: 20px;
                /* Pull down so it doesn't overlap the nav on mobile */
                padding: 15px;
            }

            .fc-toolbar-title {
                font-size: 1.2rem !important;
            }

            .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <nav class="top-navbar d-flex justify-content-between align-items-center mb-5">
        <div class="d-flex align-items-center">
            <a class="navbar-brand text-white fw-bold d-flex align-items-center me-2" href="#">
                <img src="../img/PUPLogo.png" alt="Logo" width="35" height="35" class="me-2 d-inline-block align-top">
            </a>
            <div>
                <h4 class="fw-bold m-0">PUP-STC</h4>
                <small class="opacity-75 d-none d-md-block">Classroom Management System</small>
            </div>
        </div>

        <div class="dropdown">
            <a href="#" class="user-profile-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                <i class="fas fa-user-shield me-2"></i>
                <span class="d-none d-sm-inline"><?php echo $_SESSION['admin_user'] ?? 'Administrator'; ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li>
                    <a class="dropdown-item" href="history.php">
                        <i class="fas fa-clipboard-list me-2"></i> Reservation History
                    </a>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item text-danger" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-lg-2 d-none d-lg-block">
                <div class="card border-0 shadow-sm p-3 mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-layer-group me-2"></i>Legend</h6>

                    <div class="d-flex align-items-center mb-2">
                        <div style="width:12px; height:12px; background:#28a745; border-radius:3px; margin-right:10px;"></div>
                        <span class="small">Laboratory</span>
                    </div>

                    <div class="d-flex align-items-center mb-2">
                        <div style="width:12px; height:12px; background:#fd7e14; border-radius:3px; margin-right:10px;"></div>
                        <span class="small">Gymnasium</span>
                    </div>

                    <div class="d-flex align-items-center mb-2">
                        <div style="width:12px; height:12px; background:#007bff; border-radius:3px; margin-right:10px;"></div>
                        <span class="small">Multimedia</span>
                    </div>

                    <div class="d-flex align-items-center mb-2">
                        <div style="width:12px; height:12px; background:var(--pup-maroon); border-radius:3px; margin-right:10px;"></div>
                        <span class="small">Other Rooms</span>
                    </div>

                    <hr>
                    <div class="alert alert-info py-2 px-3 small border-0">
                        <i class="fas fa-info-circle me-1"></i> Click to cancel.
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-10">
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-times me-2"></i> Confirm Cancellation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger"><i class="fas fa-exclamation-circle fa-3x"></i></div>
                    <p class="fs-5 mb-1">Remove reservation for:</p>
                    <h4 class="fw-bold text-dark mb-0" id="displayRoomName"></h4>
                    <p class="fw-bold text-muted mb-3" id="displayTimeRange" style="font-size: 1.1rem;"></p>
                    <input type="hidden" id="deleteIdHolder">
                </div>
                <div class="modal-footer justify-content-center border-0 pb-4">
                    <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Go Back</button>
                    <button type="button" class="btn btn-danger px-4 rounded-pill" onclick="executeCancellation()">Yes, Cancel It</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var bModal = new bootstrap.Modal(document.getElementById('cancelModal'));

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: (window.innerWidth < 768) ? 'listMonth' : 'dayGridMonth',

                // 1. This fixes the time shown on the events (e.g., 8:00 AM)
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short' // This ensures 'am/pm' is shown
                },

                // 2. This fixes the time labels on the side of the 'Week' and 'Day' views
                slotLabelFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short'
                },

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: (window.innerWidth < 768) ? '' : 'dayGridMonth,timeGridWeek,listMonth'
                },

                // Logic to update view if user rotates their phone
                windowResize: function(view) {
                    if (window.innerWidth < 768) {
                        calendar.changeView('listMonth');
                    } else {
                        calendar.changeView('dayGridMonth');
                    }
                },

                events: <?php echo json_encode($calendar_events); ?>,
                height: 'auto',
                eventClick: function(info) {
                    document.getElementById('displayRoomName').innerText = info.event.title;
                    const startTime = info.event.start.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    const endTime = info.event.end ? info.event.end.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    document.getElementById('displayTimeRange').innerText = `${startTime} to ${endTime}`;
                    document.getElementById('deleteIdHolder').value = info.event.id;
                    bModal.show();
                }
            });
            calendar.render();
        });

        function executeCancellation() {
            const id = document.getElementById('deleteIdHolder').value;
            fetch('cancel_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(err => console.error("Fetch error:", err));
        }
    </script>
</body>

</html>