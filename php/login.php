<?php
require_once __DIR__ . "/db.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email and password are required.";
    } else {
    $stmt = $mysqli->prepare("SELECT id, first_name, last_name, password_hash, role, account_status FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user["password_hash"])) {
            $status = $user["account_status"] ?? "approved";
            if ($status !== "approved") {
                $error = $status === "rejected"
                    ? "Your account registration was declined. Please contact the admin."
                    : "Your account is pending admin approval. Please wait for confirmation.";
            } else {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
                $_SESSION["user_role"] = $user["role"] ?? 'employee';
                if ($_SESSION["user_role"] === 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: manage_employee.php");
                }
                exit;
            }
        } else {
            $error = "Invalid login credentials.";
        }
    }
}

$registered = isset($_GET["registered"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | COA Leave/Absences Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-split-card">
            <!-- Left Info Side -->
            <div class="auth-info">
                <img src="../includes/images.png" alt="COA Logo" class="logo" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; margin-bottom: 24px; filter: drop-shadow(0 4px 10px rgba(255,255,255,0.2));">
                <div class="badge"><span>COA</span> Official System</div>
                <h2>Absences/Leave Tracker</h2>
                <p class="subtitle">Commission on Audit · Republic of the Philippines</p>
            </div>

            <!-- Right Form Side -->
            <div class="auth-form-side">
                <h2>Welcome Back</h2>
                <p class="subtitle">Log in to your COA account</p>

                <?php if ($registered): ?>
                    <div class="alert alert-success">Registration submitted. Please wait for admin approval before logging in.</div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></div>
                <?php endif; ?>

                <form method="POST" class="form" novalidate>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label for="email">Gmail</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-button">
                            <input type="password" id="password" name="password" maxlength="30" required>
                            <button type="button" class="btn btn-auth-outline toggle-password" data-target="password" aria-label="Toggle password visibility">
                                <svg class="eye-open eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg class="eye-closed eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-block" style="margin-top: 10px;">Sign In</button>
                    
                    <div class="form-footer">
                        Don't have an account? <a href="register.php">Register</a>
                    </div>
                   <div class="form-footer" style="margin-top: 8px;">
    <a href="../php/index.php">← Back to Home</a>
</div>
                </form>
            </div>
        </section>
    </main>

    <script src="../js/login.js"></script>
</body>
</html>
