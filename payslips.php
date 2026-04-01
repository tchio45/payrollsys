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

    if ($action === 'generate') {
        $employee_id = $_POST['employee_id'];
        $month = $_POST['month'];
        $year = $_POST['year'];
        
        try {
            // Get payroll record
            $stmt = $pdo->prepare("
                SELECT p.*, e.first_name, e.last_name, e.employee_id, e.department, e.designation, e.email
                FROM payroll p 
                JOIN employees e ON p.employee_id = e.id 
                WHERE p.employee_id = ? AND p.payroll_month = ? AND p.payroll_year = ?
            ");
            $stmt->execute([$employee_id, $month, $year]);
            $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payroll) {
                $message = 'No payroll record found for selected employee and period';
                $messageType = 'danger';
            } else {
                // Check if payslip already exists
                $checkStmt = $pdo->prepare("SELECT id FROM payslips WHERE payroll_id = ?");
                $checkStmt->execute([$payroll['id']]);
                if ($checkStmt->fetch()) {
                    $message = 'Payslip already generated for this period';
                    $messageType = 'warning';
                } else {
                    // Generate payslip number
                    $payslip_number = 'PSLIP/' . date('Ym') . '/' . str_pad($payroll['id'], 4, '0', STR_PAD_LEFT);
                    
                    // Insert payslip
                    $insertStmt = $pdo->prepare("INSERT INTO payslips (payroll_id, employee_id, payslip_number) VALUES (?, ?, ?)");
                    $insertStmt->execute([$payroll['id'], $employee_id, $payslip_number]);
                    
                    $message = 'Payslip generated successfully!';
                    $messageType = 'success';
                }
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get employees
$employees = $pdo->query("SELECT * FROM employees WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get generated payslips
$allPayslips = $pdo->query("
    SELECT ps.*, p.payroll_month, p.payroll_year, p.basic_salary, p.total_allowances, p.total_deductions, p.gross_salary, p.net_salary,
           e.first_name, e.last_name, e.employee_id, e.department, e.designation
    FROM payslips ps
    JOIN payroll p ON ps.payroll_id = p.id
    JOIN employees e ON p.employee_id = e.id
    ORDER BY ps.generated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$slipPagination = paginate($allPayslips, 15);
$payslips = $slipPagination['data'];

// Handle AJAX for viewing payslip
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.*, e.first_name, e.last_name, e.employee_id, e.department, e.designation, e.email
        FROM payslips ps
        JOIN payroll p ON ps.payroll_id = p.id
        JOIN employees e ON p.employee_id = e.id
        WHERE ps.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $payslip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payslip) {
        $html = generatePayslipHTML($payslip);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'html' => $html]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payslip not found']);
    }
    exit;
}

function generatePayslipHTML($p) {
    $html = '
    <div class="payslip-container">
        <div class="payslip-header">
            <h2><i class="fas fa-wallet"></i> PayPro Payroll System</h2>
            <p>Payslip - ' . htmlspecialchars($p['payroll_month']) . ' ' . $p['payroll_year'] . '</p>
        </div>
        <div class="payslip-body">
            <div class="payslip-section">
                <h4>Employee Details</h4>
                <div class="payslip-row">
                    <span>Payslip Number:</span>
                    <span><strong>' . htmlspecialchars($p['payslip_number']) . '</strong></span>
                </div>
                <div class="payslip-row">
                    <span>Employee Name:</span>
                    <span><strong>' . htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) . '</strong></span>
                </div>
                <div class="payslip-row">
                    <span>Employee ID:</span>
                    <span>' . htmlspecialchars($p['employee_id']) . '</span>
                </div>
                <div class="payslip-row">
                    <span>Department:</span>
                    <span>' . htmlspecialchars($p['department'] ?? '-') . '</span>
                </div>
                <div class="payslip-row">
                    <span>Designation:</span>
                    <span>' . htmlspecialchars($p['designation'] ?? '-') . '</span>
                </div>
                <div class="payslip-row">
                    <span>Pay Period:</span>
                    <span>' . htmlspecialchars($p['payroll_month']) . ' ' . $p['payroll_year'] . '</span>
                </div>
            </div>
            
            <div class="payslip-section">
                <h4>Earnings</h4>
                <div class="payslip-row">
                    <span>Basic Salary:</span>
                    <span>' . formatCurrency($p['basic_salary']) . '</span>
                </div>
                <div class="payslip-row">
                    <span>House Rent Allowance:</span>
                    <span>' . formatCurrency($p['total_allowances'] * 0.3) . '</span>
                </div>
                <div class="payslip-row">
                    <span>Dearness Allowance:</span>
                    <span>' . formatCurrency($p['total_allowances'] * 0.2) . '</span>
                </div>
                <div class="payslip-row">
                    <span>Conveyance Allowance:</span>
                    <span>' . formatCurrency($p['total_allowances'] * 0.15) . '</span>
                </div>
                <div class="payslip-row">
                    <span>Other Allowances:</span>
                    <span>' . formatCurrency($p['total_allowances'] * 0.35) . '</span>
                </div>
                <div class="payslip-row" style="font-weight: bold;">
                    <span>Total Earnings:</span>
                    <span>' . formatCurrency($p['basic_salary'] + $p['total_allowances']) . '</span>
                </div>
            </div>
            
            <div class="payslip-section">
                <h4>Deductions</h4>
                <div class="payslip-row">
                    <span>Provident Fund:</span>
                    <span>' . formatCurrency($p['total_deductions'] * 0.4) . '</span>
                </div>
                <div class="payslip-row">
                    <span>Professional Tax:</span>
                    <span>' . formatCurrency($p['total_deductions'] * 0.1) . '</span>
                </div>
                <div class="payslip-row">
                    <span>Income Tax:</span>
                    <span>' . formatCurrency($p['total_deductions'] * 0.4) . '</span>
                </div>
                <div class="payslip-row">
                    <span>Other Deductions:</span>
                    <span>' . formatCurrency($p['total_deductions'] * 0.1) . '</span>
                </div>
                <div class="payslip-row" style="font-weight: bold;">
                    <span>Total Deductions:</span>
                    <span>' . formatCurrency($p['total_deductions']) . '</span>
                </div>
            </div>
            
            <div class="payslip-section">
                <div class="payslip-row total">
                    <span>NET SALARY:</span>
                    <span>' . formatCurrency($p['net_salary']) . '</span>
                </div>
            </div>
            
            <div class="payslip-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                <div class="payslip-row">
                    <span>Generated On:</span>
                    <span>' . date('d M Y h:i A', strtotime($p['generated_at'])) . '</span>
                </div>
            </div>
        </div>
    </div>
    ';
    return $html;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslips - PayPro Payroll System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo getCsrfMeta(); ?>
</head>
<body>
    <div class="app-container">
        <?php $pageTitle = 'Payslips'; include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="page-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Generate Payslip -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h3><i class="fas fa-file-invoice-dollar"></i> Generate Payslip</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-row">
                            <input type="hidden" name="action" value="generate">
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
                                <label>Month</label>
                                <select name="month" class="form-control" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>">
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Year</label>
                                <select name="year" class="form-control" required>
                                    <?php for ($y = date('Y') - 1; $y <= date('Y'); $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo date('Y') == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-file-export"></i> Generate Payslip
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Payslip Records -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Generated Payslips</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payslips)): ?>
                            <div class="empty-state">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <h4>No payslips generated</h4>
                                <p>Generate payslips from processed payroll</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Payslip No.</th>
                                            <th>Employee</th>
                                            <th>Month/Year</th>
                                            <th>Basic Salary</th>
                                            <th>Gross Salary</th>
                                            <th>Net Salary</th>
                                            <th>Generated On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payslips as $slip): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($slip['payslip_number']); ?></strong></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($slip['employee_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($slip['payroll_month']); ?> <?php echo $slip['payroll_year']; ?></td>
                                                <td><?php echo formatCurrency($slip['basic_salary']); ?></td>
                                                <td><?php echo formatCurrency($slip['gross_salary']); ?></td>
                                                <td><strong><?php echo formatCurrency($slip['net_salary']); ?></strong></td>
                                                <td><?php echo date('d M Y', strtotime($slip['generated_at'])); ?></td>
                                                <td class="actions">
                                                    <button class="btn btn-sm btn-info" onclick="viewPayslip(<?php echo $slip['id']; ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="printPayslip(<?php echo $slip['id']; ?>)">
                                                        <i class="fas fa-print"></i> Print
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php renderPagination($slipPagination); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Payslip Modal -->
    <div class="modal-overlay" id="viewPayslipModal">
        <div class="modal" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-file-invoice-dollar"></i> Payslip</h3>
                <button class="modal-close" onclick="closeModal(document.getElementById('viewPayslipModal'))">&times;</button>
            </div>
            <div class="modal-body" id="payslipContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-primary" onclick="printModalPayslip()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="closeModal(document.getElementById('viewPayslipModal'))">Close</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        let currentPayslipId = null;
        
        function viewPayslip(id) {
            currentPayslipId = id;
            fetch(`payslips.php?action=get&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('payslipContent').innerHTML = data.html;
                        openModal('viewPayslipModal');
                    } else {
                        alert(data.message || 'Error loading payslip');
                    }
                });
        }

        function printPayslip(id) {
            viewPayslip(id);
        }

        function printModalPayslip() {
            const content = document.getElementById('payslipContent').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print Payslip</title>
                    <link rel="stylesheet" href="style.css">
                    <style>
                        body { padding: 20px; background: white; }
                        .payslip-container { box-shadow: none; }
                        @media print { body { padding: 0; } }
                    </style>
                </head>
                <body>${content}</body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(() => printWindow.print(), 500);
        }
    </script>
</body>
</html>
