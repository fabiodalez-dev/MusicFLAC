<?php
// Advanced security hardening functions

if (!function_exists('security_validate_input')) {
    /**
     * Enhanced input validation and sanitization
     */
    function security_validate_input($input, $type = 'string', $max_length = 255) {
        if ($input === null || $input === '') {
            return '';
        }
        
        switch ($type) {
            case 'filename':
                // Strict filename validation
                $input = basename($input);
                $input = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
                if (strlen($input) > 255 || strlen($input) < 1) {
                    throw new InvalidArgumentException('Invalid filename');
                }
                return $input;
                
            case 'url':
                // URL validation with restricted schemes
                if (!filter_var($input, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('Invalid URL');
                }
                $parsed = parse_url($input);
                if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
                    throw new InvalidArgumentException('Invalid URL scheme');
                }
                return $input;
                
            case 'email':
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Invalid email');
                }
                return $input;
                
            case 'alphanumeric':
                $input = preg_replace('/[^a-zA-Z0-9]/', '', $input);
                break;
                
            case 'int':
                if (!filter_var($input, FILTER_VALIDATE_INT)) {
                    throw new InvalidArgumentException('Invalid integer');
                }
                return (int)$input;
                
            default:
                $input = trim($input);
                $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        
        if (strlen($input) > $max_length) {
            $input = substr($input, 0, $max_length);
        }
        
        return $input;
    }
}

if (!function_exists('security_check_file_access')) {
    /**
     * Enhanced file access security check
     */
    function security_check_file_access($filepath, $allowed_dirs = []) {
        $realpath = realpath($filepath);
        
        if (!$realpath || !file_exists($realpath)) {
            throw new Exception('File not found or inaccessible');
        }
        
        // Check if file is within allowed directories
        if (!empty($allowed_dirs)) {
            $allowed = false;
            foreach ($allowed_dirs as $dir) {
                $real_dir = realpath($dir);
                if ($real_dir && strpos($realpath, $real_dir) === 0) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                throw new Exception('File access denied');
            }
        }
        
        // Block access to sensitive files
        $blocked_patterns = [
            '/\.env/',
            '/\.git/',
            '/\.htaccess/',
            '/\.htpasswd/',
            '/config\.php/',
            '/\.ini$/',
            '/\.log$/',
            '/\.conf$/',
        ];
        
        foreach ($blocked_patterns as $pattern) {
            if (preg_match($pattern, $realpath)) {
                throw new Exception('Access to sensitive file denied');
            }
        }
        
        return $realpath;
    }
}

if (!function_exists('security_prevent_command_injection')) {
    /**
     * Enhanced command injection prevention
     */
    function security_prevent_command_injection($command) {
        // Block dangerous characters and patterns
        $dangerous = [
            ';', '|', '&', '$', '`', '$(', '&&', '||', 
            'bash', 'sh', '/bin/', '/usr/bin/', 'wget', 'curl',
            'rm ', 'del ', 'format', 'shutdown', 'reboot'
        ];
        
        foreach ($dangerous as $pattern) {
            if (stripos($command, $pattern) !== false) {
                throw new Exception('Potentially dangerous command detected');
            }
        }
        
        // Only allow specific whitelisted commands
        $allowed_commands = ['metaflac'];
        $cmd_parts = explode(' ', trim($command));
        $base_cmd = basename($cmd_parts[0] ?? '');
        
        if (!in_array($base_cmd, $allowed_commands)) {
            throw new Exception('Command not in whitelist');
        }
        
        return $command;
    }
}

