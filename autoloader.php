<?php
/**
 * Custom autoloader for X402 Paywall plugin
 * 
 * This autoloader handles loading of x402-php library classes
 * when Composer dependencies are not available.
 *
 * @package X402_Paywall
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PSR-4 autoloader for X402 namespace
 */
spl_autoload_register(function ($class) {
    // Check if class is in X402 namespace
    if (strpos($class, 'X402\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $relative_class = substr($class, 5); // Remove 'X402\' prefix
    $file = X402_PAYWALL_PLUGIN_DIR . 'vendor/x402-php/src/' . str_replace('\\', '/', $relative_class) . '.php';
    
    // Load the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Check if vendor/autoload.php exists (from Composer)
 * If it does, load it for Guzzle and other dependencies
 */
$composer_autoload = X402_PAYWALL_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}
