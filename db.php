<?php
/**
 * Database Connection and Setup
 */

require_once 'config.php';

class Database {
    private $db;
    
    public function __construct() {
        try {
            $this->db = new PDO('sqlite:' . DB_PATH);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initTables();
            $this->seedData();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    private function initTables() {
        // Create users table first
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT,
            employee_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id)
        )");
        
        // Create employees table
        $this->db->exec("CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT UNIQUE NOT NULL,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            email TEXT UNIQUE,
            phone TEXT,
            date_of_birth DATE,
            gender TEXT,
            address TEXT,
            department TEXT,
            designation TEXT,
            join_date DATE,
            salary_grade_id INTEGER,
            basic_salary REAL DEFAULT 0,
            status TEXT DEFAULT 'active',
            profile_picture TEXT,
            employee_type TEXT DEFAULT 'permanent' CHECK(employee_type IN ('permanent', 'temporal')),
            working_days TEXT DEFAULT '[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\"]',
            work_start_time TEXT DEFAULT '09:00:00',
            work_end_time TEXT DEFAULT '18:00:00',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (salary_grade_id) REFERENCES salary_grades(id)
        )");
        
        // Run migrations to add missing columns to existing tables
        $this->migrateEmployeesTable();
        
        // Create salary_grades table
        $this->db->exec("CREATE TABLE IF NOT EXISTS salary_grades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            grade_name TEXT NOT NULL,
            basic_salary REAL NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create allowances table
        $this->db->exec("CREATE TABLE IF NOT EXISTS allowances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            allowance_name TEXT NOT NULL,
            allowance_type TEXT NOT NULL,
            amount_type TEXT NOT NULL,
            amount REAL DEFAULT 0,
            percentage_of_basic REAL DEFAULT 0,
            is_taxable TEXT DEFAULT 'yes',
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create deductions table
        $this->db->exec("CREATE TABLE IF NOT EXISTS deductions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            deduction_name TEXT NOT NULL,
            deduction_type TEXT NOT NULL,
            amount_type TEXT NOT NULL,
            amount REAL DEFAULT 0,
            percentage_of_basic REAL DEFAULT 0,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create employee_allowances table
        $this->db->exec("CREATE TABLE IF NOT EXISTS employee_allowances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            allowance_id INTEGER NOT NULL,
            amount REAL DEFAULT 0,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            FOREIGN KEY (allowance_id) REFERENCES allowances(id)
        )");
        
        // Create employee_deductions table
        $this->db->exec("CREATE TABLE IF NOT EXISTS employee_deductions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            deduction_id INTEGER NOT NULL,
            amount REAL DEFAULT 0,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            FOREIGN KEY (deduction_id) REFERENCES deductions(id)
        )");
        
        // Create attendance table
        $this->db->exec("CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            attendance_date DATE NOT NULL,
            status TEXT NOT NULL,
            overtime_hours REAL DEFAULT 0,
            late_hours REAL DEFAULT 0,
            remarks TEXT,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            UNIQUE(employee_id, attendance_date)
        )");
        
        // Create attendance_logs table for presence detector (clock-in/clock-out)
        $this->db->exec("CREATE TABLE IF NOT EXISTS attendance_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            log_date DATE NOT NULL,
            clock_in TIME,
            clock_out TIME,
            clock_in_status TEXT DEFAULT 'ontime' CHECK(clock_in_status IN ('ontime', 'late', 'early')),
            clock_out_status TEXT DEFAULT 'ontime' CHECK(clock_out_status IN ('ontime', 'early', 'overtime')),
            device_info TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id),
            UNIQUE(employee_id, log_date)
        )");
        
        // Create leave_requests table
        $this->db->exec("CREATE TABLE IF NOT EXISTS leave_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            request_type TEXT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            number_of_days REAL NOT NULL,
            reason TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            admin_response TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id)
        )");
        
        // Create payroll table
        
        $this->db->exec("CREATE TABLE IF NOT EXISTS payroll (
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
        
        // Create payslips table
        $this->db->exec("CREATE TABLE IF NOT EXISTS payslips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            payroll_id INTEGER NOT NULL,
            employee_id INTEGER NOT NULL,
            payslip_number TEXT UNIQUE NOT NULL,
            generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payroll_id) REFERENCES payroll(id),
            FOREIGN KEY (employee_id) REFERENCES employees(id)
        )");
    }
    
    private function seedData() {
        // Check if admin user exists
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            // Create default admin user with stronger password
            $password = password_hash('SecureAdmin2024!', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, password, full_name, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([ADMIN_USERNAME, $password, 'Administrator', 'admin@payroll.com']);
            
            // Create default salary grades
            $grades = [
                ['Grade A', 50000, 'Manager Level'],
                ['Grade B', 35000, 'Senior Level'],
                ['Grade C', 25000, 'Mid Level'],
                ['Grade D', 15000, 'Junior Level'],
                ['Grade E', 10000, 'Entry Level']
            ];
            
            foreach ($grades as $grade) {
                $stmt = $this->db->prepare("INSERT INTO salary_grades (grade_name, basic_salary, description) VALUES (?, ?, ?)");
                $stmt->execute($grade);
            }
            
            // Create default allowances
            $allowances = [
                ['House Rent Allowance (HRA)', 'fixed', 'amount', 5000, 0, 'yes'],
                ['Dearness Allowance (DA)', 'percentage', 'percentage', 0, 10, 'yes'],
                ['Conveyance Allowance', 'fixed', 'amount', 1500, 0, 'yes'],
                ['Medical Allowance', 'fixed', 'amount', 1250, 0, 'yes'],
                ['Special Allowance', 'fixed', 'amount', 3000, 0, 'yes'],
                ['Overtime Allowance', 'hourly', 'amount', 100, 0, 'yes']
            ];
            
            foreach ($allowances as $allowance) {
                $stmt = $this->db->prepare("INSERT INTO allowances (allowance_name, allowance_type, amount_type, amount, percentage_of_basic, is_taxable) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute($allowance);
            }
            
            // Create default deductions
            $deductions = [
                ['Provident Fund (PF)', 'fixed', 'amount', 1800, 0],
                ['Professional Tax', 'fixed', 'amount', 200, 0],
                ['Income Tax (TDS)', 'percentage', 'percentage', 0, 10],
                ['Health Insurance', 'fixed', 'amount', 500, 0]
            ];
            
            foreach ($deductions as $deduction) {
                $stmt = $this->db->prepare("INSERT INTO deductions (deduction_name, deduction_type, amount_type, amount, percentage_of_basic) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute($deduction);
            }
        }
    }
    
    /**
     * Migrate employees table to add missing columns
     * This handles the case where the table was created before certain columns were added
     */
    private function migrateEmployeesTable() {
        try {
            // Get current columns
            $stmt = $this->db->query('PRAGMA table_info(employees)');
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
            
            // Add missing columns
            $migrations = [
                'employee_type' => "TEXT DEFAULT 'permanent'",
                'working_days' => "TEXT DEFAULT '[\\\"Monday\\\",\\\"Tuesday\\\",\\\"Wednesday\\\",\\\"Thursday\\\",\\\"Friday\\\"]'",
                'work_start_time' => "TEXT DEFAULT '09:00:00'",
                'work_end_time' => "TEXT DEFAULT '18:00:00'"
            ];
            
            foreach ($migrations as $columnName => $definition) {
                if (!in_array($columnName, $columnNames)) {
                    $this->db->exec("ALTER TABLE employees ADD COLUMN $columnName $definition");
                }
            }
        } catch (PDOException $e) {
            // Silently ignore if migrations fail (table might not exist yet)
        }
    }
}

// Initialize database
$db = new Database();
$pdo = $db->getConnection();

/**
 * Create a user account for an employee
 * @param int $employeeId The employee ID
 * @return bool True if successful, False otherwise
 */
function createEmployeeUser($employeeId) {
    global $pdo;
    
    try {
        // Get employee details
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            return false;
        }
        
        // Check if user already exists for this employee
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$employee['employee_id']]);
        if ($stmt->fetch()) {
            return true; // User already exists
        }
        
        // Create username from employee_id and default password
        
        $username = $employee['employee_id'];
        $password = password_hash($employee['employee_id'], PASSWORD_DEFAULT); // Default password is employee_id
        $fullName = $employee['first_name'] . ' ' . $employee['last_name'];
        $email = $employee['email'];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, employee_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password, $fullName, $email, $employeeId]);
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get employee by username (for login)
 * @param string $username The username
 * @return array|false Employee data if found
 */
function getEmployeeByUsername($username) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, u.id as user_id, u.username 
            FROM employees e 
            JOIN users u ON e.id = u.employee_id 
            WHERE u.username = ?
        ");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Format currency consistently across the application
 */
function formatCurrency($amount) {
    return number_format($amount ?? 0, CURRENCY_DECIMALS) . ' ' . CURRENCY_SYMBOL;
}
?>
