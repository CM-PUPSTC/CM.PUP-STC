<?php
session_start();
include('../connect.php'); // Ensure your DB connection path is correct

// 1. Get URL Parameters
$ref = isset($_GET['ref']) ? $_GET['ref'] : 'N/A';
$room = isset($_GET['room']) ? $_GET['room'] : 'N/A';
$date_issued = date('F j, Y, g:i a');

// 2. Fetch User Data from Database
// Assuming you store the logged-in user's ID in $_SESSION['user_id']
$user_id = $_SESSION['user_id'] ?? 1; // Fallback to 1 for testing

$user_query = "SELECT full_name, student_number FROM users WHERE id = '$user_id' LIMIT 1";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);

$full_name = $user_data['full_name'] ?? "Guest User";
$student_no = $user_data['student_number'] ?? "N/A";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Receipt - <?php echo $ref; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .receipt-card {
            max-width: 650px;
            margin: 50px auto;
            background: white;
            border-top: 8px solid #800000;
            /* PUP Maroon */
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            border-radius: 8px;
        }

        .header-logo {
            width: 80px;
            margin-bottom: 15px;
        }

        .school-name {
            color: #800000;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .status-stamp {
            border: 3px solid #198754;
            color: #198754;
            display: inline-block;
            padding: 5px 15px;
            font-weight: bold;
            text-transform: uppercase;
            transform: rotate(-5deg);
            border-radius: 5px;
            opacity: 0.8;
        }

        .info-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 700;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
        }

        /* Print rules */
        @media print {
            body {
                background: white;
            }

            .receipt-card {
                box-shadow: none;
                margin: 0;
                width: 100%;
                max-width: 100%;
                border: none;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="receipt-card">
            <div class="text-center mb-5">
                <img src="../img/PUPLogo.png" alt="PUP Logo" class="header-logo">
                <h4 class="school-name mb-0">POLYTECHNIC UNIVERSITY OF THE PHILIPPINES</h4>
                <p class="text-muted small">SANTO TOMAS BRANCH<br>Classroom Management System (CMS)</p>
                <div class="status-stamp mt-2">Verified & Reserved</div>
            </div>

            <hr>

            <div class="row mt-4">
                <div class="col-6">
                    <div class="info-label">Reference Number</div>
                    <div class="info-value text-primary"><?php echo htmlspecialchars($ref); ?></div>
                </div>
                <div class="col-6 text-end">
                    <div class="info-label">Date Issued</div>
                    <div class="info-value" style="font-size: 0.9rem;"><?php echo $date_issued; ?></div>
                </div>
            </div>

            <div class="mt-5">
                <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Reservation Details</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td class="bg-light w-40 fw-bold small">Reserved Classroom</td>
                                <td class="info-value"><?php echo htmlspecialchars($room); ?></td>
                            </tr>
                            <tr>
                                <td class="bg-light fw-bold small">Reserved For</td>
                                <td class="info-value">
                                    <?php echo htmlspecialchars($full_name); ?>
                                    <span class="text-muted fw-normal" style="font-size: 0.85rem;">
                                        (<?php echo htmlspecialchars($student_no); ?>)
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light fw-bold small">Institution</td>
                                <td>PUP Sto. Tomas Campus</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5 p-3 bg-light rounded border">
                <h6 class="small fw-bold text-dark"><i class="fas fa-exclamation-triangle me-2"></i>Important Reminders:</h6>
                <ul class="mb-0 text-muted" style="font-size: 0.85rem;">
                    <li>Please present this digital or printed receipt to <strong>Engr. Liza</strong> or the Classroom in-charge upon arrival.</li>
                    <li>The reservation is valid only for the approved time slot.</li>
                    <li>Ensure the Classroom is kept clean and equipment is handled with care.</li>
                </ul>
            </div>

            <div class="text-center mt-5">
                <p class="text-muted" style="font-size: 0.7rem;">This is a system-generated document from the PUPSTC-FMS.<br>No physical signature is required for initial verification.</p>
            </div>

            <div class="text-center mt-4 no-print">
                <button onclick="window.print()" class="btn btn-dark px-4 py-2 shadow-sm">
                    <i class="fas fa-print me-2"></i>Print Receipt / Save as PDF
                </button>
                <br>
                <a href="javascript:window.close();" class="btn btn-link text-muted mt-2 small text-decoration-none">Close Window</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>