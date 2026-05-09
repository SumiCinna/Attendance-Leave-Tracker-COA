<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin();

@$mysqli->query("ALTER TABLE absences ADD COLUMN admin_remarks TEXT NULL");
@$mysqli->query("ALTER TABLE absences ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");

// ── Update Status (Approve / Reject) ────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    $absence_id   = (int)$_POST["absence_id"];
    $status       = $_POST["status"];
    $admin_remarks = trim($_POST["admin_remarks"] ?? "");

    $stmt = $mysqli->prepare("UPDATE absences SET status = ?, admin_remarks = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $admin_remarks, $absence_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin.php" . (isset($_GET['page']) ? "?page=" . (int)$_GET['page'] : ""));
    exit;
}

// ── Toggle Archive / Recover ─────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_archive"])) {
    $absence_id   = (int)$_POST["absence_id"];
    $new_archived = (int)$_POST["new_archived"]; // 1 = archive, 0 = recover

    $stmt = $mysqli->prepare("UPDATE absences SET is_archived = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_archived, $absence_id);
    $stmt->execute();
    $stmt->close();

    $redirect_qs = $_GET;
    header("Location: admin.php?" . http_build_query($redirect_qs));
    exit;
}

// ── Add Leave Manually ───────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_leave_manual"])) {
    $user_id_input  = (int)($_POST["manual_user_id"] ?? $_POST["user_select_id"] ?? 0);
    $manual_first   = trim($_POST["manual_first_name"] ?? "");
    $manual_middle  = trim($_POST["manual_middle_name"] ?? "");
    $manual_last    = trim($_POST["manual_last_name"] ?? "");
    $manual_email   = trim($_POST["manual_email"] ?? "");
    $leave_date     = trim($_POST["manual_leave_date"] ?? "");
    $leave_type     = trim($_POST["manual_leave_type"] ?? "");
    $reason         = trim($_POST["manual_reason"] ?? "");

    $add_status = "success";
    $add_msg    = "Leave added successfully.";

    if ($leave_date === "" || $leave_type === "" || $reason === "") {
        $add_status = "error";
        $add_msg    = "Leave date, type, and reason are required.";
    } elseif (strlen($reason) > 100) {
        $add_status = "error";
        $add_msg    = "Reason must be 100 characters or less.";
    }

    if ($add_status === "success") {
        if ($user_id_input > 0) {
            $stmt = $mysqli->prepare("INSERT INTO absences (user_id, leave_date, leave_type, reason, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $stmt->bind_param("isss", $user_id_input, $leave_date, $leave_type, $reason);
            $stmt->execute();
            if ($stmt->affected_rows <= 0) {
                $add_status = "error";
                $add_msg    = "Unable to save the leave request.";
            }
            $stmt->close();
        } else {
            if ($manual_first === "" || $manual_last === "" || $manual_email === "") {
                $add_status = "error";
                $add_msg    = "First name, last name, and Gmail are required for manual entry.";
            } elseif (!preg_match("/^[^\s@]+@gmail\.com$/i", $manual_email)) {
                $add_status = "error";
                $add_msg    = "Enter a valid @gmail.com address.";
            } else {
                $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $manual_email);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row) {
                    $uid = $row["id"];
                } else {
                    $first_name  = $manual_first;
                    $middle_name = $manual_middle;
                    $last_name   = $manual_last;

                    if ($first_name === "" || $last_name === "") {
                        $add_status = "error";
                        $add_msg    = "First and last name are required to create the employee.";
                    } else {
                        $temp_password = bin2hex(random_bytes(8));
                        $hash          = password_hash($temp_password, PASSWORD_DEFAULT);
                        $role          = 'employee';

                        $stmt = $mysqli->prepare("INSERT INTO users (first_name, middle_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssss", $first_name, $middle_name, $last_name, $manual_email, $hash, $role);
                        $stmt->execute();
                        if ($stmt->affected_rows > 0) {
                            $uid = $stmt->insert_id;
                        } else {
                            $add_status = "error";
                            $add_msg    = "Unable to create employee record.";
                        }
                        $stmt->close();
                    }
                }

                if ($add_status === "success" && !empty($uid)) {
                    $stmt = $mysqli->prepare("INSERT INTO absences (user_id, leave_date, leave_type, reason, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
                    $stmt->bind_param("isss", $uid, $leave_date, $leave_type, $reason);
                    $stmt->execute();
                    if ($stmt->affected_rows <= 0) {
                        $add_status = "error";
                        $add_msg    = "Unable to save the leave request.";
                    }
                    $stmt->close();
                }
            }
        }
    }

    $redirect = "admin.php?add_status=" . $add_status . "&add_msg=" . urlencode($add_msg);
    header("Location: " . $redirect);
    exit;
}

