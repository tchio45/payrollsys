<?php
require_once 'config.php';
require_once 'db.php';

requireLogin();

// Only employees can access this page
if (!isEmployee()) {
    header('Location: dashboard.php');
    exit;
}

$employeeId = getLoggedInEmployeeId();
$message = '';
$messageType = '';

// CSRF validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
}

// Handle clock-in (presence detector)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clock_in') {
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    // Get employee work schedule
    $stmt = $pdo->prepare("SELECT work_start_time FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $workStartTime = $stmt->fetchColumn();
    
    // Determine if late or on-time
    $clockInStatus = 'ontime';
    if ($currentTime > $workStartTime) {
        // Check if it's within 30 minutes tolerance
        $startTimestamp = strtotime($today . ' ' . $workStartTime);
        $currentTimestamp = strtotime($today . ' ' . $currentTime);
        $minutesLate = ($currentTimestamp - $startTimestamp) / 60;
        
        if ($minutesLate <= 30) {
            $clockInStatus = 'ontime';
        } else {
            $clockInStatus = 'late';
        }
    } else if ($currentTime < $workStartTime) {
        $clockInStatus = 'early';
    }
    
    try {
        // Check if already clocked in today
        $stmt = $pdo->prepare("SELECT id FROM attendance_logs WHERE employee_id = ? AND log_date = ?");
        $stmt->execute([$employeeId, $today]);
        
        if ($stmt->fetch()) {
            $message = 'You have already clocked in today!';
            $messageType = 'warning';
        } else {
            // Insert clock-in record
            $stmt = $pdo->prepare("INSERT INTO attendance_logs (employee_id, log_date, clock_in, clock_in_status, device_info) VALUES (?, ?, ?, ?, ?)");
            $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
            $stmt->execute([$employeeId, $today, $currentTime, $clockInStatus, $deviceInfo]);
            
            // Also create/update attendance record
            $status = ($clockInStatus === 'late') ? 'present' : 'present';
            $lateHours = ($clockInStatus === 'late') ? 0.5 : 0;
            
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO attendance (employee_id, attendance_date, status, late_hours) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employeeId, $today, $status, $lateHours]);
            
            $message = $clockInStatus === 'late' ? 'Clocked in successfully! You are late.' : 'Clocked in successfully!';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error clocking in: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle clock-out (presence detector)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clock_out') {
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    // Get employee work schedule
    $stmt = $pdo->prepare("SELECT work_end_time FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $workEndTime = $stmt->fetchColumn();
    
    // Determine if overtime, early, or on-time
    $clockOutStatus = 'ontime';
    if ($currentTime > $workEndTime) {
        $clockOutStatus = 'overtime';
    } else if ($currentTime < $workEndTime) {
        $clockOutStatus = 'early';
    }
    
    try {
        // Check if clocked in today
        $stmt = $pdo->prepare("SELECT clock_in FROM attendance_logs WHERE employee_id = ? AND log_date = ?");
        $stmt->execute([$employeeId, $today]);
        $clockInTime = $stmt->fetchColumn();
        
        if (!$clockInTime) {
            $message = 'Please clock in first before clocking out!';
            $messageType = 'warning';
        } else {
            // Update clock-out record
            $stmt = $pdo->prepare("UPDATE attendance_logs SET clock_out = ?, clock_out_status = ? WHERE employee_id = ? AND log_date = ?");
            $stmt->execute([$currentTime, $clockOutStatus, $employeeId, $today]);
            
            // Calculate overtime hours
            $overtimeHours = 0;
            if ($clockOutStatus === 'overtime') {
                $endTimestamp = strtotime($today . ' ' . $workEndTime);
                $currentTimestamp = strtotime($today . ' ' . $currentTime);
                $overtimeHours = ($currentTimestamp - $endTimestamp) / 3600;
            }
            
            // Update attendance record with overtime
            $stmt = $pdo->prepare("UPDATE attendance SET overtime_hours = ? WHERE employee_id = ? AND attendance_date = ?");
            $stmt->execute([$overtimeHours, $employeeId, $today]);
            
            $message = $clockOutStatus === 'overtime' ? 'Clocked out successfully! You have overtime.' : 'Clocked out successfully!';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error clocking out: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle form submissions for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $date_of_birth = $_POST['date_of_birth'];
    
    try {
        $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, phone = ?, address = ?, date_of_birth = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $phone, $address, $date_of_birth, $employeeId]);
        $message = 'Profile updated successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error updating profile: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'emp_' . $employeeId . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                // Delete old profile picture if exists
                $stmt = $pdo->prepare("SELECT profile_picture FROM employees WHERE id = ?");
                $stmt->execute([$employeeId]);
                $oldPicture = $stmt->fetchColumn();
                
                if ($oldPicture && file_exists($oldPicture)) {
                    unlink($oldPicture);
                }
                
                // Update profile picture path in database
                $stmt = $pdo->prepare("UPDATE employees SET profile_picture = ? WHERE id = ?");
                $stmt->execute([$targetPath, $employeeId]);
                
                $message = 'Profile picture uploaded successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error uploading file. Please try again.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Invalid file type. Please upload JPG, PNG, GIF, or WebP.';
            $messageType = 'danger';
        }
    } else {
        $message = 'Please select a file to upload.';
        $messageType = 'danger';
    }
}

