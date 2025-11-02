<?php
/**
 * Template loader handling theme overrides.
 *
 * @package X402_Paywall
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template loader singleton.
 */
class X402_Paywall_Template_Loader {

    /**
     * Singleton instance.
     *
     * @var X402_Paywall_Template_Loader|null
     */
    private static $instance = null;

    /**
     * Cached template paths.
     *
     * @var array<string, string>
     */
    private $template_cache = array();

    /**
     * Retrieve singleton instance.
     *
     * @return X402_Paywall_Template_Loader
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_filter('x402_paywall_template_paths', array($this, 'filter_template_paths'), 10, 2);
    }

    /**
     * Retrieve template paths for a given template.
     *
     * @param array  $paths    Existing paths.
     * @param string $template Template slug.
     *
     * @return array
     */
    public function filter_template_paths($paths, $template) {
        $template = $this->normalize_template_name($template);

        $theme_dir     = trailingslashit(get_stylesheet_directory());
        $parent_dir    = trailingslashit(get_template_directory());
        $plugin_dir    = trailingslashit(X402_PAYWALL_PLUGIN_DIR) . 'templates/';
        $template_file = $template . '.php';

        $default_paths = array(
            $theme_dir . 'x402-paywall/' . $template_file,
            $theme_dir . 'x402/' . $template_file,
        );

        if ($parent_dir !== $theme_dir) {
            $default_paths[] = $parent_dir . 'x402-paywall/' . $template_file;
            $default_paths[] = $parent_dir . 'x402/' . $template_file;
        }

        $default_paths[] = $plugin_dir . $template_file;

        return array_unique(array_merge($default_paths, (array) $paths));
    }

    /**
     * Locate a template path.
     *
     * @param string $template Template slug or filename.
     *
     * @return string|null
     */
    public function locate_template($template) {
        $template = $this->normalize_template_name($template);

        if (isset($this->template_cache[$template])) {
            return $this->template_cache[$template];
        }

        $paths = apply_filters('x402_paywall_template_paths', array(), $template);

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->template_cache[$template] = $path;

                return $path;
            }
        }

        return null;
    }

    /**
     * Render a template and optionally return its contents.
     *
     * @param string $template Template slug.
     * @param array  $args     Arguments to extract for the template.
     * @param bool   $echo     Whether to echo directly.
     *
     * @return string
     */
    public function render($template, $args = array(), $echo = true) {
        $template_path = $this->locate_template($template);

        if (!$template_path) {
            return '';
        }

        if (!empty($args)) {
            extract($args, EXTR_SKIP);
        }

        ob_start();
        include $template_path;
        $output = ob_get_clean();

        if ($echo) {
            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- templates handle escaping.
        }

        return $output;
    }

    /**
     * Build a sanitized class list for templates.
     *
     * @param string $template          Template slug.
     * @param array  $additional_classes Additional classes to include.
     *
     * @return string
     */
    public static function get_template_classes($template, $additional_classes = array()) {
        $template = sanitize_key($template);

        $classes = array(
            'x402-component',
            'x402-' . str_replace('_', '-', $template),
        );

        foreach ((array) $additional_classes as $class) {
            $classes[] = $class;
        }

        $classes = array_map('sanitize_html_class', array_filter($classes));
        $classes = apply_filters('x402_paywall_template_classes', $classes, $template);

        return implode(' ', array_unique($classes));
    }

    /**
     * Normalize template name.
     *
     * @param string $template Template slug or filename.
     *
     * @return string
     */
    private function normalize_template_name($template) {
        $template = trim(str_replace("\\", '/', $template));
        $template = preg_replace('/\.php$/', '', $template);

        return sanitize_key($template);
    }
}
