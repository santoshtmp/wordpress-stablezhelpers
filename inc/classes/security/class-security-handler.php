<?php

/**
 * Helperbox Security Handler
 *
 * Main security handler class for the Helperbox plugin.
 * Provides comprehensive security features including header protection,
 * REST API authentication, dashboard customization, and more.
 *
 * @package       Helperbox_Plugin
 * @subpackage    Security
 * @author        santoshtmp
 * @link          https://github.com/santoshtmp/wordpress-custom-helperbox
 * @since         1.0.0
 * @version       1.0.0
 */

namespace Helperbox_Plugin\Security;

use Helperbox_Plugin\admin\Check_Settings;
use Helperbox_Plugin\HelperBox;
use WP_Error;

/**
 * Security Handler
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Security handler main class
 *
 */
class Security_Handler {

    /**
     * Single instance of the class
     *
     * @var Security_Handler|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return Security_Handler Single instance of this class
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Decrypt value
     *
     * @link https://www.php.net/manual/en/function.openssl-encrypt.php
     * @param string $value Encrypted value to decrypt
     * @return string|false Decrypted value or false on failure
     */
    public function decryptValue($value) {
        $encryptionAlgorithm = "AES-256-CBC";
        $openssl_decrypt_key = "3uPSO9hQ/2KgLJ5iJXU03Lhaef5SWT4YghGtZGC43AExF6/eLagf2OeB3E1";
        $openssl_iv_key = ")]KK[P2Qv7G!9p-a";
        $decryptedValue = openssl_decrypt($value, $encryptionAlgorithm, $openssl_decrypt_key, 0, $openssl_iv_key);

        return $decryptedValue;
    }

    /**
     * Encrypt value
     *
     * @link https://www.php.net/manual/en/function.openssl-encrypt.php
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    public function encryptValue($value) {
        $encryptionAlgorithm = "AES-256-CBC";
        $openssl_decrypt_key = "3uPSO9hQ/2KgLJ5iJXU03Lhaef5SWT4YghGtZGC43AExF6/eLagf2OeB3E1";
        $openssl_iv_key = ")]KK[P2Qv7G!9p-a";

        return openssl_encrypt($value, $encryptionAlgorithm, $openssl_decrypt_key, 0, $openssl_iv_key);
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Prevent cloning of the instance
     *
     * @return void
     */
    private function __clone() {
    }

