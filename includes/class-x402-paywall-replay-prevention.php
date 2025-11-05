<?php
/**
 * WordPress implementation of X402 Replay Prevention
 * 
 * Implements ReplayPreventionInterface using wp_x402_payment_logs table
 * for persistent replay attack prevention.
 *
 * @package X402_Paywall
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use X402\Nonce\NonceTrackerInterface;
use X402\Exceptions\ValidationException;

/**
 * Class X402_Paywall_Replay_Prevention
 * 
 * Persistent nonce tracking using WordPress database
 */
class X402_Paywall_Replay_Prevention implements NonceTrackerInterface {
    
    /**
     * Singleton instance
     *
     * @var X402_Paywall_Replay_Prevention|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return X402_Paywall_Replay_Prevention
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to enforce singleton
     */
    private function __construct() {
        // Initialize
    }
    
    /**
     * Check if a nonce has been used (NonceTrackerInterface)
     *
     * @param string $nonce The nonce to check (format: network:txHash)
     * @return bool True if nonce has been used
     */
    public function hasNonce(string $nonce): bool {
        return $this->isNonceUsed($nonce);
    }
    
    /**
     * Check if a nonce has been used (NonceTrackerInterface)
     *
     * @param string $nonce The nonce to check (format: network:txHash)
     * @return bool True if nonce has been used
     */
    public function isNonceUsed(string $nonce): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_payment_logs';
        
        // First check transient cache for performance
        $cache_key = 'x402_nonce_' . md5($nonce);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (bool) $cached;
        }
        
        // Parse nonce format: network:txHash
        $parts = explode(':', $nonce, 2);
        if (count($parts) !== 2) {
            return false; // Invalid format
        }
        
        list($network, $txHash) = $parts;
        
        // Query database for verified/settled payments
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE network = %s 
             AND transaction_hash = %s 
             AND payment_status IN ('verified', 'settled')
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $network,
            strtolower($txHash) // Normalize to lowercase
        ));
        
        $is_used = $count > 0;
        
        // Cache result for 5 minutes
        set_transient($cache_key, $is_used ? 1 : 0, 300);
        
        return $is_used;
    }
    
    /**
     * Mark a nonce as used (NonceTrackerInterface)
     *
     * @param string $nonce The nonce to mark as used (format: network:txHash)
     * @param int $ttlSeconds Time-to-live in seconds
     * @return bool True if successfully marked, false if already used
     * @throws ValidationException If nonce format is invalid
     */
    public function markUsed(string $nonce, int $ttlSeconds): bool {
        // Validate nonce format
        $parts = explode(':', $nonce, 2);
        if (count($parts) !== 2) {
            throw new ValidationException('Invalid nonce format. Expected: network:txHash');
        }
        
        // Check if already used
        if ($this->isNonceUsed($nonce)) {
            return false;
        }
        
        // Mark as used in cache
        $cache_key = 'x402_nonce_' . md5($nonce);
        set_transient($cache_key, 1, $ttlSeconds);
        
        // Note: Database insertion is handled by X402_Paywall_Payment_Handler
        // when logging the payment. This method just updates the cache.
        
        // Fire WordPress action for tracking
        do_action('x402_paywall_nonce_marked_used', $nonce);
        
        return true;
    }
    
    /**
     * Mark a nonce as used (NonceTrackerInterface - void version)
     *
     * @param string $nonce The nonce to mark as used (format: network:txHash)
     * @param int $ttlSeconds Time-to-live in seconds
     * @throws ValidationException If nonce format is invalid or already used
     */
    public function markNonceUsed(string $nonce, int $ttlSeconds): void {
        if (!$this->markUsed($nonce, $ttlSeconds)) {
            throw new ValidationException('Nonce already used (replay attack detected)');
        }
    }
    
    /**
     * Remove a nonce from tracking (NonceTrackerInterface)
     *
     * @param string $nonce The nonce to remove
     * @return bool True if removed, false if didn't exist
     */
    public function remove(string $nonce): bool {
        $cache_key = 'x402_nonce_' . md5($nonce);
        return delete_transient($cache_key);
    }
    
    /**
     * Check if transaction was already processed (legacy method)
     *
     * @param string $network Network identifier (e.g., 'base-mainnet')
     * @param string $txHash Transaction hash
     * @return bool True if already processed
     */
    public function isProcessed(string $network, string $txHash): bool {
        return $this->isNonceUsed($network . ':' . $txHash);
    }
    
    /**
     * Mark transaction as processed (legacy method)
     *
     * @param string $network Network identifier
     * @param string $txHash Transaction hash
     * @param int $ttl Time-to-live in seconds (default: 24 hours)
     */
    public function markProcessed(string $network, string $txHash, int $ttl = 86400): void {
        try {
            $this->markNonceUsed($network . ':' . $txHash, $ttl);
        } catch (ValidationException $e) {
            // Already used - ignore for legacy compatibility
        }
    }
    
    /**
     * Clean up expired entries
     * 
     * Called by WordPress cron job
     */
    public function cleanup(): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_payment_logs';
        
        // Delete failed payment attempts older than 7 days
        $deleted_failed = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} 
             WHERE status = 'failed' 
             AND created_at < %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        // Delete pending payments older than 24 hours
        $deleted_pending = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} 
             WHERE status = 'pending' 
             AND created_at < %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        
        // Clear orphaned transients
        $this->cleanup_transients();
        
        // Log cleanup results
        if ($deleted_failed > 0 || $deleted_pending > 0) {
            error_log(sprintf(
                'X402 Replay Prevention Cleanup: Removed %d failed and %d pending payments',
                $deleted_failed,
                $deleted_pending
            ));
        }
        
        do_action('x402_paywall_replay_cleanup_completed', $deleted_failed, $deleted_pending);
    }
    
    /**
     * Clean up orphaned transients
     * 
     * @return int Number of transients deleted
     */
    private function cleanup_transients(): int {
        global $wpdb;
        
        // Delete expired transients matching our pattern
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_x402_tx_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
        
        // Delete the corresponding transient records
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_x402_tx_%' 
             AND option_name NOT IN (
                 SELECT REPLACE(option_name, '_transient_timeout_', '_transient_') 
                 FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_x402_tx_%'
             )"
        );
        
        return (int) $deleted;
    }
    
    /**
     * Get statistics about processed transactions
     * 
     * @return array Statistics
     */
    public function get_statistics(): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'x402_payment_logs';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) as settled,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM {$table}
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            ARRAY_A
        );
        
        return $stats ?: [
            'total' => 0,
            'verified' => 0,
            'settled' => 0,
            'failed' => 0,
            'pending' => 0
        ];
    }
}
