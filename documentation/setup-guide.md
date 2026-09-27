# Task Management Platform - Comprehensive Setup Guide

## 1. Prerequisites & System Requirements

Ensure the following tools are installed on your machine:

- **PHP**: 8.2 or higher (recommended: PHP 8.3)
- **PHP Extensions**: `pdo`, `pdo_sqlite` (or `pdo_mysql`), `gd`, `fileinfo`, `mbstring`, `openssl`, `curl`, `sodium`
- **Composer**: 2.x
- **Node.js**: 18.x or 20.x+ (recommended: Node 20 LTS)
- **npm**: 9.x or 10.x+
- **Optional**: MySQL 8.0+ / MariaDB 10.4+ (SQLite is supported out-of-the-box)

---

## 2. Quick Start (Zero-Configuration with SQLite)

This is the fastest way to get the entire full-stack application running in under 2 minutes.

### Step 1: Clone & Navigate to Project Root
```bash
git clone <repository-url>
cd trans-cosmos
```

### Step 2: Backend Setup (Laravel)
```bash
cd backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations and populate sample database
php artisan migrate:fresh --seed

# Create storage symbolic link for uploads
php artisan storage:link

# Start the Laravel development server (runs on http://127.0.0.1:8000)
php artisan serve --port=8000
```

### Step 3: Start Queue Worker (In a separate terminal)
```bash
cd backend
php artisan queue:work
```

### Step 4: Frontend Setup (Next.js)
Open a new terminal window:
```bash
cd frontend

# Install Node dependencies
npm install

# Start Next.js development server (runs on http://localhost:3000)
npm run dev
```

### Step 5: Access the Web Application
Open your browser and navigate to:
```
http://localhost:3000
```

---

## 3. Production Setup with MySQL (Optional)

If you prefer using MySQL instead of SQLite:

1. **Create the database in MySQL**:
   ```sql
   CREATE DATABASE task_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import the SQL dump** (contains schema and sample seed data):
   ```bash
   mysql -u root -p task_management < backend/database/dump.sql
   ```

3. **Update `.env` in `backend/`**:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=task_management
   DB_USERNAME=your_mysql_username
   DB_PASSWORD=your_mysql_password
   ```

4. **Verify migrations and seeders**:
   ```bash
   php artisan migrate --seed
   ```

---

## 4. Default Test Credentials

All seed accounts share the default password: **`password123`**

| Name | Email | Role | Recommended Use |
| --- | --- | --- | --- |
| **Alice Johnson** | `alice@example.com` | `admin` | Full administrative control, task management, queue triggers |
| **Bob Smith** | `bob@example.com` | `manager` | Project management, task assignment, status updates |
| **Charlie Brown** | `charlie@example.com` | `member` | Individual contributor, comments, file attachments |
| **Diana Prince** | `diana@example.com` | `member` | Individual contributor |
| **Evan Wright** | `evan@example.com` | `member` | Individual contributor |

---

## 5. Running Automated Tests

### 5.1 Backend Tests (PHPUnit - 41 Tests)
```bash
cd backend
php artisan test
```
*Executes all unit tests (`VirusScannerServiceTest`, `ImageThumbnailServiceTest`, `JwtServiceTest`) and feature tests (`TaskApiTest`, `AttachmentApiTest`, `AuthApiTest`, `DatabaseIntegrationTest`, `QueueApiTest`, `BonusFeaturesTest`).*

### 5.2 Frontend Tests (Vitest - 16 Tests)
```bash
cd frontend
npm test
```
*Executes all frontend domain tests, component unit tests, API integration tests, bonus feature tests, and critical user flow simulation tests.*

### 5.3 Frontend Production Build Validation
```bash
cd frontend
npm run build
```
*Verifies Next.js Turbopack compiler, TypeScript type-checking, and static page generation.*

---

## 6. Project Documentation Index

- **API Documentation (OpenAPI 3.0)**: [`documentation/api-docs/openapi.yaml`](file:///d:/test/trans-cosmos/documentation/api-docs/openapi.yaml)
- **API Documentation (Postman Collection)**: [`documentation/api-docs/postman_collection.json`](file:///d:/test/trans-cosmos/documentation/api-docs/postman_collection.json)
- **Database Schema & ERD**: [`documentation/database-schema.md`](file:///d:/test/trans-cosmos/documentation/database-schema.md)
- **Architecture Documentation & ADRs**: [`documentation/architecture.md`](file:///d:/test/trans-cosmos/documentation/architecture.md)
- **Production Deployment Guide**: [`documentation/deployment-guide.md`](file:///d:/test/trans-cosmos/documentation/deployment-guide.md)
