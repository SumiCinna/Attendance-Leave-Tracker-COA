<?php
// this code/file is not used // 
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_login();

$status = $_GET["status"] ?? "";
$message = "";
if ($status === "saved") {
    $message = "Leave request submitted.";
} elseif ($status === "error") {
    $message = "Unable to submit leave request.";
}

$userId = $_SESSION["user_id"];
$stmt = $mysqli->prepare("SELECT leave_date, leave_type, status, admin_remarks, created_at FROM absences WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Attendance Leave Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="brand">
            <img src="../includes/images.png" alt="COA Logo" class="logo">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION["user_name"], ENT_QUOTES, "UTF-8"); ?></h1>
                <p class="muted">Submit absences or leave requests.</p>
            </div>
        </div>
        <nav class="nav-links">
            <?php if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === 'admin'): ?>
                <a href="admin.php" class="btn">Admin View</a>
            <?php endif; ?>
           <a href="profile.php" class="btn btn-outline">Profile</a>
           <a href="#" class="btn btn-outline" id="logoutBtn">Logout</a>
           
        </nav>
    </header>

    <main class="container">
        <?php if ($message): ?>
            <div class="alert <?php echo $status === "saved" ? "alert-success" : "alert-error"; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>
        
            <div id="logoutModal"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45);
            align-items:center; justify-content:center; z-index:2000;">
    <div class="card"
         style="width:380px; max-width:90%; animation:rise 0.25s ease-out; text-align:center;">

        <!-- Icon -->
        <div style="margin-bottom:16px;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                 stroke="var(--navy, #1a2e5a)" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round"
                 style="display:inline-block;">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </div>

        <h3 style="margin:0 0 8px; color:var(--navy, #1a2e5a); font-size:18px;">
            Log out?
        </h3>
        <p style="margin:0 0 24px; font-size:14px; color:var(--text-muted, #666);">
            You will be returned to the login page.
        </p>

        <div style="display:flex; gap:12px; justify-content:center;">
            <button type="button" class="btn btn-auth-outline"
                    id="logoutCancel"
                    style="min-width:110px; border:1px solid var(--border, #ccc);">
                Cancel
            </button>
            
            <a href="logout.php" class="btn"
               style="min-width:110px; text-decoration:none; text-align:center;">
                Yes, Log out
            </a>
        </div>
    </div>
</div>  

        <section class="card form-card">
            <h2>Submit Leave / Absence</h2>
            <form method="POST" action="submit_leave.php" class="form" novalidate>
                <div class="form-group">
                    <label for="leave_date">Date</label>
                    <input type="date" id="leave_date" name="leave_date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="leave_type">Leave Type</label>
                    <select id="leave_type" name="leave_type" required>
                        <option value="">Select leave type</option>
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
                <button type="submit" class="btn">Submit Request</button>
            </form>
        </section>

        <section class="card">
            <h2>Your Leave History</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status/Remarks</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records->num_rows === 0): ?>
                            <tr>
                                <td colspan="4" class="muted">No requests yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = $records->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["leave_date"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($row["leave_type"], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td>
                                        <?php if ($row["status"] === "Approved"): ?>
                                            <span class="badge badge-green" style="margin-bottom:4px;">Approved</span>
                                        <?php elseif ($row["status"] === "Rejected"): ?>
                                            <span class="badge badge-red" style="margin-bottom:4px;">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge badge-gray" style="margin-bottom:4px;">Pending</span>
                                        <?php endif; ?>
                                        <div style="font-size: 12px; color: var(--text-muted);">
                                            <?php if (!empty($row["admin_remarks"])): ?>
                                                <em>Note: <?php echo htmlspecialchars($row["admin_remarks"], ENT_QUOTES, "UTF-8"); ?></em>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row["created_at"], ENT_QUOTES, "UTF-8"); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
             </section>
    
<script>
(function () {
    const modal  = document.getElementById('logoutModal');
    const btn    = document.getElementById('logoutBtn');
    const cancel = document.getElementById('logoutCancel');

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        modal.style.display = 'flex';
    });

    cancel.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    // Close on backdrop click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.style.display = 'none';
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    });
})();
</script>
    </main>
</body>
</html>
