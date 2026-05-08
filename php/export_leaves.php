<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin();

$stmt = $mysqli->prepare("SELECT users.first_name, users.middle_name, users.last_name, users.email, absences.leave_date, absences.leave_type, absences.reason, absences.status, absences.created_at FROM absences JOIN users ON absences.user_id = users.id ORDER BY absences.created_at DESC");
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leave_records.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['Employee', 'Gmail', 'Date', 'Type', 'Reason', 'Status', 'Submitted']);

while ($row = $records->fetch_assoc()) {
    $employee = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
    fputcsv($output, [
        $employee,
        $row['email'],
        $row['leave_date'],
        $row['leave_type'],
        $row['reason'],
        $row['status'],
        $row['created_at']
    ]);
}

fclose($output);
exit;
