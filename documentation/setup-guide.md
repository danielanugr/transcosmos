# Task Management Platform - Setup Guide

## System Requirements

- PHP 8.2 or higher (tested on PHP 8.3)
- PHP Extensions: `pdo`, `pdo_sqlite` (or `pdo_mysql`), `gd`, `fileinfo`, `mbstring`, `openssl`, `curl`, `sodium`
- Composer 2.x
- Optional: MySQL 8.0+ / MariaDB 10.4+

## Quick Start (Zero Configuration with SQLite)

1. Navigate to the backend directory:
   ```bash
   cd backend
   ```

2. Run migrations and populate the sample seed data:
   ```bash
   php artisan migrate:fresh --seed
   ```

3. Run the automated test suite:
   ```bash
   php artisan test
   ```

4. Start the local development server:
   ```bash
   php artisan serve --port=8000
   ```

5. In a separate terminal, start the background queue worker:
   ```bash
   php artisan queue:work
   ```

## Production Setup with MySQL

1. Create a MySQL database:
   ```sql
   CREATE DATABASE task_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the SQL dump:
   ```bash
   mysql -u root -p task_management < database/dump.sql
   ```

3. Update `.env` in `backend/`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=task_management
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

4. Run migrations and seeders:
   ```bash
   php artisan migrate --seed
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
