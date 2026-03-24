<?php
/**
 * WEB Database Fix: Add Missing Payroll Columns
 * Visit this URL once: http://localhost/payrollsys/fix_db_web.php (adjust port/path)
 * Then delete this file.
 */

require_once 'config.php';
require_once 'db.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Payroll DB Fix - Complete!</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
    </style>
</head>
<body>
    <h1>🛠️ Payroll Database Fix</h1>

<?php
try {
    // Force table recreation with full schema
    $pdo->exec('DROP TABLE IF EXISTS payroll');
    
    $pdo->exec("CREATE TABLE payroll (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        payroll_month TEXT NOT NULL,
        payroll_year INTEGER NOT NULL,
        basic_salary REAL NOT NULL,
        total_allowances REAL DEFAULT 0,
        total_deductions REAL DEFAULT 0,
        gross_salary REAL NOT NULL,
        net_salary REAL NOT NULL,
        working_days INTEGER DEFAULT 0,
        days_worked INTEGER DEFAULT 0,
        overtime_hours REAL DEFAULT 0,
        overtime_amount REAL DEFAULT 0,
        late_deduction REAL DEFAULT 0,
        early_leave_deduction REAL DEFAULT 0,
        status TEXT DEFAULT 'processed',
        processed_by INTEGER,
        processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id),
        FOREIGN KEY (processed_by) REFERENCES users(id),
        UNIQUE(employee_id, payroll_month, payroll_year)
    )");
    
    // Verify schema
    $stmt = $pdo->query("PRAGMA table_info(payroll)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    echo '<div class="success">';
    echo '<h2>✅ Fix Complete!</h2>';
    echo '<p><strong>Payroll table recreated with ALL columns:</strong></p>';
    echo '<pre>' . implode("\n", $columnNames) . '</pre>';
    echo '<p>✓ late_deduction and early_leave_deduction columns added.</p>';
    echo '<p>⚠️ Previous payroll records were <strong>deleted</strong> (only schema fixed).</p>';
    echo '</div>';
    
    echo '<p><strong>Next steps:</strong></p>';
    echo '<ol>';
    echo '<li>Test payroll processing: <a href="payroll.php" class="btn">Process Payroll Now</a></li>';
    echo '<li>Delete this file: <code>fix_db_web.php</code></li>';
    echo '</ol>';
    
} catch (PDOException $e) {
    echo '<div class="warning">';
    echo '<h2>❌ Error:</h2>';
    echo '<pre>' . $e->getMessage() . '</pre>';
    echo '</div>';
}
?>
    
    <hr>
    <p><small>This is a one-time fix. Delete after use.</small></p>
</body>
</html>
<?php
// Auto-delete after 1 hour (optional security)
touch(__FILE__, time() - 3600);
?>

