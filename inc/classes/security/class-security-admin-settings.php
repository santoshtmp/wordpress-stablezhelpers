<?php

/**
 * Helperbox Security Admin
 *
 * Admin interface for security settings and logs.
 * Provides admin menu, settings pages, and log viewing functionality.
 *
 * @package       Helperbox_Plugin
 * @subpackage    Security
 * @author        santoshtmp
 * @link          https://github.com/santoshtmp/wordpress-custom-helperbox
 * @since         1.0.0
 * @version       1.0.0
 */

namespace Helperbox_Plugin\Security;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Security Admin Settings Class
 *
 * Manages the admin interface for security settings and logs.
 * Handles admin menu registration, settings pages, log viewing,
 * and security-related administrative functionality.
 *
 * @since 1.0.0
 */
class Security_Admin_Settings {

    /**
     * Settings group name for WordPress settings API
     *
     * @since 1.0.0
     * @var string
     */
    const SETTINGS_OPTION_GROUP = 'helperbox_security_SETTINGS_OPTION_GROUP';

    /**
     * Single instance of the class
     *
     * @since 1.0.0
     * @var Security_Admin_Settings|null
     */
    private static $instance = null;

    /**
     * Authorized user ID for admin access (super_admin)
     *
     * @since 1.0.0
     * @var int
     */
    private $authorized_user_id = 1;

    /**
     * Get singleton instance
     *
     * Returns the single instance of this class, creating it if it doesn't exist.
     *
     * @since 1.0.0
     * @return Security_Admin_Settings Single instance of this class
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     *
     * Initializes the class by registering WordPress hooks.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Prevent cloning of the instance
     *
     * Ensures the singleton pattern cannot be bypassed through cloning.
     *
     * @since 1.0.0
     * @return void
     */
    private function __clone() {
    }

