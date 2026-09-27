# Task Management Platform - Production Deployment Guide

## 1. Architecture Overview

In a production environment, the platform operates with the following topology:

```
[ Internet / Clients ]
         │
         ▼
[ Nginx Reverse Proxy (SSL / TLS termination, Gzip, CORS, Rate Limiting) ]
   ├── /api/*, /storage/*  ────────► [ PHP 8.3-FPM (Laravel REST API) ]
   │                                           │
   │                                           ├────────► [ MySQL 8.0+ Database ]
   │                                           ├────────► [ Shared File Storage / S3 ]
   │                                           └────────► [ Supervisor / Systemd Queue Worker ]
   │
   └── /* (All other routes) ─────► [ Node.js 20+ Runtime (Next.js Standalone / PM2) ]
```

---

## 2. Docker & Docker Compose Deployment (Recommended)

The easiest and most reproducible way to run the entire stack in production is using Docker Compose.

### 2.1 `docker-compose.yml`

Create `docker-compose.yml` in the project root:

```yaml
version: '3.8'

services:
  # MySQL Database Service
  mysql:
    image: mysql:8.0
    container_name: tm_mysql
    restart: always
    environment:
      MYSQL_DATABASE: task_management
      MYSQL_USER: tm_user
      MYSQL_PASSWORD: SecureDatabasePassword123!
      MYSQL_ROOT_PASSWORD: RootSecurePassword123!
    volumes:
      - mysql_data:/var/lib/mysql
      - ./backend/database/dump.sql:/docker-entrypoint-initdb.d/init.sql
    networks:
      - tm_network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Laravel API Backend Service
  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: tm_backend
    restart: always
    environment:
      APP_ENV: production
      APP_DEBUG: 'false'
      APP_KEY: base64:your-generated-production-app-key-here
      APP_URL: https://api.yourdomain.com
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: task_management
      DB_USERNAME: tm_user
      DB_PASSWORD: SecureDatabasePassword123!
      QUEUE_CONNECTION: database
    volumes:
      - ./backend/storage/app/public:/var/www/html/storage/app/public
    networks:
      - tm_network
    depends_on:
      mysql:
        condition: service_healthy

  # Background Queue Worker Daemon
  queue-worker:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: tm_queue_worker
    restart: always
    command: php artisan queue:work --sleep=3 --tries=3 --timeout=120
    environment:
      APP_ENV: production
      APP_KEY: base64:your-generated-production-app-key-here
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: task_management
      DB_USERNAME: tm_user
      DB_PASSWORD: SecureDatabasePassword123!
      QUEUE_CONNECTION: database
    volumes:
      - ./backend/storage/app/public:/var/www/html/storage/app/public
    networks:
      - tm_network
    depends_on:
      backend:
        condition: service_started

  # Next.js Frontend Service
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    container_name: tm_frontend
    restart: always
    environment:
      NEXT_PUBLIC_API_URL: https://api.yourdomain.com/api
      NODE_ENV: production
    ports:
      - "3000:3000"
    networks:
      - tm_network
    depends_on:
      - backend

networks:
  tm_network:
    driver: bridge

volumes:
  mysql_data:
    driver: local
```

### 2.2 Backend `Dockerfile` (`backend/Dockerfile`)

```dockerfile
FROM php:8.3-fpm-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    file \
    bash \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql bcmath zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application source
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
```

### 2.3 Frontend `Dockerfile` (`frontend/Dockerfile`)

```dockerfile
FROM node:20-alpine AS builder
WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
ENV NEXT_TELEMETRY_DISABLED=1
RUN npm run build

FROM node:20-alpine AS runner
WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000

COPY --from=builder /app/package*.json ./
COPY --from=builder /app/.next ./.next
COPY --from=builder /app/public ./public
COPY --from=builder /app/node_modules ./node_modules

EXPOSE 3000
CMD ["npm", "start"]
```

---

## 3. Bare Metal / Linux VPS Deployment

### 3.1 Nginx Reverse Proxy Configuration

Create `/etc/nginx/sites-available/task_management.conf`:

```nginx
# API & Storage Virtual Host
server {
    listen 80;
    server_name api.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.yourdomain.com/privkey.pem;

    root /var/www/task-management/backend/public;
    index index.php index.html;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 300;
    }

    location /storage {
        alias /var/www/task-management/backend/storage/app/public;
        try_files $uri =404;
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}

# Frontend Next.js Virtual Host
server {
    listen 80;
    server_name app.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name app.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/app.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 3.2 Supervisor Configuration for Background Queue Worker

Create `/etc/supervisor/conf.d/task_queue.conf`:

```ini
[program:task-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/task-management/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/task_queue.log
stopwaitsecs=3600
```

Apply supervisor settings:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start task-queue-worker:*
```

### 3.3 Next.js Frontend Management via PM2

```bash
cd /var/www/task-management/frontend
npm ci
npm run build
pm2 start npm --name "task-frontend" -- start
pm2 save
pm2 startup
```

---

## 4. Environment Variables Checklist

### Backend (`.env`):
| Variable | Production Value | Description |
| --- | --- | --- |
| `APP_ENV` | `production` | Strict production error handling |
| `APP_DEBUG` | `false` | Disables stack trace leak |
| `APP_KEY` | `base64:...` | Run `php artisan key:generate` |
| `APP_URL` | `https://api.yourdomain.com` | Base API URL |
| `DB_CONNECTION` | `mysql` | MySQL database driver |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_DATABASE` | `task_management` | Database name |
| `DB_USERNAME` | `db_user` | Database user |
| `DB_PASSWORD` | `strong_secret_password` | Database password |
| `QUEUE_CONNECTION` | `database` | Asynchronous queue processing |

### Frontend (`.env.production`):
| Variable | Production Value | Description |
| --- | --- | --- |
| `NEXT_PUBLIC_API_URL` | `https://api.yourdomain.com/api` | Public backend endpoint |
| `NODE_ENV` | `production` | Node production optimization |

---

## 5. Automated Health Checks & Maintenance

### 5.1 API Health Check
```bash
curl -f https://api.yourdomain.com/api/health
```
Expected output: HTTP 200 with `status: "healthy"`.

### 5.2 Queue Statistics
```bash
curl -f https://api.yourdomain.com/api/queue/stats
```
Expected output: Counts of pending, processing, and failed jobs.

### 5.3 Daily Automated Database Backup Cron
Add to crontab (`crontab -e`):
```bash
0 2 * * * mysqldump -u db_user -p'strong_secret_password' task_management | gzip > /backups/task_db_$(date +\%F).sql.gz
```
