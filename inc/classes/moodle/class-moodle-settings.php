<?php

/**
 * Moodle Settings Registration
 *
 * Handles registration and rendering of Moodle integration settings.
 *
 * @package Helperbox
 * @subpackage Moodle
 * @since 1.0.0
 * @copyright 2026 https://santoshmagar.com.np/
 * @author santoshmagar.com.np
 */

namespace Helperbox_Plugin\moodle;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// =============================================================================
// MOODLE SETTINGS CLASS
// =============================================================================

if (!class_exists(__NAMESPACE__ . '\\Moodle_Settings')) {
    /**
     * Moodle Settings Registration Class
     *
     * Singleton class that handles registration and rendering
     * of Moodle integration settings in WordPress admin.
     *
     * @since 1.0.0
     */
    class Moodle_Settings {

        /**
         * Settings option group name.
         *
         * @since 1.0.0
         * @var string
         */
        const MOODLESSO_SETTINGS_OPTION_GROUP = 'helperbox_moodlesso_settings_group';

        /**
         * Single instance of this class.
         *
         * @since 1.0.0
         * @var Moodle_Settings
         */
        private static $instance = null;

        /**
         * Get singleton instance.
         *
         * Ensures only one instance of the class exists.
         *
         * @since 1.0.0
         * @return Moodle_Settings The singleton instance
         */
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor - private to prevent direct instantiation.
         *
         * @since 1.0.0
         */
        private function __construct() {
            $this->register_hooks();
        }

        /**
         * Prevent cloning of the instance.
         *
         * @since 1.0.0
         * @return void
         */
        private function __clone() {
        }

        /**
         * Prevent unserialization of the instance.
         *
         * @since 1.0.0
         * @return void
         */
        public function __wakeup() {
            throw new \Exception('Cannot unserialize singleton');
        }

        /**
         * Register WordPress hooks for settings.
         *
         * @since 1.0.0
         * @return void
         */
        private function register_hooks() {
            // Register settings on admin_init
            add_action('admin_init', [$this, 'admin_init_action']);

            // Add admin menu page
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }

        /**
         * Initialize admin settings on admin_init hook.
         *
         * Registers settings for general options and admin login customization.
         *
         * @since 1.0.0
         * @return void
         */
        public function admin_init_action() {
            // Register meta boxes
            $this->register_meta_boxes();

            //register_settings
            $this->register_settings();
        }

        /**
         * Add Moodle Integration admin menu pages.
         *
         * @since 1.0.0
         * @return void
         */
        public function add_admin_menu() {
            // Submenu: Settings
            add_submenu_page(
                'edit.php?post_type=mdl_course',          // Parent slug
                __('Settings', 'helperbox'),              // Page title
                __('Settings', 'helperbox'),              // Menu title
                'manage_options',                         // Capability
                'moodle_site_integration',                // Menu slug
                [$this, 'render_settings_page']           // Callback
            );
        }

        /**
         * Register meta boxes.
         */
        private function register_meta_boxes() {
            add_meta_box(
                'moodle_site_integration_settings',
                __('Moodle Integration', 'helperbox'),
                [$this, 'render_settings_fields'],
                'meta_box_screen_moodle_site_integration',
                'normal',
                'default'
            );
        }

        /**
         * Render Moodle Integration settings page.
         *
         * @since 1.0.0
         * @return void
         */
        public function render_settings_page() {
            // Check user permissions
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'helperbox'));
            } ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <form method="post" action="options.php">
                    <div id="poststuff">
                        <div id="post-body" class="metabox-holder">
                            <div id="post-body-content">
                                <?php
                                do_meta_boxes('meta_box_screen_moodle_site_integration', 'normal', null);
                                submit_button();
                                ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        <?php
        }

        /**
         * Register Moodle integration settings.
         *
         * Registers WordPress settings for Moodle integration:
         * - enabled_moodle_integration: Enable/disable functionality
         * - moodle_auto_sso: Enable automatic SSO on WordPress login
         * - moodle_url: Moodle site URL
         * - moodle_shared_secret: Shared secret key for encryption
         * - moodle_api_token: API token for Moodle web services
         *
         * @since 1.0.0
         * @return void
         *
         * @see register_setting()
         */
        public function register_settings() {
            // Register SSO enabled setting.
            register_setting(
                self::MOODLESSO_SETTINGS_OPTION_GROUP,
                'enabled_moodle_integration',
                [
                    'type'              => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'default'           => false,
                ]
            );

            // Register auto SSO setting.
            register_setting(
                self::MOODLESSO_SETTINGS_OPTION_GROUP,
                'moodle_auto_sso',
                [
                    'type'              => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'default'           => false,
                ]
            );

            // Register Moodle URL setting.
            register_setting(
                self::MOODLESSO_SETTINGS_OPTION_GROUP,
                'moodle_url',
                [
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                    'default'           => '',
                ]
            );

            // Register shared secret setting.
            register_setting(
                self::MOODLESSO_SETTINGS_OPTION_GROUP,
                'moodle_shared_secret',
                [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ]
            );

            // Register API token setting.
            register_setting(
                self::MOODLESSO_SETTINGS_OPTION_GROUP,
                'moodle_api_token',
                [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ]
            );
        }

        /**
         * Render Moodle settings form fields.
         *
         * Outputs HTML form for configuring Moodle integration including:
         * - enable/disable toggle
         * - Auto SSO option
         * - Moodle site URL
         * - Shared secret key
         * - API token
         * - Usage instructions and shortcode examples
         *
         * @since 1.0.0
         * @return void
         */
        public function render_settings_fields() {
            settings_fields(self::MOODLESSO_SETTINGS_OPTION_GROUP); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Moodle Integration</th>
                    <td>
                        <input type="checkbox" name="enabled_moodle_integration" value="1" <?php checked(get_option('enabled_moodle_integration'), 1); ?> />
                        <p class="description">Enable Moodle Integration.</p>
                    </td>
                </tr>
                <?php if (Moodle_Integration::get_instance()->is_enabled()) : ?>
                    <tr class="moodle-sso-field">
                        <th scope="row">Moodle URL</th>
                        <td>
                            <input type="url" name="moodle_url" value="<?php echo esc_attr(get_option('moodle_url')); ?>" class="regular-text required-field" required />
                            <p class="description">Your Moodle site URL (e.g., https://moodle.example.com)</p>
                        </td>
                    </tr>
                    <tr class="moodle-sso-field">
                        <th scope="row">Shared Secret Key</th>
                        <td>
                            <input type="text" name="moodle_shared_secret" value="<?php echo esc_attr(get_option('moodle_shared_secret')); ?>" class="regular-text required-field" required />
                            <p class="description">Must match the shared secret in Moodle settings. Used for AES-128-CTR encryption.</p>
                        </td>
                    </tr>
                    <tr class="moodle-sso-field">
                        <th scope="row">API Token</th>
                        <td>
                            <input type="text" name="moodle_api_token" value="<?php echo esc_attr(get_option('moodle_api_token')); ?>" class="regular-text required-field" required />
                            <p class="description">Generated from Moodle API token, which is wstoken for Moodle webservice.</p>
                        </td>
                    </tr>
                    <tr class="moodle-sso-field">
                        <th scope="row">Auto SSO</th>
                        <td>
                            <input type="checkbox" name="moodle_auto_sso" value="1" <?php checked(get_option('moodle_auto_sso'), 1); ?> />
                            <p class="description">Automatically redirect users to Moodle SSO on WordPress login/logout.</p>
                        </td>
                    </tr>
                    <tr class="moodle-sso-description-info">
                        <th>Usage Instructions</th>
                        <td>
                            <ol>
                                <li>Configure the same Shared Secret Key in both WordPress and Moodle</li>
                                <li>Users logging into WordPress will be automatically logged into Moodle</li>
                                <li>Use <code>[moodle_link]</code> shortcode to add "Go to Moodle" links</li>
                                <li>Use <code>[moodle_status]</code> shortcode to show connection status</li>
                            </ol>
                        </td>
                    </tr>
                    <tr class="moodle-sso-description-info">
                        <th>Shortcode Examples</th>
                        <td>
                            <ul>
                                <li><code>[moodle_link]Go to Moodle[/moodle_link]</code> - Link to Moodle dashboard</li>
                                <li><code>[moodle_link course_id="5"]View Course[/moodle_link]</code> - Link to specific course</li>
                                <li><code>[moodle_link redirect_to="/my"]View Dashboard[/moodle_link]</code> - Link to specific Moodle URL</li>
                                <li><code>[moodle_status]</code> - Show Moodle connection status</li>
                            </ul>
                        </td>
                    </tr>
                    <tr class="moodle-sso-description-info">
                        <th>Testing</th>
                        <td>
                            <?php Moodle_SSOHandler::render_test_example(); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <script>
                jQuery(document).ready(function($) {
                    $('input[name="enabled_moodle_integration"]').on('change', function() {
                        var isChecked = $(this).is(':checked');
                        $('.moodle-sso-field').toggle(isChecked);
                        $('.moodle-sso-description-info').toggle(isChecked);
                        $('.moodle-sso-field input.required-field').each(function() {
                            this.required = isChecked;
                        });
                    });
                });
            </script>
<?php
        }
    }
}
