<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}

$leaveDate = trim($_POST["leave_date"] ?? "");
$leaveType = trim($_POST["leave_type"] ?? "");

if ($leaveDate === "" || $leaveType === "") {
    header("Location: dashboard.php?status=error");
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO absences (user_id, leave_date, leave_type) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $_SESSION["user_id"], $leaveDate, $leaveType);
$success = $stmt->execute();
$stmt->close();

header("Location: dashboard.php?status=" . ($success ? "saved" : "error"));
exit;