// ── Filters & Pagination ─────────────────────────────────────────────────────
$view_archived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;

$limit  = 10;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search_name      = trim($_GET["search_name"] ?? "");
$search_date      = trim($_GET["search_date"] ?? "");
$search_submitted = trim($_GET["search_submitted"] ?? "");
$filter_type      = trim($_GET["filter_type"] ?? "");

$where_clauses = ["absences.is_archived = ?"];
$params        = [$view_archived];
$types         = "i";

if ($search_name !== "") {
    $where_clauses[] = "(users.first_name LIKE ? OR users.last_name LIKE ? OR users.middle_name LIKE ?)";
    $like_name       = "%{$search_name}%";
    $params[]        = $like_name;
    $params[]        = $like_name;
    $params[]        = $like_name;
    $types          .= "sss";
}

if ($search_date !== "") {
    $where_clauses[] = "absences.leave_date = ?";
    $params[]        = $search_date;
    $types          .= "s";
}

if ($search_submitted !== "") {
    $where_clauses[] = "DATE(absences.created_at) = ?";
    $params[]        = $search_submitted;
    $types          .= "s";
}

if ($filter_type !== "") {
    $where_clauses[] = "absences.leave_type = ?";
    $params[]        = $filter_type;
    $types          .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

$count_sql  = "SELECT COUNT(*) as count FROM absences JOIN users ON absences.user_id = users.id WHERE $where_sql";
$count_stmt = $mysqli->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages   = max(1, ceil($total_records / $limit));
$count_stmt->close();

$query = "SELECT absences.id, absences.leave_date, absences.leave_type, absences.reason,
    absences.status, absences.admin_remarks, absences.created_at, absences.is_archived,
    users.first_name, users.middle_name, users.last_name, users.email
    FROM absences JOIN users ON absences.user_id = users.id
    WHERE $where_sql ORDER BY absences.created_at DESC LIMIT ? OFFSET ?";

$stmt          = $mysqli->prepare($query);
$bind_params   = $params;
$bind_params[] = $limit;
$bind_params[] = $offset;
$bind_types    = $types . "ii";
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

// Count archived for badge
$archived_count_result = $mysqli->query("SELECT COUNT(*) as c FROM absences WHERE is_archived = 1");
$archived_count = $archived_count_result->fetch_assoc()['c'] ?? 0;

$users_result = $mysqli->query("SELECT id, first_name, middle_name, last_name, email FROM users WHERE role = 'employee' ORDER BY first_name ASC");
$all_users    = [];
while ($u = $users_result->fetch_assoc()) {
    $all_users[] = $u;
}

$query_string = $_GET;
unset($query_string['page']);
$base_qs  = http_build_query($query_string);
$base_url = "?" . ($base_qs ? $base_qs . "&" : "") . "page=";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Attendance Leave Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <header class="site-header">
        <div class="brand">
            <img src="../includes/images.png" alt="COA Logo" class="logo">
            <div>
                <h1><?php echo $view_archived ? 'Archived Leaves' : 'Admin Leave Overview'; ?></h1>
                <p class="muted"><?php echo $view_archived ? 'Archived leave and absence records.' : 'All employee leave and absence records.'; ?></p>
            </div>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php" class="btn btn-outline">My Dashboard</a>
            <a href="profile.php" class="btn btn-outline">Profile</a>
            <a href="#" class="btn btn-outline" id="logoutBtn">Logout</a>
        </nav>
    </header>

    <main class="container">
        <?php if (isset($_GET["add_status"])): ?>
            <div class="toast toast-<?php echo htmlspecialchars($_GET["add_status"], ENT_QUOTES, "UTF-8"); ?>" id="addLeaveToast">
                <?php echo htmlspecialchars($_GET["add_msg"] ?? "", ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <!-- Search / Filter -->
        <section class="card" style="margin-bottom: 24px;">
            <form method="GET" action="admin.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                <?php if ($view_archived): ?>
                    <input type="hidden" name="archived" value="1">
                <?php endif; ?>
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="search_name" style="font-size: 13px; font-weight: 600; color: var(--navy);">Employee Name</label>
                    <input type="text" name="search_name" id="search_name" value="<?php echo htmlspecialchars($search_name, ENT_QUOTES, "UTF-8"); ?>" placeholder="Search by name..." style="background:#fff; color:#333; border:1px solid var(--border);">
                </div>
                <div class="form-group" style="flex: 1; min-width: 130px;">
                    <label for="search_date" style="font-size: 13px; font-weight: 600; color: var(--navy);">Leave Date</label>
                    <input type="date" name="search_date" id="search_date" value="<?php echo htmlspecialchars($search_date, ENT_QUOTES, "UTF-8"); ?>" style="background:#fff; color:#333; border:1px solid var(--border);">
                </div>
                <div class="form-group" style="flex: 1; min-width: 130px;">
                    <label for="search_submitted" style="font-size: 13px; font-weight: 600; color: var(--navy);">Date Submitted</label>
                    <input type="date" name="search_submitted" id="search_submitted" value="<?php echo htmlspecialchars($search_submitted ?? '', ENT_QUOTES, "UTF-8"); ?>" style="background:#fff; color:#333; border:1px solid var(--border);">
                </div>
                <div class="form-group" style="flex: 1; min-width: 180px;">
                    <label for="filter_type" style="font-size: 13px; font-weight: 600; color: var(--navy);">Category (Type)</label>
                    <select name="filter_type" id="filter_type" style="background:#fff; color:#333; border:1px solid var(--border);">
                        <option value="">All Categories</option>
                        <option value="Sick Leave"                  <?php if ($filter_type === "Sick Leave")                  echo "selected"; ?>>Sick Leave</option>
                        <option value="Vacation Leave"              <?php if ($filter_type === "Vacation Leave")              echo "selected"; ?>>Vacation Leave</option>
                        <option value="Emergency Leave"             <?php if ($filter_type === "Emergency Leave")             echo "selected"; ?>>Emergency Leave</option>
                        <option value="Personal Leave"              <?php if ($filter_type === "Personal Leave")              echo "selected"; ?>>Personal Leave</option>
                        <option value="Bereavement Leave"           <?php if ($filter_type === "Bereavement Leave")           echo "selected"; ?>>Bereavement Leave</option>
                        <option value="Maternity/Paternity Leave"   <?php if ($filter_type === "Maternity/Paternity Leave")   echo "selected"; ?>>Maternity/Paternity Leave</option>
                        <option value="Official Business"           <?php if ($filter_type === "Official Business")           echo "selected"; ?>>Official Business</option>
                        <option value="Unpaid Leave"                <?php if ($filter_type === "Unpaid Leave")                echo "selected"; ?>>Unpaid Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn" style="padding: 11px 22px;">Search Filters</button>
                    <a href="admin.php<?php echo $view_archived ? '?archived=1' : ''; ?>" class="btn btn-auth-outline" style="padding: 11px 22px; text-decoration: none;">Clear</a>
                </div>
            </form>
        </section>

        <!-- Main Table -->
        <section class="card">
            <div class="action-row" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:center;">

                <?php if (!$view_archived): ?>
                    <button type="button" class="btn btn-accent" id="openAddLeaveBtn" style="padding:10px; font-size:16px;">+ Add Leave Manually</button>
                    <a href="export_leaves.php" class="btn" style="padding:10px; font-size:12px; text-decoration:none;">Export to CSV</a>
                    <button type="button" class="btn btn-outline" onclick="printTable()" style="padding:10px; font-size:13px; border:1.5px solid var(--navy); color:var(--navy); background:transparent;">Export to PDF</button>
                <?php endif; ?>

                <!-- Archive Toggle Switch -->
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

            <?php if ($view_archived): ?>
           
            <?php endif; ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Gmail</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Status / Action</th>
                            <th>Submitted</th>
                            <th><?php echo $view_archived ? 'Recover' : 'Archive'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records->num_rows === 0): ?>
                            <tr>
                                <td colspan="8" class="muted" style="text-align:center; padding:32px 0;">
                                    <?php echo $view_archived ? 'No archived leave records.' : 'No leave submissions yet.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = $records->fetch_assoc()): ?>
                                <tr class="<?php echo $row['is_archived'] ? 'row-archived' : ''; ?>">
                                    <td><?php echo htmlspecialchars(trim($row["first_name"] . " " . $row["middle_name"] . " " . $row["last_name"]), ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($row["email"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($row["leave_date"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($row["leave_type"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($row["reason"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td>
                                        <?php if ($view_archived): ?>
                                            <!-- In archive view: show status badge only, no action buttons -->
                                            <?php if ($row["status"] === "Approved"): ?>
                                                <span class="badge badge-green" style="padding:6px 12px;">Approved</span>
                                            <?php elseif ($row["status"] === "Rejected"): ?>
                                                <span class="badge badge-red" style="padding:6px 12px;">Rejected</span>
                                                <?php if (!empty($row["admin_remarks"])): ?>
                                                    <div style="font-size:11px; margin-top:4px; color:var(--text-muted);"><em>Note: <?php echo htmlspecialchars($row["admin_remarks"], ENT_QUOTES, "UTF-8"); ?></em></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge" style="padding:6px 12px; background:#e0e0e0; color:#555;">Pending</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Active view: full approve/reject actions -->
                                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                <?php if ($row["status"] === "Approved"): ?>
                                                    <div>
                                                        <span class="badge badge-green" style="margin-bottom:4px; padding:6px 12px;">Approved</span>
                                                        <div style="margin-top:6px;">
                                                            <button type="button" class="btn-auth-outline" style="padding:4px 8px; font-size:11px; cursor:pointer; border:none; background:transparent; text-decoration:underline;" onclick="openRejectModal(<?php echo $row['id']; ?>)">Change to Reject</button>
                                                        </div>
                                                    </div>
                                                <?php elseif ($row["status"] === "Rejected"): ?>
                                                    <div>
                                                        <span class="badge badge-red" style="margin-bottom:4px; padding:6px 12px;">Rejected</span>
                                                        <div style="font-size:11px; margin-top:4px; color:var(--text-muted);">
                                                            <em>Note: <?php echo htmlspecialchars($row["admin_remarks"] ?? "", ENT_QUOTES, "UTF-8"); ?></em>
                                                        </div>
                                                        <div style="margin-top:6px;">
                                                            <button type="button" class="btn-auth-outline" style="padding:4px 8px; font-size:11px; cursor:pointer; border:none; background:transparent; text-decoration:underline;" onclick="openApproveModal(<?php echo $row['id']; ?>)">Change to Approve</button>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <button type="button" class="btn" style="padding:8px 14px; font-size:12px; border:none; color:white; background:var(--accent);" onclick="openApproveModal(<?php echo $row['id']; ?>)">Approve</button>
                                                    <button type="button" class="btn" style="padding:8px 14px; font-size:12px; border:none; color:white; background:#c43c3c;" onclick="openRejectModal(<?php echo $row['id']; ?>)">Reject</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row["created_at"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td>
                                        <?php if ($row["is_archived"]): ?>
                                            <!-- Recover button -->
                                            <form method="POST" style="display:inline;">
                                                <?php
                                                    // Preserve current GET params so user stays in archived view
                                                    foreach ($_GET as $k => $v) {
                                                        echo '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES) . '" value="' . htmlspecialchars($v, ENT_QUOTES) . '">';
                                                    }
                                                ?>
                                                <input type="hidden" name="absence_id"   value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_archived" value="0">
                                                <button type="submit" name="toggle_archive" class="btn btn-recover"
                                                    title="Restore to active view">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
                                                    Recover
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Archive button -->
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="absence_id"   value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_archived" value="1">
                                                <button type="submit" name="toggle_archive" class="btn btn-archive"
                                                    title="Archive this record"
                                                    onclick="return confirm('Archive this leave record? You can recover it later from the Archived view.')">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                                                    Archive
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination" style="margin-top:20px; display:flex; justify-content:center; gap:8px;">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="<?php echo htmlspecialchars($base_url . $i, ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($i === $page) ? '' : 'btn-auth-outline'; ?>" style="padding:6px 12px; border-radius:4px; text-decoration:none;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Add Leave Modal -->
    <div id="addLeaveModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Add Leave Manually</h3>
            <p class="modal-sub">Submit a leave record on behalf of an employee. It will be set to Pending.</p>

            <div class="toggle-mode-row">
                <button type="button" class="toggle-mode-btn active" id="modeSelectBtn" onclick="setMode('select')">Select from Users</button>
                <button type="button" class="toggle-mode-btn"        id="modeManualBtn" onclick="setMode('manual')">Enter Manually</button>
            </div>

            <form method="POST" id="addLeaveForm" novalidate>
                <input type="hidden" name="add_leave_manual" value="1">
                <input type="hidden" name="manual_user_id"   id="manual_user_id" value="">

                <div id="selectMode">
                    <div class="form-group">
                        <label for="user_select">Employee</label>
                        <select id="user_select" name="user_select_id">
                            <option value="">— Select an employee —</option>
                            <?php foreach ($all_users as $u): ?>
                                <option
                                    value="<?php echo $u['id']; ?>"
                                    data-email="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-name="<?php echo htmlspecialchars(trim($u['first_name'] . ' ' . $u['middle_name'] . ' ' . $u['last_name']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(trim($u['first_name'] . ' ' . $u['middle_name'] . ' ' . $u['last_name']), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gmail (auto-filled)</label>
                        <input type="text" id="select_email_display" disabled placeholder="Will fill automatically" style="background:#f5f7ff; color:#888;">
                    </div>
                </div>

                <div id="manualMode" style="display:none;">
                    <div class="form-group">
                        <label for="manual_first_name">First Name</label>
                        <input type="text" id="manual_first_name" name="manual_first_name" maxlength="100" placeholder="e.g. Juan">
                        <div class="char-count" id="firstNameCount">0 / 100</div>
                    </div>
                    <div class="form-group">
                        <label for="manual_middle_name">Middle Name</label>
                        <input type="text" id="manual_middle_name" name="manual_middle_name" maxlength="100" placeholder="e.g. Dela">
                        <div class="char-count" id="middleNameCount">0 / 100</div>
                    </div>
                    <div class="form-group">
                        <label for="manual_last_name">Last Name</label>
                        <input type="text" id="manual_last_name" name="manual_last_name" maxlength="100" placeholder="e.g. Cruz">
                        <div class="char-count" id="lastNameCount">0 / 100</div>
                    </div>
                    <div class="form-group">
                        <label for="manual_email_input">Gmail</label>
                        <input type="email" id="manual_email_input" name="manual_email" placeholder="e.g. juan@gmail.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="manual_leave_date">Leave Date</label>
                    <input type="date" id="manual_leave_date" name="manual_leave_date" required>
                </div>

                <div class="form-group">
                    <label for="manual_leave_type">Leave Type</label>
                    <select id="manual_leave_type" name="manual_leave_type" required>
                        <option value="">— Select type —</option>
                        <option value="Sick Leave">Sick Leave</option>
                        <option value="Vacation Leave">Vacation Leave</option>
                        <option value="Emergency Leave">Emergency Leave</option>
                        <option value="Personal Leave">Personal Leave</option>
                        <option value="Bereavement Leave">Bereavement Leave</option>
                        <option value="Maternity/Paternity Leave">Maternity/Paternity Leave</option>
                        <option value="Official Business">Official Business</option>
                        <option value="Unpaid Leave">Unpaid Leave</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="manual_reason">Reason</label>
                    <textarea id="manual_reason" name="manual_reason" rows="3" maxlength="100" placeholder="Brief reason for the leave..." required></textarea>
                    <div class="char-count" id="reasonCount">0 / 100</div>
                </div>

                <div id="addLeaveError" style="display:none; color:#c43c3c; font-size:13px; margin-bottom:12px; padding:10px 12px; background:#fff0f0; border-radius:8px; border-left:3px solid #c43c3c;"></div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <button type="button" class="btn btn-auth-outline" onclick="closeAddLeaveModal()" style="border:1px solid #ccc;">Cancel</button>
                    <button type="button" class="btn" onclick="submitAddLeave()">Submit Leave</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
        <div class="card" style="width:400px; max-width:90%; animation:rise 0.3s ease-out;">
            <h3 style="margin-bottom:16px; color:var(--navy);">Reject Leave Request</h3>
            <form method="POST">
                <input type="hidden" name="absence_id" id="reject_absence_id">
                <input type="hidden" name="status"     value="Rejected">
                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-size:14px;">Reason for Rejection</label>
                    <textarea name="admin_remarks" rows="3" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:var(--radius-sm); background:#fff; color:#333;"></textarea>
                </div>
                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <button type="button" class="btn btn-auth-outline" onclick="closeRejectModal()" style="border:1px solid #ccc;">Cancel</button>
                    <button type="submit" name="update_status" class="btn" style="background:#c43c3c; box-shadow:0 4px 12px rgba(196,60,60,0.2);">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
        <div class="card" style="width:400px; max-width:90%; animation:rise 0.3s ease-out;">
            <h3 style="margin-bottom:16px; color:var(--navy);">Approve Leave Request</h3>
            <form method="POST">
                <input type="hidden" name="absence_id" id="approve_absence_id">
                <input type="hidden" name="status"     value="Approved">
                <div class="form-group" style="margin-bottom:20px;">
                    <p style="font-size:14px; color:var(--text);">Are you sure you want to approve this leave request? This will mark it as officially recorded.</p>
                </div>
                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <button type="button" class="btn btn-auth-outline" onclick="closeApproveModal()" style="border:1px solid #ccc;">Cancel</button>
                    <button type="submit" name="update_status" class="btn" style="background:var(--accent); box-shadow:0 4px 12px var(--accent-glow);">Confirm Approve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout Modal -->
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

    <script src="../js/admin.js"></script>
</body>
</html>