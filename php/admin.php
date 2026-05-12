<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin();

// ── Add Leave Type ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_leave_type"])) {
    $type_name = trim($_POST["leave_type_name"] ?? "");
    $lt_status = "success";
    $lt_msg    = "Leave type added successfully.";

    if ($type_name === "") {
        $lt_status = "error";
        $lt_msg    = "Leave type name cannot be empty.";
    } elseif (strlen($type_name) > 50) {
        $lt_status = "error";
        $lt_msg    = "Leave type name must be 50 characters or less.";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO leave_types (name) VALUES (?)");
        $stmt->bind_param("s", $type_name);
        if (!$stmt->execute()) {
            $lt_status = "error";
            $lt_msg    = "That leave type already exists.";
        }
        $stmt->close();
    }

    header("Location: admin.php?lt_status=" . $lt_status . "&lt_msg=" . urlencode($lt_msg) . "&open_lt=1");
    exit;
}

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

// ── Toggle Leave Type Active/Inactive ──────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_leave_type"])) {
    $lt_id     = (int)$_POST["lt_id"];
    $lt_active = (int)$_POST["lt_active"];

    $stmt = $mysqli->prepare("UPDATE leave_types SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $lt_active, $lt_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin.php?lt_status=success&lt_msg=" . urlencode($lt_active ? "Leave type re-enabled." : "Leave type disabled.") . "&open_lt=1");
    exit;
}

// ── Delete Leave Type ──────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_leave_type"])) {
    $lt_id = (int)$_POST["lt_id"];

    $check = $mysqli->prepare("SELECT COUNT(*) as c FROM leave_data WHERE leave_type = (SELECT name FROM leave_types WHERE id = ?)");
    $check->bind_param("i", $lt_id);
    $check->execute();
    $in_use = $check->get_result()->fetch_assoc()['c'];
    $check->close();

    if ($in_use > 0) {
        header("Location: admin.php?lt_status=error&lt_msg=" . urlencode("Cannot delete: this type is used by existing records. Disable it instead.") . "&open_lt=1");
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM leave_types WHERE id = ?");
    $stmt->bind_param("i", $lt_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin.php?lt_status=success&lt_msg=" . urlencode("Leave type deleted.") . "&open_lt=1");
    exit;
}

// ── Archive / Recover Leave Record ─────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_archive_leave"])) {
    $leave_id    = (int)$_POST["leave_id"];
    $new_archived = (int)($_POST["new_archived"] ?? 0);

    $stmt = $mysqli->prepare("UPDATE leave_data SET is_archived = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_archived, $leave_id);
    $stmt->execute();
    $stmt->close();

    $redirect_qs = $_GET;
    header("Location: admin.php?" . http_build_query($redirect_qs));
    exit;
}

// ── Delete Leave Record (Password Confirm) ─────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_leave"])) {
    $leave_id   = (int)$_POST["leave_id"];
    $password_input = $_POST["confirm_password"] ?? "";

    $msg_status = "error";
    $msg_text   = "Invalid password. Leave record was not deleted.";

    $admin_stmt = $mysqli->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $admin_stmt->bind_param("i", $_SESSION['user_id']);
    $admin_stmt->execute();
    $admin_row = $admin_stmt->get_result()->fetch_assoc();
    $admin_stmt->close();

    if ($admin_row && password_verify($password_input, $admin_row['password_hash'])) {
        $del_stmt = $mysqli->prepare("DELETE FROM leave_data WHERE id = ?");
        $del_stmt->bind_param("i", $leave_id);
        $del_stmt->execute();
        $del_stmt->close();
        $msg_status = "success";
        $msg_text   = "Leave record deleted.";
    }

    header("Location: admin.php?delete_status=" . $msg_status . "&delete_msg=" . urlencode($msg_text));
    exit;
}