    /**
     * Prevent unserializing of the instance
     *
     * Ensures the singleton pattern cannot be bypassed through unserialization.
     *
     * @since 1.0.0
     * @throws \Exception When attempting to unserialize this class.
     * @return void
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Initialize hooks
     *
     * Registers WordPress admin hooks for security settings and logs.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_hooks() {
        add_action('admin_init', [$this, 'admin_init_action']);
    }

    /**
     * Admin init action handler
     *
     * Registers security settings and handles log clearing requests.
     * Verifies nonce before processing log deletion.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_init_action() {

        if (isset($_POST['helperbox_security_nonce']) && wp_verify_nonce($_POST['helperbox_security_nonce'], 'helperbox_clear_logs')) {
            if (isset($_POST['clean_helperbox_log_admin_user_created'])) {
                delete_option('helperbox_log_admin_user_created');
            }
            if (isset($_POST['clean_helperbox_log_admin_user_login'])) {
                delete_option('helperbox_log_admin_user_login');
            }
        }
    }

    /**
     * Register security settings
     *
     * Registers all security-related settings for the helperbox plugin.
     * Settings include comment disabling, REST API restrictions, file modification controls,
     * PHP execution prevention, and emoji disabling.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_setting_options_fields() {
        // security settings
        register_setting(
            self::SETTINGS_OPTION_GROUP,
            'helperbox_disable_comment_feature',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            ]
        );

        register_setting(
            self::SETTINGS_OPTION_GROUP,
            'helperbox_disable_restapi_unauthenticated_user',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            ]
        );

        register_setting(
            self::SETTINGS_OPTION_GROUP,
            'helperbox_restapi_url_prefix',
            [
                'type' => 'text',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => "api"
            ]
        );

        register_setting(
            self::SETTINGS_OPTION_GROUP,
            'helperbox_disallow_file',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            ]
        );

        register_setting(
            self::SETTINGS_OPTION_GROUP,
            'helperbox_disable_phpexecution_upload_dir',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            ]
        );

        register_setting(
            self::SETTINGS_OPTION_GROUP,
            'helperbox_disable_emojicons',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            ]
        );
    }

    /**
     * Render settings fields
     *
     * Outputs the HTML form fields for security settings page.
     * Includes settings for:
     * - Disabling comment feature
     * - REST API access controls
     * - Custom REST API URL prefix
     * - File modification restrictions
     * - PHP execution prevention in uploads directory
     * - Emoji disabling
     * - Display of other applied security measures
     *
     * @since 1.0.0
     * @return void
     */
    public static function render_settings_fields() {
        settings_fields(self::SETTINGS_OPTION_GROUP); ?>
        <table class="form-table form-table-security" table-tab='security'>
            <tr>
                <th scope="row">
                    <label for="helperbox_disable_comment_feature">
                        Disable comment feature completely
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        name="helperbox_disable_comment_feature"
                        id="helperbox_disable_comment_feature"
                        value="1"
                        <?php checked(get_option('helperbox_disable_comment_feature', '1')); ?>>
                    <div class="description">
                        <p>This will remove edit-comments.php page and close comments feature completely.</p>
                        <P>Default: checked </P>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="helperbox_disable_restapi_unauthenticated_user">
                        Disable REST API for unauthenticated users
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        name="helperbox_disable_restapi_unauthenticated_user"
                        id="helperbox_disable_restapi_unauthenticated_user"
                        value="1"
                        <?php checked(get_option('helperbox_disable_restapi_unauthenticated_user', '1')); ?>>
                    <div class="description">
                        <p>This will disable REST API for unauthenticated user. if "_nonce" is verified, it won't restrict.</p>
                        <P>Default: checked </P>
                    </div>

                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="helperbox_restapi_url_prefix">
                        REST API URL prefix
                    </label>
                </th>
                <td>
                    <?php
                    $value = get_option('helperbox_restapi_url_prefix', 'api');
                    ?>
                    <input
                        type="text"
                        name="helperbox_restapi_url_prefix"
                        id="helperbox_restapi_url_prefix"
                        value="<?php echo esc_attr($value); ?>"
                        class="regular-text">
                    <div class="description">
                        <p>This will change the REST API URL prefix from default value "wp-json" to new custom value <?php echo esc_html($value); ?></p>
                        <p>If empty default value "wp-json" will be used.</p>
                        <P>Default: api </P>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="helperbox_disallow_file">
                        Disallow file modifications through admin interface
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        name="helperbox_disallow_file"
                        id="helperbox_disallow_file"
                        value="1"
                        <?php checked(get_option('helperbox_disallow_file', '1')); ?>>
                    <div class="description">
                        <p> This option prevents all file modifications from the WordPress admin area. Plugin and theme installation, updates, and deletion will be disabled.</p>
                        <ul>
                            <li>You can still view available update versions on the <a href="/wp-admin/options-general.php?page=helperbox&tab=security&check_update_status=true" target="_blank"> Update Status</a> page.</li>
                            <li>To apply updates, disable this option and then check for <a href="/wp-admin/update-core.php" target="_blank"> core, plugin, or theme updates.</a></li>
                        </ul>
                        <P>Default: checked </P>
                        <?php
                        // echo DISALLOW_FILE_MODS;
                        if (defined('DISALLOW_FILE_MODS_IN_WP_CONFIG') && DISALLOW_FILE_MODS_IN_WP_CONFIG) {
                            echo '<p style="color:red;">Note: DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS is already defined in wp-config.php. This setting will have no effect until it is removed from wp-config.php.</p>';
                        }
                        ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="helperbox_disable_phpexecution_upload_dir">
                        Disable PHP execution through uploads directory
                    </label>
                </th>
                <td>
                    <?php
                    $check_nginx = stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx');
                    if ($check_nginx === 0 && $check_nginx !== false) {
                    ?>
                        <div class="description">
                            <p> Your server is running Nginx.
                                PHP execution in uploads cannot be disabled automatically.
                                Please add this rule to your Nginx config:</p>
                            <pre>location ~* ^/wp-content/uploads/.*\.php$ { deny all; }</pre>
                        </div>
                    <?php
                    } else {
                    ?>
                        <input
                            type="checkbox"
                            name="helperbox_disable_phpexecution_upload_dir"
                            id="helperbox_disable_phpexecution_upload_dir"
                            value="1"
                            <?php checked(get_option('helperbox_disable_phpexecution_upload_dir', '1')); ?>>
                        <div class="description">
                            <p> This will disable PHP execution through uploads directory.</p>
                        </div>

                    <?php
                    }

                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="helperbox_disable_emojicons">
                        Disable emojicons
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        name="helperbox_disable_emojicons"
                        id="helperbox_disable_emojicons"
                        value="1"
                        <?php checked(get_option('helperbox_disable_emojicons', '1')); ?>>
                    <div class="description">
                        <p>
                            This will disable wp emojicons.
                        </p>
                        <P>Default: checked </P>
                    </div>

                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="helperbox_disable_emojicons">
                        Other Setting that are applied by default:
                    </label>
                </th>
                <td>
                    <ol>
                        <li>Hide wordpress version</li>
                        <li>Nonce life to 30 min</li>
                        <li>Disable xmlrpc and pingback</li>
                        <li>
                            Added header protection like:
                            <pre>
                        header('X-FRAME-OPTIONS: SAMEORIGIN');
                        header('X-XSS-Protection: 1; mode=block');
                        header("Content-Security-Policy: frame-ancestors 'self';");
                        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
                        </pre>
                        </li>
                        <li>
                            Modified top admin bar menu:
                            <ol>
                                <li>Customizer and New content button are removed</li>
                            </ol>
                        </li>
                        <li>Remove dashboard widgets like: Activity, Quick Draft, Wordpress Event and News, and other</li>
                    </ol>
                </td>
            </tr>

        </table>
    <?php
    }

