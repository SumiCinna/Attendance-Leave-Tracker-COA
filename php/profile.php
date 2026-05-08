<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_login();

$userId    = $_SESSION["user_id"];
$isAdmin   = ($_SESSION["user_role"] ?? '') === 'admin';
$errors    = [];
$successes = [];

// ── Fetch current user ────────────────────────────────────────────────────────
$stmt = $mysqli->prepare(
    "SELECT first_name, middle_name, last_name, email, contact_number, role
     FROM users WHERE id = ? LIMIT 1"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

function clean($v) { return trim($v ?? ""); }
function e($v)     { return htmlspecialchars($v, ENT_QUOTES, "UTF-8"); }

// ── POST handling ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // ── 1. Update contact number (employee + admin) ───────────────────────────
    if ($action === "update_contact") {
        $raw = clean($_POST["contact_number"] ?? "");

        if ($raw === "") {
            $errors[] = "Contact number is required.";
        } elseif (!preg_match("/^\d{10}$/", $raw)) {
            $errors[] = "Contact number must be exactly 10 digits after +63.";
        } else {
            $stored = '+63' . $raw;
            $stmt   = $mysqli->prepare("UPDATE users SET contact_number = ? WHERE id = ?");
            $stmt->bind_param("si", $stored, $userId);
            $stmt->execute();
            $stmt->close();
            $user["contact_number"] = $stored;
            $successes[] = "Contact number updated successfully.";
        }
    }

    // ── 2. Change password (employee + admin) ────────────────────────────────
    if ($action === "change_password") {
        $current  = $_POST["current_password"]  ?? "";
        $new      = $_POST["new_password"]       ?? "";
        $confirm  = $_POST["confirm_password"]   ?? "";

        // Fetch stored hash
        $stmt = $mysqli->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($current, $row["password_hash"])) {
            $errors[] = "Current password is incorrect.";
        } elseif (strlen($new) < 8 || strlen($new) > 30) {
            $errors[] = "New password must be between 8 and 30 characters.";
        } elseif (!preg_match("/[A-Z]/", $new) || !preg_match("/[a-z]/", $new) || !preg_match("/[0-9]/", $new)) {
            $errors[] = "New password must include at least one uppercase letter, one lowercase letter, and one number.";
        } elseif ($new !== $confirm) {
            $errors[] = "New password and confirmation do not match.";
        } elseif (password_verify($new, $row["password_hash"])) {
            $errors[] = "New password must differ from your current password.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $userId);
            $stmt->execute();
            $stmt->close();
            $successes[] = "Password changed successfully.";
        }
    }

    // ── 3. Admin-only: edit name & email ─────────────────────────────────────
    if ($action === "admin_edit_profile" && $isAdmin) {
        $first  = clean($_POST["first_name"]  ?? "");
        $middle = clean($_POST["middle_name"] ?? "");
        $last   = clean($_POST["last_name"]   ?? "");
        $email  = clean($_POST["email"]       ?? "");
        $namePattern = "/^[a-zA-Z\s\-']+$/";

        foreach (["first_name" => [$first,"First name"], "middle_name" => [$middle,"Middle name"], "last_name" => [$last,"Last name"]] as [$val, $label]) {
            if ($val === "")                              $errors[] = "$label is required.";
            elseif (strlen($val) > 100)                  $errors[] = "$label must be 100 characters or less.";
            elseif (!preg_match($namePattern, $val))     $errors[] = "$label can only contain letters, spaces, hyphens, and apostrophes.";
        }

        if ($email === "") {
            $errors[] = "Gmail is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Enter a valid email address.";
        } elseif (!preg_match("/@gmail\.com$/", $email)) {
            $errors[] = "Email must be a @gmail.com address.";
        } else {
            // Check duplicate email (exclude self)
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = "That Gmail is already in use by another account.";
            $stmt->close();
        }

        if (!$errors) {
            $stmt = $mysqli->prepare(
                "UPDATE users SET first_name=?, middle_name=?, last_name=?, email=? WHERE id=?"
            );
            $stmt->bind_param("ssssi", $first, $middle, $last, $email, $userId);
            $stmt->execute();
            $stmt->close();

            // Refresh session name
            $_SESSION["user_name"] = $first . ' ' . $last;
            $user["first_name"]  = $first;
            $user["middle_name"] = $middle;
            $user["last_name"]   = $last;
            $user["email"]       = $email;

            $successes[] = "Profile information updated successfully.";
        }
    }
}

