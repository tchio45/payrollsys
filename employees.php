<?php
require_once 'config.php';
require_once 'db.php';

requireLogin();
if (!isAdmin()) { header('Location: my_profile.php'); exit; }

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Generate unique employee ID
        do {
            $employee_id = 'EMP' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE employee_id = ?");
            $checkStmt->execute([$employee_id]);
        } while ($checkStmt->fetchColumn() > 0);

        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $date_of_birth = $_POST['date_of_birth'];
        $gender = $_POST['gender'];
        $address = trim($_POST['address']);
        $department = trim($_POST['department']);
        $designation = trim($_POST['designation']);
        $join_date = $_POST['join_date'];
        $salary_grade_id = $_POST['salary_grade_id'] ?? null;
        $basic_salary = floatval($_POST['basic_salary'] ?? 0);
        $employee_type = $_POST['employee_type'] ?? 'permanent';

        // Server-side validation
        $errors = [];
        if (strlen($first_name) < 2 || strlen($first_name) > 50) $errors[] = 'First name must be 2-50 characters';
        if (strlen($last_name) < 2 || strlen($last_name) > 50) $errors[] = 'Last name must be 2-50 characters';
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
        if (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{6,20}$/', $phone)) $errors[] = 'Invalid phone format';
        if (strlen($department) < 2 || strlen($department) > 50) $errors[] = 'Department must be 2-50 characters';
        if (strlen($designation) < 2 || strlen($designation) > 50) $errors[] = 'Designation must be 2-50 characters';
        if ($basic_salary < 0) $errors[] = 'Salary cannot be negative';
        if (!empty($join_date) && !strtotime($join_date)) $errors[] = 'Invalid join date';

        if (!empty($errors)) {
            $message = 'Validation errors: ' . implode(', ', $errors);
            $messageType = 'danger';
        } else {
        
        // Handle working days - convert array to JSON
        $working_days = isset($_POST['working_days']) ? json_encode($_POST['working_days']) : '["Monday","Tuesday","Wednesday","Thursday","Friday"]';
        $work_start_time = $_POST['work_start_time'] ?? '09:00:00';
        $work_end_time = $_POST['work_end_time'] ?? '18:00:00';
        
        try {
            $stmt = $pdo->prepare("INSERT INTO employees (employee_id, first_name, last_name, email, phone, date_of_birth, gender, address, department, designation, join_date, salary_grade_id, basic_salary, employee_type, working_days, work_start_time, work_end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $first_name, $last_name, $email, $phone, $date_of_birth, $gender, $address, $department, $designation, $join_date, $salary_grade_id, $basic_salary, $employee_type, $working_days, $work_start_time, $work_end_time]);
            
            // Get the last inserted employee ID
            $employeeId = $pdo->lastInsertId();
            
            // Create user account for employee
            createEmployeeUser($employeeId);
            
            $message = 'Employee added successfully! Login credentials - Username: ' . $employee_id . ', Password: ' . $employee_id;
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error adding employee: ' . $e->getMessage();
            $messageType = 'danger';
        }
        } // end validation else
    }
    
    if ($action === 'edit') {
        $id = $_POST['id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department = trim($_POST['department']);
        $designation = trim($_POST['designation']);
        $salary_grade_id = $_POST['salary_grade_id'] ?? null;
        $basic_salary = floatval($_POST['basic_salary'] ?? 0);
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, department = ?, designation = ?, salary_grade_id = ?, basic_salary = ?, status = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $phone, $department, $designation, $salary_grade_id, $basic_salary, $status, $id]);
            $message = 'Employee updated successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error updating employee: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $pdo->beginTransaction();
            // Delete related records first (cascade)
            $pdo->prepare("DELETE FROM payslips WHERE employee_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM payroll WHERE employee_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM attendance WHERE employee_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM attendance_logs WHERE employee_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM leave_requests WHERE employee_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM employee_allowances WHERE employee_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM employee_deductions WHERE employee_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE employee_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
            $pdo->commit();
            $message = 'Employee and all related records deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Error deleting employee: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get all employees
$allEmployees = $pdo->query("SELECT e.*, g.grade_name FROM employees e LEFT JOIN salary_grades g ON e.salary_grade_id = g.id ORDER BY e.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$empPagination = paginate($allEmployees, 15);
$employees = $empPagination['data'];

// Get salary grades for dropdown
$salaryGrades = $pdo->query("SELECT * FROM salary_grades ORDER BY basic_salary DESC")->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'employee' => $employee]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - PayPro Payroll System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo getCsrfMeta(); ?>
</head>
<body>
    <div class="app-container">
        <?php $pageTitle = 'Employees'; include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="page-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Employee List</h3>
                        <button class="btn btn-success" onclick="openModal('addEmployeeModal')">
                            <i class="fas fa-plus"></i> Add Employee
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="search-filter">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search employees...">
                            </div>
                            <div class="filter-box">
                                <select class="form-control" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <?php if (empty($employees)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h4>No employees found</h4>
                                <p>Add your first employee to get started</p>
                                <button class="btn btn-success mt-2" onclick="openModal('addEmployeeModal')">
                                    <i class="fas fa-plus"></i> Add Employee
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-scroll-container">
                                <table class="table data-table" id="employeeTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Department</th>
                                            <th>Designation</th>
                                            <th>Salary Grade</th>
                                            <th>Basic Salary</th>
                                            <th>Work Start Time</th>
                                            <th>Work End Time</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $emp): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($emp['employee_id']); ?></strong></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($emp['email'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($emp['phone'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($emp['department'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($emp['designation'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($emp['grade_name'] ?? '-'); ?></td>
                                                <td><strong><?php echo formatCurrency($emp['basic_salary']); ?></strong></td>
                                                <td><?php echo isset($emp['work_start_time']) && $emp['work_start_time'] ? date('h:i A', strtotime($emp['work_start_time'])) : '-'; ?></td>
                                                <td><?php echo isset($emp['work_end_time']) && $emp['work_end_time'] ? date('h:i A', strtotime($emp['work_end_time'])) : '-'; ?></td>
                                                <td>
                                                    <?php if ($emp['status'] === 'active'): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="actions">
                                                    <button class="btn btn-sm btn-info" onclick="editEmployee(<?php echo $emp['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteEmployee(<?php echo $emp['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php renderPagination($empPagination); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal-overlay" id="addEmployeeModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Employee</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('addEmployeeModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-control">
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="department">Department *</label>
                            <input type="text" id="department" name="department" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="designation">Designation *</label>
                            <input type="text" id="designation" name="designation" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="join_date">Join Date *</label>
                            <input type="date" id="join_date" name="join_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="salary_grade_id">Salary Grade</label>
                            <select id="salary_grade_id" name="salary_grade_id" class="form-control">
                                <option value="">Select Grade</option>
                                <?php foreach ($salaryGrades as $grade): ?>
                                    <option value="<?php echo $grade['id']; ?>" data-salary="<?php echo $grade['basic_salary']; ?>">
                                        <?php echo htmlspecialchars($grade['grade_name']); ?> - <?php echo formatCurrency($grade['basic_salary']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="basic_salary">Basic Salary</label>
                        <input type="number" id="basic_salary" name="basic_salary" class="form-control" step="0.01" min="0">
                    </div>
                    
                    <!-- Employee Type -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="employee_type">Employee Type *</label>
                            <select id="employee_type" name="employee_type" class="form-control" required>
                                <option value="permanent">Permanent</option>
                                <option value="temporal">Temporal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="work_start_time">Work Start Time</label>
                            <input type="time" id="work_start_time" name="work_start_time" class="form-control" value="09:00:00">
                        </div>
                    </div>
                    
                    <!-- Working Days -->
                    <div class="form-group">
                        <label>Working Days *</label>
                        <div class="checkbox-group">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="working_days[]" value="Monday" checked> Mon
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="working_days[]" value="Tuesday" checked> Tue
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="working_days[]" value="Wednesday" checked> Wed
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="working_days[]" value="Thursday" checked> Thu
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="working_days[]" value="Friday" checked> Fri
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="working_days[]" value="Saturday"> Sat
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="working_days[]" value="Sunday"> Sun
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="work_end_time">Work End Time</label>
                        <input type="time" id="work_end_time" name="work_end_time" class="form-control" value="18:00:00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('addEmployeeModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-save"></i> Save Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal-overlay" id="editEmployeeModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Employee</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('editEmployeeModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_first_name">First Name *</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_last_name">Last Name *</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_phone">Phone</label>
                            <input type="text" id="edit_phone" name="phone" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_department">Department *</label>
                            <input type="text" id="edit_department" name="department" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_designation">Designation *</label>
                            <input type="text" id="edit_designation" name="designation" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_salary_grade_id">Salary Grade</label>
                            <select id="edit_salary_grade_id" name="salary_grade_id" class="form-control">
                                <option value="">Select Grade</option>
                                <?php foreach ($salaryGrades as $grade): ?>
                                    <option value="<?php echo $grade['id']; ?>">
                                        <?php echo htmlspecialchars($grade['grade_name']); ?> - <?php echo formatCurrency($grade['basic_salary']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_basic_salary">Basic Salary</label>
                            <input type="number" id="edit_basic_salary" name="basic_salary" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('editEmployeeModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-save"></i> Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Search and filter functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#employeeTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        document.getElementById('statusFilter').addEventListener('change', function() {
            const status = this.value.toLowerCase();
            const rows = document.querySelectorAll('#employeeTable tbody tr');
            
            rows.forEach(row => {
                if (!status) {
                    row.style.display = '';
                } else {
                    const rowStatus = row.textContent.toLowerCase();
                    row.style.display = rowStatus.includes(status) ? '' : 'none';
                }
            });
        });

        // Auto-fill basic salary when salary grade is selected
        document.getElementById('salary_grade_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const salary = selectedOption.getAttribute('data-salary');
            if (salary) {
                document.getElementById('basic_salary').value = salary;
            }
        });
    </script>
</body>
</html>

