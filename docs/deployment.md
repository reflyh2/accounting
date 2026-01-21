# FinfasPro Deployment Guide

This document provides step-by-step instructions for deploying FinfasPro to AWS EC2 with a separate PostgreSQL database server.

## Architecture Overview

```
┌─────────────────────┐     ┌─────────────────────────────────┐
│   Application EC2   │────▶│       PostgreSQL EC2            │
│  (Laravel + Nginx)  │     │  ┌───────────┐  ┌────────────┐  │
└─────────────────────┘     │  │ PgBouncer │─▶│ PostgreSQL │  │
         │                  │  │  (:6432)  │  │  (:5432)   │  │
         ▼                  │  └───────────┘  └────────────┘  │
    ┌─────────┐             └─────────────────────────────────┘
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

### 1.2 Install PostgreSQL 15 and PgBouncer

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y postgresql-15 postgresql-contrib pgbouncer
```

### 1.3 Configure PostgreSQL

Edit PostgreSQL configuration:

```bash
sudo nano /etc/postgresql/15/main/postgresql.conf
```

Change the listen address (localhost only since PgBouncer handles external connections):

```ini
listen_addresses = 'localhost'
```

### 1.4 Create Application Database User

> **Important**: The user must have `CREATEDB` privilege to create tenant databases dynamically.

```bash
sudo -u postgres psql
```

Run the following SQL commands:

```sql
-- Create the application user with ability to create databases
CREATE ROLE finfasproadmin LOGIN PASSWORD 'REPLACE-with-strong-secret' NOSUPERUSER NOCREATEROLE NOCREATEDB INHERIT;

-- Create the central database
CREATE DATABASE finfaspro_central OWNER finfasproadmin;

-- Restrict access only to that owner
REVOKE CONNECT ON DATABASE finfaspro_central FROM PUBLIC;
GRANT CONNECT ON DATABASE finfaspro_central TO finfasproadmin;

-- Grant all privileges on central database
GRANT ALL PRIVILEGES ON DATABASE finfaspro_central TO finfasproadmin;

-- Connect to central database to grant schema privileges
\c finfaspro_central

-- Grant schema privileges
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO finfasproadmin;

-- Exit psql
\q
```

### 1.5 Configure Client Authentication

Edit `pg_hba.conf` to allow PgBouncer to connect:

```bash
sudo nano /etc/postgresql/15/main/pg_hba.conf
```

Add the following line:

```
# Allow PgBouncer to connect (localhost)
host    all    finfaspro    127.0.0.1/32    scram-sha-256
```

### 1.6 Restart PostgreSQL

```bash
sudo systemctl restart postgresql
sudo systemctl enable postgresql
```

### 1.7 Configure PgBouncer

Create the userlist file:

```bash
sudo nano /etc/pgbouncer/userlist.txt
```

Add the application user (use `md5` or `scram-sha-256` hash):

```
"finfaspro" "YOUR_SECURE_PASSWORD"
```

> **Note**: For production, generate a proper hash using:
> ```bash
> psql -h localhost -U postgres -c "SELECT concat('\"', usename, '\" \"', passwd, '\"') FROM pg_shadow WHERE usename = 'finfasproadmin';"
> ```

Edit PgBouncer configuration:

```bash
sudo nano /etc/pgbouncer/pgbouncer.ini
```

```ini
[databases]
; Template for tenant databases - * allows any database
* = host=127.0.0.1 port=5432

[pgbouncer]
listen_addr = 0.0.0.0
listen_port = 6432
auth_type = scram-sha-256
auth_file = /etc/pgbouncer/userlist.txt

; Pool settings
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 25
min_pool_size = 5
reserve_pool_size = 5

; Logging
logfile = /var/log/pgbouncer/pgbouncer.log
pidfile = /var/run/pgbouncer/pgbouncer.pid

; Admin access
admin_users = postgres
stats_users = finfaspro
```

Start PgBouncer:

```bash
sudo systemctl restart pgbouncer
sudo systemctl enable pgbouncer
```

### 1.8 Configure Firewall

