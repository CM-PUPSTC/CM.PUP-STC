<?php
include('../connect.php');

$id     = $_POST['id'] ?? '';
$status = $_POST['action'] ?? '';
$reason = $_POST['reason'] ?? null;

if (!$id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID or Status']);
    exit;
}

// Update status and reason
$sql = "UPDATE reservations SET status = ?, rejection_reason = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssi", $status, $reason, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
}
?>