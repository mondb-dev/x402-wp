<?php
/**
 * Rate Limiter for X402 Paywall
 * Prevents abuse of payment verification endpoints
 *
 * @package X402_Paywall
 */

if (!defined('ABSPATH')) {
    exit;
}

class X402_Paywall_Rate_Limiter {
    
    /**
     * Singleton instance
     *
     * @var X402_Paywall_Rate_Limiter|null
     */
    private static $instance = null;
    
    /**
     * Maximum attempts allowed
     *
     * @var int
     */
    private $max_attempts = 5;
    
    /**
     * Time window in seconds
     *
     * @var int
     */
    private $time_window = 300; // 5 minutes
    
    /**
     * Get singleton instance
     *
     * @return X402_Paywall_Rate_Limiter
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        // Allow configuration via filters
        $this->max_attempts = apply_filters('x402_paywall_rate_limit_attempts', 5);
        $this->time_window = apply_filters('x402_paywall_rate_limit_window', 300);
    }
    
    /**
     * Check if request should be rate limited
     *
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param string $action Action being rate limited (default: 'payment_verification')
     * @return bool True if within limits
     * @throws Exception If rate limit exceeded
     */
    public function check_limit($identifier, $action = 'payment_verification') {
        $key = $this->get_transient_key($identifier, $action);
        $attempts = get_transient($key);
        
        if (false === $attempts) {
            // First attempt in this window
            set_transient($key, 1, $this->time_window);
            return true;
        }
        
        if ($attempts >= $this->max_attempts) {
            $retry_after = $this->get_retry_after($key);
            
            throw new Exception(
                sprintf(
                    __('Rate limit exceeded. Please try again in %d seconds.', 'x402-paywall'),
                    $retry_after
                )
            );
        }
        
        // Increment attempt counter
        set_transient($key, $attempts + 1, $this->time_window);
        
        return true;
    }
    
    /**
     * Check rate limit without throwing exception
     *
     * @param string $identifier Unique identifier
     * @param string $action Action being checked
     * @return array Status with 'allowed' boolean and 'remaining' count
     */
    public function check_limit_soft($identifier, $action = 'payment_verification') {
        $key = $this->get_transient_key($identifier, $action);
        $attempts = get_transient($key);
        
        if (false === $attempts) {
            $attempts = 0;
        }
        
        $remaining = max(0, $this->max_attempts - $attempts);
        $allowed = $attempts < $this->max_attempts;
        
        return array(
            'allowed' => $allowed,
            'attempts' => $attempts,
            'remaining' => $remaining,
            'limit' => $this->max_attempts,
            'reset_in' => $allowed ? $this->time_window : $this->get_retry_after($key),
        );
    }
    
    /**
     * Record a successful action (increments counter)
     *
     * @param string $identifier Unique identifier
     * @param string $action Action being recorded
     */
    public function record_attempt($identifier, $action = 'payment_verification') {
        $key = $this->get_transient_key($identifier, $action);
        $attempts = get_transient($key);
        
        if (false === $attempts) {
            set_transient($key, 1, $this->time_window);
        } else {
            set_transient($key, $attempts + 1, $this->time_window);
        }
    }
    
    /**
     * Reset rate limit for an identifier
     *
     * @param string $identifier Unique identifier
     * @param string $action Action to reset
     */
    public function reset_limit($identifier, $action = 'payment_verification') {
        $key = $this->get_transient_key($identifier, $action);
        delete_transient($key);
    }
    
    /**
     * Get rate limit headers for HTTP response
     *
     * @param string $identifier Unique identifier
     * @param string $action Action being checked
     * @return array HTTP headers
     */
    public function get_rate_limit_headers($identifier, $action = 'payment_verification') {
        $status = $this->check_limit_soft($identifier, $action);
        
        return array(
            'X-RateLimit-Limit' => $this->max_attempts,
            'X-RateLimit-Remaining' => $status['remaining'],
            'X-RateLimit-Reset' => time() + $status['reset_in'],
        );
    }
    
    /**
     * Get transient key for rate limiting
     *
     * @param string $identifier Unique identifier
     * @param string $action Action being limited
     * @return string Transient key
     */
    private function get_transient_key($identifier, $action) {
        return 'x402_rate_limit_' . $action . '_' . md5($identifier);
    }
    
    /**
     * Get seconds until rate limit resets
     *
     * @param string $transient_key Transient key
     * @return int Seconds until reset
     */
    private function get_retry_after($transient_key) {
        global $wpdb;
        
        // Query WordPress transient timeout
        $timeout_key = '_transient_timeout_' . $transient_key;
        $timeout = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $timeout_key
        ));
        
        if ($timeout) {
            $retry_after = max(1, intval($timeout) - time());
            return $retry_after;
        }
        
        return $this->time_window;
    }
    
    /**
     * Get client identifier (IP address with privacy considerations)
     *
     * @return string Client identifier
     */
    public static function get_client_identifier() {
        // Get real IP (handles proxies and load balancers)
        $ip = self::get_client_ip();
        
        // Hash IP for privacy (GDPR compliance)
        $salt = defined('AUTH_KEY') ? AUTH_KEY : 'x402_paywall_salt';
        return hash('sha256', $ip . $salt);
    }
    
    /**
     * Get client IP address
     *
     * @return string IP address
     */
    public static function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_REAL_IP',          // Nginx proxy
            'HTTP_X_FORWARDED_FOR',    // Standard forwarded header
            'HTTP_CLIENT_IP',          // Proxy header
            'REMOTE_ADDR',             // Direct connection
        );
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                
                // Also allow private IPs for local development
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Clear all rate limit transients (for cleanup)
     */
    public static function clear_all_rate_limits() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_x402_rate_limit_%' 
             OR option_name LIKE '_transient_timeout_x402_rate_limit_%'"
        );
    }
}
