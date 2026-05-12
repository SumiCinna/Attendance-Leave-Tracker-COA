<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Leave/Absents Tracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
    <header class="site-header">
        <div class="brand">
            <img src="../includes/images.png" alt="COA Logo" class="logo">
            <div>
                <h1>COA Leave/Absences Tracker</h1>
                <p class="muted">Commission On Audit - Republic of the Philippines</p>
            </div>
        </div>
        <nav class="nav-links">
            <a href="register.php" class="btn">Register</a>
            <a href="login.php" class="btn btn-outline">Login</a>
        </nav>
    </header>

    <main class="container">
        <section class="card hero">
            <h2>Welcome</h2>
            <p>Track your leave requests and absence history.</p>
            <div class="hero-actions">
                <a href="register.php" class="btn">Create Account</a>
                <a href="login.php" class="btn btn-outline">Sign In</a>
            </div>
        </section>

        <section class="grid">
            <div class="card">
                <h3>Leave Types</h3>
                <ul>
                    <li>Sick Leave</li>
                    <li>Vacation Leave</li>
                    <li>Emergency Leave</li>
                    <li>Personal Leave</li>
                    <li>Bereavement Leave</li>
                    <li>Maternity/Paternity Leave</li>
                    <li>Official Business</li>
                    <li>Unpaid Leave</li>
                </ul>
            </div>
            <div class="card">
                <h3>How it works</h3>
                <ol>
                    <li>Register using a Gmail address.</li>
                    <li>Log in and submit your leave or absence details.</li>
                    <li>Admin will review your leave request.</li>
                    <li>Review your submission history anytime.</li>
                </ol>
            </div>
        </section>
    </main>
</body>
</html>