// Get employee details
$stmt = $pdo->prepare("
    SELECT e.*, g.grade_name, g.basic_salary as grade_basic_salary 
    FROM employees e 
    LEFT JOIN salary_grades g ON e.salary_grade_id = g.id 
    WHERE e.id = ?
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Get employee's recent payroll records
$stmt = $pdo->prepare("
    SELECT * FROM payroll 
    WHERE employee_id = ? 
    ORDER BY payroll_year DESC, payroll_month DESC 
    LIMIT 6
");
$stmt->execute([$employeeId]);
$payrollRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee's allowances
$stmt = $pdo->prepare("
    SELECT ea.*, a.allowance_name, a.allowance_type 
    FROM employee_allowances ea 
    JOIN allowances a ON ea.allowance_id = a.id 
    WHERE ea.employee_id = ?
");
$stmt->execute([$employeeId]);
$allowances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee's deductions
$stmt = $pdo->prepare("
    SELECT ed.*, d.deduction_name, d.deduction_type 
    FROM employee_deductions ed 
    JOIN deductions d ON ed.deduction_id = d.id 
    WHERE ed.employee_id = ?
");
$stmt->execute([$employeeId]);
$deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent attendance
$stmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE employee_id = ? 
    ORDER BY attendance_date DESC 
    LIMIT 10
");
$stmt->execute([$employeeId]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle leave request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_leave_request') {
    $request_type = $_POST['request_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $number_of_days = floatval($_POST['number_of_days']);
    $reason = trim($_POST['reason']);
    
    if (empty($request_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        $message = 'Please fill in all required fields.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, request_type, start_date, end_date, number_of_days, reason) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$employeeId, $request_type, $start_date, $end_date, $number_of_days, $reason]);
            $message = 'Leave request submitted successfully!';
            $messageType = 'success';
            
            // Send notification to admin
            $employeeName = $employee['first_name'] . ' ' . $employee['last_name'];
            sendAdminNotification($employeeName, $request_type, $start_date, $end_date, $reason);
        } catch (PDOException $e) {
            $message = 'Error submitting leave request: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get current user details for account settings
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.*, e.employee_id FROM users u LEFT JOIN employees e ON u.employee_id = e.id WHERE u.id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle username change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_username') {
    $old_password = $_POST['old_password'] ?? '';
    $new_username = trim($_POST['new_username']);
    
    if (empty($old_password) || empty($new_username)) {
        $message = 'All fields are required.';
        $messageType = 'danger';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $new_username)) {
        $message = 'Username must be 3-20 alphanumeric characters.';
        $messageType = 'danger';
    } elseif (!password_verify($old_password, $currentUser['password'])) {
        $message = 'Current password is incorrect.';
        $messageType = 'danger';
    } else {
        // Check if new username is unique (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$new_username, $userId]);
        if ($stmt->fetch()) {
            $message = 'Username already taken.';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->execute([$new_username, $userId]);
                
                // Update session
                $_SESSION['username'] = $new_username;
                $message = 'Username updated successfully!';
                $messageType = 'success';
                $currentUser['username'] = $new_username; // Refresh
            } catch (PDOException $e) {
                $message = 'Error updating username.';
                $messageType = 'danger';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'All fields are required.';
        $messageType = 'danger';
    } elseif (!password_verify($old_password, $currentUser['password'])) {
        $message = 'Current password is incorrect.';
        $messageType = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
        $messageType = 'danger';
    } elseif (strlen($new_password) < 8) {
        $message = 'New password must be at least 8 characters long.';
        $messageType = 'danger';
    } else {
        try {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            $message = 'Password updated successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error updating password.';
            $messageType = 'danger';
        }
    }
}

// Get employee's leave requests
$stmt = $pdo->prepare("
    SELECT * FROM leave_requests 
    WHERE employee_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$employeeId]);
$leaveRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatDate($date) {
    return date('d M Y', strtotime($date));
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PayPro Payroll System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo getCsrfMeta(); ?>
</head>
<body>
    <div class="app-container">
        <?php $pageTitle = 'My Profile'; include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <!-- Page Content -->
            <div class="page-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Employee Info Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                        <button class="btn btn-sm btn-primary" onclick="openModal('editProfileModal')">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="profile-header">
                            <div class="profile-avatar-large">
                                <?php if (!empty($employee['profile_picture']) && file_exists($employee['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($employee['profile_picture']); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="profile-upload">
                                <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
                                    <?php echo getCsrfField(); ?>
                                    <input type="hidden" name="action" value="upload_photo">
                                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display: none;" onchange="this.form.submit()">
                                    <label for="profile_picture" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </label>
                                </form>
                            </div>
                        </div>
                        <div class="profile-grid">
                            <div class="profile-item">
                                <label>Employee ID</label>
                                <div class="profile-value"><?php echo htmlspecialchars($employee['employee_id']); ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Full Name</label>
                                <div class="profile-value"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Email</label>
                                <div class="profile-value"><?php echo htmlspecialchars($employee['email'] ?? '-'); ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Phone</label>
                                <div class="profile-value"><?php echo htmlspecialchars($employee['phone'] ?? '-'); ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Date of Birth</label>
                                <div class="profile-value"><?php echo $employee['date_of_birth'] ? formatDate($employee['date_of_birth']) : '-'; ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Gender</label>
                                <div class="profile-value"><?php echo htmlspecialchars(ucfirst($employee['gender'] ?? '-')); ?></div>
                            </div>
                            <div class="profile-item full-width">
                                <label>Address</label>
                                <div class="profile-value"><?php echo htmlspecialchars($employee['address'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Job Details Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-briefcase"></i> Job Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="profile-grid">
                            <div class="profile-item">
                                <label>Department</label>
                                <div class="profile-value"><?php echo htmlspecialchars($employee['department'] ?? '-'); ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Designation</label>
                                <div class="profile-value"><?php echo htmlspecialchars($employee['designation'] ?? '-'); ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Salary Grade</label>
                                <div class="profile-value"><?php echo htmlspecialchars($employee['grade_name'] ?? '-'); ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Basic Salary</label>
                                <div class="profile-value"><?php echo formatCurrency($employee['basic_salary']); ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Join Date</label>
                                <div class="profile-value"><?php echo $employee['join_date'] ? formatDate($employee['join_date']) : '-'; ?></div>
                            </div>
                            <div class="profile-item">
                                <label>Status</label>
                                <div class="profile-value">
                                    <?php if ($employee['status'] === 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Tracker Card - Presence Detector -->
                <?php 
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND log_date = ?");
                $stmt->execute([$employeeId, $today]);
                $todayLog = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get working days
                $workingDays = json_decode($employee['working_days'] ?? '[]', true);
                $currentDay = date('l');
                $isWorkingDay = in_array($currentDay, $workingDays);
                ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Attendance Tracker (Presence Detector)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$isWorkingDay): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle"></i> Today (<?php echo $currentDay; ?>) is not a scheduled working day for you.
                            </div>
                        <?php endif; ?>
                        
                        <div class="attendance-tracker">
                            <div class="tracker-info">
                                <div class="tracker-item">
                                    <label>Work Schedule</label>
                                    <div class="tracker-value">
                                        <?php echo htmlspecialchars($employee['work_start_time'] ?? '09:00'); ?> - <?php echo htmlspecialchars($employee['work_end_time'] ?? '18:00'); ?>
                                    </div>
                                </div>
                                <div class="tracker-item">
                                    <label>Working Days</label>
                                    <div class="tracker-value">
                                        <?php echo implode(', ', $workingDays); ?>
                                    </div>
                                </div>
                                <div class="tracker-item">
                                    <label>Employee Type</label>
                                    <div class="tracker-value">
                                        <span class="badge badge-<?php echo $employee['employee_type'] === 'permanent' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($employee['employee_type']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($todayLog): ?>
                            <div class="tracker-status">
                                <div class="status-card">
                                    <div class="status-header">
                                        <i class="fas fa-sign-in-alt"></i> Clock In
                                    </div>
                                    <div class="status-time"><?php echo $todayLog['clock_in']; ?></div>
                                    <div class="status-badge badge-<?php echo $todayLog['clock_in_status'] === 'ontime' ? 'success' : ($todayLog['clock_in_status'] === 'late' ? 'danger' : 'info'); ?>">
                                        <?php echo ucfirst($todayLog['clock_in_status']); ?>
                                    </div>
                                </div>
                                <div class="status-card">
                                    <div class="status-header">
                                        <i class="fas fa-sign-out-alt"></i> Clock Out
                                    </div>
                                    <div class="status-time"><?php echo $todayLog['clock_out'] ?? 'Not yet'; ?></div>
                                    <div class="status-badge badge-<?php echo $todayLog['clock_out_status'] === 'ontime' ? 'success' : ($todayLog['clock_out_status'] === 'overtime' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($todayLog['clock_out_status'] ?? 'Pending'); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="tracker-buttons">
                                <?php if (!$todayLog): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <?php echo getCsrfField(); ?>
                                        <input type="hidden" name="action" value="clock_in">
                                        <button type="submit" class="btn btn-success" <?php echo !$isWorkingDay ? 'disabled' : ''; ?>>
                                            <i class="fas fa-sign-in-alt"></i> Clock In
                                        </button>
                                    </form>
                                <?php elseif (!$todayLog['clock_out']): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <?php echo getCsrfField(); ?>
                                        <input type="hidden" name="action" value="clock_out">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-sign-out-alt"></i> Clock Out
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i> You have completed your attendance for today!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="report-grid">
                    <!-- Allowances -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-plus-circle"></i> My Allowances</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($allowances)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <p>No allowances configured</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Allowance</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allowances as $allowance): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($allowance['allowance_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($allowance['allowance_type'])); ?></td>
                                                    <td><strong><?php echo formatCurrency($allowance['amount']); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Deductions -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-minus-circle"></i> My Deductions</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($deductions)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <p>No deductions configured</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Deduction</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($deductions as $deduction): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($deduction['deduction_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($deduction['deduction_type'])); ?></td>
                                                    <td><strong><?php echo formatCurrency($deduction['amount']); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Payroll -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Payroll</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payrollRecords)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h4>No payroll records yet</h4>
                                <p>Your payroll records will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Year</th>
                                            <th>Basic Salary</th>
                                            <th>Allowances</th>
                                            <th>Deductions</th>
                                            <th>Net Salary</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payrollRecords as $payroll): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payroll['payroll_month']); ?></td>
                                                <td><?php echo htmlspecialchars($payroll['payroll_year']); ?></td>
                                                <td><?php echo formatCurrency($payroll['basic_salary']); ?></td>
                                                <td><?php echo formatCurrency($payroll['total_allowances']); ?></td>
                                                <td><?php echo formatCurrency($payroll['total_deductions']); ?></td>
                                                <td><strong><?php echo formatCurrency($payroll['net_salary']); ?></strong></td>
                                                <td>
                                                    <?php if ($payroll['status'] === 'processed'): ?>
                                                        <span class="badge badge-success">Processed</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning"><?php echo htmlspecialchars($payroll['status']); ?></span>
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

                <!-- Recent Attendance -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-check"></i> Recent Attendance</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar"></i>
                                <h4>No attendance records yet</h4>
                                <p>Your attendance records will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Overtime Hours</th>
                                            <th>Late Hours</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance as $att): ?>
                                            <tr>
                                                <td><?php echo formatDate($att['attendance_date']); ?></td>
                                                <td>
                                                    <?php 
                                                        $statusClass = '';
                                                        switch($att['status']) {
                                                            case 'present': $statusClass = 'badge-success'; break;
                                                            case 'absent': $statusClass = 'badge-danger'; break;
                                                            case 'leave': $statusClass = 'badge-warning'; break;
                                                            default: $statusClass = 'badge-info';
                                                        }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($att['status']); ?></span>
                                                </td>
                                                <td><?php echo $att['overtime_hours'] > 0 ? $att['overtime_hours'] . ' hrs' : '-'; ?></td>
                                                <td><?php echo $att['late_hours'] > 0 ? $att['late_hours'] . ' hrs' : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($att['remarks'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Leave Request Form -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope"></i> Submit Leave/Absence Request</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-row">
                            <?php echo getCsrfField(); ?>
                            <input type="hidden" name="action" value="submit_leave_request">
                            <div class="form-group">
                                <label>Request Type *</label>
                                <select name="request_type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="absence">Absence</option>
                                    <option value="lateness">Lateness</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Start Date *</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>End Date *</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Number of Days *</label>
                                <input type="number" name="number_of_days" class="form-control" step="0.5" min="0.5" required placeholder="e.g. 1, 1.5, 2">
                            </div>
                            <div class="form-group full-width">
                                <label>Reason *</label>
                                <textarea name="reason" class="form-control" rows="3" required placeholder="Please explain the reason for your absence or lateness..."></textarea>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Settings Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-cog"></i> Account Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="profile-grid">
                            <div class="profile-item">
                                <label>Username</label>
                                <div class="profile-value"><?php echo htmlspecialchars($currentUser['username'] ?? $_SESSION['username']); ?></div>
                            </div>
                        </div>
                        
                        <div class="form-section mt-4">
                            <h4><i class="fas fa-user-edit"></i> Change Username</h4>
                            <form method="POST" action="" id="usernameForm">
                                <?php echo getCsrfField(); ?>
                                <input type="hidden" name="action" value="change_username">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Current Password *</label>
                                        <input type="password" name="old_password" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>New Username *</label>
                                        <input type="text" name="new_username" class="form-control" required 
                                               placeholder="New username" maxlength="20" pattern="[a-zA-Z0-9_]{3,20}"
                                               value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>">
                                        <small class="text-muted">3-20 alphanumeric characters</small>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Username
                                </button>
                            </form>
                        </div>

                        <div class="form-section mt-4">
                            <h4><i class="fas fa-lock"></i> Change Password</h4>
                            <form method="POST" action="" id="passwordForm">
                                <?php echo getCsrfField(); ?>
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Current Password *</label>
                                        <input type="password" name="old_password" class="form-control" required id="oldPass">
                                    </div>
                                    <div class="form-group">
                                        <label>New Password *</label>
                                        <input type="password" name="new_password" class="form-control" required 
                                               id="newPass" minlength="8" placeholder="At least 8 characters">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Confirm New Password *</label>
                                        <input type="password" name="confirm_password" class="form-control" required id="confirmPass">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Leave Request History -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> My Leave Requests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leaveRequests)): ?>
                            <div class="empty-state">
                                <i class="fas fa-envelope-open"></i>
                                <h4>No leave requests</h4>
                                <p>Your leave request history will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
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
                                        <?php foreach ($leaveRequests as $request): ?>
                                            <tr>
                                                <td><?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></td>
                                                <td><?php echo formatDate($request['start_date']); ?></td>
                                                <td><?php echo formatDate($request['end_date']); ?></td>
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
    <script>
        // Password toggle functionality
        document.querySelectorAll('input[type="password"]').forEach(input => {
            input.addEventListener('focus', function() {
                let toggle = document.createElement('i');
                toggle.className = 'fas fa-eye toggle-password';
                toggle.style.cssText = 'position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; z-index: 10;';
                toggle.onclick = function() {
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.className = 'fas fa-eye-slash toggle-password';
                    } else {
                        input.type = 'password';
                        this.className = 'fas fa-eye toggle-password';
                    }
                };
                if (!input.parentElement.querySelector('.toggle-password')) {
                    input.parentElement.style.position = 'relative';
                    input.parentElement.appendChild(toggle);
                }
            });
        });

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('newPass').value;
            const confirmPass = document.getElementById('confirmPass').value;
            if (newPass !== confirmPass) {
                alert('Passwords do not match!');
                e.preventDefault();
            } else if (newPass.length < 8) {
                alert('Password must be at least 8 characters!');
                e.preventDefault();
            }
        });

        document.getElementById('usernameForm').addEventListener('submit', function(e) {
            const username = this.querySelector('input[name="new_username"]').value;
            if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                alert('Username must be 3-20 alphanumeric characters only!');
                e.preventDefault();
            }
        });
    </script>
    
    <!-- Edit Profile Modal -->
    <div class="modal-overlay" id="editProfileModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Profile</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('editProfileModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_first_name">First Name *</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($employee['first_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_last_name">Last Name *</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($employee['last_name']); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_phone">Phone</label>
                            <input type="text" id="edit_phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Address</label>
                        <textarea id="edit_address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('editProfileModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>
