<?php
// Create logs directory 
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logMessage = "[$timestamp] [ERROR] $message $contextStr\n";
    
    file_put_contents(__DIR__ . '/../logs/error.log', $logMessage, FILE_APPEND);
    error_log($message . ' ' . $contextStr);
}

function logActivity($userId, $action, $details = '') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logMessage = "[$timestamp] [ACTIVITY] User:$userId Action:$action IP:$ip Details:$details\n";
    
    file_put_contents(__DIR__ . '/../logs/activity.log', $logMessage, FILE_APPEND);
}

function logSecurity($event, $details = '') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logMessage = "[$timestamp] [SECURITY] Event:$event IP:$ip Details:$details\n";
    
    file_put_contents(__DIR__ . '/../logs/security.log', $logMessage, FILE_APPEND);
}
?>