<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["account_action"])) {
    $target_id = (int)($_POST["user_id"] ?? 0);
    $action = $_POST["account_action"] ?? "";
    $acct_status = "error";
    $acct_msg = "Unable to update the account.";

    if ($target_id > 0 && ($action === "approve" || $action === "reject")) {
        $new_status = $action === "approve" ? "approved" : "rejected";
        $stmt = $mysqli->prepare(
            "UPDATE users SET account_status = ?
             WHERE id = ? AND role <> 'admin' AND account_status = 'pending'"
        );
        $stmt->bind_param("si", $new_status, $target_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $acct_status = "success";
            $acct_msg = $action === "approve" ? "Account approved." : "Account rejected.";
        } else {
            $acct_msg = "Account is no longer pending.";
        }
        $stmt->close();
    }

    header("Location: account_registrations.php?acct_status=" . $acct_status . "&acct_msg=" . urlencode($acct_msg));
    exit;
}

$pending_users = [];
$pending_stmt = $mysqli->prepare(
    "SELECT id, first_name, middle_name, last_name, email, contact_number, created_at
     FROM users
     WHERE account_status = 'pending' AND role <> 'admin'
     ORDER BY created_at ASC"
);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
while ($row = $pending_result->fetch_assoc()) {
    $pending_users[] = $row;
}
$pending_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Registrations | Attendance Leave Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <header class="site-header">
        <div class="brand">
            <img src="../includes/images.png" alt="COA Logo" class="logo">
            <div>
                <h1>Pending Account Registrations</h1>
                <p class="muted">Approve or reject newly registered accounts.</p>
            </div>
        </div>
        <nav class="nav-links">
            <a href="admin.php" class="btn btn-outline">Back to Admin</a>
            <a href="profile.php" class="btn btn-outline">Profile</a>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        </nav>
    </header>

    <main class="container">
        <?php if (isset($_GET["acct_status"])): ?>
            <div class="toast toast-<?php echo htmlspecialchars($_GET["acct_status"], ENT_QUOTES, "UTF-8"); ?>" id="acctToast">
                <?php echo htmlspecialchars($_GET["acct_msg"] ?? "", ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <?php if (count($pending_users) === 0): ?>
                <div class="muted" style="padding:12px 0;">No pending registrations.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_users as $pending): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $full = trim($pending['first_name'] . " " . $pending['middle_name'] . " " . $pending['last_name']);
                                        echo htmlspecialchars($full, ENT_QUOTES, "UTF-8");
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($pending['email'], ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($pending['contact_number'] ?? '—', ENT_QUOTES, "UTF-8"); ?></td>
                                    <td><?php echo htmlspecialchars($pending['created_at'] ?? '—', ENT_QUOTES, "UTF-8"); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$pending['id']; ?>">
                                            <button type="submit" name="account_action" value="approve" class="btn" style="padding:6px 12px;">Approve</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$pending['id']; ?>">
                                            <button type="submit" name="account_action" value="reject" class="btn btn-auth-outline" style="padding:6px 12px;">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
