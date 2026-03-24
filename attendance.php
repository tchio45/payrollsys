<?php
require_once 'config.php';
require_once 'db.php';

requireLogin();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_attendance') {
        $employee_id = $_POST['employee_id'];
        $attendance_date = $_POST['attendance_date'];
        $status = $_POST['status'];
        $overtime_hours = floatval($_POST['overtime_hours'] ?? 0);
        $late_hours = floatval($_POST['late_hours'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        
        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO attendance (employee_id, attendance_date, status, overtime_hours, late_hours, remarks) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $attendance_date, $status, $overtime_hours, $late_hours, $remarks]);
            $message = 'Attendance marked successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'bulk_attendance') {
        $attendance_date = $_POST['attendance_date'];
        $status = $_POST['status'];
        
        try {
            $employees = $pdo->query("SELECT id FROM employees WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO attendance (employee_id, attendance_date, status) VALUES (?, ?, ?)");
            
            foreach ($employees as $employee_id) {
                $stmt->execute([$employee_id, $attendance_date, $status]);
            }
            
            $message = 'Bulk attendance marked for all employees!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Lock In / Lock Out functionality
    if ($action === 'clock_in') {
        $employee_id = $_POST['employee_id'];
        $log_date = $_POST['log_date'];
        $clock_in_time = date('H:i:s');
        
        // Get employee's work start time to determine status
        $empStmt = $pdo->prepare("SELECT work_start_time FROM employees WHERE id = ?");
        $empStmt->execute([$employee_id]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        $workStartTime = $employee['work_start_time'] ?? '09:00:00';
        $clockInStatus = 'ontime';
        
        if (strtotime($clock_in_time) > strtotime($workStartTime)) {
            $clockInStatus = 'late';
        } elseif (strtotime($clock_in_time) < strtotime($workStartTime . ' -30 minutes')) {
            $clockInStatus = 'early';
        }
        
        try {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO attendance_logs (employee_id, log_date, clock_in, clock_in_status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employee_id, $log_date, $clock_in_time, $clockInStatus]);
            $message = 'Clock In recorded successfully at ' . date('h:i A', strtotime($clock_in_time)) . '!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'clock_out') {
        $employee_id = $_POST['employee_id'];
        $log_date = $_POST['log_date'];
        $clock_out_time = date('H:i:s');
        
        // Get employee's work end time to determine status
        $empStmt = $pdo->prepare("SELECT work_end_time FROM employees WHERE id = ?");
        $empStmt->execute([$employee_id]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        $workEndTime = $employee['work_end_time'] ?? '18:00:00';
        $clockOutStatus = 'ontime';
        
        if (strtotime($clock_out_time) < strtotime($workEndTime)) {
            $clockOutStatus = 'early';
        } elseif (strtotime($clock_out_time) > strtotime($workEndTime . ' +30 minutes')) {
            $clockOutStatus = 'overtime';
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE attendance_logs SET clock_out = ?, clock_out_status = ? WHERE employee_id = ? AND log_date = ?");
            $stmt->execute([$clock_out_time, $clockOutStatus, $employee_id, $log_date]);
            $message = 'Clock Out recorded successfully at ' . date('h:i A', strtotime($clock_out_time)) . '!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Generate monthly attendance for all employees
    if ($action === 'generate_monthly') {
        $month = $_POST['month'];
        $year = $_POST['year'];
        
        try {
            // Get all active employees
            $employees = $pdo->query("SELECT * FROM employees WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
            
            // Get working days for the month
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $workingDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            
            $generatedCount = 0;
            
            foreach ($employees as $emp) {
                $empWorkingDays = json_decode($emp['working_days'] ?? '["Monday","Tuesday","Wednesday","Thursday","Friday"]', true) ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $dayName = date('l', strtotime($date));
                    
                    // Check if it's a working day for this employee
                    if (in_array($dayName, $empWorkingDays)) {
                        // Check if record doesn't exist
                        $checkStmt = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                        $checkStmt->execute([$emp['id'], $date]);
                        
                        if (!$checkStmt->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status) VALUES (?, ?, 'present')");
                            $stmt->execute([$emp['id'], $date]);
                            $generatedCount++;
                        }
                    }
                }
            }
            
            $message = "Monthly attendance generated! $generatedCount records created for " . date('F', mktime(0, 0, 0, $month, 1)) . " $year";
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Attendance record deleted!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get employees for dropdown
$employees = $pdo->query("SELECT * FROM employees WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get attendance records
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$emp_filter = $_GET['employee_id'] ?? '';

$sql = "SELECT a.*, e.first_name, e.last_name, e.employee_id, e.department, e.designation 
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.id 
        WHERE strftime('%m', a.attendance_date) = ? AND strftime('%Y', a.attendance_date) = ?";

$params = [$month, $year];

if ($emp_filter) {
    $sql .= " AND a.employee_id = ?";
    $params[] = $emp_filter;
}

$sql .= " ORDER BY a.attendance_date DESC, e.first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalPresent = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'present'));
$totalAbsent = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'absent'));
$totalLeave = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'leave'));

// Handle leave request actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_request' || $_POST['action'] === 'reject_request') {
        $request_id = $_POST['request_id'];
        $admin_response = trim($_POST['admin_response'] ?? '');
        $status = $_POST['action'] === 'approve_request' ? 'approved' : 'rejected';
        
        try {
            // Update the request status
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, admin_response = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$status, $admin_response, $request_id]);
            
            // If approved, create attendance records
            if ($status === 'approved') {
                // Get the leave request details
                $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($leaveRequest) {
                    $emp_id = $leaveRequest['employee_id'];
                    $startDate = new DateTime($leaveRequest['start_date']);
                    $endDate = new DateTime($leaveRequest['end_date']);
                    
                    // Determine attendance status based on request type
                    $attStatus = $leaveRequest['request_type'] === 'lateness' ? 'present' : 'leave';
                    
                    // Create attendance records for each day
                    $interval = new DateInterval('P1D');
                    $dateRange = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
                    
                    foreach ($dateRange as $date) {
                        $attDate = $date->format('Y-m-d');
                        $stmt = $pdo->prepare("INSERT OR REPLACE INTO attendance (employee_id, attendance_date, status, remarks) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$emp_id, $attDate, $attStatus, 'Approved leave request: ' . $leaveRequest['reason']]);
                    }
                }
            }
            
            $message = 'Leave request ' . $status . ' successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get pending leave requests
$pendingRequests = $pdo->query("
    SELECT lr.*, e.first_name, e.last_name, e.employee_id, e.department 
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.status = 'pending' 
    ORDER BY lr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all leave requests
$allRequests = $pdo->query("
    SELECT lr.*, e.first_name, e.last_name, e.employee_id, e.department 
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    ORDER BY lr.created_at DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - PayPro Payroll System</title>
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
                <a href="attendance.php" class="menu-item active">
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
                <a href="reports.php" class="menu-item">
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
                    <h3>Attendance Management</h3>
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
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="stats-grid mb-3">
                    <div class="stat-card success">
                        <div class="stat-icon success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Present</h4>
                            <div class="stat-value"><?php echo $totalPresent; ?></div>
                        </div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon danger">
                            <i class="fas fa-times"></i>
                        </div>
                        <div class="stat-content">
                            <h4>Absent</h4>
                            <div class="stat-value"><?php echo $totalAbsent; ?></div>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon warning">
                            <i class="fas fa-calendar-minus"></i>
                        </div>
                        <div class="stat-content">
                            <h4>On Leave</h4>
                            <div class="stat-value"><?php echo $totalLeave; ?></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-check"></i> Mark Attendance</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-row">
                            <input type="hidden" name="action" value="mark_attendance">
                            <div class="form-group">
                                <label>Employee</label>
                                <select name="employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="attendance_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="leave">On Leave</option>
                                    <option value="half_day">Half Day</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Overtime Hours</label>
                                <input type="number" name="overtime_hours" class="form-control" step="0.5" min="0" placeholder="0">
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-save"></i> Mark Attendance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Bulk Attendance</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-row">
                            <input type="hidden" name="action" value="bulk_attendance">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="attendance_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Status for All</label>
                                <select name="status" class="form-control" required>
                                    <option value="present">All Present</option>
                                    <option value="absent">All Absent</option>
                                    <option value="leave">All On Leave</option>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-warning" style="width: 100%;">
                                    <i class="fas fa-users-cog"></i> Mark All Employees
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lock In / Lock Out Section -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Lock In / Lock Out (Daily Time Tracking)</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-row">
                            <input type="hidden" name="action" value="clock_in">
                            <div class="form-group">
                                <label>Employee</label>
                                <select name="employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="log_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-sign-in-alt"></i> Lock In (Clock In)
                                </button>
                            </div>
                        </form>
                        
                        <hr style="margin: 20px 0;">
                        
                        <form method="POST" action="" class="form-row">
                            <input type="hidden" name="action" value="clock_out">
                            <div class="form-group">
                                <label>Employee</label>
                                <select name="employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="log_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-danger" style="width: 100%;">
                                    <i class="fas fa-sign-out-alt"></i> Lock Out (Clock Out)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Generate Monthly Attendance -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-month"></i> Generate Monthly Attendance</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-row">
                            <input type="hidden" name="action" value="generate_monthly">
                            <div class="form-group">
                                <label>Month</label>
                                <select name="month" class="form-control" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Year</label>
                                <select name="year" class="form-control" required>
                                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-info" style="width: 100%;">
                                    <i class="fas fa-magic"></i> Generate Attendance
                                </button>
                            </div>
                        </form>
                        <p style="margin-top: 10px; font-size: 12px; color: var(--text-secondary);">
                            <i class="fas fa-info-circle"></i> This will create attendance records for all active employees for each working day of the selected month.
                        </p>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Attendance Records</h3>
                        <form method="GET" action="" class="d-flex gap-1">
                            <select name="month" class="form-control" style="width: auto;">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" class="form-control" style="width: auto;">
                                <?php for ($y = date('Y') - 1; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="employee_id" class="form-control" style="width: auto;">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo $emp_filter == $emp['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendanceRecords)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h4>No attendance records</h4>
                                <p>Mark attendance to see records here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Overtime</th>
                                            <th>Remarks</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendanceRecords as $record): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($record['attendance_date'])); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($record['employee_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['department'] ?? '-'); ?></td>
                                                <td>
                                                    <?php 
                                                        $statusClass = [
                                                            'present' => 'success',
                                                            'absent' => 'danger',
                                                            'leave' => 'warning',
                                                            'half_day' => 'info'
                                                        ];
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass[$record['status']] ?? 'primary'; ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $record['overtime_hours'] > 0 ? $record['overtime_hours'] . ' hrs' : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($record['remarks'] ?? '-'); ?></td>
                                                <td class="actions">
                                                    <button class="btn btn-sm btn-danger" onclick="deleteAttendance(<?php echo $record['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                        </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Leave Requests -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope-open-text"></i> Pending Leave Requests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingRequests)): ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h4>No pending requests</h4>
                                <p>All leave requests have been processed</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Type</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Days</th>
                                            <th>Reason</th>
                                            <th>Submitted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingRequests as $request): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($request['employee_id']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $request['request_type'] === 'absence' ? 'danger' : 'warning'; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($request['start_date'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($request['end_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($request['number_of_days']); ?></td>
                                                <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                                <td><?php echo date('d M Y H:i', strtotime($request['created_at'])); ?></td>
                                                <td class="actions">
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="approve_request">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <input type="hidden" name="admin_response" value="Approved by admin">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this request?');">
                                                        <input type="hidden" name="action" value="reject_request">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <input type="hidden" name="admin_response" value="Rejected by admin">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Reject">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- All Leave Requests History -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Leave Request History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($allRequests)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h4>No leave requests</h4>
                                <p>Leave requests will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Type</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Days</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Admin Response</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allRequests as $request): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($request['employee_id']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $request['request_type'] === 'absence' ? 'danger' : 'warning'; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($request['start_date'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($request['end_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($request['number_of_days']); ?></td>
                                                <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                                <td>
                                                    <?php 
                                                        $statusClass = '';
                                                        switch($request['status']) {
                                                            case 'pending': $statusClass = 'badge-warning'; break;
                                                            case 'approved': $statusClass = 'badge-success'; break;
                                                            case 'rejected': $statusClass = 'badge-danger'; break;
                                                            default: $statusClass = 'badge-info';
                                                        }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($request['status']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($request['admin_response'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>

<?php
function formatCurrency($amount) {
    return '₹' . number_format($amount ?? 0, 2);
}
?>
