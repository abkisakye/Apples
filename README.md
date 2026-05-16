# Apples Of Gold

Laravel-based replacement for the legacy Microsoft Access business system.

## Repository

- GitHub repository: [abkisakye/Apples](https://github.com/abkisakye/Apples)
- stable branch: `main`
- active development branch: `version-2.0.0-dev`
- release tag baseline: `v1.0.0`

## Versioning

- current release baseline: `v1.0.0`
- active improvement branch: `version-2.0.0-dev`
- release notes: [CHANGELOG](CHANGELOG.md)
- version 2 planning: [Version 2 Roadmap](docs/VERSION_2_ROADMAP.txt)
- version 2 backlog: [Version 2 Build Backlog](docs/V2_BUILD_BACKLOG.txt)

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

## Git Workflow

1. Keep `main` as the stable branch.
2. Do ongoing work in `version-2.0.0-dev` or a short-lived feature branch from it.
3. Commit locally with clear messages.
4. Push changes to GitHub.
5. Open a pull request into `main` when a release-ready batch is complete.

See [Repository Workflow](docs/REPOSITORY_WORKFLOW.txt) for the fuller branch and release flow.

## Production Notes

- local development currently uses SQLite
- production should use MySQL
- see `.env.mysql.example` for a production-style environment template

## Project Docs

- [Go Live Checklist](docs/GO_LIVE_CHECKLIST.txt)
- [Deployment Guide](docs/DEPLOYMENT_GUIDE.txt)
- [Pilot Install Guide](docs/PILOT_INSTALL_GUIDE.txt)
- [Permissions Matrix](docs/PERMISSIONS_MATRIX.txt)
- [Backup And Restore](docs/BACKUP_AND_RESTORE.txt)
- [Data Migration Cleanup](docs/DATA_MIGRATION_CLEANUP.txt)
- [UAT Checklist](docs/UAT_CHECKLIST.txt)
- [User Training Guide](docs/USER_TRAINING_GUIDE.txt)
- [Repository Workflow](docs/REPOSITORY_WORKFLOW.txt)