```bash
sudo ufw allow from YOUR_APP_SERVER_IP to any port 6432
sudo ufw enable
```

### 1.9 Verify Connection

From the app server, test the connection through PgBouncer:

```bash
psql -h YOUR_DB_SERVER_IP -p 6432 -U finfaspro -d finfaspro_central
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

# Install PHP and extensions (for Apache, use libapache2-mod-php instead of php-fpm)
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-pgsql \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
    php8.3-gd php8.3-intl php8.3-redis libapache2-mod-php8.3

# Install Apache2, Redis, and utilities
sudo apt install -y apache2 redis-server git unzip supervisor

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
npm ci
NODE_OPTIONS="--max-old-space-size=4096" npm run build
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

# Database (pointing to PgBouncer on DB server)
DB_CONNECTION=pgsql
DB_HOST=YOUR_DB_SERVER_PRIVATE_IP
DB_PORT=6432
DB_DATABASE=finfaspro_central
DB_USERNAME=finfaspro
DB_PASSWORD=YOUR_SECURE_PASSWORD

# Session & Cache (local Redis)
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Tenancy database connection (via PgBouncer)
TENANCY_DATABASE_HOST=YOUR_DB_SERVER_PRIVATE_IP
TENANCY_DATABASE_PORT=6432
TENANCY_DATABASE_USERNAME=finfaspro
TENANCY_DATABASE_PASSWORD=YOUR_SECURE_PASSWORD

# Tenancy central domains (comma-separated, NO subdomains here)
TENANCY_CENTRAL_DOMAINS=yourdomain.com
TENANCY_MAIN_DOMAIN=yourdomain.com
```

> [!IMPORTANT]
> **Laravel with PgBouncer (Transaction Pooling)**
>
> When using `pool_mode = transaction`, you must disable prepared statements in Laravel.
> Edit `config/database.php` and add `'options'` to your pgsql connection:
>
> ```php
> 'pgsql' => [
>     // ... other settings
>     'options' => [
>         PDO::ATTR_EMULATE_PREPARES => true,
>     ],
> ],
> ```

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

## Part 3: Apache2 Configuration

Enable required Apache modules:

```bash
sudo a2enmod rewrite headers ssl
sudo systemctl restart apache2
```

Create virtual host configuration:

```bash
sudo nano /etc/apache2/sites-available/finfaspro.conf
```

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias *.yourdomain.com
    DocumentRoot /var/www/finfaspro/public

    <Directory /var/www/finfaspro/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/finfaspro-error.log
    CustomLog ${APACHE_LOG_DIR}/finfaspro-access.log combined

    # PHP settings
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>
</VirtualHost>
```

Enable the site:

```bash
sudo a2ensite finfaspro.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest && sudo systemctl reload apache2
```

---

## Part 4: SSL Certificate

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com -d *.yourdomain.com
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
npm ci
NODE_OPTIONS="--max-old-space-size=4096" npm run build
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
# Test connection through PgBouncer
psql -h YOUR_DB_SERVER_IP -p 6432 -U finfaspro -d finfaspro_central

# Check PgBouncer logs
sudo tail -f /var/log/pgbouncer/pgbouncer.log

# Check PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-15-main.log
```

### PgBouncer connection issues

```bash
# Check PgBouncer status
sudo systemctl status pgbouncer

# Connect to PgBouncer admin console
psql -h 127.0.0.1 -p 6432 -U postgres pgbouncer -c "SHOW POOLS;"
```

### Tenant database creation fails

Ensure the `finfaspro` user has `CREATEDB` privilege:

```sql
ALTER USER finfaspro CREATEDB;
```

### Prepared statement errors with PgBouncer

If you see "prepared statement does not exist" errors, ensure you have added the PDO option in `config/database.php`:

```php
'options' => [
    PDO::ATTR_EMULATE_PREPARES => true,
],
```

### Permission errors

```bash
sudo chown -R www-data:www-data /var/www/finfaspro/storage
sudo chmod -R 775 /var/www/finfaspro/storage
```
