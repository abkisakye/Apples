# Access Import Usage

## Current setup

The Laravel app lives in this folder and imports from:

- `VENUS BUSINESS MANAGER-PREMIER-POSB-FEB21.mdb`

The local default database is:

- `database/database.sqlite`

## Main commands

Create or reset the schema and seed base records:

```bash
php artisan migrate:fresh --seed
```

Import master data and transactions from the Access MDB:

```bash
php artisan access:import-core-data --fresh
```

Import only master data:

```bash
php artisan access:import-core-data --master-only --fresh
```

Use a different MDB path:

```bash
php artisan access:import-core-data --path="C:\path\to\file.mdb" --fresh
```

## What gets imported

- stores
- payment modes
- categories
- customers
- suppliers
- products
- product units
- cash and credit sales
- sale items
- customer payments
- cash and credit purchases
- purchase items
- supplier payments
- opening stock
- disposal stock movements

## Notes

- Banking was intentionally excluded from this phase.
- Legacy IDs are preserved in the new tables for traceability.
- Access name-based records such as `CASH SALE` and `OTHERS` are handled as system records.
- The current local validation run completed successfully against SQLite.
