<?php
/**
 * openARMS Helper Functions
 * 
 * Common utility functions used throughout the application
 */

if (!function_exists('h')) {
    /**
     * HTML entity encoding helper
     * 
     * @param string|null $s String to encode
     * @return string Encoded string
     */
    function h($s): string {
        return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists('toFloat')) {
    /**
     * Convert value to float safely
     * 
     * @param mixed $v Value to convert
     * @return float Float value
     */
    function toFloat($v): float {
        $v = trim((string)$v);
        if ($v === '') return 0.0;
        return (float)$v;
    }
}

if (!function_exists('redirect')) {
    /**
     * Safe redirect helper
     * 
     * @param string $url URL to redirect to
     * @return void
     */
    function redirect(string $url): void {
        header("Location: " . $url);
        exit;
    }
}

if (!function_exists('jsonResponse')) {
    /**
     * JSON response helper for API endpoints
     * 
     * @param mixed $data Data to encode
     * @param int $status HTTP status code
     * @return void
     */
    function jsonResponse($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('validateRequired')) {
    /**
     * Validate required fields
     * 
     * @param array $data Data to validate
     * @param array $fields Required field names
     * @return array Array of missing fields (empty if all present)
     */
    function validateRequired(array $data, array $fields): array {
        $missing = [];
        foreach ($fields as $field) {
            $value = trim($data[$field] ?? '');
            if ($value === '' || $value === null) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize input data
     * 
     * @param mixed $input Input to sanitize
     * @return string Sanitized string
     */
    function sanitizeInput($input): string {
        return trim(strip_tags((string)$input));
    }
}

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate CSRF token for form protection
     * 
     * @return string CSRF token
     */
    function generateCSRFToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    /**
     * Verify CSRF token
     * 
     * @param string $token Token to verify
     * @return bool True if valid
     */
    function verifyCSRFToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('formatDate')) {
    /**
     * Format date consistently
     * 
     * @param string $date Date string
     * @param string $format Date format
     * @return string Formatted date
     */
    function formatDate(string $date, string $format = 'Y-m-d'): string {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d ? $d->format($format) : $date;
    }
}

if (!function_exists('isLoggedIn')) {
    /**
     * Check if user is logged in
     * 
     * @return bool True if logged in
     */
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) || isset($_SESSION['asrms_user']);
    }
}

if (!function_exists('requireLogin')) {
    /**
     * Require user login, redirect if not authenticated
     * 
     * @return void
     */
    function requireLogin(): void {
        if (!isLoggedIn()) {
            redirect(BASE_URL . '/login.php');
        }
    }
}
