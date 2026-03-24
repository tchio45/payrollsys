<?php
require_once 'config.php';
require_once 'db.php';

requireLogin();

// Get filter values
$month = $_GET['month'] ?? date('F');
$year = $_GET['year'] ?? date('Y');
$department = $_GET['department'] ?? '';

// Get departments for filter
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Payroll Summary by Month
$monthlySummary = $pdo->query("
    SELECT payroll_month, payroll_year, 
           COUNT(*) as employee_count,
           SUM(basic_salary) as total_basic,
           SUM(total_allowances) as total_allowances,
           SUM(total_deductions) as total_deductions,
           SUM(gross_salary) as total_gross,
           SUM(net_salary) as total_net
    FROM payroll 
    GROUP BY payroll_year, payroll_month
    ORDER BY payroll_year DESC, CASE payroll_month 
        WHEN 'January' THEN 1 
        WHEN 'February' THEN 2 
        WHEN 'March' THEN 3 
        WHEN 'April' THEN 4 
        WHEN 'May' THEN 5 
        WHEN 'June' THEN 6 
        WHEN 'July' THEN 7 
        WHEN 'August' THEN 8 
        WHEN 'September' THEN 9 
        WHEN 'October' THEN 10 
        WHEN 'November' THEN 11 
        WHEN 'December' THEN 12 
    END DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

// Employee-wise salary report
$employeeReport = $pdo->query("
    SELECT e.id, e.employee_id, e.first_name, e.last_name, e.department, e.designation,
           e.basic_salary, g.grade_name,
           (SELECT SUM(net_salary) FROM payroll WHERE employee_id = e.id) as total_paid
    FROM employees e
    LEFT JOIN salary_grades g ON e.salary_grade_id = g.id
    WHERE e.status = 'active'
    ORDER BY e.first_name, e.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Current month report
$currentMonthReport = $pdo->prepare("
    SELECT p.*, e.first_name, e.last_name, e.employee_id, e.department, e.designation
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    WHERE p.payroll_month = ? AND p.payroll_year = ?
    " . ($department ? "AND e.department = ?" : "") . "
    ORDER BY e.first_name
");
$params = [$month, $year];
if ($department) $params[] = $department;
$currentMonthReport->execute($params);
$monthlyRecords = $currentMonthReport->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalBasic = array_sum(array_column($monthlyRecords, 'basic_salary'));
$totalAllowances = array_sum(array_column($monthlyRecords, 'total_allowances'));
$totalDeductions = array_sum(array_column($monthlyRecords, 'total_deductions'));
$totalNet = array_sum(array_column($monthlyRecords, 'net_salary'));

// Department-wise summary
$deptSummary = $pdo->query("
    SELECT e.department,
           COUNT(DISTINCT e.id) as employee_count,
           SUM(p.basic_salary) as total_basic,
           SUM(p.net_salary) as total_net
    FROM employees e
    LEFT JOIN payroll p ON e.id = p.employee_id AND p.payroll_month = '$month' AND p.payroll_year = '$year'
    WHERE e.status = 'active' AND e.department != ''
    GROUP BY e.department
    ORDER BY e.department
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PayPro Payroll System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <span class="logo"><i class="fas fa-wallet"></i></span>
                <span>PayPro</span>
            </div>
            <nav class="sidebar-menu">
                <div class="menu-section">Main</div>
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="employees.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>
                <a href="salary.php" class="menu-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Salary & Grades</span>
                </a>
                <a href="attendance.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <div class="menu-section">Payroll</div>
                <a href="payroll.php" class="menu-item">
                    <i class="fas fa-calculator"></i>
                    <span>Payroll Processing</span>
                </a>
                <a href="payslips.php" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Payslips</span>
                </a>
                <div class="menu-section">Reports</div>
                <a href="reports.php" class="menu-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="toggle-btn"><i class="fas fa-bars"></i></button>
                    <h3>Reports & Analytics</h3>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                    <a href="logout.php" class="btn btn-sm btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <div class="page-content">
                <!-- Filter -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-filter"></i> Filter Reports</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="form-row">
                            <div class="form-group">
                                <label>Month</label>
                                <select name="month" class="form-control">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>" <?php echo $month === date('F', mktime(0, 0, 0, $m, 1)) ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Year</label>
                                <select name="year" class="form-control">
                                    <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department" class="form-control">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-search"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Monthly Summary Stats -->
                <div class="stats-grid mb-3">
                    <div class="stat-card primary">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Employees</h4>
                            <div class="stat-value"><?php echo count($monthlyRecords); ?></div>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon success">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Total Basic</h4>
                            <div class="stat-value"><?php echo formatCurrency($totalBasic); ?></div>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon warning">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Total Allowances</h4>
                            <div class="stat-value"><?php echo formatCurrency($totalAllowances); ?></div>
                        </div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon danger">
                            <i class="fas fa-minus-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Total Deductions</h4>
                            <div class="stat-value"><?php echo formatCurrency($totalDeductions); ?></div>
                        </div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-icon info">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Total Net Salary</h4>
                            <div class="stat-value"><?php echo formatCurrency($totalNet); ?></div>
                        </div>
                    </div>
                </div>

                <div class="report-grid">
                    <!-- Monthly Payroll Summary -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-alt"></i> Monthly Payroll Summary</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($monthlySummary)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-chart-bar"></i>
                                    <h4>No data available</h4>
                                    <p>Process payroll to see reports</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Month/Year</th>
                                                <th>Employees</th>
                                                <th>Basic Salary</th>
                                                <th>Net Salary</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($monthlySummary as $summary): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($summary['payroll_month']); ?> <?php echo $summary['payroll_year']; ?></strong></td>
                                                    <td><?php echo $summary['employee_count']; ?></td>
                                                    <td><?php echo formatCurrency($summary['total_basic']); ?></td>
                                                    <td><strong><?php echo formatCurrency($summary['total_net']); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Department Summary -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-building"></i> Department-wise Summary</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($deptSummary)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-building"></i>
                                    <h4>No department data</h4>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Employees</th>
                                                <th>Total Basic</th>
                                                <th>Total Net</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($deptSummary as $dept): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($dept['department'] ?? 'Unassigned'); ?></strong></td>
                                                    <td><?php echo $dept['employee_count']; ?></td>
                                                    <td><?php echo formatCurrency($dept['total_basic'] ?? 0); ?></td>
                                                    <td><strong><?php echo formatCurrency($dept['total_net'] ?? 0); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Employee Salary Report -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Employee Salary Report</h3>
                        <button class="btn btn-sm btn-primary" onclick="exportToCSV()">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employeeReport)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h4>No employee data</h4>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table data-table" id="employeeReportTable">
                                    <thead>
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Designation</th>
                                            <th>Salary Grade</th>
                                            <th>Basic Salary</th>
                                            <th>Total Paid</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employeeReport as $emp): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($emp['employee_id']); ?></strong></td>
                                                <td><strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($emp['department'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($emp['designation'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($emp['grade_name'] ?? '-'); ?></td>
                                                <td><?php echo formatCurrency($emp['basic_salary']); ?></td>
                                                <td><strong><?php echo formatCurrency($emp['total_paid'] ?? 0); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Month Details -->
                <?php if ($month && $year): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> <?php echo htmlspecialchars($month); ?> <?php echo $year; ?> - Detailed Report</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($monthlyRecords)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h4>No payroll processed for this month</h4>
                                <p>Process payroll to see detailed report</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Basic</th>
                                            <th>Allowances</th>
                                            <th>Deductions</th>
                                            <th>Net Salary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthlyRecords as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($record['employee_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['department'] ?? '-'); ?></td>
                                                <td><?php echo formatCurrency($record['basic_salary']); ?></td>
                                                <td class="text-success">+<?php echo formatCurrency($record['total_allowances']); ?></td>
                                                <td class="text-danger">-<?php echo formatCurrency($record['total_deductions']); ?></td>
                                                <td><strong><?php echo formatCurrency($record['net_salary']); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="font-weight: bold; background: #f8f9fa;">
                                            <td colspan="2">TOTAL</td>
                                            <td><?php echo formatCurrency($totalBasic); ?></td>
                                            <td class="text-success">+<?php echo formatCurrency($totalAllowances); ?></td>
                                            <td class="text-danger">-<?php echo formatCurrency($totalDeductions); ?></td>
                                            <td><?php echo formatCurrency($totalNet); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
    <script>
        function exportToCSV() {
            const table = document.getElementById('employeeReportTable');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                cols.forEach(col => {
                    rowData.push(col.innerText.replace(/₹/g, '').trim());
                });
                csv.push(rowData.join(','));
            });
            
            const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            const downloadLink = document.createElement('a');
            downloadLink.download = 'employee_salary_report.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();
        }
    </script>
</body>
</html>

<?php
?>

