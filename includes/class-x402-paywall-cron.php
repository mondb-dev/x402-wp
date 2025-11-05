<?php
/**
 * Cron job registration for replay prevention cleanup
 * 
 * @package X402_Paywall
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class X402_Paywall_Cron {
    
    /**
     * Singleton instance
     *
     * @var X402_Paywall_Cron|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return X402_Paywall_Cron
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
        // Register cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Hook cron job
        add_action('x402_paywall_daily_cleanup', array($this, 'run_daily_cleanup'));
        
        // Schedule on plugin activation
        add_action('x402_paywall_activated', array($this, 'schedule_events'));
        
        // Clear on plugin deactivation
        add_action('x402_paywall_deactivated', array($this, 'clear_scheduled_events'));
    }
    
    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['x402_daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display'  => __('Once Daily (X402 Cleanup)', 'x402-paywall')
        );
        
        return $schedules;
    }
    
    /**
     * Schedule cron events
     */
    public function schedule_events() {
        if (!wp_next_scheduled('x402_paywall_daily_cleanup')) {
            wp_schedule_event(time(), 'x402_daily', 'x402_paywall_daily_cleanup');
        }
    }
    
    /**
     * Clear scheduled events
     */
    public function clear_scheduled_events() {
        wp_clear_scheduled_hook('x402_paywall_daily_cleanup');
    }
    
    /**
     * Run daily cleanup
     */
    public function run_daily_cleanup() {
        $client = X402_Paywall_X402_Client::get_instance();
        
        if ($client->is_available()) {
            $replay_prevention = $client->get_replay_prevention();
            
            if ($replay_prevention) {
                $replay_prevention->cleanup();
                error_log('X402 Paywall: Daily cleanup completed');
            }
        }
    }
}
