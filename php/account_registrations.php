<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin();

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// ── Admin Role Changes ───────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["admin_role_action"])) {
    $target_id     = (int)($_POST["user_id"] ?? 0);
    $password_input = $_POST["confirm_password"] ?? "";
    $action        = $_POST["admin_role_action"] ?? "";
    $acct_status   = "error";
    $acct_msg      = "Unable to update admin access.";

    $admin_stmt = $mysqli->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $admin_stmt->bind_param("i", $currentUserId);
    $admin_stmt->execute();
    $admin_row = $admin_stmt->get_result()->fetch_assoc();
    $admin_stmt->close();

    if (!$admin_row || !password_verify($password_input, $admin_row["password_hash"])) {
        $acct_msg = "Admin password is incorrect.";
    } elseif ($target_id <= 0 || $target_id === $currentUserId) {
        $acct_msg = "Invalid account selected.";
    } elseif ($action === "grant") {
        $stmt = $mysqli->prepare(
            "UPDATE users
             SET role = 'admin', is_active = 1, account_status = 'approved'
             WHERE id = ? AND role <> 'admin' AND is_registered_account = 1"
        );
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $acct_status = "success";
            $acct_msg    = "Admin access granted successfully.";
        } else {
            $acct_msg = "Account is already an admin or no longer available.";
        }
        $stmt->close();
    } elseif ($action === "revoke") {
        $stmt = $mysqli->prepare(
            "UPDATE users
             SET role = 'employee'
             WHERE id = ? AND role = 'admin' AND is_registered_account = 1"
        );
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $acct_status = "success";
            $acct_msg    = "Admin access revoked successfully.";
        } else {
            $acct_msg = "Account is not an admin or no longer available.";
        }
        $stmt->close();
    } else {
        $acct_msg = "Invalid admin action.";
    }

    header("Location: account_registrations.php?acct_status=" . $acct_status . "&acct_msg=" . urlencode($acct_msg) . "&tab=manage");
    exit;
}

// ── Approve / Reject Pending ───────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["account_action"])) {
    $target_id   = (int)($_POST["user_id"] ?? 0);
    $action      = $_POST["account_action"] ?? "";
    $acct_status = "error";
    $acct_msg    = "Unable to update the account.";

    if ($target_id > 0 && ($action === "approve" || $action === "reject")) {
        $new_status = $action === "approve" ? "approved" : "rejected";
        $stmt = $mysqli->prepare(
            "UPDATE users SET account_status = ?
             WHERE id = ? AND role <> 'admin' AND account_status = 'pending' AND is_registered_account = 1"
        );
        $stmt->bind_param("si", $new_status, $target_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $acct_status = "success";
            $acct_msg    = $action === "approve" ? "Account approved." : "Account rejected.";
        } else {
            $acct_msg = "Account is no longer pending.";
        }
        $stmt->close();
    }

    header("Location: account_registrations.php?acct_status=" . $acct_status . "&acct_msg=" . urlencode($acct_msg));
    exit;
}

// ── Activate / Deactivate Approved Accounts ────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_active"])) {
    $target_id   = (int)($_POST["user_id"] ?? 0);
    $new_active  = (int)($_POST["new_active"] ?? 1);
    $acct_status = "error";
    $acct_msg    = "Unable to update the account.";

    if ($target_id > 0) {
        $stmt = $mysqli->prepare(
            "UPDATE users SET is_active = ? WHERE id = ? AND role <> 'admin' AND is_registered_account = 1"
        );
        $stmt->bind_param("ii", $new_active, $target_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $acct_status = "success";
            $acct_msg    = $new_active ? "Account activated." : "Account deactivated.";
        } else {
            $acct_msg = "No changes were made.";
        }
        $stmt->close();
    }

    header("Location: account_registrations.php?acct_status=" . $acct_status . "&acct_msg=" . urlencode($acct_msg) . "&tab=manage");
    exit;
}

