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
$reason = trim($_POST["reason"] ?? "");

if ($leaveDate === "" || $leaveType === "" || $reason === "") {
    header("Location: dashboard.php?status=error");
    exit;
}

if (strlen($reason) > 500) {
    header("Location: dashboard.php?status=error");
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO absences (user_id, leave_date, leave_type, reason) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $_SESSION["user_id"], $leaveDate, $leaveType, $reason);
$success = $stmt->execute();
$stmt->close();

header("Location: dashboard.php?status=" . ($success ? "saved" : "error"));
exit;
