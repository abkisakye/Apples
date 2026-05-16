# Apples Of Gold

Laravel-based replacement for the legacy Microsoft Access business system.

## Versioning

- current release baseline: `v1.0.0`
- active improvement branch: `version-2.0.0-dev`
- release notes: [CHANGELOG](C:/wamp64/www/Apples/CHANGELOG.md)
- version 2 planning: [Version 2 Roadmap](C:/wamp64/www/Apples/docs/VERSION_2_ROADMAP.txt)
- version 2 backlog: [Version 2 Build Backlog](C:/wamp64/www/Apples/docs/V2_BUILD_BACKLOG.txt)

## What The System Covers

- sales and receipt/invoice printing
- purchases and supplier payment tracking
- customer payments and statements
- stock balances, reorder alerts, transfers, and adjustments
- capital inputs and source tracking
- aging reports, follow-ups, and activity logs
- Access MDB import, including legacy staff accounts

## Local Run

1. Copy `.env.example` to `.env`
2. Set `APP_KEY` with `php artisan key:generate`
3. Run `php artisan migrate --seed`
4. Import Access data with `php artisan access:import-core-data`
5. Start the app with `php artisan serve --port 8081`

Default local admin:

- username: `admin`
- email: `admin@apples.local`
- password: `password`

## Operational Commands

- `php artisan access:import-core-data`
- `php artisan access:audit-import`
- `php artisan access:clean-imported-data`
- `php artisan ops:go-live-check`
- `php artisan ops:backup-database`

## Production Notes

- local development currently uses SQLite
- production should use MySQL
- see `.env.mysql.example` for a production-style environment template

## Project Docs

- [Go Live Checklist](C:/wamp64/www/Apples/docs/GO_LIVE_CHECKLIST.txt)
- [Deployment Guide](C:/wamp64/www/Apples/docs/DEPLOYMENT_GUIDE.txt)
- [Pilot Install Guide](C:/wamp64/www/Apples/docs/PILOT_INSTALL_GUIDE.txt)
- [Permissions Matrix](C:/wamp64/www/Apples/docs/PERMISSIONS_MATRIX.txt)
- [Backup And Restore](C:/wamp64/www/Apples/docs/BACKUP_AND_RESTORE.txt)
- [Data Migration Cleanup](C:/wamp64/www/Apples/docs/DATA_MIGRATION_CLEANUP.txt)
- [UAT Checklist](C:/wamp64/www/Apples/docs/UAT_CHECKLIST.txt)
- [User Training Guide](C:/wamp64/www/Apples/docs/USER_TRAINING_GUIDE.txt)
