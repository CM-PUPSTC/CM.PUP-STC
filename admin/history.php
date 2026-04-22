<?php
session_start();
// Security: Only Admins can see the history
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include('../connect.php');

// We JOIN the tables from your ERD to get names instead of just IDs
// Use LEFT JOIN so the row shows up even if IDs are missing
// Check if your reservations table uses 'account_number' instead of 'user_id'
$query = "SELECT r.*, u.account_number, u.section_name, c.room_name 
          FROM reservations r
          LEFT JOIN users u ON r.account_number = u.account_number
          LEFT JOIN classrooms c ON r.room_name = c.room_name
          ORDER BY r.reservation_date DESC, r.start_time DESC";

$result = mysqli_query($conn, $query);

// This will tell us if the database is actually having an error
if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        }

        .text-maroon {
            color: var(--pup-maroon);
        }

        .history-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        .table thead {
            background-color: #f1f3f5;
        }

        .badge-accepted {
            background-color: #28a745;
        }

        .badge-cancelled {
            background-color: #dc3545;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
    </style>
</head>

<body>

    <nav class="top-navbar d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <img src="../img/PUPLogo.png" alt="Logo" width="35" height="35" class="me-2">
            <div>
                <h4 class="fw-bold m-0">PUPSTC <span style="color: var(--pup-gold);">CMS</span></h4>
                <small class="opacity-75">Reservation History Log</small>
            </div>
        </div>
        <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill">
            <i class="fas fa-calendar-alt me-1"></i> Back to Calendar
        </a>
    </nav>

    <div class="container-fluid px-4">
        <div class="history-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-maroon m-0"><i class="fas fa-history me-2"></i>Audit Trail</h5>
                <button class="btn btn-sm btn-dark" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print Report
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Room Name</th>
                            <th>Student / Section</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="fw-bold"><?php echo date('M d, Y', strtotime($row['reservation_date'])); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo $row['room_name']; ?></span></td>
                                <td>
                                    <div class="small fw-bold"><?php echo $row['account_number']; ?></div>
                                    <div class="text-muted small"><?php echo $row['section_name']; ?></div>
                                </td>
                                <td>
                                    <small>
                                        <?php echo date('h:i A', strtotime($row['start_time'])); ?> -
                                        <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $s = $row['status'];
                                    $class = ($s == 'Accepted') ? 'badge-accepted' : (($s == 'Pending') ? 'badge-pending' : 'badge-cancelled');
                                    ?>
                                    <span class="badge <?php echo $class; ?> rounded-pill px-3"><?php echo $s; ?></span>
                                </td>
                                <td class="small italic text-muted">
                                    <?php
                                    if (!empty($row['cancel_reason'])) {
                                        echo $row['cancel_reason'];
                                    } elseif ($s == 'Accepted') {
                                        echo 'System Confirmed';
                                    } else {
                                        echo '---';
                                    }
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