    /**
     * Prevent unserializing of the instance
     *
     * @return void
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Initialize hooks
     */
    private function register_hooks() {
        add_action('init', [$this, 'init_action'], 10);
        add_action('send_headers', [$this, 'header_protection'], 10);

        add_action('admin_init', [$this, 'disable_plugin_modifications']);
        add_action('admin_bar_menu', [$this, 'modify_top_admin_bar_menu'], 99);
        add_action('wp_dashboard_setup', [$this, 'remove_dashboard_widgets'], 99999);

        add_filter('rest_authentication_errors', [$this, 'rest_authentication_auth']);

        // Initialize User Security Monitor
        Security_User_Monitor::get_instance();
        Security_Admin_Settings::get_instance();

        // Disable comment feature
        $this->disable_comment_feature();

        /**
         * Modify nonce lifetime (default: 24 hours)
         *
         * @link https://developer.wordpress.org/reference/hooks/nonce_life/
         */
        add_filter('nonce_life', function () {
            return 30 * MINUTE_IN_SECONDS;
        });

        /**
         * Hide WordPress version from generator tag
         *
         * @link https://developer.wordpress.org/reference/hooks/the_generator/
         */
        add_filter('the_generator', '__return_empty_string');

        /**
         * Clean up wp_head output by removing default WordPress actions
         * @link https://developer.wordpress.org/reference/functions/remove_action/
         * @link https://developer.wordpress.org/reference/hooks/wp_head/
         * @see wp-includes/default-filters.php
         */
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_shortlink_wp_head');


        /**
         * Disable XML-RPC functionality for security hardening
         *
         * @link https://developer.wordpress.org/reference/hooks/xmlrpc_enabled/
         * @link https://developer.wordpress.org/reference/hooks/xmlrpc_methods/
         * @link https://developer.wordpress.org/reference/hooks/wp_headers/
         */
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', function ($methods) {
            // Disable user creation/editing via XML-RPC
            unset($methods['wp.newUser']);
            unset($methods['wp.editUser']);
            return $methods;
        });
        add_filter('wp_headers', function ($headers) {
            unset($headers['X-Pingback']);
            return $headers;
        });

        /**
         * Disable self pingbacks (internal links triggering pingbacks)
         *
         * @link https://developer.wordpress.org/reference/hooks/pre_ping/
         */
        add_action('pre_ping', function (&$links) {
            $home = get_option('home');
            foreach ($links as $l => $link) {
                if (0 === strpos($link, $home)) {
                    unset($links[$l]);
                }
            }
        });

        /**
         * Customize REST API URL prefix (default: wp-json → custom slug like /api)
         *
         * @link https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
         * @link https://developer.wordpress.org/rest-api/reference/
         * @link https://developer.wordpress.org/apis/
         * 
         */
        $restapi_url_prefix = get_option('helperbox_restapi_url_prefix', 'api');
        if ($restapi_url_prefix) {
            add_filter('rest_url_prefix', function () use ($restapi_url_prefix) {
                return $restapi_url_prefix;
            });
        }
    }

    /**
     * Init action
     * 
     * @return void
     */
    public function init_action() {
        // Disable emojis
        $this->disable_wp_emojicons();
    }

    /**
     * Header protection
     *
     * Add security headers to stop site loading in an iFrame and protect against common attacks.
     *
     * @link https://developer.wordpress.org/reference/hooks/send_headers/
     * @return void
     */
    public function header_protection() {
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header("Content-Security-Policy: frame-ancestors 'self';");
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header("Permissions-Policy: geolocation=(self), microphone=(), camera=(), fullscreen=*");
        header("X-Permitted-Cross-Domain-Policies: none");
        // header("Access-Control-Allow-Origin: " . home_url());
    }

    /**
     * REST API authentication
     *
     * Disable REST API for not logged in users if the _nonce is not verified.
     *
     * @link https://developer.wordpress.org/reference/hooks/rest_authentication_errors/
     * @param true|WP_Error $access Result of authentication attempt
     * @return void|WP_Error
     */
    public function rest_authentication_auth($access) {

        // Check setting
        if (get_option('helperbox_disable_restapi_unauthenticated_user', '1') != '1') {
            return;
        }

        if (true === $access || is_wp_error($access)) {
            return $access;
        }

        $_nonce = (isset($_REQUEST['_nonce'])) ? $_REQUEST['_nonce'] : '';
        if (!wp_verify_nonce($_nonce, home_url(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)))) {
            if (!is_user_logged_in()) {
                return new WP_Error(
                    'rest_disabled',
                    __('The WordPress REST API has been disabled.'),
                    array(
                        'status' => rest_authorization_required_code(),
                    )
                );
            }
        }
    }

    /**
     * Disable comment feature
     *
     * Completely disable WordPress comment functionality.
     *
     * @link https://developer.wordpress.org/reference/hooks/comments_open/
     * @link https://developer.wordpress.org/reference/hooks/pings_open/
     * @link https://developer.wordpress.org/reference/hooks/comment_form_default_fields/
     * @return void
     */
    private function disable_comment_feature() {

        // Check setting
        if (!Check_Settings::is_helperbox_disable_comment()) {
            return;
        }

        add_action('admin_init', function () {
            // Redirect any user trying to access comments page
            global $pagenow;
            if ($pagenow === 'edit-comments.php') {
                wp_safe_redirect(admin_url());
                exit;
            }
            // Remove comments metabox from dashboard
            remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');

            // Disable support for comments and trackbacks in post types
            foreach (get_post_types() as $post_type) {
                if (post_type_supports($post_type, 'comments')) {
                    remove_post_type_support($post_type, 'comments');
                    remove_post_type_support($post_type, 'trackbacks');
                }
            }

            // Remove comments page in menu
            remove_menu_page('edit-comments.php');
        });

        // Close comments on the front-end
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);

        // Hide existing comments
        add_filter('comments_array', '__return_empty_array', 10, 2);

        // Remove comments links from admin bar
        add_action('init', function () {
            if (is_admin_bar_showing()) {
                remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
            }
        });

        // Disable Comment Form Website URL
        add_filter('comment_form_default_fields', function ($fields) {
            if (isset($fields['url'])) {
                unset($fields['url']);
            }
            return $fields;
        }, 150);

        // Remove comments admin menu bar
        add_action('admin_bar_menu', function ($admin_bar) {
            $admin_bar->remove_menu('comments');
        }, 99);
    }

    /**
     * Disable plugin modifications
     *
     * Disable plugin installation, updates, and file editing.
     * To re-enable plugin installations or updates, remove disable_plugin_modifications.
     *
     * @return void
     */
    public function disable_plugin_modifications() {
        // Check if DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS are already defined in wp-config.php
        if ((defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) || (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)) {
            define('DISALLOW_FILE_MODS_IN_WP_CONFIG', true);
            return;
        }

        // Check setting
        if (get_option('helperbox_disallow_file', '1') != '1') {
            return;
        }

        // Disable plugin and theme editor
        define('DISALLOW_FILE_EDIT', true);

        // Disable plugin and theme installation, updates, and deletion
        define('DISALLOW_FILE_MODS', true);
    }

    /**
     * Modify top admin bar menu
     *
     * Remove unwanted menu items from the admin bar.
     *
     * @link https://developer.wordpress.org/reference/classes/wp_admin_bar/add_menu/
     * @link https://developer.wordpress.org/reference/classes/wp_admin_bar/remove_menu/
     * @param WP_Admin_Bar $wp_admin_bar WordPress Admin Bar object
     * @return void
     */
    public function modify_top_admin_bar_menu($wp_admin_bar) {
        $wp_admin_bar->remove_menu('customize');
        $wp_admin_bar->remove_node('new-content');

        // Check setting for updates helperbox_disallow_file
        if (get_option('helperbox_disallow_file', '1') == '1') {
            $wp_admin_bar->remove_node('updates');
        }
    }


    /**
     * Disable emojis in WordPress
     *
     * @return void
     */
    public function disable_wp_emojicons() {

        // Check setting
        if (get_option('helperbox_disable_emojicons', '1') != '1') {
            return;
        }

        // Remove emoji script from header
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');

        // Remove emoji from TinyMCE editor
        remove_filter('the_content', 'wp_staticize_emoji');
        remove_filter('the_excerpt', 'wp_staticize_emoji');
        remove_filter('comment_text', 'wp_staticize_emoji');
        remove_filter('widget_text_content', 'wp_staticize_emoji');

        // Remove emoji from RSS feed
        remove_action('wp_mail', 'wp_staticize_emoji_for_email');
        remove_action('the_content_feed', 'wp_staticize_emoji');
        remove_action('comment_text_rss', 'wp_staticize_emoji');

        // Remove emoji CDN path
        /**
         * Disable emojis in TinyMCE editor
         *
         * @param array $plugins
         * @return array Difference between the two arrays
         */
        add_filter('tiny_mce_plugins', function ($plugins) {
            if (is_array($plugins)) {
                return array_diff($plugins, array('wpemoji'));
            }
            return array();
        });

        /**
         * Remove emoji CDN hostname from DNS prefetching hints.
         *
         * @param array  $urls          URLs to print for resource hints.
         * @param string $relation_type The relation type the URLs are printed for.
         * @return array Difference between the two arrays.
         */
        add_filter('wp_resource_hints', function ($urls, $relation_type) {
            if ('dns-prefetch' == $relation_type) {
                /** This filter is documented in wp-includes/formatting.php */
                $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');

                $urls = array_diff($urls, array($emoji_svg_url));
            }

            return $urls;
        }, 10, 2);
    }

    /**
     * Remove dashboard widgets
     *
     * Remove unwanted widgets from the WordPress dashboard.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_dashboard_setup/
     * @link https://developer.wordpress.org/reference/functions/unregister_widget/
     * @return void
     */
    public function remove_dashboard_widgets() {
        global $wp_meta_boxes;

        unset($wp_meta_boxes['dashboard']['normal']['core']['happy_addons_news_update']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['e-dashboard-overview']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity']);

        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    public function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'Unknown';
    }
 
}
