<?php
// Determine current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="logo"><i class="fas fa-wallet"></i></span>
        <span>PayPro</span>
    </div>
    <nav class="sidebar-menu">
        <?php if (isAdmin()): ?>
        <div class="menu-section">Main</div>
        <a href="dashboard.php" class="menu-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="employees.php" class="menu-item <?php echo $currentPage === 'employees.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Employees</span>
        </a>
        <a href="salary.php" class="menu-item <?php echo $currentPage === 'salary.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Salary & Grades</span>
        </a>
        <a href="attendance.php" class="menu-item <?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Attendance</span>
        </a>
        <div class="menu-section">Payroll</div>
        <a href="payroll.php" class="menu-item <?php echo $currentPage === 'payroll.php' ? 'active' : ''; ?>">
            <i class="fas fa-calculator"></i>
            <span>Payroll Processing</span>
        </a>
        <a href="payslips.php" class="menu-item <?php echo $currentPage === 'payslips.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Payslips</span>
        </a>
        <div class="menu-section">Reports</div>
        <a href="reports.php" class="menu-item <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <?php else: ?>
        <div class="menu-section">My Space</div>
        <a href="my_profile.php" class="menu-item <?php echo $currentPage === 'my_profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
        <?php endif; ?>
    </nav>
</aside>
