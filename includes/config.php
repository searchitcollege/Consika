<?php
// ============================================
// SESSION CONFIGURATION - MUST BE FIRST!
// ============================================
// These must be set BEFORE any session_start() is called
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 when using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_NAME', 'group_companies_db');

// ============================================
// APPLICATION CONFIGURATION
// ============================================
define('APP_NAME', 'Consika Companies Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');
define('APP_ROOT', dirname(dirname(__FILE__)));

// ============================================
// FILE UPLOAD CONFIGURATION
// ============================================
define('UPLOAD_PATH', APP_ROOT . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx');

// ============================================
// TIME ZONE
// ============================================
date_default_timezone_set('Africa/Accra'); // Changed from Nairobi to Accra

// ============================================
// ERROR REPORTING
// ============================================
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1') {
    // Development
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', APP_ROOT . '/logs/error.log');
} else {
    // Production
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', APP_ROOT . '/logs/error.log');
}

// ============================================
// COMPANY IDs CONSTANTS
// ============================================
define('COMPANY_ESTATE', 1);
define('COMPANY_PROCUREMENT', 2);
define('COMPANY_WORKS', 3);
define('COMPANY_BLOCK_FACTORY', 4);

// ============================================
// USER ROLES
// ============================================
define('ROLE_SUPER_ADMIN', 'SuperAdmin');
define('ROLE_COMPANY_ADMIN', 'CompanyAdmin');
define('ROLE_MANAGER', 'Manager');
define('ROLE_STAFF', 'Staff');

// ============================================
// CURRENCY SETTINGS - CHANGED TO GHANA CEDIS
// ============================================
define('CURRENCY_SYMBOL', 'GHS ');
define('CURRENCY_CODE', 'GHS');
define('DECIMAL_PLACES', 2);

// ============================================
// DATE FORMATS
// ============================================
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i:s');
define('SQL_DATE_FORMAT', 'Y-m-d');
define('SQL_DATETIME_FORMAT', 'Y-m-d H:i:s');

// ============================================
// PAGINATION
// ============================================
define('ITEMS_PER_PAGE', 20);
define('MAX_PAGE_LINKS', 5);

// ============================================
// SYSTEM SETTINGS
// ============================================
define('ENABLE_2FA', false);
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes in seconds

// ============================================
// EMAIL CONFIGURATION
// ============================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-password');
define('SMTP_FROM', 'noreply@groupcompanies.com');
define('SMTP_FROM_NAME', APP_NAME);

// ============================================
// CREATE LOGS DIRECTORY IF NOT EXISTS
// ============================================
if (!file_exists(APP_ROOT . '/logs')) {
    mkdir(APP_ROOT . '/logs', 0777, true);
}

// ============================================
// CUSTOM FUNCTIONS
// ============================================
function base_url($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function format_money($amount) {
    return CURRENCY_SYMBOL . number_format($amount, DECIMAL_PLACES);
}

function format_date($date, $format = DATE_FORMAT) {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

// ============================================
// GHANA-SPECIFIC FUNCTIONS (Optional)
// ============================================

/**
 * Format amount in Ghana Cedis with pesewas
 * @param float $amount Amount to format
 * @param bool $include_pesewas Whether to include pesewas (decimal places)
 * @return string Formatted amount
 */
function format_ghs($amount, $include_pesewas = true) {
    $decimals = $include_pesewas ? 2 : 0;
    return 'GHS ' . number_format($amount, $decimals);
}

/**
 * Convert amount to words in Ghana Cedis (useful for receipts)
 * @param float $amount Amount to convert
 * @return string Amount in words
 */
function ghs_to_words($amount) {
    $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
    $cedis = floor($amount);
    $pesewas = round(($amount - $cedis) * 100);
    
    $words = ucwords($f->format($cedis)) . " Ghana Cedis";
    if ($pesewas > 0) {
        $words .= " and " . ucwords($f->format($pesewas)) . " Pesewas";
    }
    
    return $words;
}

// REMOVED: function redirect($url) { ... } - Now in functions.php
?>