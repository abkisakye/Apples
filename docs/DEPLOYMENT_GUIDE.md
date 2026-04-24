# Deployment Guide

## Recommended Production Stack

- Windows or Linux server
- PHP 8.2+
- MySQL 8+ or MariaDB
- Composer
- web server such as Apache or Nginx

## Environment Setup

Use [.env.mysql.example](C:/wamp64/www/Apples/.env.mysql.example) as the starting point.

Important production settings:

- `APP_ENV=production`
- `APP_DEBUG=false`
- generated `APP_KEY`
- `APP_URL=https://your-domain.example`
- `DB_CONNECTION=mysql`
- `SESSION_SECURE_COOKIE=true`
- real SMTP settings instead of `MAIL_MAILER=log`

## Database Setup

1. create the MySQL database
2. create a dedicated MySQL user
3. update `.env`
4. run `php artisan migrate --seed`
5. run `php artisan access:import-core-data`
6. run `php artisan access:audit-import`
7. if using database sessions / cache / queue, confirm `sessions`, `cache`, `jobs`, and `failed_jobs` tables exist

## Laravel Hardening

- run `php artisan config:cache`
- run `php artisan route:cache`
- run `php artisan view:cache`
- ensure `storage` and `bootstrap/cache` are writable
- keep `vendor` installed on the server
- confirm `storage/app/backups` is writable

## Background Operations

- if reminders or queued work grow, run a queue worker
- keep database backups on a schedule
- rotate logs regularly

## Final Validation

- run `php artisan ops:go-live-check`
- run `php artisan ops:backup-database`
- review the in-app readiness page at `/admin/readiness`
- log in as admin and verify dashboard loads
- test one cashier sale
- test one stock clerk purchase
- test one manager report export
