# Payroll Database Setup (Windows)

## Locations
- **Development:** `./payroll.db` (114KB - active data)
- **XAMPP:** `../../xampp/htdocs/payrollsys/payroll.db`

## Permissions (Verified Full Access)
```
payroll.db: SYSTEM(F), Admins(F), hp(F)
Folder: Full recursive control
```

## Sync Data
```cmd
copy payroll.db ../../xampp/htdocs/payrollsys/payroll.db
```

## Test
```php
<?php require 'db.php'; echo \"DB OK\"; ?>
```

## Config
- `config.php`: `DB_PATH = __DIR__ . '/payroll.db'`
- PDO SQLite connection auto-creates tables