// ── Fetch Pending Users (real registered accounts only) ────────────────────────
$pending_users = [];
$pending_stmt  = $mysqli->prepare(
    "SELECT id, first_name, middle_name, last_name, email, created_at
     FROM users
     WHERE account_status = 'pending'
       AND role <> 'admin'
       AND is_registered_account = 1
     ORDER BY created_at ASC"
);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
while ($row = $pending_result->fetch_assoc()) {
    $pending_users[] = $row;
}
$pending_stmt->close();

// ── Fetch All Non-Admin Approved/Rejected Accounts (real accounts only) ────────
$search_manage = trim($_GET['search_manage'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');

$manage_where  = [
    "account_status <> 'pending'",
    "is_registered_account = 1"
];
$manage_params = [];
$manage_types  = "";

if ($search_manage !== '') {
    $manage_where[]  = "(CONCAT(first_name,' ',COALESCE(middle_name,''),' ',last_name) LIKE ? OR email LIKE ?)";
    $like_s          = "%{$search_manage}%";
    $manage_params[] = $like_s;
    $manage_params[] = $like_s;
    $manage_types   .= "ss";
}
if ($filter_status === 'active') {
    $manage_where[] = "is_active = 1";
} elseif ($filter_status === 'inactive') {
    $manage_where[] = "is_active = 0";
} elseif ($filter_status === 'rejected') {
    $manage_where[] = "account_status = 'rejected'";
}

$manage_sql  = "SELECT id, first_name, middle_name, last_name, email,
                       role, account_status, is_active, created_at
                FROM users
                WHERE " . implode(" AND ", $manage_where) . "
                ORDER BY last_name ASC, first_name ASC";

$manage_stmt = $mysqli->prepare($manage_sql);
if ($manage_types !== '') {
    $manage_stmt->bind_param($manage_types, ...$manage_params);
}
$manage_stmt->execute();
$manage_result    = $manage_stmt->get_result();
$managed_accounts = [];
while ($row = $manage_result->fetch_assoc()) {
    $managed_accounts[] = $row;
}
$manage_stmt->close();

$active_tab = $_GET['tab'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Registrations | Attendance Leave Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* ── Tabs ── */
        .tab-bar {
            display: flex;
            gap: 4px;
            border-bottom: 2px solid var(--border, #e0e0e0);
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 700;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            cursor: pointer;
            color: var(--text-muted, #888);
            border-radius: 6px 6px 0 0;
            transition: color 0.18s, border-color 0.18s, background 0.18s;
        }
        .tab-btn:hover {
            background: #f1f5f9;
            color: var(--navy);
        }
        .tab-btn.active {
            color: var(--navy);
            border-bottom-color: var(--primary, #2563eb);
            background: #f8fafc;
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── Status Badge ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-active   { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-rejected { background: #f3f4f6; color: #6b7280; }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-active   { background: #16a34a; }
        .dot-inactive { background: #dc2626; }
        .dot-rejected { background: #9ca3af; }

        /* ── Filter row ── */
        .manage-filter-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 16px;
        }
        .manage-filter-row .form-group {
            margin: 0;
            flex: 1;
            min-width: 160px;
        }

        /* ── Confirm Modal ── */
        #toggleConfirmModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            align-items: center;
            justify-content: center;
            z-index: 2400;
        }
        #toggleConfirmModal.open { display: flex; }

        /* ── Tab count badge ── */
        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            background: #2563eb;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 9px;
            margin-left: 6px;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="brand">
            <img src="../includes/images.png" alt="COA Logo" class="logo">
            <div>
                <h1>Account Registrations</h1>
                <p class="muted">Approve, reject, or manage account access.</p>
            </div>
        </div>
        <nav class="nav-links">
            <a href="admin.php"   class="btn btn-outline">Back to Admin</a>
            <a href="profile.php" class="btn btn-outline">Profile</a>
            <a href="#"           class="btn btn-outline" id="logoutBtn">Logout</a>
        </nav>
    </header>

    <main class="container">

        <?php if (isset($_GET["acct_status"])): ?>
            <div class="toast toast-<?php echo htmlspecialchars($_GET["acct_status"], ENT_QUOTES, "UTF-8"); ?>" id="acctToast">
                <?php echo htmlspecialchars($_GET["acct_msg"] ?? "", ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <section class="card">

            <!-- ── Tab Bar ── -->
            <div class="tab-bar">
                <button class="tab-btn <?php echo $active_tab === 'pending' ? 'active' : ''; ?>"
                    onclick="switchTab('pending')">
                    Pending Requests
                    <?php if (count($pending_users) > 0): ?>
                        <span class="tab-badge"><?php echo count($pending_users); ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn <?php echo $active_tab === 'manage' ? 'active' : ''; ?>"
                    onclick="switchTab('manage')">
                    Manage Accounts
                    <span class="tab-badge" style="background:#475569;"><?php echo count($managed_accounts); ?></span>
                </button>
            </div>

            <!-- ══ Tab: Pending ══════════════════════════════════════════════════ -->
            <div id="tab-pending" class="tab-panel <?php echo $active_tab === 'pending' ? 'active' : ''; ?>">
                <?php if (count($pending_users) === 0): ?>
                    <div class="muted" style="padding:24px 0; text-align:center;">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#cbd5e0"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                            style="display:block; margin:0 auto 10px;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        No pending registrations at this time.
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Requested</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_users as $pending): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $parts = array_filter([
                                                $pending['first_name'],
                                                $pending['middle_name'],
                                                $pending['last_name']
                                            ]);
                                            echo htmlspecialchars(implode(' ', $parts), ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($pending['email'],                 ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($pending['created_at']     ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td style="white-space:nowrap;">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$pending['id']; ?>">
                                                <button type="submit" name="account_action" value="approve"
                                                    class="btn" style="padding:6px 14px; font-size:12px;">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$pending['id']; ?>">
                                                <button type="submit" name="account_action" value="reject"
                                                    class="btn btn-auth-outline" style="padding:6px 14px; font-size:12px;">
                                                    Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ Tab: Manage Accounts ══════════════════════════════════════════ -->
            <div id="tab-manage" class="tab-panel <?php echo $active_tab === 'manage' ? 'active' : ''; ?>">

                <!-- Filter row -->
                <form method="GET" action="account_registrations.php" class="manage-filter-row">
                    <input type="hidden" name="tab" value="manage">
                    <div class="form-group">
                        <label style="font-size:13px; font-weight:600; color:var(--navy);">Search</label>
                        <input type="text" name="search_manage"
                            value="<?php echo htmlspecialchars($search_manage, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Search by name or email…"
                            style="background:#fff; color:#333; border:1px solid var(--border);">
                    </div>
                    <div class="form-group" style="max-width:180px;">
                        <label style="font-size:13px; font-weight:600; color:var(--navy);">Status</label>
                        <select name="filter_status"
                            style="background:#fff; color:#333; border:1px solid var(--border);">
                            <option value="">All</option>
                            <option value="active"   <?php echo $filter_status === 'active'   ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:8px; align-items:flex-end;">
                        <button type="submit" class="btn" style="padding:10px 18px; font-size:13px;">Filter</button>
                        <a href="account_registrations.php?tab=manage"
                            class="btn btn-auth-outline"
                            style="padding:10px 14px; font-size:13px; text-decoration:none;">Clear</a>
                    </div>
                </form>

                <?php if (count($managed_accounts) === 0): ?>
                    <div class="muted" style="padding:24px 0; text-align:center;">
                        No accounts found matching your filters.
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($managed_accounts as $acc): ?>
                                    <?php
                                    $parts      = array_filter([
                                        $acc['first_name'],
                                        $acc['middle_name'],
                                        $acc['last_name']
                                    ]);
                                    $fullName   = htmlspecialchars(implode(' ', $parts), ENT_QUOTES, 'UTF-8');
                                    $isActive   = (int)$acc['is_active'];
                                    $isRejected = $acc['account_status'] === 'rejected';
                                    $isAdmin    = ($acc['role'] ?? '') === 'admin';
                                    ?>
                                    <tr>
                                        <td style="font-weight:600;"><?php echo $fullName; ?></td>
                                        <td><?php echo htmlspecialchars($acc['email'],                   ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($acc['created_at']     ?? '—',   ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($isAdmin): ?>
                                                <span class="status-badge" style="background:#dbeafe; color:#1d4ed8;">
                                                    <span class="status-dot" style="background:#2563eb;"></span>Admin
                                                </span>
                                            <?php elseif ($isRejected): ?>
                                                <span class="status-badge badge-rejected">
                                                    <span class="status-dot dot-rejected"></span>Rejected
                                                </span>
                                            <?php elseif ($isActive): ?>
                                                <span class="status-badge badge-active">
                                                    <span class="status-dot dot-active"></span>Active
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge badge-inactive">
                                                    <span class="status-dot dot-inactive"></span>Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <?php if ($isAdmin): ?>
                                                <?php if ((int)$_SESSION['user_id'] !== (int)$acc['id']): ?>
                                                    <button type="button"
                                                        class="btn btn-delete"
                                                        style="padding:6px 14px; font-size:12px;"
                                                        onclick="openRevokeAdminModal(
                                                            <?php echo (int)$acc['id']; ?>,
                                                            '<?php echo addslashes($fullName); ?>'
                                                        )">
                                                        Revoke Admin
                                                    </button>
                                                <?php else: ?>
                                                    <span class="muted" style="font-size:12px;">Current admin</span>
                                                <?php endif; ?>
                                            <?php elseif (!$isRejected): ?>
                                                <?php if ($isActive): ?>
                                                    <button type="button"
                                                        class="btn btn-delete"
                                                        style="padding:6px 14px; font-size:12px;"
                                                        onclick="openToggleModal(
                                                            <?php echo (int)$acc['id']; ?>,
                                                            0,
                                                            '<?php echo addslashes($fullName); ?>'
                                                        )">
                                                        Deactivate
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button"
                                                        class="btn"
                                                        style="padding:6px 14px; font-size:12px; background:#16a34a; box-shadow:0 4px 12px rgba(22,163,74,0.2);"
                                                        onclick="openToggleModal(
                                                            <?php echo (int)$acc['id']; ?>,
                                                            1,
                                                            '<?php echo addslashes($fullName); ?>'
                                                        )">
                                                        Activate
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button"
                                                    class="btn btn-auth-outline"
                                                    style="padding:6px 14px; font-size:12px; margin-left:6px;"
                                                    onclick="openGrantAdminModal(
                                                        <?php echo (int)$acc['id']; ?>,
                                                        '<?php echo addslashes($fullName); ?>'
                                                    )">
                                                    Grant Admin
                                                </button>
                                            <?php else: ?>
                                                <span class="muted" style="font-size:12px;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div><!-- /tab-manage -->

        </section>
    </main>

    <!-- ══ Logout Confirm Modal ═════════════════════════════════════════════ -->
    <div id="logoutModal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45);
                align-items:center; justify-content:center; z-index:2600;">
        <div class="card" style="width:380px; max-width:90%; animation:rise 0.25s ease-out; text-align:center;">
            <div style="margin-bottom:16px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                     stroke="var(--navy, #1a2e5a)" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </div>
            <h3 style="margin:0 0 8px; color:var(--navy, #1a2e5a); font-size:18px;">Log out?</h3>
            <p style="margin:0 0 24px; font-size:14px; color:var(--text-muted, #666);">
                You will be returned to the login page.
            </p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button type="button" class="btn btn-auth-outline" id="logoutCancel"
                        style="min-width:110px; border:1px solid var(--border, #ccc);">Cancel</button>
                <a href="logout.php" class="btn"
                   style="min-width:110px; text-decoration:none; text-align:center;">Yes, Log out</a>
            </div>
        </div>
    </div>

    <!-- ══ Activate / Deactivate Confirm Modal ═══════════════════════════════════ -->
    <div id="toggleConfirmModal">
        <div class="card" style="width:400px; max-width:92%; animation:rise 0.25s ease-out; text-align:center;">
            <div id="toggleIconWrap"
                style="width:52px; height:52px; border-radius:50%; display:flex; align-items:center;
                       justify-content:center; margin:0 auto 14px;">
            </div>
            <h3 id="toggleModalTitle" style="margin:0 0 8px; color:var(--navy);"></h3>
            <p  id="toggleModalBody"  style="font-size:14px; color:var(--text-muted,#666); margin:0 0 24px;"></p>
            <form method="POST" id="toggleActiveForm">
                <input type="hidden" name="toggle_active" value="1">
                <input type="hidden" name="user_id"    id="toggleUserId">
                <input type="hidden" name="new_active" id="toggleNewActive">
                <div style="display:flex; gap:12px; justify-content:center;">
                    <button type="button" class="btn btn-auth-outline"
                        onclick="closeToggleModal()"
                        style="min-width:110px; border:1px solid var(--border,#ccc);">
                        Cancel
                    </button>
                    <button type="submit" id="toggleConfirmBtn" class="btn" style="min-width:110px;">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ Grant Admin Confirm Modal ═══════════════════════════════════════ -->
    <div id="grantAdminModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); align-items:center; justify-content:center; z-index:2500;">
        <div class="card" style="width:400px; max-width:92%; animation:rise 0.25s ease-out; text-align:center;">
            <h3 id="grantAdminTitle" style="margin:0 0 8px; color:var(--navy);">Grant admin access?</h3>
            <p id="grantAdminBody" style="font-size:14px; color:var(--text-muted,#666); margin:0 0 20px;"></p>
            <form method="POST" id="grantAdminForm">
                <input type="hidden" name="admin_role_action" id="adminRoleAction" value="grant">
                <input type="hidden" name="user_id" id="grantAdminUserId">
                <div class="form-group" style="text-align:left; margin-bottom:18px;">
                    <label for="confirm_password" style="font-size:13px; font-weight:600; color:var(--navy);">Admin Password</label>
                    <div class="input-with-button">
                        <input type="password" id="confirm_password" name="confirm_password" maxlength="30" required>
                        <button type="button" class="btn btn-auth-outline toggle-password" data-target="confirm_password" aria-label="Toggle password visibility">
                            <svg class="eye-open eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg class="eye-closed eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="display:none;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div style="display:flex; gap:12px; justify-content:center;">
                    <button type="button" class="btn btn-auth-outline" onclick="closeGrantAdminModal()" style="min-width:110px; border:1px solid var(--border,#ccc);">Cancel</button>
                    <button type="submit" id="grantAdminSubmitBtn" class="btn" style="min-width:110px; background:#2563eb;">Grant Access</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ── Tab switching ────────────────────────────────────────────────── */
        function switchTab(tab) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            document.querySelectorAll('.tab-btn').forEach(b => {
                if (b.getAttribute('onclick').includes("'" + tab + "'")) b.classList.add('active');
            });
        }

        /* ── Activate / Deactivate Modal ──────────────────────────────────── */
        function openToggleModal(userId, newActive, name) {
            document.getElementById('toggleUserId').value    = userId;
            document.getElementById('toggleNewActive').value = newActive;

            const iconWrap = document.getElementById('toggleIconWrap');
            const title    = document.getElementById('toggleModalTitle');
            const body     = document.getElementById('toggleModalBody');
            const btn      = document.getElementById('toggleConfirmBtn');

            if (newActive === 1) {
                iconWrap.style.background = '#dcfce7';
                iconWrap.innerHTML = `<svg width="26" height="26" viewBox="0 0 24 24" fill="none"
                    stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/></svg>`;
                title.textContent      = 'Activate account?';
                body.textContent       = name + ' will be able to log in and access the system.';
                btn.style.background   = '#16a34a';
                btn.style.boxShadow    = '0 4px 12px rgba(22,163,74,0.25)';
                btn.textContent        = 'Yes, Activate';
            } else {
                iconWrap.style.background = '#fee2e2';
                iconWrap.innerHTML = `<svg width="26" height="26" viewBox="0 0 24 24" fill="none"
                    stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>`;
                title.textContent      = 'Deactivate account?';
                body.textContent       = name + ' will be blocked from logging in until reactivated.';
                btn.style.background   = '#dc2626';
                btn.style.boxShadow    = '0 4px 12px rgba(220,38,38,0.25)';
                btn.textContent        = 'Yes, Deactivate';
            }

            document.getElementById('toggleConfirmModal').classList.add('open');
        }

        function closeToggleModal() {
            document.getElementById('toggleConfirmModal').classList.remove('open');
        }

        function openLogoutModal(e) {
            if (e) e.preventDefault();
            document.getElementById('logoutModal').style.display = 'flex';
        }

        function closeLogoutModal() {
            document.getElementById('logoutModal').style.display = 'none';
        }

        function openGrantAdminModal(userId, name) {
            document.getElementById('adminRoleAction').value = 'grant';
            document.getElementById('grantAdminUserId').value = userId;
            document.getElementById('grantAdminTitle').textContent = 'Grant admin access?';
            document.getElementById('grantAdminBody').textContent = name + ' will be promoted to admin after password confirmation.';
            document.getElementById('grantAdminSubmitBtn').textContent = 'Grant Access';
            document.getElementById('grantAdminModal').style.display = 'flex';
            document.getElementById('confirm_password').value = '';
            document.getElementById('confirm_password').focus();
        }

        function openRevokeAdminModal(userId, name) {
            document.getElementById('adminRoleAction').value = 'revoke';
            document.getElementById('grantAdminUserId').value = userId;
            document.getElementById('grantAdminTitle').textContent = 'Revoke admin access?';
            document.getElementById('grantAdminBody').textContent = name + ' will be demoted to employee after password confirmation.';
            document.getElementById('grantAdminSubmitBtn').textContent = 'Revoke Access';
            document.getElementById('grantAdminModal').style.display = 'flex';
            document.getElementById('confirm_password').value = '';
            document.getElementById('confirm_password').focus();
        }

        function closeGrantAdminModal() {
            document.getElementById('grantAdminModal').style.display = 'none';
        }

        /* Close on backdrop click */
        document.getElementById('toggleConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeToggleModal();
        });

        document.getElementById('grantAdminModal').addEventListener('click', function(e) {
            if (e.target === this) closeGrantAdminModal();
        });

        document.getElementById('logoutBtn').addEventListener('click', openLogoutModal);
        document.getElementById('logoutCancel').addEventListener('click', closeLogoutModal);
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) closeLogoutModal();
        });

        document.querySelectorAll('#grantAdminModal .toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                if (!target) return;
                target.type = target.type === 'password' ? 'text' : 'password';
                btn.querySelector('.eye-open').style.display  = target.type === 'password' ? '' : 'none';
                btn.querySelector('.eye-closed').style.display = target.type === 'text' ? '' : 'none';
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeToggleModal();
                closeGrantAdminModal();
                closeLogoutModal();
            }
        });

        /* ── Auto-dismiss toasts ──────────────────────────────────────────── */
        document.querySelectorAll('.toast').forEach(t => {
            setTimeout(() => {
                t.style.transition = 'opacity 0.5s';
                t.style.opacity    = '0';
            }, 3500);
        });
    </script>
</body>
</html>