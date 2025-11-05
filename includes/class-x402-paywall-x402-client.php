<?php
/**
 * X402 Library Client Wrapper
 * Integrates mondb-dev/x402-php with WordPress
 *
 * This class wraps the x402-php library from mondb-dev, which implements
 * the X402 protocol specification by Coinbase.
 *
 * @package X402_Paywall
 * @link https://github.com/mondb-dev/x402-php
 * @link https://github.com/coinbase/x402
 */

if (!defined('ABSPATH')) {
    exit;
}

use X402\Facilitator\FacilitatorClient;
use X402\Middleware\PaymentHandler;
use X402\Exceptions\PaymentRequiredException;
use X402\Exceptions\ValidationException;
use X402\Exceptions\FacilitatorException;

class X402_Paywall_X402_Client {
    
    /**
     * Singleton instance
     *
     * @var X402_Paywall_X402_Client|null
     */
    private static $instance = null;
    
    /**
     * X402 FacilitatorClient instance
     *
     * @var FacilitatorClient|null
     */
    private $facilitator_client = null;

    /**
     * X402 PaymentHandler instance
     *
     * @var PaymentHandler|null
     */
    private $payment_handler = null;

    /**
     * Replay prevention handler
     *
     * @var X402_Paywall_Replay_Prevention|null
     */
    private $replay_prevention = null;

    /**
     * Cached configuration used for client initialization
     *
     * @var array
     */
    private $config = array();
    
    /**
     * Library available flag
     *
     * @var bool
     */
    private $library_available = false;
    
    /**
     * Get singleton instance
     *
     * @return X402_Paywall_X402_Client
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize
     */
    private function __construct() {
        $this->load_library();
        $this->config = $this->build_client_config();
        $this->init_replay_prevention();
        $this->init_client();
    }
    
    /**
     * Initialize replay prevention
     */
    private function init_replay_prevention() {
        $this->replay_prevention = X402_Paywall_Replay_Prevention::get_instance();
    }
    
    /**
     * Load X402 library
     */
    private function load_library() {
        $autoload_path = X402_PAYWALL_PLUGIN_DIR . 'vendor/autoload.php';
        
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
            
            // Check for the correct classes from mondb-dev/x402-php
            $this->library_available = class_exists('X402\Facilitator\FacilitatorClient') && 
                                      class_exists('X402\Middleware\PaymentHandler');
        }
        
