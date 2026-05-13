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

// ── OTP state ─────────────────────────────────────────────────────────────────
$showOtpModal = false;
$otpPlain     = null;   // passed to JS only when freshly generated

// ── POST handling ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // ── 1. Update contact number ──────────────────────────────────────────────
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

    // ── 2. Change password ────────────────────────────────────────────────────
    if ($action === "change_password") {
        $current = $_POST["current_password"]  ?? "";
        $new     = $_POST["new_password"]       ?? "";
        $confirm = $_POST["confirm_password"]   ?? "";

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

    // ── 3. Admin: edit profile — email change triggers OTP ───────────────────
    if ($action === "admin_edit_profile" && $isAdmin) {
        $first  = clean($_POST["first_name"]  ?? "");
        $middle = clean($_POST["middle_name"] ?? "");
        $last   = clean($_POST["last_name"]   ?? "");
        $email  = clean($_POST["email"]       ?? "");
        $namePattern = "/^[a-zA-Z\s\-']+$/";

        foreach ([
            "first_name"  => [$first,  "First name"],
            "middle_name" => [$middle, "Middle name"],
            "last_name"   => [$last,   "Last name"],
        ] as [$val, $label]) {
            if ($val === "")                          $errors[] = "$label is required.";
            elseif (strlen($val) > 100)               $errors[] = "$label must be 100 characters or less.";
            elseif (!preg_match($namePattern, $val))  $errors[] = "$label can only contain letters, spaces, hyphens, and apostrophes.";
        }

        if ($email === "") {
            $errors[] = "Gmail is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Enter a valid email address.";
        } elseif (!preg_match("/@gmail\.com$/", $email)) {
            $errors[] = "Email must be a @gmail.com address.";
        } else {
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = "That Gmail is already in use by another account.";
            $stmt->close();
        }

        if (!$errors) {
            // ── Email changed → send OTP to current email ──────────────────
            if ($email !== $user["email"]) {
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                // Store everything in session; hash OTP for security
                $_SESSION["pending_otp"]    = password_hash($otp, PASSWORD_DEFAULT);
                $_SESSION["pending_email"]  = $email;
                $_SESSION["pending_first"]  = $first;
                $_SESSION["pending_middle"] = $middle;
                $_SESSION["pending_last"]   = $last;
                $_SESSION["otp_expires"]    = time() + 600; // 10 minutes

                $showOtpModal = true;
                $otpPlain     = $otp; // JS will use this to send via EmailJS
            } else {
                // No email change — save name immediately
                $stmt = $mysqli->prepare(
                    "UPDATE users SET first_name=?, middle_name=?, last_name=? WHERE id=?"
                );
                $stmt->bind_param("sssi", $first, $middle, $last, $userId);
                $stmt->execute();
                $stmt->close();

                $_SESSION["user_name"]   = $first . ' ' . $last;
                $user["first_name"]  = $first;
                $user["middle_name"] = $middle;
                $user["last_name"]   = $last;
                $successes[] = "Profile information updated successfully.";
            }
        }
    }

    // ── 4. Resend OTP request ────────────────────────────────────────────────
    if ($action === "resend_otp" && $isAdmin) {
        if (!empty($_SESSION["pending_otp"])) {
            // Re-generate OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION["pending_otp"]  = password_hash($otp, PASSWORD_DEFAULT);
            $_SESSION["otp_expires"]  = time() + 600;
            $showOtpModal = true;
            $otpPlain     = $otp;
        }
    }

    // ── 5. Verify OTP and save email ─────────────────────────────────────────
    if ($action === "verify_email_otp" && $isAdmin) {
        $entered = clean($_POST["otp_code"] ?? "");

        if (empty($_SESSION["pending_otp"]) || empty($_SESSION["pending_email"])) {
            $errors[] = "Verification session expired. Please try again.";
        } elseif (time() > ($_SESSION["otp_expires"] ?? 0)) {
            // Expired — clean up
            unset($_SESSION["pending_otp"], $_SESSION["pending_email"],
                  $_SESSION["pending_first"], $_SESSION["pending_middle"],
                  $_SESSION["pending_last"], $_SESSION["otp_expires"]);
            $errors[] = "OTP has expired. Please submit the form again.";
        } elseif (!preg_match("/^\d{6}$/", $entered)) {
            $errors[]     = "Please enter the 6-digit code sent to your current email.";
            $showOtpModal = true;
        } elseif (!password_verify($entered, $_SESSION["pending_otp"])) {
            $errors[]     = "Incorrect OTP. Please try again.";
            $showOtpModal = true;
        } else {
            // ✅ OTP correct — save all pending changes
            $newEmail  = $_SESSION["pending_email"];
            $newFirst  = $_SESSION["pending_first"];
            $newMiddle = $_SESSION["pending_middle"];
            $newLast   = $_SESSION["pending_last"];

            $stmt = $mysqli->prepare(
                "UPDATE users SET first_name=?, middle_name=?, last_name=?, email=? WHERE id=?"
            );
            $stmt->bind_param("ssssi", $newFirst, $newMiddle, $newLast, $newEmail, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION["user_name"] = $newFirst . ' ' . $newLast;
            $user["first_name"]  = $newFirst;
            $user["middle_name"] = $newMiddle;
            $user["last_name"]   = $newLast;
            $user["email"]       = $newEmail;

            unset($_SESSION["pending_otp"], $_SESSION["pending_email"],
                  $_SESSION["pending_first"], $_SESSION["pending_middle"],
                  $_SESSION["pending_last"], $_SESSION["otp_expires"]);

            $successes[] = "Email and profile updated successfully.";
        }
    }
}

// Strip +63 for display
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
        .profile-grid   { display:grid; gap:24px; grid-template-columns:1fr; max-width:720px; margin:0 auto; }
        .readonly-field { background:var(--input-bg,#f5f5f5); border:1px solid var(--border,#ddd); border-radius:8px; padding:10px 14px; font-size:15px; color:var(--text-muted,#666); }
        .section-label  { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--text-muted,#888); margin-bottom:16px; }
        .badge-role     { display:inline-block; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; background:var(--accent,#1a56db); color:#fff; margin-left:8px; vertical-align:middle; }

        /* ── OTP Modal ─────────────────────────────────────────────────────── */
        .otp-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 3000;
            backdrop-filter: blur(3px);
        }
        .otp-modal-backdrop.open { display: flex; }

        .otp-modal {
            background: #fff;
            border-radius: 16px;
            padding: 36px 32px 28px;
            width: 420px;
            max-width: 92vw;
            box-shadow: 0 24px 60px rgba(13,27,75,0.18);
            animation: otpRise 0.28s cubic-bezier(.22,1,.36,1);
            text-align: center;
        }
        @keyframes otpRise {
            from { opacity:0; transform:translateY(22px) scale(0.97); }
            to   { opacity:1; transform:translateY(0)    scale(1); }
        }

        .otp-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #e8f0fe, #d1e3ff);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
        }
        .otp-icon svg { width:28px; height:28px; color: var(--navy, #1a2e5a); stroke: var(--navy, #1a2e5a); }

        .otp-modal h3 {
            margin: 0 0 6px;
            font-size: 20px;
            color: var(--navy, #1a2e5a);
            font-weight: 700;
        }
        .otp-modal p.otp-sub {
            font-size: 14px;
            color: var(--text-muted, #666);
            margin: 0 0 24px;
            line-height: 1.5;
        }
        .otp-modal p.otp-sub strong { color: var(--navy, #1a2e5a); }

        /* OTP digit boxes */
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        .otp-digit {
            width: 50px; height: 58px;
            border: 2px solid var(--border, #dde1ea);
            border-radius: 10px;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            color: var(--navy, #1a2e5a);
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            background: #fafbff;
        }
        .otp-digit:focus {
            border-color: var(--accent, #1a56db);
            box-shadow: 0 0 0 3px rgba(26,86,219,0.12);
            background: #fff;
        }
        .otp-digit.filled { border-color: var(--navy, #1a2e5a); }

        /* Hidden combined input (submitted) */
        #otpCodeHidden { display: none; }

        .otp-error {
            font-size: 13px;
            color: #dc2626;
            margin: -10px 0 14px;
            min-height: 18px;
        }

        .otp-modal .btn-verify {
            width: 100%;
            padding: 13px;
            font-size: 15px;
            margin-bottom: 16px;
            border-radius: 10px;
        }

        .otp-footer {
            font-size: 13px;
            color: var(--text-muted, #888);
        }
        .otp-footer a, .otp-footer button.resend-btn {
            color: var(--accent, #1a56db);
            font-weight: 600;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            font-size: 13px;
            text-decoration: underline;
        }
        .otp-footer button.resend-btn:disabled {
            color: var(--text-muted, #aaa);
            cursor: not-allowed;
            text-decoration: none;
        }
        #otpTimer { font-weight: 600; color: var(--navy, #1a2e5a); }

        .otp-sending {
            font-size: 13px;
            color: var(--text-muted, #666);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .otp-sending .spinner {
            width: 14px; height: 14px;
            border: 2px solid #dde1ea;
            border-top-color: var(--accent, #1a56db);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
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
        <?php if ($errors && !$showOtpModal): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <!-- ── Logout Modal ─────────────────────────────────────────────────── -->
        <div id="logoutModal"
             style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45);
                    align-items:center; justify-content:center; z-index:2000;">
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

        <!-- ── OTP Verification Modal ────────────────────────────────────────── -->
        <div class="otp-modal-backdrop" id="otpModalBackdrop">
            <div class="otp-modal" role="dialog" aria-modal="true" aria-labelledby="otpModalTitle">

                <div class="otp-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                </div>

                <h3 id="otpModalTitle">Verify Your Email Change</h3>
                <p class="otp-sub">
                    A 6-digit code was sent to your <strong>current email</strong>.<br>
                    Enter it below to confirm the change.
                </p>

                <div class="otp-sending" id="otpSendingStatus" style="display:none;">
                    <div class="spinner"></div>
                    <span>Sending code to your email&hellip;</span>
                </div>

                <?php if ($showOtpModal && $errors): ?>
                    <div class="otp-error"><?php echo e($errors[0]); ?></div>
                <?php else: ?>
                    <div class="otp-error" id="otpClientError"></div>
                <?php endif; ?>

                <!-- OTP form — submits verify_email_otp action -->
                <form method="POST" id="otpVerifyForm" novalidate>
                    <input type="hidden" name="action" value="verify_email_otp">
                    <input type="hidden" name="otp_code" id="otpCodeHidden">

                    <div class="otp-inputs" id="otpInputsRow" aria-label="Enter 6-digit OTP">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input type="text" inputmode="numeric" maxlength="1"
                                   class="otp-digit" data-index="<?php echo $i; ?>"
                                   autocomplete="<?php echo $i === 0 ? 'one-time-code' : 'off'; ?>"
                                   aria-label="Digit <?php echo $i + 1; ?>">
                        <?php endfor; ?>
                    </div>

                    <button type="submit" class="btn btn-verify">Verify &amp; Save Changes</button>
                </form>

                <div class="otp-footer">
                    Didn't receive a code?
                    <button class="resend-btn" id="resendBtn" disabled>
                        Resend (<span id="otpTimer">60</span>s)
                    </button>
                </div>

                <!-- Hidden resend form -->
                <form method="POST" id="resendForm" style="display:none;">
                    <input type="hidden" name="action" value="resend_otp">
                </form>
            </div>
        </div>

        <div class="profile-grid">

            <!-- ── Card 1: Identity ────────────────────────────────────────── -->
            <section class="card">
                <p class="section-label">
                    Account Information
                    <span class="badge-role"><?php echo e(ucfirst($user["role"])); ?></span>
                </p>

                <?php if ($isAdmin): ?>
                    <form method="POST" class="form" id="profileForm" novalidate>
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
                            <div class="hint" style="margin-top:4px;">
                                Changing your Gmail will require OTP verification sent to your current email.
                            </div>
                        </div>
                        <button type="submit" class="btn">Save Changes</button>
                    </form>
                <?php else: ?>
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

            <!-- ── Card 2: Contact Number ──────────────────────────────────── -->
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

            <!-- ── Card 3: Change Password ─────────────────────────────────── -->
            <section class="card">
                <p class="section-label">Change Password</p>
                <form method="POST" class="form" id="passwordForm" novalidate>
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-with-button">
                            <input type="password" id="current_password" name="current_password" maxlength="30" required>
                            <button type="button" class="btn btn-auth-outline toggle-password"
                                    data-target="current_password" aria-label="Toggle"><?php echo eye_svg(); ?></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-with-button">
                            <input type="password" id="new_password" name="new_password" maxlength="30" required>
                            <button type="button" class="btn btn-auth-outline toggle-password"
                                    data-target="new_password" aria-label="Toggle"><?php echo eye_svg(); ?></button>
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
                                    data-target="confirm_password" aria-label="Toggle"><?php echo eye_svg(); ?></button>
                        </div>
                        <div class="hint" id="confirmHint"></div>
                    </div>
                    <button type="submit" class="btn" style="margin-top: 20px;">Change Password</button>
                </form>
            </section>
        </div>
    </main>

    <!-- EmailJS SDK -->
    <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <script>
    // ════════════════════════════════════════════════════════════════════════
    // EmailJS config — replace these with your actual credentials
    // ════════════════════════════════════════════════════════════════════════
    const EMAILJS_PUBLIC_KEY  = "caPpuVrkPsXy7ffiv";        // from EmailJS account
    const EMAILJS_SERVICE_ID  = "service_ecq5q27";        // e.g. "service_xxxxxxx"
    const EMAILJS_TEMPLATE_ID = "template_vskitfz";   // OTP email template ID

    emailjs.init({ publicKey: EMAILJS_PUBLIC_KEY });

    // ── OTP state from PHP ────────────────────────────────────────────────
    const SHOW_OTP_MODAL  = <?php echo $showOtpModal ? 'true' : 'false'; ?>;
    const OTP_PLAIN       = <?php echo $otpPlain !== null ? json_encode($otpPlain) : 'null'; ?>;
    const CURRENT_EMAIL   = <?php echo json_encode($user["email"]); ?>;
    const CURRENT_NAME    = <?php echo json_encode($user["first_name"]); ?>;

    // ── OTP Modal elements ────────────────────────────────────────────────
    const otpBackdrop    = document.getElementById('otpModalBackdrop');
    const otpDigits      = document.querySelectorAll('.otp-digit');
    const otpHidden      = document.getElementById('otpCodeHidden');
    const otpVerifyForm  = document.getElementById('otpVerifyForm');
    const otpClientError = document.getElementById('otpClientError');
    const resendBtn      = document.getElementById('resendBtn');
    const timerEl        = document.getElementById('otpTimer');
    const sendingStatus  = document.getElementById('otpSendingStatus');

    let countdown = null;

    // ── Open modal & send OTP via EmailJS ────────────────────────────────
    function openOtpModal(otpCode) {
        otpBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        otpDigits.forEach(d => { d.value = ''; d.classList.remove('filled'); });
        if (otpClientError) otpClientError.textContent = '';

        if (otpCode) {
            sendOtpEmail(otpCode);
        }

        startTimer(60);
        // Focus first digit
        setTimeout(() => otpDigits[0]?.focus(), 300);
    }

    function closeOtpModal() {
        otpBackdrop.classList.remove('open');
        document.body.style.overflow = '';
        clearInterval(countdown);
    }

    function sendOtpEmail(otpCode) {
        sendingStatus.style.display = 'flex';

        emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_TEMPLATE_ID, {
            to_email : CURRENT_EMAIL,       // {{to_email}} in your template
            name     : CURRENT_NAME,        // {{name}}
            otp_code : otpCode,             // {{otp_code}}
            message  : `Your verification code is: ${otpCode}. It expires in 10 minutes.`
        }).then(() => {
            sendingStatus.style.display = 'none';
        }).catch(err => {
            sendingStatus.style.display = 'none';
            console.error('EmailJS error:', err);
            if (otpClientError) otpClientError.textContent = 'Failed to send code. Please use Resend.';
        });
    }

    // ── Countdown timer ───────────────────────────────────────────────────
    function startTimer(seconds) {
        clearInterval(countdown);
        resendBtn.disabled = true;
        let remaining = seconds;
        timerEl.textContent = remaining;

        countdown = setInterval(() => {
            remaining--;
            timerEl.textContent = remaining;
            if (remaining <= 0) {
                clearInterval(countdown);
                resendBtn.disabled = false;
                resendBtn.innerHTML = 'Resend code';
            }
        }, 1000);
    }

    // ── OTP digit input UX ────────────────────────────────────────────────
    otpDigits.forEach((input, idx) => {
        input.addEventListener('input', (e) => {
            // Allow only digits
            input.value = input.value.replace(/\D/g, '').slice(-1);
            input.classList.toggle('filled', input.value !== '');

            if (input.value && idx < otpDigits.length - 1) {
                otpDigits[idx + 1].focus();
            }
            syncHidden();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && idx > 0) {
                otpDigits[idx - 1].focus();
                otpDigits[idx - 1].value = '';
                otpDigits[idx - 1].classList.remove('filled');
                syncHidden();
            }
            // Allow arrow keys
            if (e.key === 'ArrowLeft' && idx > 0)                    otpDigits[idx - 1].focus();
            if (e.key === 'ArrowRight' && idx < otpDigits.length - 1) otpDigits[idx + 1].focus();
        });

        // Handle paste on first digit
        input.addEventListener('paste', (e) => {
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            if (pasted.length >= 6) {
                e.preventDefault();
                [...pasted.slice(0, 6)].forEach((ch, i) => {
                    otpDigits[i].value = ch;
                    otpDigits[i].classList.add('filled');
                });
                otpDigits[5].focus();
                syncHidden();
            }
        });
    });

    function syncHidden() {
        otpHidden.value = [...otpDigits].map(d => d.value).join('');
    }

    // ── Form submit validation ────────────────────────────────────────────
    otpVerifyForm.addEventListener('submit', (e) => {
        syncHidden();
        if (otpHidden.value.length < 6) {
            e.preventDefault();
            if (otpClientError) otpClientError.textContent = 'Please enter all 6 digits.';
            otpDigits[otpHidden.value.length]?.focus();
        }
    });

    // ── Resend button ─────────────────────────────────────────────────────
    resendBtn.addEventListener('click', () => {
        // Submit the resend form to regenerate OTP in session
        document.getElementById('resendForm').submit();
    });

    // ── Auto-open if PHP flagged it ───────────────────────────────────────
    if (SHOW_OTP_MODAL) {
        openOtpModal(OTP_PLAIN); // OTP_PLAIN is null on re-render after wrong code
    }

    // ── Digits-only contact input ────────────────────────────────────────
    document.getElementById('contact_number').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });

    // ── Password strength hints ───────────────────────────────────────────
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
                if (el) el.style.color = rx.test(val) ? 'var(--color-success, green)' : '';
            }
            checkConfirm();
        });
    }
    function checkConfirm() {
        const hint = document.getElementById('confirmHint');
        if (!hint || !confirmPass) return;
        if (!confirmPass.value) { hint.textContent = ''; return; }
        if (newPass.value === confirmPass.value) {
            hint.textContent = '✓ Passwords match';
            hint.style.color = 'var(--color-success, green)';
        } else {
            hint.textContent = '✗ Passwords do not match';
            hint.style.color = 'var(--color-danger, red)';
        }
    }
    if (confirmPass) confirmPass.addEventListener('input', checkConfirm);

    // ── Toggle password visibility ────────────────────────────────────────
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            target.type = target.type === 'password' ? 'text' : 'password';
            btn.querySelector('.eye-open').style.display  = target.type === 'password' ? '' : 'none';
            btn.querySelector('.eye-closed').style.display = target.type === 'text'    ? '' : 'none';
        });
    });

    // ── Logout modal ──────────────────────────────────────────────────────
    (function () {
        const modal  = document.getElementById('logoutModal');
        const btn    = document.getElementById('logoutBtn');
        const cancel = document.getElementById('logoutCancel');
        btn.addEventListener('click', e => { e.preventDefault(); modal.style.display = 'flex'; });
        cancel.addEventListener('click', () => { modal.style.display = 'none'; });
        modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (modal.style.display === 'flex') modal.style.display = 'none';
            }
        });
    })();
    </script>
</body>
</html>

<?php
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