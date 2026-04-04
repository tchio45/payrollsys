<?php
require_once 'config.php';
require_once 'db.php';

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Check if this is an employee user (has employee_id)
            if (isset($user['employee_id']) && $user['employee_id']) {
                $_SESSION['user_role'] = 'employee';
                $_SESSION['employee_id'] = $user['employee_id'];
                header('Location: my_profile.php');
            } else {
                $_SESSION['user_role'] = 'admin';
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PayPro Payroll System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <!-- Paper Money Animated Background -->
        <div class="money-bg">
            <div class="bill bill-1">
                <span class="dollar-sign">FCFA</span>
                <span class="bill-amount">10 000</span>
            </div>
            <div class="bill bill-2">
                <span class="dollar-sign">FCFA</span>
                <span class="bill-amount">5 000</span>
            </div>
            <div class="bill bill-3">
                <span class="dollar-sign">FCFA</span>
                <span class="bill-amount">2 000</span>
            </div>
            <div class="bill bill-4">
                <span class="dollar-sign">FCFA</span>
                <span class="bill-amount">1 000</span>
            </div>
            <div class="bill bill-5">
                <span class="dollar-sign">FCFA</span>
                <span class="bill-amount">10 000</span>
            </div>
            <div class="bill bill-6">
                <span class="dollar-sign">FCFA</span>
                <span class="bill-amount">500</span>
            </div>
        </div>
        <div class="video-overlay"></div>
        
        <div class="login-box">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-wallet"></i>
                </div>
                <h2>PayPro</h2>
                <p>Payroll Management System</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Enter your username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
