# COA Attendance Leave/Absents Tracker

## ✅ Features
- Register with Gmail only (unique per account)
- Real-time validation for names and password
- Login + dashboard for leave submissions
- Leave history table
- Admin view for all employee leaves
- Export to Excel (CSV) and Print/PDF

## ⚙️ Setup
1. Import `database.sql` into MySQL.
2. Update database credentials inside `php/db.php`.
3. Open `php/index.php` in your browser using XAMPP.

## � Admin Account
Default admin user (seeded in `database.sql`):
- Gmail: `admin@gmail.com`
- Password: `Admin1234`

The admin can view all employee leave requests and export them.

## �🗂️ Pages
- `php/index.php`
- `php/register.php`
- `php/login.php`
- `php/dashboard.php`
- `php/admin.php`
