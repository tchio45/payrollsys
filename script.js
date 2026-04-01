// ============================================
// PAYPRO PAYROLL SYSTEM - JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initModals();
    initFormValidation();
    initDataTables();
    initDatePickers();
    initAnimatedTaskBar();
});

// Get CSRF token from meta tag
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function initAnimatedTaskBar() {
    const topHeader = document.querySelector('.top-header');
    
    if (topHeader) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 10) {
                topHeader.classList.add('scrolled');
            } else {
                topHeader.classList.remove('scrolled');
            }
        });
        
        // Trigger initial check
        if (window.scrollY > 10) {
            topHeader.classList.add('scrolled');
        }
    }
}

// Sidebar Toggle
function initSidebar() {
    const toggleBtn = document.querySelector('.toggle-btn');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
}

// Modal Functions
function initModals() {
    const modalOverlays = document.querySelectorAll('.modal-overlay');
    
    modalOverlays.forEach(overlay => {
        const closeBtn = overlay.querySelector('.modal-close');
        const cancelBtn = overlay.querySelector('.btn-cancel');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => closeModal(overlay));
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => closeModal(overlay));
        }
        
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

function showModal(modalId) {
    openModal(modalId);
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        closeModal(modal);
    }
}

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('.ajax-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateForm(form)) {
                submitForm(form);
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            isValid = false;
        } else {
            input.classList.remove('error');
        }
    });
    
    return isValid;
}

function submitForm(form) {
    const formData = new FormData(form);
    const action = form.getAttribute('action');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
    
    fetch(action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            }
            if (data.reset) {
                form.reset();
            }
            if (data.modal) {
                hideModal(data.modal);
            }
            if (data.reload) {
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('An error occurred. Please try again.', 'danger');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save';
        }
    });
}

// Alert Messages
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} fade-in`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? '-exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        ${message}
    `;
    
    const container = document.querySelector('.page-content');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// Data Tables
function initDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        const searchInput = table.parentElement.querySelector('.table-search');
        const filterSelect = table.parentElement.querySelector('.table-filter');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterTable(table, searchInput.value, filterSelect ? filterSelect.value : '');
            });
        }
        
        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                filterTable(table, searchInput ? searchInput.value : '', filterSelect.value);
            });
        }
    });
}

function filterTable(table, search = '', filter = '') {
    const rows = table.querySelectorAll('tbody tr');
    const searchLower = search.toLowerCase();
    
    rows.forEach(row => {
        let show = true;
        
        if (search) {
            const text = row.textContent.toLowerCase();
            show = text.includes(searchLower);
        }
        
        if (filter && show) {
            const filterCell = row.querySelector('[data-filter]');
            if (filterCell) {
                show = filterCell.getAttribute('data-filter') === filter;
            }
        }
        
        row.style.display = show ? '' : 'none';
    });
}

// Date Pickers
function initDatePickers() {
    const dateInputs = document.querySelectorAll('.date-picker');
    
    dateInputs.forEach(input => {
        if (input.type !== 'date') {
            input.type = 'date';
        }
    });
}

// Search Functionality
function performSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (input && table) {
        filterTable(table, input.value);
    }
}

// Employee Functions
function editEmployee(id) {
    fetch(`employees.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const employee = data.employee;
                document.getElementById('edit_id').value = employee.id;
                document.getElementById('edit_employee_id').value = employee.employee_id;
                document.getElementById('edit_first_name').value = employee.first_name;
                document.getElementById('edit_last_name').value = employee.last_name;
                document.getElementById('edit_email').value = employee.email;
                document.getElementById('edit_phone').value = employee.phone;
                document.getElementById('edit_department').value = employee.department;
                document.getElementById('edit_designation').value = employee.designation;
                document.getElementById('edit_basic_salary').value = employee.basic_salary;
                document.getElementById('edit_salary_grade_id').value = employee.salary_grade_id;
                document.getElementById('edit_status').value = employee.status;
                openModal('editEmployeeModal');
            }
        });
}

function deleteEmployee(id) {
    if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        formData.append('csrf_token', getCsrfToken());
        
        fetch('employees.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    }
}

// Salary Grade Functions
function editSalaryGrade(id) {
    fetch(`salary.php?action=get_grade&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const grade = data.grade;
                document.getElementById('edit_grade_id').value = grade.id;
                document.getElementById('edit_grade_name').value = grade.grade_name;
                document.getElementById('edit_basic_salary').value = grade.basic_salary;
                document.getElementById('edit_grade_description').value = grade.description;
                openModal('editGradeModal');
            }
        });
}

