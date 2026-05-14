<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_login();

$is_admin = is_admin();
$owner_user_id = $_SESSION["user_id"] ?? null;

$view_archived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

$lt_result = $mysqli->query("SELECT name FROM leave_types ORDER BY name ASC");
$leave_types = [];
while ($lt = $lt_result->fetch_assoc()) { $leave_types[] = $lt['name']; }

$employee_query = "SELECT DISTINCT employee_name FROM leave_data WHERE is_archived = ? AND YEAR(leave_date) = ?";
$employee_types = "ii";
$employee_params = [$view_archived, $selected_year];
if (!$is_admin) {
    $employee_query .= " AND owner_user_id = ?";
    $employee_types .= "i";
    $employee_params[] = $owner_user_id;
}
if ($selected_month > 0 && $selected_month <= 12) {
    $employee_query .= " AND MONTH(leave_date) = ?";
    $employee_types .= "i";
    $employee_params[] = $selected_month;
}
$employee_query .= " ORDER BY employee_name ASC";

$employee_stmt = $mysqli->prepare($employee_query);
$employee_stmt->bind_param($employee_types, ...$employee_params);
$employee_stmt->execute();
$employee_result = $employee_stmt->get_result();
$employees = [];
while ($row = $employee_result->fetch_assoc()) { $employees[] = $row['employee_name']; }
$employee_stmt->close();

$leave_query = "SELECT employee_name, leave_date, leave_type FROM leave_data WHERE is_archived = ? AND YEAR(leave_date) = ?";
$leave_types_sig = "ii";
$leave_params = [$view_archived, $selected_year];
if (!$is_admin) {
    $leave_query .= " AND owner_user_id = ?";
    $leave_types_sig .= "i";
    $leave_params[] = $owner_user_id;
}
if ($selected_month > 0 && $selected_month <= 12) {
    $leave_query .= " AND MONTH(leave_date) = ?";
    $leave_types_sig .= "i";
    $leave_params[] = $selected_month;
}
$leave_query .= " ORDER BY leave_date ASC";

$stmt = $mysqli->prepare($leave_query);
$stmt->bind_param($leave_types_sig, ...$leave_params);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

$data = [];
while ($row = $records->fetch_assoc()) {
    $type = $row['leave_type'];
    $employee = $row['employee_name'];
    $date = DateTime::createFromFormat('Y-m-d', $row['leave_date']);
    if (!$date || !$employee) { continue; }
    $month = (int)$date->format('n');
    $day = (int)$date->format('j');
    if (!isset($data[$type])) { $data[$type] = []; }
    if (!isset($data[$type][$employee])) { $data[$type][$employee] = []; }
    if (!isset($data[$type][$employee][$month])) { $data[$type][$employee][$month] = []; }
    $data[$type][$employee][$month][] = $day;
}

header('Content-Type: text/csv; charset=utf-8');
$filename_suffix = $selected_year;
if ($selected_month > 0 && $selected_month <= 12) {
    $filename_suffix .= "-" . str_pad((string)$selected_month, 2, "0", STR_PAD_LEFT);
}
header('Content-Disposition: attachment; filename=leave_monitoring_' . $filename_suffix . '.csv');

$output = fopen('php://output', 'w');

$months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

$month_label = ($selected_month > 0 && $selected_month <= 12) ? $months[$selected_month - 1] . " " : "";
$title_row = ["Leave Monitoring - " . $month_label . $selected_year];
fputcsv($output, $title_row);
fputcsv($output, []);

foreach ($leave_types as $type) {
    $type_row = array_fill(0, 13, "");
    $type_row[0] = $type;
    fputcsv($output, $type_row);
    fputcsv($output, array_merge(['Employee'], $months));

    foreach ($employees as $name) {
        $row = [$name];
        for ($m = 1; $m <= 12; $m++) {
            $days = $data[$type][$name][$m] ?? [];
            $days = array_unique($days);
            sort($days, SORT_NUMERIC);
            $row[] = $days ? implode(", ", $days) : "";
        }
        fputcsv($output, $row);
    }

    fputcsv($output, []);
}

fclose($output);
exit;