if (!function_exists('security_rate_limit')) {
    /**
     * Advanced rate limiting with multiple levels
     */
    function security_rate_limit($action, $limit_per_minute = 60, $limit_per_hour = 1000) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_id = $_SESSION['user_id'] ?? 'anonymous';
        $key_minute = 'rate_' . $action . '_' . md5($ip . $user_id) . '_' . floor(time() / 60);
        $key_hour = 'rate_' . $action . '_' . md5($ip . $user_id) . '_' . floor(time() / 3600);
        
        $temp_dir = sys_get_temp_dir();
        $minute_file = $temp_dir . '/' . $key_minute;
        $hour_file = $temp_dir . '/' . $key_hour;
        
        // Check minute limit
        $minute_count = 0;
        if (file_exists($minute_file)) {
            $minute_count = (int)file_get_contents($minute_file);
        }
        
        if ($minute_count >= $limit_per_minute) {
            http_response_code(429);
            throw new Exception('Rate limit exceeded (per minute)');
        }
        
        // Check hour limit
        $hour_count = 0;
        if (file_exists($hour_file)) {
            $hour_count = (int)file_get_contents($hour_file);
        }
        
        if ($hour_count >= $limit_per_hour) {
            http_response_code(429);
            throw new Exception('Rate limit exceeded (per hour)');
        }
        
        // Increment counters
        file_put_contents($minute_file, $minute_count + 1);
        file_put_contents($hour_file, $hour_count + 1);
        
        return true;
    }
}

if (!function_exists('security_log_event')) {
    /**
     * Security event logging
     */
    function security_log_event($event_type, $details = []) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $user_id = $_SESSION['user_id'] ?? 'anonymous';
        $timestamp = date('Y-m-d H:i:s');
        
        $log_entry = [
            'timestamp' => $timestamp,
            'event_type' => $event_type,
            'ip' => $ip,
            'user_agent' => $user_agent,
            'user_id' => $user_id,
            'details' => $details
        ];
        
        $log_file = __DIR__ . '/../data/security.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        
        @file_put_contents($log_file, json_encode($log_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('security_validate_csrf_with_expiry')) {
    /**
     * Enhanced CSRF protection with expiry
     */
    function security_validate_csrf_with_expiry($token, $max_age = 3600) {
        if (!isset($_SESSION['csrf_token_data'])) {
            return false;
        }
        
        $csrf_data = $_SESSION['csrf_token_data'];
        
        // Check if token has expired
        if ((time() - $csrf_data['created']) > $max_age) {
            unset($_SESSION['csrf_token_data']);
            return false;
        }
        
        return hash_equals($csrf_data['token'], $token);
    }
    
    function security_generate_csrf_with_expiry() {
        $token = bin2hex(random_bytes(16));
        $_SESSION['csrf_token_data'] = [
            'token' => $token,
            'created' => time()
        ];
        return $token;
    }
}

if (!function_exists('security_domain_match')) {
    /**
     * Check if domain matches or is subdomain of allowed domain
     * PHP 7.x compatible version of str_ends_with for domain checking
     */
    function security_domain_match($host, $allowed_domain) {
        return $host === $allowed_domain || 
               (strlen($host) > strlen($allowed_domain) && 
                substr($host, -strlen('.' . $allowed_domain)) === '.' . $allowed_domain);
    }
}

if (!function_exists('security_sanitize_output')) {
    /**
     * Output sanitization to prevent XSS
     */
    function security_sanitize_output($content, $context = 'html') {
        switch ($context) {
            case 'html':
                return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            case 'js':
                return json_encode($content, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            case 'url':
                return urlencode($content);
            case 'css':
                return preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $content);
            default:
                return $content;
        }
    }
}

// Initialize security monitoring
if (session_status() === PHP_SESSION_ACTIVE) {
    // Track suspicious activity patterns
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Log suspicious patterns (properly formatted regex with delimiters)
    $suspicious_patterns = [
        '/\.\.\//', '/\.\.\\\\/', '/\/etc\//', '/\/proc\//', 
        '/php:\/\//', '/data:\/\//', '/expect:\/\//', '/zip:\/\//',
        '/<script/', '/javascript:/', '/vbscript:/', '/onload=/',
        '/union.*select/i', '/drop.*table/i', '/insert.*into/i'
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $request_uri . ' ' . $user_agent)) {
            security_log_event('suspicious_request', [
                'pattern' => $pattern,
                'uri' => $request_uri,
                'user_agent' => $user_agent
            ]);
            break;
        }
    }
}

// Intentionally no closing PHP tag to avoid accidental output