function deleteSalaryGrade(id) {
    if (confirm('Are you sure you want to delete this salary grade?')) {
        const formData = new FormData();
        formData.append('action', 'delete_grade');
        formData.append('id', id);
        formData.append('csrf_token', getCsrfToken());
        
        fetch('salary.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    }
}

// Allowance Functions
function editAllowance(id) {
    fetch(`salary.php?action=get_allowance&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const allowance = data.allowance;
                document.getElementById('edit_allowance_id').value = allowance.id;
                document.getElementById('edit_allowance_name').value = allowance.allowance_name;
                document.getElementById('edit_allowance_type').value = allowance.allowance_type;
                document.getElementById('edit_amount_type').value = allowance.amount_type;
                document.getElementById('edit_amount').value = allowance.amount;
                document.getElementById('edit_percentage').value = allowance.percentage_of_basic;
                document.getElementById('edit_taxable').value = allowance.is_taxable;
                openModal('editAllowanceModal');
            }
        });
}

function deleteAllowance(id) {
    if (confirm('Are you sure you want to delete this allowance?')) {
        const formData = new FormData();
        formData.append('action', 'delete_allowance');
        formData.append('id', id);
        formData.append('csrf_token', getCsrfToken());
        
        fetch('salary.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    }
}

// Deduction Functions
function editDeduction(id) {
    fetch(`salary.php?action=get_deduction&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const deduction = data.deduction;
                document.getElementById('edit_deduction_id').value = deduction.id;
                document.getElementById('edit_deduction_name').value = deduction.deduction_name;
                document.getElementById('edit_deduction_type').value = deduction.deduction_type;
                document.getElementById('edit_deduction_amount_type').value = deduction.amount_type;
                document.getElementById('edit_deduction_amount').value = deduction.amount;
                document.getElementById('edit_deduction_percentage').value = deduction.percentage_of_basic;
                openModal('editDeductionModal');
            }
        });
}

function deleteDeduction(id) {
    if (confirm('Are you sure you want to delete this deduction?')) {
        const formData = new FormData();
        formData.append('action', 'delete_deduction');
        formData.append('id', id);
        formData.append('csrf_token', getCsrfToken());
        
        fetch('salary.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    }
}

// Attendance Functions
function markAttendance(employeeId, date, status) {
    const formData = new FormData();
    formData.append('action', 'mark_attendance');
    formData.append('employee_id', employeeId);
    formData.append('date', date);
    formData.append('status', status);
    formData.append('csrf_token', getCsrfToken());
    
    fetch('attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            setTimeout(() => window.location.reload(), 1000);
        }
    });
}

function deleteAttendance(id) {
    if (confirm('Are you sure you want to delete this attendance record?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        formData.append('csrf_token', getCsrfToken());
        
        fetch('attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    }
}

// Payroll Functions
function processPayroll() {
    const month = document.getElementById('payroll_month').value;
    const year = document.getElementById('payroll_year').value;
    
    if (!month || !year) {
        showAlert('Please select month and year', 'warning');
        return;
    }
    
    if (confirm(`Are you sure you want to process payroll for ${month} ${year}? This will calculate salaries for all active employees.`)) {
        const formData = new FormData();
        formData.append('action', 'process');
        formData.append('month', month);
        formData.append('year', year);
        formData.append('csrf_token', getCsrfToken());
        
        const btn = document.querySelector('#processPayrollBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }
        
        fetch('payroll.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                setTimeout(() => window.location.reload(), 2000);
            }
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cog"></i> Process Payroll';
            }
        });
    }
}

function viewPayrollDetails(id) {
    fetch(`payroll.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const p = data.payroll;
                const details = `
                    <div class="payslip-container">
                        <div class="payslip-header">
                            <h2>PayPro Payroll System</h2>
                            <p>Payroll Details - ${p.payroll_month} ${p.payroll_year}</p>
                        </div>
                        <div class="payslip-body">
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
                                    <span>${p.department}</span>
                                </div>
                                <div class="payslip-row">
                                    <span>Designation:</span>
                                    <span>${p.designation}</span>
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
                                    <span>Overtime:</span>
                                    <span>${formatCurrency(p.overtime_amount)}</span>
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
                        </div>
                    </div>
                `;
                document.getElementById('payrollDetailsContent').innerHTML = details;
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
            showAlert(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    }
}

// Payslip Functions
function generatePayslip(employeeId, month, year) {
    const formData = new FormData();
    formData.append('action', 'generate');
    formData.append('employee_id', employeeId);
    formData.append('month', month);
    formData.append('year', year);
    formData.append('csrf_token', getCsrfToken());

    fetch('payslips.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            setTimeout(() => window.location.reload(), 1500);
        }
    });
}

function viewPayslip(id) {
    fetch(`payslips.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('payslipContent').innerHTML = data.html;
                openModal('viewPayslipModal');
            }
        });
}

function printPayslip() {
    const content = document.getElementById('payslipContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Payslip</title>
            <link rel="stylesheet" href="style.css">
            <style>
                body { padding: 20px; }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>${content}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Utility Functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount) + ' FCFA';
}

function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-IN', options);
}

function exportToCSV() {
    const table = document.querySelector('.data-table');
    if (table) {
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            cols.forEach(col => {
                rowData.push(col.innerText);
            });
            csv.push(rowData.join(','));
        });
        
        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        const downloadLink = document.createElement('a');
        downloadLink.download = 'export.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.click();
    }
}

// Confirm Delete
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Toggle Password Visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }
}
