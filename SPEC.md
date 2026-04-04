# Payroll System - Specification

## Project Overview
- **Project Name**: PayPro - Payroll Management System
- **Type**: Web Application (PHP + SQLite)
- **Core Functionality**: Full-featured payroll management with employee, salary, attendance, and reporting modules
- **Target Users**: HR/Payroll administrators, small to medium businesses
- **Currency**: FCFA (Central Africa)

## Tech Stack
- **Backend**: PHP 8.4
- **Database**: SQLite (portable, no setup required)
- **Frontend**: HTML5, CSS3, JavaScript
- **Server**: Nginx + PHP 8.4-FPM
- **CI/CD**: GitHub Actions (auto-deploy on push to master)
- **Hosting**: Ubuntu 24.04 VPS

## Database Schema

### Tables
1. **users** - System users (admin + employees)
2. **employees** - Employee personal and professional details (permanent/temporal types)
3. **salary_grades** - Salary grades/levels (Grade A-E)
4. **allowances** - Allowances configuration (HRA, DA, Conveyance, Medical, Special, Overtime)
5. **deductions** - Deductions configuration (PF, Tax, Insurance)
6. **employee_allowances** - Per-employee allowance assignments
7. **employee_deductions** - Per-employee deduction assignments
8. **attendance** - Daily attendance records
9. **attendance_logs** - Clock-in/clock-out records with lateness detection
10. **leave_requests** - Employee leave requests with admin approval
11. **payroll** - Monthly payroll records with overtime, late/early deductions
12. **payslips** - Generated payslips

## Architecture

### Shared Components (includes/)
- **sidebar.php** - Dynamic navigation menu (admin vs employee view, auto-active detection)
- **header.php** - Top header with user info and role
- **pagination.php** - Reusable pagination (paginate() + renderPagination())

### Security
- CSRF protection on all POST forms and AJAX calls (meta tag + hidden fields)
- Role-based access control (admin pages redirect non-admin users)
- PDO prepared statements for all queries
- Password hashing (bcrypt via password_hash)
- Nginx blocks access to sensitive files (.db, config.php, includes/)

### Functionality

#### 1. Authentication (index.php)
- Login with username/password
- Session management with role detection (admin vs employee)
- Admin redirects to dashboard, employee to my_profile

#### 2. Dashboard (dashboard.php) - Admin only
- Total employees count, departments, monthly payroll
- Processed employees ratio
- Recent payroll and employee tables
- Quick action buttons

#### 3. Employee Management (employees.php) - Admin only
- Add/edit/delete employees with full details
- Employee types: permanent, temporal
- Configurable working days and hours
- Auto-create user account on employee creation
- Paginated list (15 per page)

#### 4. Salary Management (salary.php) - Admin only
- Salary grades CRUD
- Allowances CRUD (fixed amount or percentage of basic)
- Deductions CRUD (fixed amount or percentage of basic)

#### 5. Attendance Management (attendance.php) - Admin only
- Mark individual/bulk attendance
- Clock-in/clock-out with late/early detection
- Generate monthly attendance for all employees
- Leave request approval/rejection
- Paginated records (20 per page)

#### 6. Employee Profile (my_profile.php) - Employee only
- View personal info and payroll history
- Clock-in/clock-out
- Submit leave requests (with WhatsApp notification to admin)
- Change username/password
- Upload profile picture

#### 7. Payroll Processing (payroll.php) - Admin only
- Select month/year and process for all active employees
- Auto-calculates: basic + allowances + overtime - deductions - late/early penalties
- Attendance-based working days calculation
- Paginated history (15 per page)

#### 8. Payslip Generation (payslips.php) - Admin only
- Generate payslips from processed payroll
- View and print payslips
- Paginated list (15 per page)

#### 9. Reports (reports.php) - Admin only
- Monthly payroll summary
- Department-wise breakdown
- Employee-wise salary report
- Filterable by month, year, department
