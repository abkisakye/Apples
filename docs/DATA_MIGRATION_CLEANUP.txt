# Data Migration Cleanup

Use:

```bash
php artisan access:audit-import
```

## What To Review

- imported users and their mapped roles
- customers without phone numbers
- suppliers without phone numbers
- credit sales without due dates
- credit purchases without due dates
- inactive product units
- system records such as walk-in or fallback entities

## Manual Cleanup Priorities

1. confirm important customer names and balances
2. confirm supplier names and outstanding balances
3. confirm walk-in customer behavior
4. confirm old usernames and staff access levels
5. review odd legacy values like placeholder suppliers or blank contact fields

## Migration Rule Notes

- old Access usernames were imported
- old Access passwords remain usable in Laravel, but are stored safely as hashed passwords
- generated legacy emails are technical placeholders for Laravel compatibility
