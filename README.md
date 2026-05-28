# Hospital Management (DB_Labs/hospital)

A small PHP-based hospital management demo containing simple forms and reports. The app is intended to run on a local LAMP/WAMP stack (XAMPP is recommended).

## Features
- Patient, doctor, nurse, ward and treatment forms (under /forms).
- Basic reports in /reports (patient, ward, consultant team records).
- Reusable header/footer in /includes and a central DB connection in /includes/db.php.

## Prerequisites
- Windows: XAMPP (Apache + PHP + MySQL/MariaDB) or another PHP+MySQL stack.
- PHP 7.2+ (match your XAMPP version).
- A MySQL/MariaDB database and user.

## Installation / Setup
1. Copy the project folder to your web server document root. Example for XAMPP: C:\xampp\htdocs\DB_Labs\hospital

2. Start Apache and MySQL using the XAMPP Control Panel.
3. Create a MySQL database for the application (e.g. hospital).
4. Update database credentials in includes/db.php to match your MySQL user, password, host and database name.

Example mysql import (if you have a schema file):

```bash
mysql -u root -p hospital < path/to/schema.sql
```

If you don't have a schema file, create the tables manually or through phpMyAdmin.

## Configuration
- Database connection is stored in includes/db.php. Open and edit it to set $host, $user, $pass, and $dbname.

## Run / Usage
1. Browse to the application in your browser. Example URL:

http://localhost/DB_Labs/hospital/

2. Use the links on the homepage to access forms under /forms and reports under /reports.

## Project Layout

- index.php — Application entry / dashboard.
- assets/ — CSS and JavaScript (styles in assets/css/main.css, scripts in assets/js/main.js).
- forms/ — Data-entry forms (admissions, doctors, patients, wards, etc.).
- includes/ — db.php, header.php, footer.php (shared components).
- reports/ — Example reports (patient_record, ward_record, consultant_team_record).

## Notes & Troubleshooting
- If you see database connection errors, first verify credentials and that MySQL is running.
- Enable PHP error display in development (php.ini) if you need more details.
- This repository does not include a database schema file by default — export your DB or create tables as needed.

## Next steps / Improvements
- Add an SQL schema (schema.sql) for quick setup.
- Add installation instructions or an install script.
- Harden input validation and add prepared statements where missing.

---
If you want, I can add a schema.sql template, or create a quick installer script to populate tables.
