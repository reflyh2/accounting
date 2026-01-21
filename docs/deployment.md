# FinfasPro Deployment Guide

This document provides step-by-step instructions for deploying FinfasPro to AWS EC2 with a separate PostgreSQL database server.

## Architecture Overview

```
┌─────────────────────┐     ┌─────────────────────┐
│   Application EC2   │────▶│   PostgreSQL EC2    │
│  (Laravel + Nginx)  │     │   (Database Server) │
└─────────────────────┘     └─────────────────────┘
         │
         ▼
    ┌─────────┐
    │  Redis  │ (on App Server)
    └─────────┘
```

---

## Prerequisites

- [ ] Two AWS EC2 instances (App Server + DB Server)
- [ ] SSH key pairs for both instances
- [ ] Domain name with wildcard DNS configured
- [ ] Security groups allowing communication between servers

---

## Part 1: PostgreSQL Database Server Setup

### 1.1 Connect to Database Server

```bash
ssh -i your-key.pem ubuntu@YOUR_DB_SERVER_IP
```

### 1.2 Install PostgreSQL 15

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y postgresql-15 postgresql-contrib
```

### 1.3 Configure PostgreSQL for Remote Access

Edit PostgreSQL configuration:

```bash
sudo nano /etc/postgresql/15/main/postgresql.conf
```

Change the listen address:

```ini
listen_addresses = '*'    # Or your specific app server IP for security
```

### 1.4 Create Application Database User

> **Important**: The user must have `CREATEDB` privilege to create tenant databases dynamically.

```bash
sudo -u postgres psql
```

Run the following SQL commands:

```sql
-- Create the application user with ability to create databases
CREATE USER finfaspro WITH PASSWORD 'YOUR_SECURE_PASSWORD' CREATEDB;

-- Create the central database
CREATE DATABASE finfaspro_central OWNER finfaspro;

-- Grant all privileges on central database
GRANT ALL PRIVILEGES ON DATABASE finfaspro_central TO finfaspro;

-- Connect to central database to grant schema privileges
\c finfaspro_central

-- Grant schema privileges
GRANT ALL ON SCHEMA public TO finfaspro;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO finfaspro;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO finfaspro;

-- Exit psql
\q
```

### 1.5 Configure Client Authentication

Edit `pg_hba.conf` to allow connections from the application server:

```bash
sudo nano /etc/postgresql/15/main/pg_hba.conf
```

Add the following line (replace with your app server IP):

```
# Allow app server to connect
host    all    finfaspro    YOUR_APP_SERVER_IP/32    scram-sha-256

# Or allow from VPC subnet (more flexible)
host    all    finfaspro    10.0.0.0/16    scram-sha-256
```

### 1.6 Restart PostgreSQL

```bash
sudo systemctl restart postgresql
sudo systemctl enable postgresql
```

### 1.7 Configure Firewall

```bash
sudo ufw allow from YOUR_APP_SERVER_IP to any port 5432
sudo ufw enable
```

### 1.8 Verify Connection

From the app server, test the connection:

```bash
psql -h YOUR_DB_SERVER_IP -U finfaspro -d finfaspro_central
```

---

## Part 2: Application Server Setup

### 2.1 Connect to Application Server

```bash
ssh -i your-key.pem ubuntu@YOUR_APP_SERVER_IP
```

### 2.2 Install System Dependencies

```bash
sudo apt update && sudo apt upgrade -y

# Add PHP 8.3 repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP and extensions
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common php8.3-pgsql \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
    php8.3-gd php8.3-intl php8.3-redis

# Install Nginx, Redis, and utilities
sudo apt install -y nginx redis-server git unzip supervisor

# Install Node.js 20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2.3 Deploy Application Code

```bash
sudo mkdir -p /var/www/finfaspro
sudo chown -R $USER:www-data /var/www/finfaspro
cd /var/www/finfaspro

# Clone your repository
git clone YOUR_GIT_REPOSITORY_URL .
```

### 2.4 Install Dependencies

```bash
composer install --optimize-autoloader --no-dev
npm ci && npm run build
```

### 2.5 Configure Environment

```bash
cp .env.example .env
nano .env
```

Key environment variables:

```env
APP_NAME="FinfasPro"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database (pointing to separate DB server)
DB_CONNECTION=pgsql
DB_HOST=YOUR_DB_SERVER_PRIVATE_IP
DB_PORT=5432
DB_DATABASE=finfaspro_central
DB_USERNAME=finfaspro
DB_PASSWORD=YOUR_SECURE_PASSWORD

# Session & Cache (local Redis)
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Tenancy database connection (same credentials, tenant DBs created automatically)
TENANCY_DATABASE_HOST=YOUR_DB_SERVER_PRIVATE_IP
TENANCY_DATABASE_PORT=5432
TENANCY_DATABASE_USERNAME=finfaspro
TENANCY_DATABASE_PASSWORD=YOUR_SECURE_PASSWORD
```

### 2.6 Initialize Application

```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2.7 Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/finfaspro
sudo chmod -R 755 /var/www/finfaspro
sudo chmod -R 775 /var/www/finfaspro/storage
sudo chmod -R 775 /var/www/finfaspro/bootstrap/cache
```

---

## Part 3: Nginx Configuration

Create site configuration:

```bash
sudo nano /etc/nginx/sites-available/finfaspro
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com *.yourdomain.com;
    root /var/www/finfaspro/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 100M;
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/finfaspro /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## Part 4: SSL Certificate

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d *.yourdomain.com
```

---

## Part 5: Queue Workers

Create Supervisor configuration:

```bash
sudo nano /etc/supervisor/conf.d/finfaspro-worker.conf
```

```ini
[program:finfaspro-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/finfaspro/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/finfaspro/storage/logs/worker.log
```

Start workers:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start finfaspro-worker:*
```

---

## Part 6: Scheduler

```bash
sudo crontab -e
```

Add:

```cron
* * * * * cd /var/www/finfaspro && php artisan schedule:run >> /dev/null 2>&1
```

---

## Part 7: DNS Configuration

Configure wildcard DNS in your DNS provider:

| Type | Name | Value              |
| ---- | ---- | ------------------ |
| A    | @    | YOUR_APP_SERVER_IP |
| A    | \*   | YOUR_APP_SERVER_IP |

---

## Deployment Updates

To deploy updates:

```bash
cd /var/www/finfaspro
git pull origin main
composer install --optimize-autoloader --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart finfaspro-worker:*
sudo systemctl restart php8.3-fpm
```

---

## Troubleshooting

### Cannot connect to PostgreSQL

```bash
# Test connection from app server
psql -h YOUR_DB_SERVER_IP -U finfaspro -d finfaspro_central

# Check PostgreSQL logs on DB server
sudo tail -f /var/log/postgresql/postgresql-15-main.log
```

### Tenant database creation fails

Ensure the `finfaspro` user has `CREATEDB` privilege:

```sql
ALTER USER finfaspro CREATEDB;
```

### Permission errors

```bash
sudo chown -R www-data:www-data /var/www/finfaspro/storage
sudo chmod -R 775 /var/www/finfaspro/storage
```
