# Go-Live Checklist

## Before Go-Live

- confirm final business name, address, phone, email, and TIN in the environment file
- switch production from SQLite to MySQL
- set `APP_ENV=production`
- set `APP_DEBUG=false`
- set final `APP_URL` to the live HTTPS domain
- run `php artisan migrate --seed`
- run `php artisan access:import-core-data` if production needs legacy data
- run `php artisan access:audit-import`
- review imported users, customers, suppliers, and products
- confirm low-stock report, customer statement, supplier statement, receipt, and invoice printing
- confirm cashier, stock clerk, manager, and admin access paths
- confirm email settings if reminder emails will be used
- confirm regular backup procedure with `php artisan ops:backup-database`
- run `php artisan ops:go-live-check`

## Go-Live Day

- take a fresh database backup
- put old system into read-only mode if possible
- import any final delta data
- verify one test sale, one payment, one purchase, and one stock adjustment
- verify one login for each staff role
- verify receipt and statement printing from the live server
- confirm management sign-off

## After Go-Live

- keep the old Access file archived, not actively edited
- monitor activity log daily for the first week
- review low-stock alerts and overdue follow-ups daily
- take daily backups during the stabilization period
- collect user feedback and log any workflow gaps