// ── Add Leave Manually ─────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_leave_manual"])) {
    $employee_mode = trim($_POST["employee_mode"] ?? "existing");
    $existing_employee = trim($_POST["existing_employee"] ?? "");
    $new_employee_name = trim($_POST["new_employee_name"] ?? "");

    $employee_name = $employee_mode === "new" ? $new_employee_name : $existing_employee;
    $leave_date    = trim($_POST["manual_leave_date"] ?? "");
    $leave_type    = trim($_POST["manual_leave_type"] ?? "");

    $add_status = "success";
    $add_msg    = "Leave added successfully.";

    if ($employee_mode !== "existing" && $employee_mode !== "new") {
        $add_status = "error";
        $add_msg    = "Please select an employee or add a new one.";
    } elseif ($employee_name === "" || $leave_date === "" || $leave_type === "") {
        $add_status = "error";
        $add_msg    = "Employee name, leave date, and leave type are required.";
    } elseif (mb_strlen($employee_name) > 255) {
        $add_status = "error";
        $add_msg    = "Employee name is too long.";
    } else {
        $date_obj = DateTime::createFromFormat('Y-m-d', $leave_date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $leave_date) {
            $add_status = "error";
            $add_msg    = "Please enter a valid leave date.";
        }
    }

    if ($add_status === "success") {
        // Check for duplicate
        $dup_stmt = $mysqli->prepare("SELECT 1 FROM leave_data WHERE employee_name = ? AND leave_date = ? LIMIT 1");
        $dup_stmt->bind_param("ss", $employee_name, $leave_date);
        $dup_stmt->execute();
        $dup_stmt->store_result();
        $exists = $dup_stmt->num_rows > 0;
        $dup_stmt->close();

        if ($exists) {
            $add_status = "error";
            $add_msg    = "A leave entry already exists on that date for this employee.";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO leave_data (employee_name, leave_date, leave_type, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $employee_name, $leave_date, $leave_type);
            $stmt->execute();
            if ($stmt->affected_rows <= 0) {
                $add_status = "error";
                $add_msg    = "Unable to save the leave record.";
            }
            $stmt->close();
        }
    }

    header("Location: admin.php?add_status=" . $add_status . "&add_msg=" . urlencode($add_msg));
    exit;
}

// ── Filters & Pagination ───────────────────────────────────────────────────────
$view_archived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;

$search_name      = trim($_GET["search_name"] ?? "");
$filter_type      = trim($_GET["filter_type"] ?? "");

$where_clauses = [
    "leave_data.is_archived = ?",
    "YEAR(leave_data.leave_date) = ?"
];


$params        = [$view_archived, $selected_year];
$types         = "ii";

