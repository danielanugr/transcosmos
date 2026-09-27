# Task Management Platform - Setup Guide

## System Requirements

- PHP 8.2 or higher (tested on PHP 8.3)
- PHP Extensions: `pdo`, `pdo_sqlite` (or `pdo_mysql`), `gd`, `fileinfo`, `mbstring`, `openssl`, `curl`
- Composer (or use standalone PSR-4 autoloader)
- Optional: MySQL 8.0+ / MariaDB 10.4+

## Quick Start (Zero Configuration with SQLite)

1. Clone or extract the project to your workspace:
   ```bash
   cd trans-cosmos/backend
   ```

2. Generate autoload files:
   ```bash
   composer dump-autoload
   ```

3. Initialize the database and populate seed data:
   ```bash
   php database/Seeder.php
   ```

4. Run the automated test suite:
   ```bash
   php tests/run_tests.php
   ```

5. Start the local development server:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

6. In a separate terminal, start the background queue worker:
   ```bash
   php worker.php
   ```

## Production Setup with MySQL

1. Create a MySQL database:
   ```sql
   CREATE DATABASE task_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the database dump:
   ```bash
   mysql -u root -p task_management < database/dump.sql
   ```

3. Update `.env` configuration:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=task_management
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

4. Verify database connection and entities:
   ```bash
   php database/Seeder.php
   ```

## Default Test User Accounts

All default test accounts share the password: `password123`

| Name | Email | Role |
| --- | --- | --- |
| Alice Johnson | `alice@example.com` | `admin` |
| Bob Smith | `bob@example.com` | `manager` |
| Charlie Brown | `charlie@example.com` | `member` |
| Diana Prince | `diana@example.com` | `member` |
| Evan Wright | `evan@example.com` | `member` |