        // Show admin notice if library not available
        if (!$this->library_available && is_admin()) {
            add_action('admin_notices', array($this, 'library_missing_notice'));
        }
    }
    
    /**
     * Initialize X402 client using the mondb-dev/x402-php API
     */
    private function init_client() {
        if (!$this->library_available) {
            return;
        }
        
        try {
            $facilitator_url = $this->config['facilitator_url'];
            $auto_settle = $this->config['auto_settle'];
            $buffer_seconds = $this->config['valid_before_buffer'];
            
            // Initialize FacilitatorClient
            $this->facilitator_client = new FacilitatorClient($facilitator_url);
            
            // Initialize PaymentHandler with facilitator and nonce tracker
            $this->payment_handler = new PaymentHandler(
                facilitator: $this->facilitator_client,
                autoSettle: $auto_settle,
                validBeforeBufferSeconds: $buffer_seconds,
                nonceTracker: $this->replay_prevention
            );
            
            do_action('x402_client_initialized', $this->facilitator_client, $this->payment_handler);
            
        } catch (\Exception $e) {
            error_log('X402 Client Initialization Error: ' . $e->getMessage());
            if (is_admin()) {
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>X402 Paywall:</strong> Failed to initialize X402 client: ';
                    echo esc_html($e->getMessage());
                    echo '</p></div>';
                });
            }
        }
    }

    /**
     * Build configuration array from stored options
     *
     * @return array
     */
    private function build_client_config() {
        $facilitator_url = get_option('x402_paywall_facilitator_url', 'https://facilitator.x402.org');
        $auto_settle = get_option('x402_paywall_auto_settle', '1') === '1';
        $valid_before_buffer_option = get_option('x402_paywall_valid_before_buffer', 6);
        $valid_before_buffer = function_exists('absint')
            ? absint($valid_before_buffer_option)
            : (int) $valid_before_buffer_option;
        $enable_evm = get_option('x402_paywall_enable_evm', '1') === '1';
        $enable_spl = get_option('x402_paywall_enable_spl', '1') === '1';

        $config = array(
            'facilitator_url' => $facilitator_url,
            'auto_settle' => $auto_settle,
            'valid_before_buffer' => $valid_before_buffer,
            'enable_evm' => $enable_evm,
            'enable_spl' => $enable_spl,
            'timeout' => 30,
        );

        return apply_filters('x402_client_config', $config);
    }

    /**
     * Get configuration array used to initialize the client
     *
     * @return array
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Check if library is available
     *
     * @return bool
     */
    public function is_available() {
        return $this->library_available && 
               $this->facilitator_client !== null && 
               $this->payment_handler !== null;
    }
    
    /**
     * Get X402 FacilitatorClient instance
     *
     * @return FacilitatorClient|null
     */
    public function get_facilitator_client() {
        return $this->facilitator_client;
    }
    
    /**
     * Get X402 PaymentHandler instance
     *
     * @return PaymentHandler|null
     */
    public function get_payment_handler() {
        return $this->payment_handler;
    }
    
    /**
     * Get X402 client (legacy support - returns facilitator)
     *
     * @return FacilitatorClient|null
     * @deprecated Use get_facilitator_client() or get_payment_handler() instead
     */
    public function get_client() {
        return $this->facilitator_client;
    }
    
    /**
     * Get supported networks and schemes from facilitator
     *
     * @return array|WP_Error Configuration data from facilitator
     */
    public function get_supported_config() {
        if (!$this->is_available()) {
            return new WP_Error('x402_unavailable', 'X402 library not available');
        }
        
        try {
            $config = $this->facilitator_client->getSupported();
            
            // Convert to array format for WordPress
            return array(
                'networks' => $config->networks ?? array(),
                'schemes' => $config->schemes ?? array(),
                'facilitator_url' => $this->config['facilitator_url'],
            );
        } catch (\Exception $e) {
            error_log('X402 Get Supported Config Error: ' . $e->getMessage());
            return new WP_Error('x402_facilitator_error', $e->getMessage());
        }
    }
    
    /**
     * Check if facilitator supports a specific network
     *
     * @param string $network Network identifier (e.g., 'base-mainnet')
     * @return bool
     */
    public function supports_network($network) {
        if (!$this->is_available()) {
            return false;
        }
        
        try {
            $config = $this->facilitator_client->getSupported();
            return $config->supportsNetwork($network);
        } catch (\Exception $e) {
            error_log('X402 Check Network Support Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if facilitator supports a specific payment scheme
     *
     * @param string $scheme Payment scheme (e.g., 'exact')
     * @return bool
     */
    public function supports_scheme($scheme) {
        if (!$this->is_available()) {
            return false;
        }
        
        try {
            $config = $this->facilitator_client->getSupported();
            return $config->supportsScheme($scheme);
        } catch (\Exception $e) {
            error_log('X402 Check Scheme Support Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get network details from facilitator
     *
     * @param string $network Network identifier
     * @return array|null Network configuration
     */
    public function get_network_details($network) {
        if (!$this->is_available()) {
            return null;
        }
        
        try {
            $config = $this->facilitator_client->getSupported();
            if ($config->supportsNetwork($network)) {
                $network_obj = $config->getNetwork($network);
                return array(
                    'id' => $network,
                    'chain_id' => $network_obj->chainId ?? null,
                    'explorer_url' => $network_obj->explorerUrl ?? null,
                    'rpc_url' => $network_obj->rpcUrl ?? null,
                );
            }
            return null;
        } catch (\Exception $e) {
            error_log('X402 Get Network Details Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get replay prevention instance
     *
     * @return X402_Paywall_Replay_Prevention|null
     */
    public function get_replay_prevention() {
        return $this->replay_prevention;
    }
    
    /**
     * Run replay prevention cleanup
     *
     * @return array Cleanup results
     */
    public function run_cleanup() {
        if (!$this->replay_prevention) {
            return array(
                'success' => false,
                'message' => 'Replay prevention not initialized'
            );
        }
        
        $this->replay_prevention->cleanup();
        
        return array(
            'success' => true,
            'message' => 'Cleanup completed',
            'statistics' => $this->replay_prevention->get_statistics()
        );
    }
    
    /**
     * Admin notice for missing library
     */
    public function library_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('X402 Paywall Error:', 'x402-paywall'); ?></strong> 
                <?php esc_html_e('The x402-php library by mondb-dev is not installed.', 'x402-paywall'); ?>
            </p>
            <p>
                <?php esc_html_e('Required library:', 'x402-paywall'); ?> 
                <code>mondb-dev/x402-php</code>
            </p>
            <p><?php esc_html_e('To install, run one of these commands in the plugin directory:', 'x402-paywall'); ?></p>
            <pre style="background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto;">cd <?php echo esc_html(X402_PAYWALL_PLUGIN_DIR); ?>
composer install --no-dev

# OR use the installer script
./install-x402.sh</pre>
            <p>
                <?php esc_html_e('Library repository:', 'x402-paywall'); ?> 
                <a href="https://github.com/mondb-dev/x402-php" target="_blank" rel="noopener noreferrer">
                    https://github.com/mondb-dev/x402-php
                </a>
            </p>
            <p>
                <?php esc_html_e('X402 Protocol Specification:', 'x402-paywall'); ?> 
                <a href="https://github.com/coinbase/x402" target="_blank" rel="noopener noreferrer">
                    https://github.com/coinbase/x402
                </a>
            </p>
            <p>
                <em style="color: #d63638;">
                    <?php esc_html_e('⚠️ The plugin will not function until this dependency is installed.', 'x402-paywall'); ?>
                </em>
            </p>
        </div>
        <?php
    }
}
