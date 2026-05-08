<?php
session_start();
// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../login.php");
    exit();
}
/** @var mysqli $conn */
include('../connect.php');
$prof_name = $_SESSION['name'];
// Change this line in your Dashboard PHP
$account_no = $_SESSION['account_number'] ?? 'N/A';

// Fetch the Professor's Schedule
$query = "SELECT * FROM class_schedules 
          WHERE professor_name = ? 
          ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
          start_time ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $prof_name);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard | PUPSTC CMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pup-maroon: #800000;
            --pup-gold: #FFD700;
        }

        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', sans-serif;
        }

        .navbar-pup {
            background-color: var(--pup-maroon);
            color: white;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #e0e0e0;
        }

        .user-pill {
            transition: 0.3s;
            cursor: pointer;
        }

        .user-pill:hover {
            background-color: rgba(255, 255, 255, 0.2) !important;
        }

        .badge-room {
            background-color: rgba(128, 0, 0, 0.1);
            color: var(--pup-maroon);
            border: 1px solid rgba(128, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-pup py-3 mb-4 sticky-top">
        <div class="container-fluid px-md-4">
            <a class="navbar-brand text-white fw-bold d-flex align-items-center" href="#">
                <img src="../img/PUPLogo.png" alt="Logo" width="30" height="30" class="me-2">
                <span>PUPSTC CMS</span>
            </a>
            <div class="dropdown">
                <div class="d-flex align-items-center bg-white bg-opacity-10 rounded-pill px-3 py-1 dropdown-toggle user-pill" data-bs-toggle="dropdown">
                    <span class="small me-2 text-white d-none d-md-inline"><?php echo htmlspecialchars($prof_name); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($prof_name); ?>&background=FFD700&color=800000" class="rounded-circle" width="28">
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li class="px-3 py-2 text-center">
                        <div class="fw-bold"><?php echo htmlspecialchars($prof_name); ?></div>
                        <div class="text-muted small">ID: <?php echo htmlspecialchars($account_no); ?></div>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item text-danger small" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-container shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0 text-dark"><i class="fas fa-chalkboard-teacher me-2 text-maroon"></i>My Academic Load</h5>
                <span class="badge bg-light text-dark border px-3 py-2"><?php echo date('l, F d'); ?></span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light">
                        <tr class="small text-uppercase text-muted">
                            <th class="ps-3">Day</th>
                            <th>Subject & Section</th>
                            <th>Schedule</th>
                            <th>Room</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-maroon"><?php echo $row['day_of_week']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $row['subject_code']; ?></div>
                                        <div class="text-muted small"><?php echo $row['section_name']; ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark">
                                            <?php echo date("h:i A", strtotime($row['start_time'])); ?> -
                                            <?php echo date("h:i A", strtotime($row['end_time'])); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge rounded-pill badge-room px-3"><?php echo $row['room_name']; ?></span></td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold"
                                            onclick="openCancelModal(<?php echo $row['id']; ?>, '<?php echo $row['subject_code']; ?>', '<?php echo $row['day_of_week']; ?>')">
                                            <i class="fas fa-times-circle me-1"></i> Cancel Session
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">No assigned classes found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form action="process_cancel_class.php" method="POST">
                    <div class="modal-header border-0 bg-danger text-white">
                        <h5 class="modal-title fw-bold">Cancel Class Session</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                            <p class="text-muted px-3">You are about to cancel the session for:</p>
                            <h4 class="fw-bold" id="modalSubject">Subject Code</h4>
                            <p class="badge bg-light text-dark border" id="modalDayDisplay"></p>
                        </div>

                        <input type="hidden" name="schedule_id" id="modalSchedId">

                        <div class="mb-3 px-3">
                            <label class="form-label small fw-bold">Effective Date</label>
                            <input type="date" name="cancelled_date" class="form-control form-control-lg"
                                value="<?php echo date('Y-m-d'); ?>" required>
                            <div class="form-text text-danger mt-2" style="font-size: 0.75rem;">
                                <i class="fas fa-info-circle me-1"></i> Note: This room will become available for reservations once cancelled.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Keep Session</button>
                        <button type="submit" name="confirm_cancel" class="btn btn-danger rounded-pill px-4 shadow">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openCancelModal(id, subject, day) {
            document.getElementById('modalSchedId').value = id;
            document.getElementById('modalSubject').innerText = subject;
            document.getElementById('modalDayDisplay').innerText = "Every " + day;

            var myModal = new bootstrap.Modal(document.getElementById('cancelModal'));
            myModal.show();
        }
    </script>
</body>

</html>