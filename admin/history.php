<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include('../connect.php');

$query = "SELECT r.*, u.account_number, u.section_name, c.room_name 
          FROM reservations r
          LEFT JOIN users u ON r.account_number = u.account_number
          LEFT JOIN classrooms c ON r.room_name = c.room_name
          ORDER BY r.reservation_date DESC, r.start_time DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation History | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pup-maroon: #800000;
            --pup-gold: #FFD700;
        }

        body {
            background-color: #f4f7f6;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .top-navbar {
            background: var(--pup-maroon);
            color: white;
            padding: 12px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .history-card {
            background: white;
            border-radius: 12px;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .text-maroon {
            color: var(--pup-maroon);
        }

        /* Table Badges */
        .badge-accepted {
            background-color: #198754;
            color: white;
        }

        .badge-cancelled {
            background-color: #dc3545;
            color: white;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--pup-gold);
        }

        /* Responsive Table Strategy */
        @media (max-width: 768px) {
            .table-responsive-stack thead {
                display: none;
            }

            .table-responsive-stack tr {
                display: block;
                border: 1px solid #eee;
                margin-bottom: 1rem;
                padding: 10px;
                border-radius: 8px;
                background: #fff;
            }

            .table-responsive-stack td {
                display: flex;
                justify-content: space-between;
                text-align: right;
                border-bottom: 1px solid #f8f9fa;
                padding: 8px 5px;
            }

            .table-responsive-stack td::before {
                content: attr(data-label);
                font-weight: bold;
                text-align: left;
                color: #666;
            }
        }

        /* Print Specific Styles */
        @media print {

            .top-navbar,
            .btn,
            .no-print {
                display: none !important;
            }

            .history-card {
                box-shadow: none;
                padding: 0;
            }

            body {
                background: white;
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
                <small class="d-none d-sm-block opacity-75">Reservation History Log</small>
            </div>
        </div>
        <a href="index.php" class="user-profile btn btn-sm btn-outline-light rounded-pill px-3">
            <i class="fas fa-calendar-alt me-1"></i> <span class="d-none d-sm-inline">Calendar</span>
        </a>
    </nav>

    <div class="container-fluid py-4">
        <div class="history-card p-3 p-md-4">
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h5 class="fw-bold text-maroon m-0">
                        <i class="fas fa-clipboard-list me-2"></i>Reservation Logs
                    </h5>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-dark px-3" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle table-responsive-stack">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Room</th>
                            <th>User Details</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $s = $row['status'];
                            $badgeClass = ($s == 'Accepted') ? 'badge-accepted' : (($s == 'Pending') ? 'badge-pending' : 'badge-cancelled');
                        ?>
                            <tr>
                                <td data-label="Date" class="fw-bold">
                                    <?php echo date('M d, Y', strtotime($row['reservation_date'])); ?>
                                </td>
                                <td data-label="Room">
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['room_name']); ?></span>
                                </td>
                                <td data-label="User">
                                    <div class="fw-bold small"><?php echo htmlspecialchars($row['account_number']); ?></div>
                                    <div class="text-muted extra-small" style="font-size: 0.75rem;"><?php echo htmlspecialchars($row['section_name']); ?></div>
                                </td>
                                <td data-label="Schedule">
                                    <span class="small">
                                        <?php echo date('h:i A', strtotime($row['start_time'])); ?> - <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                    </span>
                                </td>
                                <td data-label="Status">
                                    <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3"><?php echo $s; ?></span>
                                </td>
                                <td data-label="Remarks" class="small text-muted italic">
                                    <?php
                                    if (!empty($row['cancel_reason'])) echo htmlspecialchars($row['cancel_reason']);
                                    elseif ($s == 'Accepted') echo 'System Confirmed';
                                    else echo '---';
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>