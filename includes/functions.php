<?php
require_once __DIR__ . '/config.php';

// ============================================
// STRING FUNCTIONS
// ============================================

function truncate($string, $length = 100, $append = '...') {
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length) . $append;
    }
    return $string;
}

function slugify($text) {
    // Replace non letter or digits with -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

function randomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }
    return $randomString;
}

function generatePassword($length = 8) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*';
    
    $all = $uppercase . $lowercase . $numbers . $symbols;
    
    $password = '';
    $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
    $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
    $password .= $numbers[rand(0, strlen($numbers) - 1)];
    $password .= $symbols[rand(0, strlen($symbols) - 1)];
    
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[rand(0, strlen($all) - 1)];
    }
    
    return str_shuffle($password);
}

// ============================================
// FILE FUNCTIONS
// ============================================

function uploadFile($file, $subdirectory = 'general', $allowed_extensions = null) {
    if ($allowed_extensions === null) {
        $allowed_extensions = explode(',', ALLOWED_EXTENSIONS);
    }
    
    $target_dir = UPLOAD_PATH . $subdirectory . '/';
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
    }
    
    // Check file extension
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions)];
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true,
            'filename' => $new_filename,
            'path' => $subdirectory . '/' . $new_filename,
            'full_path' => $target_file
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

function deleteFile($file_path) {
    $full_path = UPLOAD_PATH . $file_path;
    if (file_exists($full_path)) {
        return unlink($full_path);
    }
    return false;
}

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'pdf',
        'doc' => 'word',
        'docx' => 'word',
        'xls' => 'excel',
        'xlsx' => 'excel',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'txt' => 'text',
        'zip' => 'archive',
        'rar' => 'archive'
    ];
    
    return $icons[$extension] ?? 'file';
}

// ============================================
// DATE FUNCTIONS
// ============================================

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

function getMonthsBetween($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = DateInterval::createFromDateString('1 month');
    $period = new DatePeriod($start, $interval, $end);
    
    $months = [];
    foreach ($period as $dt) {
        $months[] = $dt->format('Y-m');
    }
    
    return $months;
}

function addMonths($date, $months) {
    $dt = new DateTime($date);
    $dt->modify('+' . $months . ' months');
    return $dt->format(SQL_DATE_FORMAT);
}

// ============================================
// VALIDATION FUNCTIONS
// ============================================

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]+$/', $phone);
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validateIdNumber($id_number, $country = 'KE') {
    if ($country == 'KE') {
        return preg_match('/^\d{7,8}$/', $id_number);
    }
    return true;
}

// ============================================
// NUMBER FUNCTIONS
// ============================================

function formatNumber($number, $decimals = 0) {
    return number_format($number ?? 0, $decimals);
}

function formatPercentage($number, $decimals = 2) {
    return number_format($number ?? 0, $decimals) . '%';
}

function calculatePercentage($value, $total, $decimals = 2) {
    $value = floatval($value ?? 0);
    $total = floatval($total ?? 0);
    if ($total == 0) return 0;
    return round(($value / $total) * 100, $decimals);
}

function moneyToFloat($money) {
    return floatval(str_replace([CURRENCY_SYMBOL, ',', ' '], '', $money));
}

// ============================================
// ARRAY FUNCTIONS
// ============================================

function arrayToSelect($array, $value_field, $label_field, $selected = null) {
    $html = '';
    foreach ($array as $item) {
        $value = $item[$value_field];
        $label = $item[$label_field];
        $sel = ($selected == $value) ? 'selected' : '';
        $html .= "<option value='{$value}' {$sel}>{$label}</option>";
    }
    return $html;
}

function arrayToOptions($array, $selected = null) {
    $html = '';
    foreach ($array as $value => $label) {
        $sel = ($selected == $value) ? 'selected' : '';
        $html .= "<option value='{$value}' {$sel}>{$label}</option>";
    }
    return $html;
}

function groupBy($array, $key) {
    $result = [];
    foreach ($array as $item) {
        $group_key = $item[$key];
        if (!isset($result[$group_key])) {
            $result[$group_key] = [];
        }
        $result[$group_key][] = $item;
    }
    return $result;
}

