<?php
/**
 * Error Handling Configuration
 * Grama Voice - Village Governance Platform
 */

// Set error reporting level
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);

// Set custom error handler
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

/**
 * Custom error handler
 */
function customErrorHandler($severity, $message, $file, $line) {
    // Don't execute PHP internal error handler
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $error_types = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $error_type = isset($error_types[$severity]) ? $error_types[$severity] : 'Unknown Error';
    
    $log_message = "[$error_type] $message in $file on line $line";
    
    // Log to file
    logError($log_message, $file, $line);
    
    // For fatal errors, show user-friendly message
    if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
        http_response_code(500);
        die('An internal error occurred. Please try again later.');
    }
    
    return true;
}

/**
 * Custom exception handler
 */
function customExceptionHandler($exception) {
    $log_message = "Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    logError($log_message, $exception->getFile(), $exception->getLine());
    
    http_response_code(500);
    die('An internal error occurred. Please try again later.');
}

/**
 * Log error to file
 */
function logError($message, $file = '', $line = '') {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $context = $file ? " in $file" : '';
    $context .= $line ? " on line $line" : '';
    $log_entry = "[$timestamp] $message$context" . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log application events
 */
function logApplicationEvent($event, $details = '') {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/application.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] EVENT: $event" . ($details ? " - $details" : '') . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '') {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $log_entry = "[$timestamp] SECURITY: $event - IP: $ip - User Agent: $user_agent" . ($details ? " - $details" : '') . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>