// Strip +63 for display in input
$contactDigits = preg_replace('/^\+63/', '', $user["contact_number"] ?? "");
$fullName = e($user["first_name"]) . ' ' . e($user["middle_name"]) . ' ' . e($user["last_name"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Attendance Leave Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .profile-grid          { display:grid; gap:24px; grid-template-columns:1fr; max-width:720px; margin:0 auto; margin}
        .readonly-field        { background:var(--input-bg,#f5f5f5); border:1px solid var(--border,#ddd); border-radius:8px; padding:10px 14px; font-size:15px; color:var(--text-muted,#666); }
        .section-label         { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--text-muted,#888); margin-bottom:16px; }
        .card + .card          { margin-top:0; }
        .badge-role            { display:inline-block; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; background:var(--accent,#1a56db); color:#fff; margin-left:8px; vertical-align:middle; }
        .profile-grid .form .btn { margin-top: 12px; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="brand">
            <img src="../includes/images.png" alt="COA Logo" class="logo">
            <div>
                <h1>My Profile</h1>
                <p class="muted">Manage your account settings</p>
            </div>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php" class="btn btn-outline">← Dashboard</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" class="btn">Admin View</a>
            <?php endif; ?>
            <a href="#" class="btn btn-outline" id="logoutBtn">Logout</a>
        </nav>
    </header>

    <main class="container">
        <?php foreach ($successes as $s): ?>
            <div class="alert alert-success"><?php echo e($s); ?></div>
        <?php endforeach; ?>
        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?></ul>
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

        <div class="profile-grid">

            <!-- ── Card 1: Identity (view-only for employees, editable for admin) ── -->
            <section class="card">
                <p class="section-label">
                    Account Information
                    <span class="badge-role"><?php echo e(ucfirst($user["role"])); ?></span>
                </p>

                <?php if ($isAdmin): ?>
                    <!-- Admin can edit name & email -->
                    <form method="POST" class="form" novalidate>
                        <input type="hidden" name="action" value="admin_edit_profile">
                        <div class="two-col-group">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" maxlength="100"
                                       value="<?php echo e($user['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" maxlength="100"
                                       value="<?php echo e($user['middle_name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" maxlength="100"
                                   value="<?php echo e($user['last_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Gmail</label>
                            <input type="email" id="email" name="email"
                                   value="<?php echo e($user['email']); ?>" required>
                        </div>
                        <button type="submit" class="btn">Save Changes</button>
                    </form>
                <?php else: ?>
                    <!-- Employee: read-only -->
                    <div class="form-group">
                        <label>Full Name</label>
                        <div class="readonly-field"><?php echo $fullName; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Gmail</label>
                        <div class="readonly-field"><?php echo e($user['email']); ?></div>
                    </div>
                    <p class="hint" style="margin-top:8px;">
                        Name and Gmail can only be changed by an administrator.
                    </p>
                <?php endif; ?>
            </section>

            <!-- ── Card 2: Contact Number ─────────────────────────────────────── -->
            <section class="card">
                <p class="section-label">Contact Number</p>
                <form method="POST" class="form" novalidate>
                    <input type="hidden" name="action" value="update_contact">
                    <div class="form-group">
                        <label for="contact_number">Mobile Number</label>
                        <div class="input-prefix-wrap">
                            <span class="input-prefix">+63</span>
                            <input type="text" id="contact_number" name="contact_number"
                                   maxlength="10" inputmode="numeric" pattern="\d{10}"
                                   placeholder="9XXXXXXXXX"
                                   value="<?php echo e($contactDigits); ?>" required>
                        </div>
                        <div class="hint">10 digits after +63</div>
                    </div>
                    <button type="submit" class="btn">Update Number</button>
                </form>
            </section>

            <!-- ── Card 3: Change Password ────────────────────────────────────── -->
            <section class="card">
                <p class="section-label">Change Password</p>
                <form method="POST" class="form" id="passwordForm" novalidate>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-with-button">
                            <input type="password" id="current_password" name="current_password" maxlength="30" required>
                            <button type="button" class="btn btn-auth-outline toggle-password"
                                    data-target="current_password" aria-label="Toggle">
                                <?php echo eye_svg(); ?>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-with-button">
                            <input type="password" id="new_password" name="new_password" maxlength="30" required>
                            <button type="button" class="btn btn-auth-outline toggle-password"
                                    data-target="new_password" aria-label="Toggle">
                                <?php echo eye_svg(); ?>
                            </button>
                        </div>
                        <ul class="hint-list" style="margin-top:6px;">
                            <li id="passLength">8-30 characters</li>
                            <li id="passUpper">One uppercase letter</li>
                            <li id="passLower">One lowercase letter</li>
                            <li id="passNumber">One number</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-with-button">
                            <input type="password" id="confirm_password" name="confirm_password" maxlength="30" required>
                            <button type="button" class="btn btn-auth-outline toggle-password"
                                    data-target="confirm_password" aria-label="Toggle">
                                <?php echo eye_svg(); ?>
                            </button>
                        </div>
                        <div class="hint" id="confirmHint"></div>
                    </div>

                    <button type="submit" class="btn">Change Password</button>
                </form>
            </section>

        </div><!-- /profile-grid -->
        
    </main>

    <script>
    // ── Digits-only contact input ────────────────────────────────────────────
    document.getElementById('contact_number').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });

    // ── Password strength hints ──────────────────────────────────────────────
    const newPass    = document.getElementById('new_password');
    const confirmPass = document.getElementById('confirm_password');
    const hints = {
        passLength : /^.{8,30}$/,
        passUpper  : /[A-Z]/,
        passLower  : /[a-z]/,
        passNumber : /[0-9]/,
    };
    if (newPass) {
        newPass.addEventListener('input', () => {
            const val = newPass.value;
            for (const [id, rx] of Object.entries(hints)) {
                const el = document.getElementById(id);
                if (!el) continue;
                el.style.color = rx.test(val) ? 'var(--color-success, green)' : '';
            }
            checkConfirm();
        });
    }
    function checkConfirm() {
        const hint = document.getElementById('confirmHint');
        if (!hint || !confirmPass) return;
        if (confirmPass.value === '') { hint.textContent = ''; return; }
        if (newPass.value === confirmPass.value) {
            hint.textContent = '✓ Passwords match';
            hint.style.color = 'var(--color-success, green)';
        } else {
            hint.textContent = '✗ Passwords do not match';
            hint.style.color = 'var(--color-danger, red)';
        }
    }
    if (confirmPass) confirmPass.addEventListener('input', checkConfirm);

    // ── Toggle password visibility ───────────────────────────────────────────
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            target.type = target.type === 'password' ? 'text' : 'password';
            btn.querySelector('.eye-open').style.display  = target.type === 'password' ? '' : 'none';
            btn.querySelector('.eye-closed').style.display = target.type === 'text'    ? '' : 'none';
        });
    });
    
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
</body>
</html>

<?php
// Helper: reusable eye SVG pair
function eye_svg() {
    return '
    <svg class="eye-open eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
    </svg>
    <svg class="eye-closed eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="display:none;">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
    </svg>';
}
?>