<?php
/**
 * Payroll System Configuration
 */

// Database configuration
define('DB_PATH', __DIR__ . '/payroll.db');

// Application settings
define('APP_NAME', 'PayPro Payroll System');
define('APP_VERSION', '1.0.0');

// Default admin credentials (change in production)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');
define('ADMIN_PHONE', '+237694827157'); // Admin phone number for notifications

// SMS API Configuration - CallMeBot (free WhatsApp/SMS service)
// Get your free API key from https://www.callmebot.com/free-ws-messaging-sms/
define('SMS_API_KEY', ''); // Add your API key here for actual SMS delivery

// Time zone (Central Africa - UTC+1)
date_default_timezone_set('Africa/Lagos');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Currency configuration for FCFA
define('CURRENCY_SYMBOL', 'FCFA');
define('CURRENCY_DECIMALS', 0);

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// Generate CSRF token for forms (call in HTML)
function getCsrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}


// Get current user info
function getCurrentUser() {
    return isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
}

// Get user role
function getUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
}

// Check if current user is admin
function isAdmin() {
    return getUserRole() === 'admin';
}

// Check if current user is employee
function isEmployee() {
    return getUserRole() === 'employee';
}

// Get logged in employee ID (for employee users)
function getLoggedInEmployeeId() {
    return isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] : null;
}

// CSRF Protection Functions
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}


/**
 * Send SMS notification to admin when employee submits leave request
 * Uses CallMeBot API for SMS/WhatsApp delivery
 * @param string $employeeName Employee full name
 * @param string $requestType Type of request (absence, lateness)
 * @param string $startDate Start date of leave
 * @param string $endDate End date of leave
 * @param string $reason Reason for the request
 * @return bool True if SMS sent successfully, False otherwise
 */
function sendAdminNotification($employeeName, $requestType, $startDate, $endDate, $reason) {
    $adminPhone = ADMIN_PHONE;
    
    // Format the message
    $message = "NEW LEAVE REQUEST\n";
    $message .= "Employee: " . $employeeName . "\n";
    $message .= "Type: " . ucfirst($requestType) . "\n";
    $message .= "Dates: " . $startDate . " to " . $endDate . "\n";
    $message .= "Reason: " . substr($reason, 0, 80);
    
    // Log the notification
    $logFile = __DIR__ . '/notifications.log';
    $logEntry = date('Y-m-d H:i:s') . " | Admin: " . $adminPhone . " | " . str_replace("\n", " | ", $message) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Send actual SMS using CallMeBot API
    $apiKey = SMS_API_KEY;
    
    if (!empty($apiKey)) {
        // CallMeBot API endpoint for WhatsApp
        $url = 'https://api.callmebot.com/whatsapp.php';
        
        // Prepare POST data
        $postData = [
            'phone' => $adminPhone,
            'text' => $message,
            'apikey' => $apiKey
        ];
        
        // Send SMS via cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log API response
        $logEntry = date('Y-m-d H:i:s') . " | SMS API Response: HTTP $httpCode\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        return ($httpCode === 200);
    }
    
    // If no API key configured, just log the notification
    return true;
}
?>

