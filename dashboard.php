<?php
require_once 'config.php';
require_once 'db.php';

requireLogin();

// Get dashboard statistics
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$totalDepartments = $pdo->query("SELECT COUNT(DISTINCT department) FROM employees WHERE department != ''")->fetchColumn();

// Get current month payroll
$currentMonth = date('F');
$currentYear = date('Y');
$stmt = $pdo->prepare("SELECT SUM(net_salary) as total FROM payroll WHERE payroll_month = ? AND payroll_year = ?");
$stmt->execute([$currentMonth, $currentYear]);
$monthlyPayroll = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get total employees processed this month
$processedEmployees = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE payroll_month = ? AND payroll_year = ?");
$processedEmployees->execute([$currentMonth, $currentYear]);
$processedCount = $processedEmployees->fetchColumn();

// Get recent payroll records
$recentPayroll = $pdo->query("
    SELECT p.*, e.first_name, e.last_name, e.employee_id, e.department 
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    ORDER BY p.processed_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent employees
$recentEmployees = $pdo->query("
    SELECT * FROM employees 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PayPro Payroll System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo getCsrfMeta(); ?>
</head>
<body>
    <div class="app-container">
        <?php $pageTitle = 'Dashboard'; include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Total Employees</h4>
                            <div class="stat-value"><?php echo number_format($totalEmployees); ?></div>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Departments</h4>
                            <div class="stat-value"><?php echo number_format($totalDepartments); ?></div>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon warning">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Monthly Payroll</h4>
                            <div class="stat-value"><?php echo formatCurrency($monthlyPayroll); ?></div>
                        </div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-icon info">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Processed</h4>
                            <div class="stat-value"><?php echo $processedCount; ?> / <?php echo $totalEmployees; ?></div>
                        </div>
                    </div>
                </div>

                <div class="report-grid">
                    <!-- Recent Payroll -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Recent Payroll</h3>
                            <a href="payroll.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentPayroll)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h4>No payroll records yet</h4>
                                    <p>Process payroll to see records here</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Department</th>
                                                <th>Net Salary</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentPayroll as $pay): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($pay['employee_id']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($pay['department'] ?? '-'); ?></td>
                                                    <td><strong><?php echo formatCurrency($pay['net_salary']); ?></strong></td>
                                                    <td><span class="badge badge-success"><?php echo htmlspecialchars($pay['status']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Employees -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-plus"></i> Recent Employees</h3>
                            <a href="employees.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentEmployees)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h4>No employees yet</h4>
                                    <p>Add employees to get started</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Department</th>
                                                <th>Designation</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentEmployees as $emp): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($emp['employee_id']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($emp['department'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($emp['designation'] ?? '-'); ?></td>
                                                    <td>
                                                        <?php if ($emp['status'] === 'active'): ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2">
                            <a href="employees.php?action=add" class="btn btn-success">
                                <i class="fas fa-user-plus"></i> Add Employee
                            </a>
                            <a href="payroll.php" class="btn btn-warning">
                                <i class="fas fa-calculator"></i> Process Payroll
                            </a>
                            <a href="payslips.php" class="btn btn-info">
                                <i class="fas fa-file-invoice"></i> Generate Payslips
                            </a>
                            <a href="reports.php" class="btn btn-primary">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>

<?php
?>

