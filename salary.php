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

    // Salary Grades
    if ($action === 'add_grade') {
        $grade_name = trim($_POST['grade_name']);
        $basic_salary = floatval($_POST['basic_salary']);
        $description = trim($_POST['description']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO salary_grades (grade_name, basic_salary, description) VALUES (?, ?, ?)");
            $stmt->execute([$grade_name, $basic_salary, $description]);
            $message = 'Salary grade added successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'edit_grade') {
        $id = $_POST['id'];
        $grade_name = trim($_POST['grade_name']);
        $basic_salary = floatval($_POST['basic_salary']);
        $description = trim($_POST['description']);
        
        try {
            $stmt = $pdo->prepare("UPDATE salary_grades SET grade_name = ?, basic_salary = ?, description = ? WHERE id = ?");
            $stmt->execute([$grade_name, $basic_salary, $description, $id]);
            $message = 'Salary grade updated successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete_grade') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM salary_grades WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Salary grade deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Allowances
    if ($action === 'add_allowance') {
        $allowance_name = trim($_POST['allowance_name']);
        $allowance_type = $_POST['allowance_type'];
        $amount_type = $_POST['amount_type'];
        $amount = floatval($_POST['amount']);
        $percentage_of_basic = floatval($_POST['percentage_of_basic']);
        $is_taxable = $_POST['is_taxable'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO allowances (allowance_name, allowance_type, amount_type, amount, percentage_of_basic, is_taxable) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$allowance_name, $allowance_type, $amount_type, $amount, $percentage_of_basic, $is_taxable]);
            $message = 'Allowance added successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'edit_allowance') {
        $id = $_POST['id'];
        $allowance_name = trim($_POST['allowance_name']);
        $allowance_type = $_POST['allowance_type'];
        $amount_type = $_POST['amount_type'];
        $amount = floatval($_POST['amount']);
        $percentage_of_basic = floatval($_POST['percentage_of_basic']);
        $is_taxable = $_POST['is_taxable'];
        
        try {
            $stmt = $pdo->prepare("UPDATE allowances SET allowance_name = ?, allowance_type = ?, amount_type = ?, amount = ?, percentage_of_basic = ?, is_taxable = ? WHERE id = ?");
            $stmt->execute([$allowance_name, $allowance_type, $amount_type, $amount, $percentage_of_basic, $is_taxable, $id]);
            $message = 'Allowance updated successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete_allowance') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM allowances WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Allowance deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Deductions
    if ($action === 'add_deduction') {
        $deduction_name = trim($_POST['deduction_name']);
        $deduction_type = $_POST['deduction_type'];
        $amount_type = $_POST['amount_type'];
        $amount = floatval($_POST['amount']);
        $percentage_of_basic = floatval($_POST['percentage_of_basic']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO deductions (deduction_name, deduction_type, amount_type, amount, percentage_of_basic) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$deduction_name, $deduction_type, $amount_type, $amount, $percentage_of_basic]);
            $message = 'Deduction added successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'edit_deduction') {
        $id = $_POST['id'];
        $deduction_name = trim($_POST['deduction_name']);
        $deduction_type = $_POST['deduction_type'];
        $amount_type = $_POST['amount_type'];
        $amount = floatval($_POST['amount']);
        $percentage_of_basic = floatval($_POST['percentage_of_basic']);
        
        try {
            $stmt = $pdo->prepare("UPDATE deductions SET deduction_name = ?, deduction_type = ?, amount_type = ?, amount = ?, percentage_of_basic = ? WHERE id = ?");
            $stmt->execute([$deduction_name, $deduction_type, $amount_type, $amount, $percentage_of_basic, $id]);
            $message = 'Deduction updated successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete_deduction') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM deductions WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Deduction deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get all data
$salaryGrades = $pdo->query("SELECT * FROM salary_grades ORDER BY basic_salary DESC")->fetchAll(PDO::FETCH_ASSOC);
$allowances = $pdo->query("SELECT * FROM allowances ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$deductions = $pdo->query("SELECT * FROM deductions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary & Grades - PayPro Payroll System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo getCsrfMeta(); ?>
</head>
<body>
    <div class="app-container">
        <?php $pageTitle = 'Salary & Grades'; include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="page-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Salary Grades -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-layer-group"></i> Salary Grades</h3>
                        <button class="btn btn-success btn-sm" onclick="openModal('addGradeModal')">
                            <i class="fas fa-plus"></i> Add Grade
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($salaryGrades)): ?>
                            <div class="empty-state">
                                <i class="fas fa-layer-group"></i>
                                <h4>No salary grades</h4>
                                <p>Add salary grades to categorize employees</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Grade Name</th>
                                            <th>Basic Salary</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($salaryGrades as $grade): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($grade['grade_name']); ?></strong></td>
                                                <td><strong><?php echo formatCurrency($grade['basic_salary']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($grade['description'] ?? '-'); ?></td>
                                                <td class="actions">
                                                    <button class="btn btn-sm btn-info" onclick="editSalaryGrade(<?php echo $grade['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteSalaryGrade(<?php echo $grade['id']; ?>)">
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

                <!-- Allowances -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Allowances</h3>
                        <button class="btn btn-success btn-sm" onclick="openModal('addAllowanceModal')">
                            <i class="fas fa-plus"></i> Add Allowance
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($allowances)): ?>
                            <div class="empty-state">
                                <i class="fas fa-plus-circle"></i>
                                <h4>No allowances configured</h4>
                                <p>Add allowances like HRA, DA, etc.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Allowance Name</th>
                                            <th>Type</th>
                                            <th>Amount Type</th>
                                            <th>Amount</th>
                                            <th>% of Basic</th>
                                            <th>Taxable</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allowances as $allowance): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($allowance['allowance_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars(ucfirst($allowance['allowance_type'])); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($allowance['amount_type'])); ?></td>
                                                <td><?php echo $allowance['amount_type'] === 'percentage' ? '-' : formatCurrency($allowance['amount']); ?></td>
                                                <td><?php echo $allowance['percentage_of_basic'] > 0 ? $allowance['percentage_of_basic'] . '%' : '-'; ?></td>
                                                <td>
                                                    <?php if ($allowance['is_taxable'] === 'yes'): ?>
                                                        <span class="badge badge-warning">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="actions">
                                                    <button class="btn btn-sm btn-info" onclick="editAllowance(<?php echo $allowance['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteAllowance(<?php echo $allowance['id']; ?>)">
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

                <!-- Deductions -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-minus-circle"></i> Deductions</h3>
                        <button class="btn btn-success btn-sm" onclick="openModal('addDeductionModal')">
                            <i class="fas fa-plus"></i> Add Deduction
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($deductions)): ?>
                            <div class="empty-state">
                                <i class="fas fa-minus-circle"></i>
                                <h4>No deductions configured</h4>
                                <p>Add deductions like PF, Tax, etc.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Deduction Name</th>
                                            <th>Type</th>
                                            <th>Amount Type</th>
                                            <th>Amount</th>
                                            <th>% of Basic</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deductions as $deduction): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($deduction['deduction_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars(ucfirst($deduction['deduction_type'])); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($deduction['amount_type'])); ?></td>
                                                <td><?php echo $deduction['amount_type'] === 'percentage' ? '-' : formatCurrency($deduction['amount']); ?></td>
                                                <td><?php echo $deduction['percentage_of_basic'] > 0 ? $deduction['percentage_of_basic'] . '%' : '-'; ?></td>
                                                <td class="actions">
                                                    <button class="btn btn-sm btn-info" onclick="editDeduction(<?php echo $deduction['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteDeduction(<?php echo $deduction['id']; ?>)">
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
            </div>
        </main>
    </div>

    <!-- Add Grade Modal -->
    <div class="modal-overlay" id="addGradeModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add Salary Grade</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('addGradeModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="add_grade">
                    <div class="form-group">
                        <label>Grade Name *</label>
                        <input type="text" name="grade_name" class="form-control" required placeholder="e.g., Grade A">
                    </div>
                    <div class="form-group">
                        <label>Basic Salary *</label>
                        <input type="number" name="basic_salary" class="form-control" required step="0.01" min="0" placeholder="e.g., 50000">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('addGradeModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Grade Modal -->
    <div class="modal-overlay" id="editGradeModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Salary Grade</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('editGradeModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="edit_grade">
                    <input type="hidden" name="id" id="edit_grade_id">
                    <div class="form-group">
                        <label>Grade Name *</label>
                        <input type="text" name="grade_name" id="edit_grade_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Basic Salary *</label>
                        <input type="number" name="basic_salary" id="edit_basic_salary" class="form-control" required step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_grade_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('editGradeModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Allowance Modal -->
    <div class="modal-overlay" id="addAllowanceModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add Allowance</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('addAllowanceModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="add_allowance">
                    <div class="form-group">
                        <label>Allowance Name *</label>
                        <input type="text" name="allowance_name" class="form-control" required placeholder="e.g., House Rent Allowance">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Allowance Type</label>
                            <select name="allowance_type" class="form-control">
                                <option value="fixed">Fixed</option>
                                <option value="variable">Variable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount Type</label>
                            <select name="amount_type" class="form-control" onchange="toggleAmountFields(this, 'allowance')">
                                <option value="amount">Fixed Amount</option>
                                <option value="percentage">Percentage of Basic</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>% of Basic Salary</label>
                            <input type="number" name="percentage_of_basic" class="form-control" step="0.01" min="0" placeholder="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Taxable</label>
                        <select name="is_taxable" class="form-control">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('addAllowanceModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Allowance Modal -->
    <div class="modal-overlay" id="editAllowanceModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Allowance</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('editAllowanceModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="edit_allowance">
                    <input type="hidden" name="id" id="edit_allowance_id">
                    <div class="form-group">
                        <label>Allowance Name *</label>
                        <input type="text" name="allowance_name" id="edit_allowance_name" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Allowance Type</label>
                            <select name="allowance_type" id="edit_allowance_type" class="form-control">
                                <option value="fixed">Fixed</option>
                                <option value="variable">Variable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount Type</label>
                            <select name="amount_type" id="edit_amount_type" class="form-control">
                                <option value="amount">Fixed Amount</option>
                                <option value="percentage">Percentage of Basic</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>% of Basic Salary</label>
                            <input type="number" name="percentage_of_basic" id="edit_percentage" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Taxable</label>
                        <select name="is_taxable" id="edit_taxable" class="form-control">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('editAllowanceModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Deduction Modal -->
    <div class="modal-overlay" id="addDeductionModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add Deduction</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('addDeductionModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="add_deduction">
                    <div class="form-group">
                        <label>Deduction Name *</label>
                        <input type="text" name="deduction_name" class="form-control" required placeholder="e.g., Provident Fund">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Deduction Type</label>
                            <select name="deduction_type" class="form-control">
                                <option value="fixed">Fixed</option>
                                <option value="variable">Variable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount Type</label>
                            <select name="amount_type" class="form-control">
                                <option value="amount">Fixed Amount</option>
                                <option value="percentage">Percentage of Basic</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>% of Basic Salary</label>
                            <input type="number" name="percentage_of_basic" class="form-control" step="0.01" min="0" placeholder="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('addDeductionModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Deduction Modal -->
    <div class="modal-overlay" id="editDeductionModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Deduction</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('editDeductionModal'))">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php echo getCsrfField(); ?>
                    <input type="hidden" name="action" value="edit_deduction">
                    <input type="hidden" name="id" id="edit_deduction_id">
                    <div class="form-group">
                        <label>Deduction Name *</label>
                        <input type="text" name="deduction_name" id="edit_deduction_name" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Deduction Type</label>
                            <select name="deduction_type" id="edit_deduction_type" class="form-control">
                                <option value="fixed">Fixed</option>
                                <option value="variable">Variable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount Type</label>
                            <select name="amount_type" id="edit_deduction_amount_type" class="form-control">
                                <option value="amount">Fixed Amount</option>
                                <option value="percentage">Percentage of Basic</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" name="amount" id="edit_deduction_amount" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>% of Basic Salary</label>
                            <input type="number" name="percentage_of_basic" id="edit_deduction_percentage" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('editDeductionModal'))">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Fetch and populate edit modals
        function editSalaryGrade(id) {
            fetch(`salary.php?action=get_grade&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const g = data.grade;
                        document.getElementById('edit_grade_id').value = g.id;
                        document.getElementById('edit_grade_name').value = g.grade_name;
                        document.getElementById('edit_basic_salary').value = g.basic_salary;
                        document.getElementById('edit_grade_description').value = g.description || '';
                        openModal('editGradeModal');
                    }
                });
        }

        function editAllowance(id) {
            fetch(`salary.php?action=get_allowance&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const a = data.allowance;
                        document.getElementById('edit_allowance_id').value = a.id;
                        document.getElementById('edit_allowance_name').value = a.allowance_name;
                        document.getElementById('edit_allowance_type').value = a.allowance_type;
                        document.getElementById('edit_amount_type').value = a.amount_type;
                        document.getElementById('edit_amount').value = a.amount;
                        document.getElementById('edit_percentage').value = a.percentage_of_basic;
                        document.getElementById('edit_taxable').value = a.is_taxable;
                        openModal('editAllowanceModal');
                    }
                });
        }

        function editDeduction(id) {
            fetch(`salary.php?action=get_deduction&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const d = data.deduction;
                        document.getElementById('edit_deduction_id').value = d.id;
                        document.getElementById('edit_deduction_name').value = d.deduction_name;
                        document.getElementById('edit_deduction_type').value = d.deduction_type;
                        document.getElementById('edit_deduction_amount_type').value = d.amount_type;
                        document.getElementById('edit_deduction_amount').value = d.amount;
                        document.getElementById('edit_deduction_percentage').value = d.percentage_of_basic;
                        openModal('editDeductionModal');
                    }
                });
        }
    </script>
</body>
</html>

<?php
// Handle AJAX requests
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_grade' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM salary_grades WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'grade' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }
    if ($_GET['action'] === 'get_allowance' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM allowances WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'allowance' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }
    if ($_GET['action'] === 'get_deduction' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM deductions WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'deduction' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }
}
?>

