# Backup And Restore

## Backup

Use:

```bash
php artisan ops:backup-database
```

What it does:

- on SQLite: copies the `.sqlite` database file into `storage/app/backups`
- on MySQL: runs `mysqldump` and writes an `.sql` backup into `storage/app/backups`

## Restore Plan

SQLite:

1. stop the application
2. copy the desired `.sqlite` backup over the active database file
3. restart the application

MySQL:

1. create a fresh database if needed
2. import the chosen `.sql` backup using MySQL tools
3. confirm `.env` points to the restored database
4. test login and core dashboard access

## Recommended Frequency

- daily during early go-live
- before major imports
- before deployment changes
- before user-account cleanup or bulk edits
