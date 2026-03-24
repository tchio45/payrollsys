# Private Enterprise Hosting Guide

## Local Development/Testing (Already Working)
```
cd "c:/Users/hp/Desktop/REPORT AUDE/payrollsys"
php -S localhost:8000
```
Open http://localhost:8000 - Access from local machine only.

## Production Local Server (Windows)
1. Install XAMPP (https://www.apachefriends.org)
2. Copy payrollsys to htdocs/payrollsys
3. Start Apache
4. http://localhost/payrollsys/
5. Firewall: Windows Defender Firewall → Inbound Rule → Allow port 80 from enterprise IPs only

## VPS Deploy (Recommended - $6/mo)
1. DigitalOcean Droplet (Ubuntu)
2. SSH: `apt update && apt install apache2 php php-sqlite3 php-gd`
3. `scp -r payrollsys user@server:/var/www/html/`
4. `chown -R www-data uploads/`
5. Firewall: `ufw allow from OFFICE_IP to any port 80`

## PaaS (Railway/Heroku - Sign up required)
1. GitHub repo with project
2. Railway: New Project → Deploy from GitHub
3. SQLite auto-migrates
4. Private: Railway Private Networking + Basic Auth

## Security Hardening
**.htaccess:**
```
AuthType Basic
AuthName "Payroll Enterprise"
AuthUserFile .htpasswd
Require valid-user
```
`htpasswd -c .htpasswd user`

**Completed:** Username/password change feature. Project enterprise-ready!