// ============================================
// ADDITIONAL UTILITY FUNCTIONS
// ============================================

/**
 * Generate a unique ID with prefix
 * @param string $prefix Prefix for the ID
 * @return string Unique ID
 */
function generateUniqueId($prefix = '') {
    return $prefix . uniqid() . rand(1000, 9999);
}

/**
 * Log activity to database
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $description Description
 * @param string $module Module name
 * @param int $reference_id Reference ID
 * @return bool Success status
 */
function logActivity($user_id, $action, $description, $module = null, $reference_id = null) {
    global $db;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $sql = "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, module, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("isssssi", $user_id, $action, $description, $ip, $user_agent, $module, $reference_id);
    return $stmt->execute();
}

/**
 * Send notification to user
 * @param int $user_id User ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type
 * @param string $module Module name
 * @param int $reference_id Reference ID
 * @return bool Success status
 */
function sendNotification($user_id, $title, $message, $type = 'Info', $module = null, $reference_id = null) {
    global $db;
    $sql = "INSERT INTO notifications (user_id, title, message, type, module, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("issssi", $user_id, $title, $message, $type, $module, $reference_id);
    return $stmt->execute();
}

/**
 * Get status badge HTML
 * @param string $status Status value
 * @return string HTML badge
 */
function getStatusBadge($status) {
    $badges = [
        'Active' => 'success',
        'Inactive' => 'secondary',
        'Pending' => 'warning',
        'Approved' => 'success',
        'Rejected' => 'danger',
        'Completed' => 'info',
        'Cancelled' => 'danger',
        'Paid' => 'success',
        'Unpaid' => 'danger',
        'Partial' => 'warning',
        'Available' => 'success',
        'Occupied' => 'primary',
        'Under Maintenance' => 'warning',
        'In Progress' => 'primary',
        'Delivered' => 'success',
        'Scheduled' => 'info',
        'In Transit' => 'warning'
    ];
    
    $class = $badges[$status] ?? 'secondary';
    return "<span class='badge bg-{$class}'>{$status}</span>";
}

/**
 * Get avatar letter from name
 * @param string $name Full name
 * @return string Initials
 */
