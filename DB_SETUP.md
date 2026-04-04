# Database Setup & Troubleshooting

## File Locations

**Primary (Development):** `c:/Users/hp/Desktop/REPORT AUDE/payrollsys/payroll.db` (114KB)
**XAMPP Web Server:** `c:/Users/hp/Desktop/xampp/htdocs/payrollsys/payroll.db`

## Connection Details
```
config.php: define('DB_PATH', __DIR__ . '/payroll.db');
db.php: new PDO('sqlite:' . DB_PATH)
```

## Windows Permissions (Verified)
```
payroll.db → Full Control: SYSTEM, Administrators, hp
Folder → Full recursive access
```
**No `chmod` needed** - Windows Full Control = Linux 777

## Sync Command
```cmd
copy payroll.db ..\..\xampp\htdocs\payrollsys\payroll.db
```

## Test Connection
```php
<?php
require 'config.php';
require 'db.php';
echo "✅ DB Connected";
?>
```

## Common Issues
- **Empty XAMPP DB:** Copy from main → restart Apache
- **Path issues:** Use absolute paths in dev
- **Locked file:** Close VSCode tab → copy → reopen