    /**
     * Render security log page
     *
     * Displays the security logs page showing administrator user creation/modification logs
     * and login logs. Includes functionality to clear logs via form submission.
     *
     * @since 1.0.0
     * @global int $this->authorized_user_id The authorized user ID for email notifications.
     * @return void
     */
    public static function render_security_log_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $creation_logs = get_option('helperbox_log_admin_user_created', []);
        $login_logs = get_option('helperbox_log_admin_user_login', []);  ?>
        <div class="wrap">
            <h1>🔒 Custom Security Logs</h1>

            <h2>Administrator User Creation/Modification Logs</h2>
            <?php if (empty($creation_logs)): ?>
                <p>No administrator user creation logs found.</p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Status</th>
                            <th>Target User</th>
                            <th>Performed By</th>
                            <th>IP Address</th>
                            <th>Context</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($creation_logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td>
                                    <span style="color: <?php echo 'blocked' === $log['status'] ? 'red' : 'green'; ?>; font-weight: bold;">
                                        <?php echo esc_html(strtoupper($log['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($log['target_user']); ?><br>
                                    <small><?php echo esc_html($log['target_email']); ?></small>
                                </td>
                                <td><?php echo esc_html($log['actor_login']); ?> (ID: <?php echo esc_html($log['actor_id']); ?>)</td>
                                <td><?php echo esc_html($log['ip_address']); ?></td>
                                <td><small><?php echo esc_html($log['context'] ?? 'unknown'); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top: 30px;">Administrator Login Logs</h2>
            <?php if (empty($login_logs)): ?>
                <p>No administrator login logs found.</p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($login_logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td><?php echo esc_html($log['user_login']); ?> (ID: <?php echo esc_html($log['user_id']); ?>)</td>
                                <td><?php echo esc_html($log['ip_address']); ?></td>
                                <td><small><?php echo esc_html(substr($log['user_agent'], 0, 100)); ?>...</small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h3>📧 Email Notifications</h3>
                <p>Security notifications are being sent to: <strong><?php echo esc_html(get_userdata(self::get_instance()->authorized_user_id)->user_email ?? 'super_admin'); ?></strong></p>
                <p>Backup notifications are sent to site admin: <strong><?php echo esc_html(get_option('admin_email')); ?></strong></p>
            </div>

            <div style="margin-top: 20px;">
                <form method="post" action="">
                    <?php wp_nonce_field('helperbox_clear_logs', 'helperbox_security_nonce'); ?>
                    <button type="submit" name="clean_helperbox_log_admin_user_created" class="button" onclick="return confirm('Clear all creation logs?')">
                        Clear Creation Logs
                    </button>
                    <button type="submit" name="clean_helperbox_log_admin_user_login" class="button" onclick="return confirm('Clear all login logs?')">
                        Clear Login Logs
                    </button>
                </form>
            </div>
        </div>
        <?php

        // Handle log clearing
        if (isset($_POST['helperbox_security_nonce']) && wp_verify_nonce($_POST['helperbox_security_nonce'], 'helperbox_clear_logs')) {
            if (isset($_POST['clean_helperbox_log_admin_user_created'])) {
                delete_option('helperbox_log_admin_user_created');
                echo '<div class="notice notice-success"><p>Creation logs cleared.</p></div>';
            }
            if (isset($_POST['clean_helperbox_log_admin_user_login'])) {
                delete_option('helperbox_log_admin_user_login');
                echo '<div class="notice notice-success"><p>Login logs cleared.</p></div>';
            }
        }
    }

    /**
     * Show admin notice for recent admin creations
     *
     * Displays a warning notice in the admin area when administrator user creation
     * or modification activities have occurred within the last 24 hours.
     * Only shown to the authorized super_admin user.
     *
     * @since 1.0.0
     * @global int $this->authorized_user_id The authorized user ID to display notices.
     * @return void
     */
    public function show_recent_admin_creation_notice() {

        // Only show to super_admin
        if (get_current_user_id() !== $this->authorized_user_id) {
            return;
        }

        $logs = get_option('helperbox_log_admin_user_created', []);

        if (empty($logs)) {
            return;
        }

        // Check for recent creations (last 24 hours)
        $recent_logs = array_filter($logs, function ($log) {
            $log_time = strtotime($log['timestamp']);
            return $log_time > (time() - DAY_IN_SECONDS);
        });

        if (empty($recent_logs)) {
            return;
        }     ?>
        <div class="notice notice-warning is-dismissible">
            <h3>🔒 Security Alert: Recent Administrator User Activity</h3>
            <p><strong><?php echo count($recent_logs); ?></strong> administrator user action(s) detected in the last 24 hours:</p>
            <p style="margin-top: 10px;">
                <a href="<?php echo admin_url('options-general.php?page=helperbox&tab=log'); ?>" class="button">
                    View Complete Security Log
                </a>
            </p>
        </div>
<?php
    }
}
