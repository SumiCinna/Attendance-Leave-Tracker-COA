<?php
require_once __DIR__ . "/db.php";

$errors = [];
$values = [
    "first_name"     => "",
    "middle_name"    => "",
    "last_name"      => "",
    "email"          => "",
    "contact_number" => "",
];

function clean_input($value)
{
    return trim($value ?? "");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $values["first_name"]     = clean_input($_POST["first_name"]     ?? "");
    $values["middle_name"]    = clean_input($_POST["middle_name"]    ?? "");
    $values["last_name"]      = clean_input($_POST["last_name"]      ?? "");
    $values["email"]          = clean_input($_POST["email"]          ?? "");
    $values["contact_number"] = clean_input($_POST["contact_number"] ?? "");
    $password                 = $_POST["password"] ?? "";

    $namePattern = "/^[a-zA-Z\s\-']+$/";

    foreach (["first_name" => "First name", "middle_name" => "Middle name", "last_name" => "Last name"] as $field => $label) {
        if ($values[$field] === "") {
            $errors[] = "$label is required.";
        } elseif (strlen($values[$field]) > 100) {
            $errors[] = "$label must be 100 characters or less.";
        } elseif (!preg_match($namePattern, $values[$field])) {
            $errors[] = "$label can only contain letters, spaces, hyphens, and apostrophes.";
        }
    }

    if ($values["email"] === "") {
        $errors[] = "Gmail is required.";
    } elseif (!filter_var($values["email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    } elseif (!preg_match("/@gmail\.com$/", $values["email"])) {
        $errors[] = "Email must be a Gmail address ending with @gmail.com.";
    }

    // Contact number: must be exactly 10 digits (we store with +63 prefix)
    if ($values["contact_number"] === "") {
        $errors[] = "Contact number is required.";
    } elseif (!preg_match("/^\d{10}$/", $values["contact_number"])) {
        $errors[] = "Contact number must be exactly 10 digits after +63.";
    }

    if ($password === "") {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8 || strlen($password) > 30) {
        $errors[] = "Password must be between 8 and 30 characters.";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must include at least one uppercase letter, one lowercase letter, and one number.";
    }

    if (!$errors) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $values["email"]);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "This Gmail is already registered. Use another Gmail to avoid duplicate accounts.";
        }
        $stmt->close();
    }

    if (!$errors) {
        $hash           = password_hash($password, PASSWORD_DEFAULT);
        $role           = 'employee';
        $account_status = 'pending';
        $contact_stored = '+63' . $values["contact_number"]; // store full number

        $stmt = $mysqli->prepare(
            "INSERT INTO users (first_name, middle_name, last_name, email, password_hash, role, contact_number, account_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "ssssssss",
            $values["first_name"],
            $values["middle_name"],
            $values["last_name"],
            $values["email"],
            $hash,
            $role,
            $contact_stored,
            $account_status
        );
        if ($stmt->execute()) {
            header("Location: login.php?registered=1");
            exit;
        }
        $errors[] = "Registration failed. Please try again.";
        $stmt->close();
    }
}

function e($value)
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | COA Leave/Absences Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-split-card">
            <!-- Left Info Side -->
            <div class="auth-info">
                <img src="../includes/images.png" alt="COA Logo" class="logo"
                     style="width:64px;height:64px;border-radius:50%;object-fit:cover;margin-bottom:24px;filter:drop-shadow(0 4px 10px rgba(255,255,255,0.2));">
                <div class="badge"><span>COA</span> Official System</div>
                <h2>Attendance<br>Leave Tracker</h2>
                <p class="subtitle">Commission on Audit · Republic of the Philippines</p>
                
            </div>

            <!-- Right Form Side -->
            <div class="auth-form-side">
                <h2>Create Account</h2>
                <p class="subtitle">One Gmail per employee account</p>

                <?php if ($errors): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" class="form" id="registerForm" novalidate>
                    <div class="two-col-group">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" maxlength="100" required
                                   value="<?php echo e($values['first_name']); ?>">
                            <div class="hint" id="firstNameHint">Letters and spaces only</div>
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" maxlength="100" required
                                   value="<?php echo e($values['middle_name']); ?>">
                            <div class="hint" id="middleNameHint">Letters and spaces only</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:12px;">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" maxlength="100" required
                               value="<?php echo e($values['last_name']); ?>">
                        <div class="hint" id="lastNameHint">Letters and spaces only</div>
                    </div>

                    <div class="form-group" style="margin-bottom:12px;">
                        <label for="email">Gmail</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo e($values['email']); ?>">
                        <div class="hint" id="emailHint">Must be a valid @gmail.com address</div>
                    </div>

                    <!-- Contact Number -->
                    <div class="form-group" style="margin-bottom:12px;">
                        <label for="contact_number">Contact Number</label>
                        <div class="input-prefix-wrap">
                            <span class="input-prefix">+63</span>
                            <input type="text" id="contact_number" name="contact_number"
                                   maxlength="10" inputmode="numeric" pattern="\d{10}"
                                   placeholder="9XXXXXXXXX" required
                                   value="<?php echo e($values['contact_number']); ?>">
                        </div>
                        <div class="hint">10 digits after +63 (e.g. 9171234567)</div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-button">
                            <input type="password" id="password" name="password" maxlength="30" required>
                            <button type="button" class="btn btn-auth-outline toggle-password"
                                    data-target="password" aria-label="Toggle password visibility">
                                <svg class="eye-open eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg class="eye-closed eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="display:none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        <ul class="hint-list" style="margin-top:6px;">
                            <li id="passLength">8-30 characters</li>
                            <li id="passUpper">One uppercase letter</li>
                            <li id="passLower">One lowercase letter</li>
                            <li id="passNumber">One number</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-block" id="registerSubmit" style="margin-top:10px;">Register</button>

                    <div class="form-footer">
                        Have an account? <a href="login.php">Sign in</a>
                    </div>
                    <div class="form-footer" style="margin-top: 8px;">
    <a href="../php/index.php">← Back to Home</a>
</div>
                </form>
            </div>
        </section>
    </main>

    <script src="../js/register.js"></script>
    <script>
        // Allow digits only in contact field
        document.getElementById('contact_number').addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });
    </script>
</body>
</html>
