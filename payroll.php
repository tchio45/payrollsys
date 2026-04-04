<?php
require_once 'config.php';
require_once 'db.php';

requireLogin();
if (!isAdmin()) { header('Location: my_profile.php'); exit; }

$message = '';
$messageType = '';

// Handle AJAX requests (before any HTML output)
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT p.*, e.first_name, e.last_name, e.employee_id, e.department, e.designation
        FROM payroll p
        JOIN employees e ON p.employee_id = e.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'payroll' => $payroll]);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'process') {
        $month = $_POST['month'];
        $year = $_POST['year'];
        
        // Get all active employees
        $employees = $pdo->query("SELECT * FROM employees WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
        
        // Get allowances and deductions
        $allowances = $pdo->query("SELECT * FROM allowances WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
        $deductions = $pdo->query("SELECT * FROM deductions WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
        
        $processed = 0;
        $errors = [];
        
        foreach ($employees as $employee) {
            try {
                $basic_salary = floatval($employee['basic_salary']);
                
                // Check if payroll already exists for this employee/month/year
                $checkStmt = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND payroll_month = ? AND payroll_year = ?");
                $checkStmt->execute([$employee['id'], $month, $year]);
                if ($checkStmt->fetch()) {
                    // Update existing
                    $errors[] = "Payroll already exists for {$employee['first_name']} {$employee['last_name']}";
                    continue;
                }
                
                // Calculate total allowances
                $total_allowances = 0;
                $allowance_breakdown = [];
                foreach ($allowances as $allowance) {
                    $allowance_amount = 0;
                    if ($allowance['amount_type'] === 'amount') {
                        $allowance_amount = floatval($allowance['amount']);
                    } elseif ($allowance['amount_type'] === 'percentage') {
                        $allowance_amount = ($basic_salary * floatval($allowance['percentage_of_basic'])) / 100;
                    }
                    $total_allowances += $allowance_amount;
                    $allowance_breakdown[] = $allowance_amount;
                }
                
                // Calculate total deductions
                $total_deductions = 0;
                $deduction_breakdown = [];
                foreach ($deductions as $deduction) {
                    $deduction_amount = 0;
                    if ($deduction['amount_type'] === 'amount') {
                        $deduction_amount = floatval($deduction['amount']);
                    } elseif ($deduction['amount_type'] === 'percentage') {
                        $deduction_amount = ($basic_salary * floatval($deduction['percentage_of_basic'])) / 100;
                    }
                    $total_deductions += $deduction_amount;
                    $deduction_breakdown[] = $deduction_amount;
                }
                
                // Convert month name to number for attendance query
                $monthNumber = str_pad(date('m', strtotime("1 $month $year")), 2, '0', STR_PAD_LEFT);
                $yearStr = strval($year);

                // Calculate actual working days for this employee in this month
                $empWorkingDays = json_decode($employee['working_days'] ?? '["Monday","Tuesday","Wednesday","Thursday","Friday"]', true)
                    ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, intval($monthNumber), intval($year));
                $actual_working_days = 0;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayName = date('l', mktime(0, 0, 0, intval($monthNumber), $d, intval($year)));
                    if (in_array($dayName, $empWorkingDays)) {
                        $actual_working_days++;
                    }
                }

                // Get attendance for the month
                $attendanceStmt = $pdo->prepare("
                    SELECT COUNT(*) as days_worked, SUM(overtime_hours) as overtime
                    FROM attendance
                    WHERE employee_id = ?
                    AND strftime('%m', attendance_date) = ?
                    AND strftime('%Y', attendance_date) = ?
                    AND status = 'present'
                ");
                $attendanceStmt->execute([$employee['id'], $monthNumber, $yearStr]);
                $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
                
                $days_worked = intval($attendance['days_worked'] ?? 0);
                $overtime_hours = floatval($attendance['overtime'] ?? 0);
                
                // Calculate late and early leave deductions from attendance logs
                $late_deduction = 0;
                $early_leave_deduction = 0;
                $late_deduction_rate = 100; // 100 FCAFA per hour late
                $early_leave_deduction_rate = 50; // 50 FCAFA per hour early leave
                
                // Get employee's work schedule
                $work_start_time = $employee['work_start_time'] ?? '09:00:00';
                $work_end_time = $employee['work_end_time'] ?? '18:00:00';
                
                // Get attendance logs for the month to calculate late/early deductions
                $logsStmt = $pdo->prepare("
                    SELECT clock_in, clock_in_status, clock_out, clock_out_status
                    FROM attendance_logs
                    WHERE employee_id = ?
                    AND strftime('%m', log_date) = ?
                    AND strftime('%Y', log_date) = ?
                ");
                $logsStmt->execute([$employee['id'], $monthNumber, $yearStr]);
                $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($logs as $log) {
                    // Calculate late deduction (if clock_in_status is 'late')
                    if (!empty($log['clock_in']) && $log['clock_in_status'] === 'late') {
                        $clockInTime = strtotime($log['clock_in']);
                        $scheduledStart = strtotime($work_start_time);
                        $minutesLate = ($clockInTime - $scheduledStart) / 60;
                        $hoursLate = ceil($minutesLate / 60); // Round up to nearest hour
                        if ($hoursLate > 0) {
                            $late_deduction += $hoursLate * $late_deduction_rate;
                        }
                    }
                    
                    // Calculate early leave deduction (if clock_out_status is 'early')
                    if (!empty($log['clock_out']) && $log['clock_out_status'] === 'early') {
                        $clockOutTime = strtotime($log['clock_out']);
                        $scheduledEnd = strtotime($work_end_time);
                        $minutesEarly = ($scheduledEnd - $clockOutTime) / 60;
                        $hoursEarly = ceil($minutesEarly / 60); // Round up to nearest hour
                        if ($hoursEarly > 0) {
                            $early_leave_deduction += $hoursEarly * $early_leave_deduction_rate;
                        }
                    }
                }
                
                // Add time-based deductions to total deductions
                $total_deductions += $late_deduction + $early_leave_deduction;
                
                // Calculate overtime amount (assuming 100 per hour)
                $overtime_rate = 100;
                $overtime_amount = $overtime_hours * $overtime_rate;
                
                // Calculate gross and net salary
                $gross_salary = $basic_salary + $total_allowances + $overtime_amount;
                $net_salary = $gross_salary - $total_deductions;
                
                // Insert payroll record
                $insertStmt = $pdo->prepare("
                    INSERT INTO payroll (
                        employee_id, payroll_month, payroll_year, basic_salary,
                        total_allowances, total_deductions, gross_salary, net_salary,
                        working_days, days_worked, overtime_hours, overtime_amount,
                        late_deduction, early_leave_deduction,
                        status, processed_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processed', ?)
                ");
                $insertStmt->execute([
                    $employee['id'], $month, $year, $basic_salary,
                    $total_allowances, $total_deductions, $gross_salary, $net_salary,
                    $actual_working_days, $days_worked, $overtime_hours, $overtime_amount,
                    $late_deduction, $early_leave_deduction,
                    $_SESSION['user_id']
                ]);
                
                $processed++;
                
            } catch (PDOException $e) {
                $errors[] = "Error processing {$employee['first_name']}: " . $e->getMessage();
            }
        }
        
        if ($processed > 0) {
            $message = "Successfully processed payroll for $processed employees!";
            $messageType = 'success';
        } else {
            $message = "No employees were processed. " . implode(', ', $errors);
            $messageType = 'warning';
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Payroll record deleted!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get payroll records
$allPayrollRecords = $pdo->query("
    SELECT p.*, e.first_name, e.last_name, e.employee_id, e.department, e.designation
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    ORDER BY p.payroll_year DESC, p.payroll_month DESC, e.first_name
")->fetchAll(PDO::FETCH_ASSOC);
$payPagination = paginate($allPayrollRecords, 15);
$payrollRecords = $payPagination['data'];

// Calculate totals (use all records, not paginated)
$totalPayroll = array_sum(array_column($allPayrollRecords, 'net_salary'));

// Get current month/year for display
$currentMonth = date('F');
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Processing - PayPro Payroll System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo getCsrfMeta(); ?>
</head>
<body>
    <div class="app-container">
        <?php $pageTitle = 'Payroll Processing'; include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="page-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Process Payroll -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-calculator"></i> Process Monthly Payroll</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-row">
                            <?php echo getCsrfField(); ?>
                            <input type="hidden" name="action" value="process">
                            <div class="form-group">
                                <label>Select Month</label>
                                <select name="month" class="form-control" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>" <?php echo date('F') === date('F', mktime(0, 0, 0, $m, 1)) ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Select Year</label>
                                <select name="year" class="form-control" required>
                                    <?php for ($y = date('Y') - 1; $y <= date('Y'); $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo date('Y') == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-cog"></i> Process Payroll
                                </button>
                            </div>
                        </form>
                        <p class="text-muted mt-2">
                            <i class="fas fa-info-circle"></i> 
                            This will calculate salaries for all active employees based on their basic salary, allowances, deductions, and attendance.
                        </p>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="stats-grid mb-3">
                    <div class="stat-card primary">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Total Records</h4>
                            <div class="stat-value"><?php echo count($payrollRecords); ?></div>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon success">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Total Payroll</h4>
                            <div class="stat-value"><?php echo formatCurrency($totalPayroll); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Payroll Records -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Payroll History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payrollRecords)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calculator"></i>
                                <h4>No payroll records</h4>
                                <p>Process payroll to see records here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Month/Year</th>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Basic Salary</th>
                                            <th>Allowances</th>
                                            <th>Deductions</th>
                                            <th>Net Salary</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payrollRecords as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($record['payroll_month']); ?></strong>
                                                    <br><small><?php echo $record['payroll_year']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($record['employee_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['department'] ?? '-'); ?></td>
                                                <td><?php echo formatCurrency($record['basic_salary']); ?></td>
                                                <td class="text-success">+<?php echo formatCurrency($record['total_allowances']); ?></td>
                                                <td class="text-danger">-<?php echo formatCurrency($record['total_deductions']); ?></td>
                                                <td><strong><?php echo formatCurrency($record['net_salary']); ?></strong></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $record['status'] === 'processed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="actions">
                                                    <button class="btn btn-sm btn-info" onclick="viewPayrollDetails(<?php echo $record['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deletePayroll(<?php echo $record['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php renderPagination($payPagination); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Payroll Details Modal -->
    <div class="modal-overlay" id="payrollDetailsModal">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Payroll Details</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('payrollDetailsModal'))">&times;</button>
            </div>
            <div class="modal-body" id="payrollDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function viewPayrollDetails(id) {
            fetch(`payroll.php?action=get&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const p = data.payroll;
                        document.getElementById('payrollDetailsContent').innerHTML = `
                            <div class="payslip-section">
                                <h4>Employee Information</h4>
                                <div class="payslip-row">
                                    <span>Employee Name:</span>
                                    <span>${p.first_name} ${p.last_name}</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Employee ID:</span>
                                    <span>${p.employee_id}</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Department:</span>
                                    <span>${p.department || '-'}</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Designation:</span>
                                    <span>${p.designation || '-'}</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Payroll Month:</span>
                                    <span>${p.payroll_month} ${p.payroll_year}</span>
                                </div>
                            </div>
                            <div class="payslip-section">
                                <h4>Earnings</h4>
                                <div class="payslip-row">
                                    <span>Basic Salary:</span>
                                    <span>${formatCurrency(p.basic_salary)}</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Total Allowances:</span>
                                    <span>${formatCurrency(p.total_allowances)}</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Overtime Amount:</span>
                                    <span>${formatCurrency(p.overtime_amount)}</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Working Days:</span>
                                    <span>${p.working_days} days</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Days Worked:</span>
                                    <span>${p.days_worked} days</span>
                                </div>
                            </div>
                            <div class="payslip-section">
                                <h4>Deductions</h4>
                                <div class="payslip-row">
                                    <span>Total Deductions:</span>
                                    <span>${formatCurrency(p.total_deductions)}</span>
                                </div>
                            </div>
                            <div class="payslip-section">
                                <div class="payslip-row total">
                                    <span>Net Salary:</span>
                                    <span>${formatCurrency(p.net_salary)}</span>
                                </div>
                            </div>
                        `;
                        openModal('payrollDetailsModal');
                    }
                });
        }

        function deletePayroll(id) {
            if (confirm('Are you sure you want to delete this payroll record?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                formData.append('csrf_token', getCsrfToken());

                fetch('payroll.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        setTimeout(() => window.location.reload(), 1500);
                    }
                });
            }
        }
    </script>
</body>
</html>

