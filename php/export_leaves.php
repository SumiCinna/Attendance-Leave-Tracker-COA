<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin();

$view_archived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;

$lt_result = $mysqli->query("SELECT name FROM leave_types ORDER BY name ASC");
$leave_types = [];
while ($lt = $lt_result->fetch_assoc()) { $leave_types[] = $lt['name']; }

$users_result = $mysqli->query("SELECT id, first_name, middle_name, last_name FROM users WHERE role = 'employee' ORDER BY first_name ASC");
$users = [];
while ($u = $users_result->fetch_assoc()) { $users[] = $u; }

$stmt = $mysqli->prepare("SELECT user_id, leave_date, leave_type FROM absences WHERE is_archived = ? ORDER BY leave_date ASC");
$stmt->bind_param("i", $view_archived);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

$data = [];
while ($row = $records->fetch_assoc()) {
    $type = $row['leave_type'];
    $uid = (int)$row['user_id'];
    $date = DateTime::createFromFormat('Y-m-d', $row['leave_date']);
    if (!$date) { continue; }
    $month = (int)$date->format('n');
    $day = (int)$date->format('j');
    if (!isset($data[$type])) { $data[$type] = []; }
    if (!isset($data[$type][$uid])) { $data[$type][$uid] = []; }
    if (!isset($data[$type][$uid][$month])) { $data[$type][$uid][$month] = []; }
    $data[$type][$uid][$month][] = $day;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leave_monitoring.csv');

$output = fopen('php://output', 'w');

$months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

foreach ($leave_types as $type) {
    $type_row = array_fill(0, 13, "");
    $type_row[0] = $type;
    fputcsv($output, $type_row);
    fputcsv($output, array_merge(['Employee'], $months));

    foreach ($users as $u) {
        $uid = (int)$u['id'];
        $name = trim($u['first_name'] . ' ' . $u['middle_name'] . ' ' . $u['last_name']);
        $row = [$name];
        for ($m = 1; $m <= 12; $m++) {
            $days = $data[$type][$uid][$m] ?? [];
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