function getAvatarLetter($name) {
    if (empty($name)) return 'U';
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

/**
 * Format currency amount
 * @param float $amount Amount to format
 * @return string Formatted amount
 */
function formatMoney($amount) {
    // Ensure we have a valid number
    $amount = floatval($amount ?? 0);
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Format date
 * @param string $date Date string
 * @param string $format Format
 * @return string Formatted date
 */
function formatDate($date, $format = null) {
    if (empty($date)) return '';
    $format = $format ?: DATE_FORMAT;
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format datetime
 * @param string $datetime Datetime string
 * @return string Formatted datetime
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    return date(DATETIME_FORMAT, $timestamp);
}

/**
 * Sanitize input data
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Redirect to URL
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header('Location: ' . base_url($url));
    exit();
}

/**
 * Get base URL
 * @param string $path Path to append
 * @return string Full URL
 */
function baseUrl($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Check if user has permission
 * @param string $module Module name
 * @param string $permission Permission type
 * @return bool Has permission
 */
function checkPermission($module, $permission) {
    global $session;
    return $session->hasPermission($module, $permission);
}

/**
 * Get current user
 * @return array Current user data
 */
function getCurrentUser() {
    global $session;
    return $session->getCurrentUser();
}

/**
 * Check if user is logged in
 * @return bool Login status
 */
function isLoggedIn() {
    global $session;
    return $session->isLoggedIn();
}

/**
 * Generate random color
 * @return string Random hex color
 */
function randomColor() {
    return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}

/**
 * Convert bytes to human readable
 * @param int $bytes Bytes
 * @return string Human readable size
 */
function formatBytes($bytes) {
    $bytes = floatval($bytes ?? 0);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Get time difference in hours
 * @param string $start Start time
 * @param string $end End time
 * @return float Hours difference
 */
function getHoursDiff($start, $end) {
    $start_time = strtotime($start ?? '');
    $end_time = strtotime($end ?? '');
    if (!$start_time || !$end_time) return 0;
    $diff = $end_time - $start_time;
    return round($diff / 3600, 2);
}

/**
 * Generate invoice number
 * @param string $prefix Prefix
 * @return string Invoice number
 */
function generateInvoiceNumber($prefix = 'INV') {
    return $prefix . '-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Generate receipt number
 * @return string Receipt number
 */
function generateReceiptNumber() {
    return 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
}

/**
 * Validate date range
 * @param string $start Start date
 * @param string $end End date
 * @return bool Valid range
 */
function validateDateRange($start, $end) {
    if (!validateDate($start) || !validateDate($end)) {
        return false;
    }
    return strtotime($start) <= strtotime($end);
}

/**
 * Get financial year
 * @param string $date Date
 * @return string Financial year
 */
function getFinancialYear($date = null) {
    $date = $date ? strtotime($date) : time();
    $year = date('Y', $date);
    $month = date('m', $date);
    
    if ($month >= 7) {
        return $year . '-' . ($year + 1);
    } else {
        return ($year - 1) . '-' . $year;
    }
}

/**
 * Calculate tax amount
 * @param float $amount Amount
 * @param float $rate Tax rate
 * @return float Tax amount
 */
function calculateTax($amount, $rate = 16) {
    $amount = floatval($amount ?? 0);
    return ($amount * $rate) / 100;
}

/**
 * Calculate discount amount
 * @param float $amount Amount
 * @param float $rate Discount rate
 * @return float Discount amount
 */
function calculateDiscount($amount, $rate) {
    $amount = floatval($amount ?? 0);
    return ($amount * $rate) / 100;
}

/**
 * Get ordinal suffix
 * @param int $number Number
 * @return string Number with suffix
 */
function ordinal($number) {
    $number = intval($number ?? 0);
    $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    if (($number % 100) >= 11 && ($number % 100) <= 13) {
        return $number . 'th';
    }
    return $number . $ends[$number % 10];
}

/**
 * Mask string
 * @param string $string String to mask
 * @param int $visible Visible characters
 * @param string $mask Mask character
 * @return string Masked string
 */
function maskString($string, $visible = 4, $mask = '*') {
    $string = $string ?? '';
    $length = strlen($string);
    if ($length <= $visible) {
        return $string;
    }
    $mask_length = $length - $visible;
    return substr($string, 0, $visible) . str_repeat($mask, $mask_length);
}

/**
 * Get file extension
 * @param string $filename Filename
 * @return string Extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename ?? '', PATHINFO_EXTENSION));
}

/**
 * Create directory if not exists
 * @param string $path Directory path
 * @return bool Success
 */
function createDirectory($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0777, true);
    }
    return true;
}

/**
 * Delete directory recursively
 * @param string $path Directory path
 * @return bool Success
 */
function deleteDirectory($path) {
    if (!file_exists($path)) {
        return true;
    }
    if (!is_dir($path)) {
        return unlink($path);
    }
    foreach (scandir($path) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($path . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($path);
}

/**
 * Get directory size
 * @param string $path Directory path
 * @return int Size in bytes
 */
function getDirectorySize($path) {
    $size = 0;
    if (!file_exists($path)) return 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

/**
 * Convert CSV to array
 * @param string $csv CSV string
 * @return array Array data
 */
function csvToArray($csv) {
    if (empty($csv)) return [];
    $lines = explode("\n", trim($csv));
    $header = str_getcsv(array_shift($lines));
    $data = [];
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $row = str_getcsv($line);
        if (count($row) == count($header)) {
            $data[] = array_combine($header, $row);
        }
    }
    return $data;
}

/**
 * Convert array to CSV
 * @param array $data Array data
 * @return string CSV string
 */
function arrayToCsv($data) {
    if (empty($data)) {
        return '';
    }
    $output = fopen('php://temp', 'r+');
    fputcsv($output, array_keys($data[0]));
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}

/**
 * Generate UUID
 * @return string UUID
 */
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Get client IP address
 * @return string IP address
 */
function getClientIp() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if request is AJAX
 * @return bool Is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Send JSON response
 * @param mixed $data Data to send
 * @param int $status HTTP status code
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Get pagination links
 * @param int $total Total items
 * @param int $per_page Items per page
 * @param int $current Current page
 * @param string $url Base URL
 * @return string HTML pagination
 */
function getPagination($total, $per_page = 20, $current = 1, $url = '') {
    $total = intval($total ?? 0);
    $total_pages = ceil($total / $per_page);
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav><ul class="pagination">';
    
    // Previous
    if ($current > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Pages
    $start = max(1, $current - 2);
    $end = min($total_pages, $current + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next
    if ($current < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($current + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

// ============================================
// BACKWARD COMPATIBILITY ALIASES
// ============================================
// These functions provide compatibility with code expecting underscore naming
// They simply call the camelCase versions defined above

if (!function_exists('currentUser')) {
    /**
     * Alias for getCurrentUser() - for backward compatibility
     * @return array Current user data
     */
    function currentUser() {
        return getCurrentUser();
    }
}

if (!function_exists('hasPermission')) {
    /**
     * Alias for checkPermission() - for backward compatibility
     * @param string $module Module name
     * @param string $permission Permission type
     * @return bool Has permission
     */
    function hasPermission($module, $permission) {
        return checkPermission($module, $permission);
    }
}

if (!function_exists('requirePermission')) {
    /**
     * Alias for requiring permission - for backward compatibility
     * @param string $module Module name
     * @param string $action Action type
     */
    function requirePermission($module, $action) {
        if (!hasPermission($module, $action)) {
            $_SESSION['error'] = 'You do not have permission to perform this action.';
            redirect('dashboard.php');
        }
    }
}

if (!function_exists('format_money')) {
    /**
     * Alias for formatMoney() - for backward compatibility
     * @param float $amount Amount to format
     * @return string Formatted amount
     */
    function format_money($amount) {
        return formatMoney($amount);
    }
}

if (!function_exists('get_avatar_letter')) {
    /**
     * Alias for getAvatarLetter() - for backward compatibility
     * @param string $name Full name
     * @return string Initials
     */
    function get_avatar_letter($name) {
        return getAvatarLetter($name);
    }
}

if (!function_exists('get_status_badge')) {
    /**
     * Alias for getStatusBadge() - for backward compatibility
     * @param string $status Status value
     * @return string HTML badge
     */
    function get_status_badge($status) {
        return getStatusBadge($status);
    }
}

if (!function_exists('log_activity')) {
    /**
     * Alias for logActivity() - for backward compatibility
     * @param int $user_id User ID
     * @param string $action Action performed
     * @param string $description Description
     * @param string $module Module name
     * @param int $reference_id Reference ID
     * @return bool Success status
     */
    function log_activity($user_id, $action, $description, $module = null, $reference_id = null) {
        return logActivity($user_id, $action, $description, $module, $reference_id);
    }
}

if (!function_exists('send_notification')) {
    /**
     * Alias for sendNotification() - for backward compatibility
     * @param int $user_id User ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param string $module Module name
     * @param int $reference_id Reference ID
     * @return bool Success status
     */
    function send_notification($user_id, $title, $message, $type = 'Info', $module = null, $reference_id = null) {
        return sendNotification($user_id, $title, $message, $type, $module, $reference_id);
    }
}

if (!function_exists('sanitize_input')) {
    /**
     * Alias for sanitizeInput() - for backward compatibility
     * @param string $data Input data
     * @return string Sanitized data
     */
    function sanitize_input($data) {
        return sanitizeInput($data);
    }
}

if (!function_exists('format_date')) {
    /**
     * Alias for formatDate() - for backward compatibility
     * @param string $date Date string
     * @param string $format Format
     * @return string Formatted date
     */
    function format_date($date, $format = null) {
        return formatDate($date, $format);
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Alias for formatDateTime() - for backward compatibility
     * @param string $datetime Datetime string
     * @return string Formatted datetime
     */
    function format_datetime($datetime) {
        return formatDateTime($datetime);
    }
}

if (!function_exists('base_url')) {
    /**
     * Alias for baseUrl() - for backward compatibility
     * @param string $path Path to append
     * @return string Full URL
     */
    function base_url($path = '') {
        return baseUrl($path);
    }
}

if (!function_exists('check_permission')) {
    /**
     * Alias for checkPermission() - for backward compatibility
     * @param string $module Module name
     * @param string $permission Permission type
     * @return bool Has permission
     */
    function check_permission($module, $permission) {
        return checkPermission($module, $permission);
    }
}

if (!function_exists('generate_unique_id')) {
    /**
     * Alias for generateUniqueId() - for backward compatibility
     * @param string $prefix Prefix for the ID
     * @return string Unique ID
     */
    function generate_unique_id($prefix = '') {
        return generateUniqueId($prefix);
    }
}

if (!function_exists('validate_date_range')) {
    /**
     * Alias for validateDateRange() - for backward compatibility
     * @param string $start Start date
     * @param string $end End date
     * @return bool Valid range
     */
    function validate_date_range($start, $end) {
        return validateDateRange($start, $end);
    }
}

if (!function_exists('get_financial_year')) {
    /**
     * Alias for getFinancialYear() - for backward compatibility
     * @param string $date Date
     * @return string Financial year
     */
    function get_financial_year($date = null) {
        return getFinancialYear($date);
    }
}

if (!function_exists('calculate_tax')) {
    /**
     * Alias for calculateTax() - for backward compatibility
     * @param float $amount Amount
     * @param float $rate Tax rate
     * @return float Tax amount
     */
    function calculate_tax($amount, $rate = 16) {
        return calculateTax($amount, $rate);
    }
}

if (!function_exists('calculate_discount')) {
    /**
     * Alias for calculateDiscount() - for backward compatibility
     * @param float $amount Amount
     * @param float $rate Discount rate
     * @return float Discount amount
     */
    function calculate_discount($amount, $rate) {
        return calculateDiscount($amount, $rate);
    }
}

if (!function_exists('get_file_extension')) {
    /**
     * Alias for getFileExtension() - for backward compatibility
     * @param string $filename Filename
     * @return string Extension
     */
    function get_file_extension($filename) {
        return getFileExtension($filename);
    }
}

if (!function_exists('create_directory')) {
    /**
     * Alias for createDirectory() - for backward compatibility
     * @param string $path Directory path
     * @return bool Success
     */
    function create_directory($path) {
        return createDirectory($path);
    }
}

if (!function_exists('delete_directory')) {
    /**
     * Alias for deleteDirectory() - for backward compatibility
     * @param string $path Directory path
     * @return bool Success
     */
    function delete_directory($path) {
        return deleteDirectory($path);
    }
}

if (!function_exists('get_directory_size')) {
    /**
     * Alias for getDirectorySize() - for backward compatibility
     * @param string $path Directory path
     * @return int Size in bytes
     */
    function get_directory_size($path) {
        return getDirectorySize($path);
    }
}

if (!function_exists('csv_to_array')) {
    /**
     * Alias for csvToArray() - for backward compatibility
     * @param string $csv CSV string
     * @return array Array data
     */
    function csv_to_array($csv) {
        return csvToArray($csv);
    }
}

if (!function_exists('array_to_csv')) {
    /**
     * Alias for arrayToCsv() - for backward compatibility
     * @param array $data Array data
     * @return string CSV string
     */
    function array_to_csv($data) {
        return arrayToCsv($data);
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * Alias for getClientIp() - for backward compatibility
     * @return string IP address
     */
    function get_client_ip() {
        return getClientIp();
    }
}

if (!function_exists('is_ajax_request')) {
    /**
     * Alias for isAjaxRequest() - for backward compatibility
     * @return bool Is AJAX
     */
    function is_ajax_request() {
        return isAjaxRequest();
    }
}

if (!function_exists('json_response')) {
    /**
     * Alias for jsonResponse() - for backward compatibility
     * @param mixed $data Data to send
     * @param int $status HTTP status code
     */
    function json_response($data, $status = 200) {
        jsonResponse($data, $status);
    }
}

if (!function_exists('get_pagination')) {
    /**
     * Alias for getPagination() - for backward compatibility
     * @param int $total Total items
     * @param int $per_page Items per page
     * @param int $current Current page
     * @param string $url Base URL
     * @return string HTML pagination
     */
    function get_pagination($total, $per_page = 20, $current = 1, $url = '') {
        return getPagination($total, $per_page, $current, $url);
    }
}
?>