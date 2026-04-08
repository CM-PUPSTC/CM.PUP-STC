<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PUPSTC FMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

    <style>
        :root {
            --pup-maroon: #800000;
            --classroom-color: #800000;
            --lab-color: #008080;
            --equip-color: #f39c12;
        }

        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        
        /* TOP MAROON HEADER */
        .top-navbar {
            background-color: var(--pup-maroon);
            color: white;
            padding: 15px 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .legend-card { border-radius: 15px; border: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        .dot { height: 12px; width: 12px; border-radius: 50%; display: inline-block; margin-right: 10px; }
        .calendar-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); }

        /* FullCalendar Customization */
        .fc-daygrid-event { border: none !important; padding: 2px 4px !important; }
        .fc-button-primary {
            background-color: #fff !important;
            border-color: #dee2e6 !important;
            color: #444 !important;
            font-weight: 600 !important;
        }
        .fc-button-active {
            background-color: var(--pup-maroon) !important;
            border-color: var(--pup-maroon) !important;
            color: #fff !important;
        }
        .fc-toolbar-title { font-weight: bold; color: var(--pup-maroon); }
    </style>
</head>

<body>

    <nav class="top-navbar d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold m-0 text-uppercase">PUPSTC Master Schedule</h4>
            <small class="opacity-75">Classroom Management System | Administrator: Engr. Liza</small>
        </div>
        <div>
            <button class="btn btn-light rounded-pill px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#pendingModal">
                <i class="fas fa-bell me-2 text-danger"></i>Requests
                <span id="requestCount" class="badge bg-danger ms-1">2</span>
            </button>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-md-2">
                <div class="card legend-card p-3 mb-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Legend</h6>
                    <div class="mb-2"><span class="dot" style="background: var(--classroom-color);"></span> Classrooms</div>
                    <div class="mb-2"><span class="dot" style="background: var(--lab-color);"></span> Laboratory</div>
                    <div class="mb-2"><span class="dot" style="background: var(--equip-color);"></span> Equipment</div>
                </div>
            </div>

            <div class="col-md-10">
                <div class="calendar-card">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pendingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Pending Approvals</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Reservation</th>
                                <th>Time Slot</th>
                                <th>Category</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="pendingList"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Use display: 'block' for solid colors in Month View
        let approvedEvents = [
            { 
                title: 'Room 302: DIT 3-1', 
                start: '2026-04-10T08:00:00', 
                end: '2026-04-10T12:00:00', 
                backgroundColor: '#800000',
                display: 'block' 
            }
        ];

        let pendingRequests = [
            { id: 1, title: 'Projector 01', date: '2026-04-15', start: '09:00', end: '11:00', type: 'Equipment', color: '#f39c12' },
            { id: 2, title: 'Lab 1: Networking', date: '2026-04-18', start: '13:00', end: '16:00', type: 'Laboratory', color: '#008080' }
        ];

        let calendar;

        function format12Hour(timeString) {
            let [hours, minutes] = timeString.split(':');
            let ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            return `${hours}:${minutes} ${ampm}`;
        }

        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listMonth'
                },
                events: approvedEvents,
                height: '75vh',
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short'
                }
            });

            calendar.render();
            renderPendingList();
        });

        function renderPendingList() {
            const list = document.getElementById('pendingList');
            const count = document.getElementById('requestCount');
            list.innerHTML = "";
            count.innerText = pendingRequests.length;

            if (pendingRequests.length === 0) {
                list.innerHTML = "<tr><td colspan='4' class='text-center py-5 text-muted'>No pending requests.</td></tr>";
                return;
            }

            pendingRequests.forEach((req, index) => {
                list.innerHTML += `
                <tr>
                    <td class="ps-4"><strong>${req.title}</strong><br><small class="text-muted">${req.date}</small></td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            ${format12Hour(req.start)} - ${format12Hour(req.end)}
                        </span>
                    </td>
                    <td><span class="badge text-dark" style="background: ${req.color}33; border: 1px solid ${req.color}">${req.type}</span></td>
                    <td class="text-center">
                        <button onclick="handleRequest(${index}, 'accept')" class="btn btn-sm btn-success rounded-pill px-3 me-2">Accept</button>
                        <button onclick="handleRequest(${index}, 'decline')" class="btn btn-sm btn-outline-danger rounded-pill px-3">Decline</button>
                    </td>
                </tr>`;
            });
        }

        function handleRequest(index, action) {
            const req = pendingRequests[index];

            if (action === 'accept') {
                calendar.addEvent({
                    title: req.title,
                    start: `${req.date}T${req.start}:00`,
                    end: `${req.date}T${req.end}:00`,
                    backgroundColor: req.color,
                    display: 'block'
                });
                alert("Reservation Approved!");
            } else {
                alert("Reservation Declined.");
            }

            pendingRequests.splice(index, 1);
            renderPendingList();
        }
    </script>
</body>
</html>