if ($search_name !== "") {
    $where_clauses[] = "leave_data.employee_name LIKE ?";
    $like_name = "%{$search_name}%";
    $params[] = $like_name;
    $types   .= "s";
}
if ($selected_month > 0 && $selected_month <= 12) {
    $where_clauses[] = "MONTH(leave_data.leave_date) = ?";
    $params[] = $selected_month;
    $types   .= "i";
}
if ($filter_type !== "") {
    $where_clauses[] = "leave_data.leave_type = ?";
    $params[] = $filter_type;
    $types   .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Get summary grouped by employee name
$summary_query = $view_archived
    ? "SELECT id, employee_name, leave_date, leave_type FROM leave_data WHERE $where_sql ORDER BY employee_name ASC, leave_date ASC"
    : "SELECT 
        employee_name,
        COUNT(id) as leave_count,
        MAX(leave_date) as last_leave_date,
        GROUP_CONCAT(DISTINCT leave_type ORDER BY leave_type) as leave_types
        FROM leave_data
        WHERE $where_sql
        GROUP BY employee_name
        ORDER BY employee_name ASC";

$summary_stmt = $mysqli->prepare($summary_query);
$summary_stmt->bind_param($types, ...$params);
$summary_stmt->execute();
$summary_records = $summary_stmt->get_result();
$summary_stmt->close();

$archived_count = $mysqli->query("SELECT COUNT(*) as c FROM leave_data WHERE is_archived = 1")->fetch_assoc()['c'] ?? 0;

// Get unique employee names
$employees_result = $mysqli->query("SELECT DISTINCT employee_name FROM leave_data ORDER BY employee_name ASC");
$all_employees = [];
while ($e = $employees_result->fetch_assoc()) {
    $all_employees[] = $e['employee_name'];
}

$lt_result   = $mysqli->query("SELECT id, name, is_active FROM leave_types ORDER BY name ASC");
$leave_types = [];
while ($lt = $lt_result->fetch_assoc()) {
    $leave_types[] = $lt;
}
$active_leave_types = array_filter($leave_types, fn($lt) => $lt['is_active']);

$calendar_year = $selected_year;
$calendar_month = ($selected_month > 0 && $selected_month <= 12) ? $selected_month : 0;

// Get calendar entries
$calendar_query = "SELECT id, employee_name, leave_date, leave_type, is_archived
    FROM leave_data
    WHERE is_archived = ? AND YEAR(leave_date) = ?";
$calendar_types = "ii";
$calendar_params = [$view_archived, $calendar_year];
if ($calendar_month) {
    $calendar_query .= " AND MONTH(leave_date) = ?";
    $calendar_types .= "i";
    $calendar_params[] = $calendar_month;
}
$calendar_query .= " ORDER BY employee_name ASC, leave_date ASC";

$calendar_stmt = $mysqli->prepare($calendar_query);
$calendar_stmt->bind_param($calendar_types, ...$calendar_params);
$calendar_stmt->execute();
$calendar_result = $calendar_stmt->get_result();
$calendar_entries = [];
while ($row = $calendar_result->fetch_assoc()) {
    $calendar_entries[] = [
        'id' => (int)$row['id'],
        'employee_name' => $row['employee_name'],
        'leave_date' => $row['leave_date'],
        'leave_type' => $row['leave_type'],
        'is_archived' => (int)$row['is_archived']
    ];
}
$calendar_stmt->close();

$calendar_employee_names = array_values(array_unique(array_filter(array_map(
    fn($entry) => $entry['employee_name'] ?? '',
    $calendar_entries
))));
sort($calendar_employee_names, SORT_NATURAL | SORT_FLAG_CASE);

$reopen_lt_modal = isset($_GET['open_lt']) && $_GET['open_lt'] === '1';
$export_params = ['year' => $selected_year];
if ($selected_month > 0 && $selected_month <= 12) {
    $export_params['month'] = $selected_month;
}
if ($view_archived) {
    $export_params['archived'] = '1';
}
$export_query = http_build_query($export_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Attendance Leave Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* ── Leave Type Chips ── */
        .lt-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }

        .lt-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 8px 7px 14px; border-radius: 20px; font-size: 13px;
            font-weight: 500; border: 1.5px solid transparent; transition: all 0.2s;
        }
        .lt-chip.active   { background: #e8f5e9; border-color: #4caf50; color: #2e7d32; }
        .lt-chip.inactive { background: #fafafa; border-color: #ccc; color: #999; text-decoration: line-through; }

        .lt-chip-actions { display: flex; gap: 2px; }
        .lt-chip-btn {
            background: none; border: none; cursor: pointer;
            padding: 3px 5px; border-radius: 4px; line-height: 1;
            opacity: 0.65; transition: opacity 0.15s, background 0.15s;
            display: inline-flex; align-items: center;
        }
        .lt-chip-btn:hover { opacity: 1; background: rgba(0,0,0,0.06); }
        .lt-chip-btn.disable { color: #e67e22; }
        .lt-chip-btn.enable  { color: #4caf50; }
        .lt-chip-btn.del     { color: #c43c3c; }

        /* ── LT Modal specifics ── */
        .lt-section-label {
            font-size: 11px; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--text-muted, #888);
            margin: 0 0 10px;
        }
        .lt-divider { border: none; border-top: 1px solid var(--border, #e0e0e0); margin: 18px 0; }
        .lt-add-row { display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap; }
        .lt-add-row input[type="text"] {
            flex: 1; min-width: 180px; padding: 10px 14px;
            border: 1.5px solid var(--border, #e0e0e0);
            border-radius: var(--radius-sm, 8px); font-size: 14px;
            background: #fff; color: #333; transition: border-color 0.2s;
        }
        .lt-add-row input[type="text"]:focus { outline: none; border-color: var(--accent, #2563eb); }
        #ltAddError {
            display: none; width: 100%; font-size: 12px; color: #c43c3c;
            padding: 8px 10px; background: #fff0f0; border-radius: 6px;
            border-left: 3px solid #c43c3c; margin-top: 6px;
        }
        #ltCharCount {
            width: 100%; font-size: 11px; color: var(--text-muted, #888);
            margin-top: 4px; text-align: right; transition: color 0.2s;
        }
        #ltCharCount.near-limit { color: #e67e22; }
        #ltCharCount.at-limit   { color: #c43c3c; font-weight: 600; }

        /* ── Delete confirm modal ── */
        #ltDeleteModal .modal-box { max-width: 380px; }
        .delete-icon-wrap {
            width: 52px; height: 52px; border-radius: 50%;
            background: #fff0f0; display: flex; align-items: center;
            justify-content: center; margin: 0 auto 14px;
        }

        /* Toast */
        .toast {
            padding: 12px 18px; border-radius: 8px;
            margin-bottom: 18px; font-size: 14px; font-weight: 500;
            transition: opacity 0.5s;
        }
        .toast-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .toast-error   { background: #fff0f0; color: #c43c3c; border: 1px solid #f5c6c6; }
    </style>
</head>
<body data-reopen-lt="<?php echo $reopen_lt_modal ? '1' : '0'; ?>">
    <header class="site-header">
        <div class="brand">
            <img src="../includes/images.png" alt="COA Logo" class="logo">
            <div>
                <h1><?php echo $view_archived ? 'Archived Leaves' : 'Admin Leave Overview'; ?></h1>
                <p class="muted"><?php echo $view_archived ? 'Archived leave and absence records.' : 'All employee leave and absence records.'; ?></p>
            </div>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php" class="btn btn-outline">Dashboard</a>
            <a href="profile.php"   class="btn btn-outline">Profile</a>
            <a href="#" class="btn btn-outline" id="logoutBtn">Logout</a>
        </nav>
    </header>

    <main class="container">
        <?php if (isset($_GET["add_status"])): ?>
            <div class="toast toast-<?php echo htmlspecialchars($_GET["add_status"], ENT_QUOTES, "UTF-8"); ?>" id="addLeaveToast">
                <?php echo htmlspecialchars($_GET["add_msg"] ?? "", ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["lt_status"])): ?>
            <div class="toast toast-<?php echo htmlspecialchars($_GET["lt_status"], ENT_QUOTES, "UTF-8"); ?>" id="ltToast">
                <?php echo htmlspecialchars($_GET["lt_msg"] ?? "", ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["delete_status"])): ?>
            <div class="toast toast-<?php echo htmlspecialchars($_GET["delete_status"], ENT_QUOTES, "UTF-8"); ?>" id="deleteToast">
                <?php echo htmlspecialchars($_GET["delete_msg"] ?? "", ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <!-- Search / Filter -->
        <section class="card" style="margin-bottom: 24px;">
            <form method="GET" action="admin.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                <?php if ($view_archived): ?>
                    <input type="hidden" name="archived" value="1">
                <?php endif; ?>
                <div class="form-group" style="flex:1; min-width:120px;">
    <label for="year">Year</label>

    <select name="year" id="year">
        <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
            <option value="<?php echo $y; ?>"
                <?php echo $selected_year == $y ? 'selected' : ''; ?>>
                <?php echo $y; ?>
            </option>
        <?php endfor; ?>
    </select>
</div>
                <div class="form-group" style="flex:1; min-width:200px;">
                    <label for="search_name" style="font-size:13px; font-weight:600; color:var(--navy);">Employee Name</label>
                    <input type="text" name="search_name" id="search_name"
                        value="<?php echo htmlspecialchars($search_name, ENT_QUOTES, "UTF-8"); ?>"
                        placeholder="Search by name..." style="background:#fff; color:#333; border:1px solid var(--border);">
                </div>
                <div class="form-group" style="flex:1; min-width:160px;">
                    <label for="month" style="font-size:13px; font-weight:600; color:var(--navy);">Month</label>
                    <select name="month" id="month" style="background:#fff; color:#333; border:1px solid var(--border);">
                        <option value="">All Months</option>
                        <?php
                        $month_names = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
                            7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        foreach ($month_names as $num => $label):
                        ?>
                            <option value="<?php echo $num; ?>" <?php echo $selected_month === $num ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:1; min-width:180px;">
                    <label for="filter_type" style="font-size:13px; font-weight:600; color:var(--navy);">Leave Type</label>
                    <select name="filter_type" id="filter_type" style="background:#fff; color:#333; border:1px solid var(--border);">
                        <option value="">All Types</option>
                        <?php foreach ($leave_types as $lt): ?>
                            <option value="<?php echo htmlspecialchars($lt['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $filter_type === $lt['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lt['name'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo !$lt['is_active'] ? ' (disabled)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn" style="padding:11px 22px;">Search Filters</button>
                    <a href="admin.php<?php echo $view_archived ? '?archived=1' : ''; ?>"
                        class="btn btn-auth-outline" style="padding:11px 22px; text-decoration:none;">Clear</a>
                </div>
            </form>
        </section>

        <!-- Main Table -->
        <section class="card">
            <div class="action-row" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:center;">
                <button type="button" class="btn btn-accent" id="openAddLeaveBtn" style="padding:10px; font-size:16px;">+ Add Leave Manually</button>
                <a href="export_leaves.php?<?php echo htmlspecialchars($export_query, ENT_QUOTES, 'UTF-8'); ?>" class="btn" style="padding:9px; font-size:14px; text-decoration:none;">Export to CSV</a>
                <button type="button" class="btn" onclick="printTable()" style="padding:11px; font-size:14px;">Export to PDF</button>
                <button type="button" class="btn btn-outline" id="openLtModalBtn"
                    style="padding:10px; font-size:13px; border:1.5px solid var(--navy); color:var(--navy); background:transparent; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    Manage Leave Types
                </button>

                <div style="margin-left:auto; display:flex; align-items:center; gap:12px;">
                    <span class="archive-view-label <?php echo $view_archived ? 'archived' : 'active'; ?>">
                        <?php if ($view_archived): ?>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:4px;"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                            Archived View
                            <?php if ($archived_count > 0): ?>
                                <span class="archive-badge"><?php echo $archived_count; ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            Active View
                        <?php endif; ?>
                    </span>
                    <label class="archive-toggle" title="<?php echo $view_archived ? 'Switch to Active' : 'Switch to Archived'; ?>">
                        <input type="checkbox" id="archiveToggle" <?php echo $view_archived ? 'checked' : ''; ?>>
                        <span class="archive-slider"></span>
                    </label>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php if ($view_archived): ?>
                                <th>Employee Name</th>
                                <th>Leave Date</th>
                                <th>Leave Type</th>
                                <th>Action</th>
                            <?php else: ?>
                                <th>Employee Name</th>
                                <th>Leave Count</th>
                                <th>Last Leave Date</th>
                                <th>Leave Types</th>
                                <th>Calendar</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($summary_records->num_rows === 0): ?>
                            <tr>
                                <td colspan="<?php echo $view_archived ? 4 : 5; ?>" class="muted" style="text-align:center; padding:32px 0;">
                                    <?php echo $view_archived ? 'No archived leave records.' : 'No leave records yet.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = $summary_records->fetch_assoc()): ?>
                                <tr>
                                    <?php if ($view_archived): ?>
                                        <td><?php echo htmlspecialchars($row["employee_name"], ENT_QUOTES, "UTF-8"); ?></td>
                                        <td><?php echo htmlspecialchars($row['leave_date'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['leave_type'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="toggle_archive_leave" value="1">
                                                <input type="hidden" name="leave_id" value="<?php echo (int)$row['id']; ?>">
                                                <input type="hidden" name="new_archived" value="0">
                                                <button type="submit" class="btn btn-outline" style="padding:6px 12px; text-color: black;">Restore</button>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <td><?php echo htmlspecialchars($row["employee_name"], ENT_QUOTES, "UTF-8"); ?></td>
                                        <td><?php echo (int)$row['leave_count']; ?></td>
                                        <td><?php echo htmlspecialchars($row['last_leave_date'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['leave_types'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-calendar" data-employee-name="<?php echo htmlspecialchars($row['employee_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                View Calendar
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- ══ Archive Confirm Modal ══════════════════════════════════════════════════ -->
    <div id="archiveConfirmModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); align-items:center; justify-content:center; z-index:1000;">
        <div class="card" style="width:400px; max-width:90%; animation:rise 0.3s ease-out; text-align:center;">
            <div style="width:52px; height:52px; border-radius:50%; background:#fff3e0; display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e67e22" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>
                </svg>
            </div>
            <h3 style="margin:0 0 8px; color:var(--navy);">Archive these records?</h3>
            <p style="font-size:14px; color:var(--text-muted, #666); margin:0 0 24px;">
                These records will be moved to the Archived view. You can recover them at any time.
            </p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button type="button" class="btn btn-auth-outline" onclick="closeArchiveModal()"
                    style="min-width:110px; border:1px solid var(--border, #ccc);">Cancel</button>
                <button type="button" class="btn" onclick="confirmArchive()"
                    style="min-width:110px; background:#e67e22; box-shadow:0 4px 12px rgba(230,126,34,0.25);">Yes, Archive</button>
            </div>
        </div>
    </div>

    <!-- ══ Manage Leave Types Modal ══════════════════════════════════════════════ -->
    <div id="ltModal" class="modal-overlay" style="display:none;">
        <div class="modal-box" style="max-width:520px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                <h3 style="margin:0;">Manage Leave Types</h3>
                <button type="button" onclick="closeLtModal()"
                    style="background:none; border:none; cursor:pointer; padding:4px; color:var(--text-muted, #888); border-radius:6px;"
                    title="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <p class="modal-sub" style="margin-top:0; margin-bottom:18px;">Active types appear in all leave forms. Disable to hide without losing history.</p>

            <p class="lt-section-label">Current Types</p>
            <div class="lt-grid">
                <?php foreach ($leave_types as $lt): ?>
                    <span class="lt-chip <?php echo $lt['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo htmlspecialchars($lt['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <span class="lt-chip-actions">
                            <?php if ($lt['is_active']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="lt_id"     value="<?php echo $lt['id']; ?>">
                                    <input type="hidden" name="lt_active" value="0">
                                    <button type="submit" name="toggle_leave_type" class="lt-chip-btn disable" title="Disable">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="lt_id"     value="<?php echo $lt['id']; ?>">
                                    <input type="hidden" name="lt_active" value="1">
                                    <button type="submit" name="toggle_leave_type" class="lt-chip-btn enable" title="Re-enable">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="lt-chip-btn del" title="Delete"
                                onclick="openLtDeleteModal(<?php echo $lt['id']; ?>, '<?php echo htmlspecialchars(addslashes($lt['name']), ENT_QUOTES, 'UTF-8'); ?>')">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </span>
                    </span>
                <?php endforeach; ?>
            </div>

            <hr class="lt-divider">

            <p class="lt-section-label">Add New Type</p>
            <form method="POST" class="lt-add-row" onsubmit="return validateNewLeaveType()">
                <div style="flex:1; min-width:180px; display:flex; flex-direction:column;">
                    <input type="text" name="leave_type_name" id="newLeaveTypeName"
                        maxlength="50" placeholder="e.g. Study Leave, Solo Parent Leave…" autocomplete="off">
                    <div id="ltCharCount">0 / 50</div>
                </div>
                <button type="submit" name="add_leave_type" class="btn" style="padding:10px 20px; white-space:nowrap; align-self:flex-start;">+ Add</button>
                <div id="ltAddError"></div>
            </form>
        </div>
    </div>

    <!-- ══ Delete Leave Type Confirm Modal ═══════════════════════════════════════ -->
    <div id="ltDeleteModal" class="modal-overlay" style="display:none;">
        <div class="modal-box" style="max-width:380px; text-align:center;">
            <div class="delete-icon-wrap">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#c43c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </div>
            <h3 style="margin:0 0 8px; color:var(--navy);">Delete Leave Type?</h3>
            <p style="font-size:14px; color:var(--text-muted, #666); margin:0 0 6px;">
                You're about to delete <strong id="ltDeleteName" style="color:var(--navy);">—</strong>.
            </p>
            <p style="font-size:13px; color:#e67e22; margin:0 0 24px;">
                This cannot be undone. If any records use this type, deletion will be blocked — disable it instead.
            </p>
            <form method="POST" id="ltDeleteForm">
                <input type="hidden" name="lt_id" id="ltDeleteId">
                <div style="display:flex; gap:12px; justify-content:center;">
                    <button type="button" class="btn btn-auth-outline"
                        onclick="closeLtDeleteModal()" style="min-width:110px; border:1px solid var(--border,#ccc);">
                        Cancel
                    </button>
                    <button type="submit" name="delete_leave_type" class="btn"
                        style="min-width:110px; background:#c43c3c; box-shadow:0 4px 12px rgba(196,60,60,0.2);">
                        Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ Add Leave Modal ════════════════════════════════════════════════════════ -->
    <div id="addLeaveModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Add Leave Manually</h3>
            <p class="modal-sub">Submit a leave record directly. It will be recorded as active.</p>

            <form method="POST" id="addLeaveForm" novalidate>
                <input type="hidden" name="add_leave_manual" value="1">

                <div class="form-group" style="margin-bottom:6px;">
                    <label style="font-weight:600;">Employee Source</label>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px;">
                            <input type="radio" name="employee_mode" value="existing" id="employeeModeExisting" checked>
                            Select existing
                        </label>
                        <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px;">
                            <input type="radio" name="employee_mode" value="new" id="employeeModeNew">
                            Add new employee
                        </label>
                    </div>
                </div>

                <div class="form-group" id="employeeSelectDiv">
                    <label for="existing_employee">Select Employee</label>
                    <select id="existing_employee" name="existing_employee">
                        <option value="">— Select employee —</option>
                        <?php foreach ($all_employees as $emp): ?>
                            <option value="<?php echo htmlspecialchars($emp, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($emp, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="employeeManualDiv" style="display:none;">
                    <label for="new_employee_name">New Employee Name</label>
                    <input type="text" id="new_employee_name" name="new_employee_name" placeholder="e.g. Juan Dela Cruz">
                </div>

                <div class="form-group">
                    <label for="manual_leave_date">Leave Date</label>
                    <input type="date" id="manual_leave_date" name="manual_leave_date" required>
                </div>

                <div class="form-group">
                    <label for="manual_leave_type">Leave Type</label>
                    <select id="manual_leave_type" name="manual_leave_type" required>
                        <option value="">— Select type —</option>
                        <?php foreach ($active_leave_types as $lt): ?>
                            <option value="<?php echo htmlspecialchars($lt['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($lt['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="addLeaveError" style="display:none; color:#c43c3c; font-size:13px; margin-bottom:12px; padding:10px 12px; background:#fff0f0; border-radius:8px; border-left:3px solid #c43c3c;"></div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <button type="button" class="btn btn-auth-outline" onclick="closeAddLeaveModal()" style="border:1px solid #ccc;">Cancel</button>
                    <button type="button" class="btn" onclick="submitAddLeave()">Submit Leave</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ Delete Leave Confirm Modal ═══════════════════════════════════════════ -->
    <div id="deleteLeavesModal" class="modal-overlay">
        <div class="modal-box" style="max-width:420px;">
            <h3>Delete leave record?</h3>
            <p class="modal-sub" id="deleteLeavesSub">Enter your password to confirm deletion.</p>
            <form method="POST" id="deleteLeavesForm">
                <input type="hidden" name="delete_leave" value="1">
                <input type="hidden" name="leave_id" id="deleteLeaveId">
                <div class="form-group">
                    <label for="delete_confirm_password">Admin Password</label>
                    <div class="input-with-button">
                        <input type="password" id="delete_confirm_password" name="confirm_password" required>
                        <button type="button" class="btn btn-auth-outline toggle-password" data-target="delete_confirm_password" aria-label="Toggle password visibility">
                            <svg class="eye-open eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg class="eye-closed eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="display:none;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <button type="button" class="btn btn-auth-outline" onclick="closeDeleteLeavesModal()" style="border:1px solid #ccc;">Cancel</button>
                    <button type="submit" class="btn" style="background:#c43c3c; box-shadow:0 4px 12px rgba(196,60,60,0.2);">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ Calendar Modal ═══════════════════════════════════════════════════════ -->
    <div id="calendarModal" class="modal-overlay">
        <div class="modal-box" style="max-width:1100px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px;">
                <div>
                    <h3 style="margin:0 0 4px;">Employee Leave Calendar</h3>
                    <p class="modal-sub" id="calendarSubtitle">Select an employee to view monthly absences for <?php echo $calendar_year; ?>.</p>
                </div>
                <button type="button" class="btn btn-auth-outline" onclick="closeCalendarModal()" style="border:1px solid #ccc;">Close</button>
            </div>
            <div class="calendar-header" style="margin-bottom:10px;">
                <div class="calendar-legend" id="calendarLegend"></div>
            </div>
            <form method="POST" id="archiveLeaveForm" style="display:none;">
                <input type="hidden" name="toggle_archive_leave" value="1">
                <input type="hidden" name="leave_id" id="archiveLeaveId">
                <input type="hidden" name="new_archived" id="archiveLeaveValue">
            </form>
            <div class="calendar-content">
                <div class="calendar-title" id="calendarTitle">Select an employee</div>
                <div class="calendar-grid" id="calendarGrid"></div>
                <div class="leave-list" id="leaveList"></div>
            </div>
        </div>
    </div>

    <!-- ══ Logout Modal ═══════════════════════════════════════════════════════════ -->
    <div id="logoutModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); align-items:center; justify-content:center; z-index:2000;">
        <div class="card" style="width:380px; max-width:90%; animation:rise 0.25s ease-out; text-align:center;">
            <div style="margin-bottom:16px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--navy, #1a2e5a)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </div>
            <h3 style="margin:0 0 8px; color:var(--navy, #1a2e5a); font-size:18px;">Log out?</h3>
            <p style="margin:0 0 24px; font-size:14px; color:var(--text-muted, #666);">You will be returned to the login page.</p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button type="button" class="btn btn-auth-outline" id="logoutCancel" style="min-width:110px; border:1px solid var(--border, #ccc);">Cancel</button>
                <a href="logout.php" class="btn" style="min-width:110px; text-decoration:none; text-align:center;">Yes, Log out</a>
            </div>
        </div>
    </div>

    <script>
        window.calendarData = <?php echo json_encode([
            'year' => $calendar_year,
            'month' => $calendar_month,
            'employees' => $calendar_employee_names,
            'entries' => $calendar_entries,
            'types' => array_values(array_map(fn($lt) => $lt['name'], $leave_types))
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    </script>
    <script src="../js/admin.js"></script>
</body>
</html>