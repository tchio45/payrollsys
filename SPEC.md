# Payroll System Specification

## Project Overview
- **Project Name**: Complete Payroll Management System
- **Type**: Web Application (PHP + SQLite)
- **Core Functionality**: Full-featured payroll management with employee, salary, attendance, and reporting modules
- **Target Users**: HR/Payroll administrators, small to medium businesses

## Tech Stack
- **Backend**: PHP 7.4+
- **Database**: SQLite (portable, no setup required)
- **Frontend**: HTML5, CSS3, JavaScript
- **Styling**: Custom CSS with modern, clean design

## Database Schema

### Tables
1. **users** - System administrators
2. **employees** - Employee personal and professional details
3. **salary_grades** - Salary grades/levels
4. **allowances** - Allowances configuration
5. **deductions** - Deductions configuration
6. **attendance** - Employee attendance records
7. **payroll** - Monthly payroll records
8. **payslips** - Generated payslips

## UI/UX Specification

### Color Palette
- **Primary**: #2c3e50 (Dark blue-gray)
- **Secondary**: #3498db (Bright blue)
- **Accent**: #27ae60 (Green - for success/positive)
- **Warning**: #f39c12 (Orange)
- **Danger**: #e74c3c (Red)
- **Background**: #f8f9fa (Light gray)
- **Card Background**: #ffffff (White)
- **Text Primary**: #2c3e50
- **Text Secondary**: #7f8c8d

### Typography
- **Font Family**: 'Segoe UI', 'Roboto', sans-serif
- **Headings**: Bold, 24-32px
- **Body**: Regular, 14-16px
- **Small**: 12px

### Layout
- **Sidebar**: Fixed left, 250px width, dark theme
- **Main Content**: Fluid, with padding
- **Cards**: White background, subtle shadow, rounded corners
- **Tables**: Striped rows, hover effects
- **Forms**: Clean input fields with validation

### Components
- Navigation sidebar with icons
- Top header with user info
- Dashboard cards with statistics
- Data tables with actions
- Modal forms for add/edit
- Print-friendly payslip
- Charts for reports

## Functionality Specification

### 1. Authentication
- Login page with username/password
- Session management
- Logout functionality

### 2. Dashboard
- Total employees count
- Total payroll for current month
- Recent payroll processed
- Quick action buttons

### 3. Employee Management
- Add new employee (personal details, job details)
- Edit employee information
- Delete employee
- View employee list with search/filter
- Employee profile view

### 4. Salary Management
- Create salary grades
- Define allowances (HRA, DA, Conveyance, etc.)
- Define deductions (PF, Tax, Insurance, etc.)
- Assign salary grade to employees

### 5. Attendance Management
- Mark daily attendance
- View attendance calendar
- Attendance reports by month/employee

### 6. Payroll Processing
- Select month/year
- Calculate gross salary
- Apply allowances/deductions
- Calculate net salary
- Process payroll
- View payroll history

### 7. Payslip Generation
- Generate monthly payslip
- Print-friendly format
- Download/view payslips

### 8. Reports
- Monthly payroll summary
- Employee-wise salary report
- Allowance/deduction reports
- Export capabilities

## File Structure
```
PAYROLL_SYS/
├── index.php          # Login page
├── dashboard.php      # Main dashboard
├── employees.php      # Employee management
├── salary.php         # Salary grades & settings
├── attendance.php     # Attendance management
├── payroll.php        # Payroll processing
├── payslips.php       # Payslip generation
├── reports.php        # Reports & analytics
├── logout.php         # Logout handler
├── config.php         # Configuration
├── db.php             # Database setup
├── style.css          # Styles
└── script.js          # JavaScript
```

## Acceptance Criteria
1. ✓ User can login with admin credentials
2. ✓ Dashboard displays accurate statistics
3. ✓ Can add, edit, delete employees
4. ✓ Can configure salary grades and allowances/deductions
5. ✓ Can mark and view attendance
6. ✓ Can process monthly payroll
7. ✓ Can generate and print payslips
8. ✓ Can view various reports
9. ✓ UI is responsive and beautiful
10. ✓ All data persists in SQLite database
