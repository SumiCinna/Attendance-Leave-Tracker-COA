<?php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "DREAMTEAM";
$DB_NAME = "attendance_leave_tracker";

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("Database connection failed.");
}
$mysqli->set_charset("utf8mb4");
