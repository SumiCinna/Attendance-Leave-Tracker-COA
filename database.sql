CREATE DATABASE IF NOT EXISTS attendance_leave_tracker;
USE attendance_leave_tracker;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- ...existing code...
ALTER TABLE users
ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'employee';
-- ...existing code...
INSERT INTO users (first_name, middle_name, last_name, email, password_hash, role)
VALUES ('COA', 'Admin', 'User', 'admin@gmail.com', '$2y$10$uelPDzDrmGgHD.pPSda6S.tgkdM2Bl33kOFuw9M.UEYFHM07MWQie', 'admin')
ON DUPLICATE KEY UPDATE role = VALUES(role);

CREATE TABLE IF NOT EXISTS absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_date DATE NOT NULL,
    leave_type VARCHAR(100) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE users
    ADD COLUMN contact_number VARCHAR(15) NULL AFTER email;
    
ALTER TABLE absences ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0;