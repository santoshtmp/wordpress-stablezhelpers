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
 * @version       1.0.0
 */

namespace Helperbox_Plugin\Security;

use Helperbox_Plugin\HelperBox;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * User Security Monitor Class
 */
class Security_User_Monitor {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Authorized user ID for admin creation (super_admin)
     */
    private $authorized_user_id = 1;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
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
        // Intercept user
        add_action('wp_login', [$this, 'log_user_login'], 10, 2);
        add_filter('wp_insert_user_data', [$this, 'validate_user_creation'], 10, 2);
        add_action('user_register', [$this, 'notify_admin_user_created'], 10, 1);
        add_action('profile_update', [$this, 'notify_admin_role_changed'], 10, 2);
        add_action('set_user_role', [$this, 'log_role_assignment'], 10, 3);

        // REST API protection
        add_filter('rest_pre_insert_user', [$this, 'validate_rest_user_creation'], 10, 2);
    }

    /**
     * Log user login for security monitoring
     *
     * @param string  $user_login Username.
     * @param WP_User $user       User object.
     */
    public function log_user_login($user_login, $user) {
        if (in_array('administrator', (array) $user->roles, true)) {
            $log_entry = [
                'timestamp' => current_time('mysql'),
                'event' => 'admin_login',
                'user_id' => $user->ID,
                'user_login' => $user_login,
                'ip_address' => Security_Handler::get_instance()->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            ];

            $log_message = sprintf(
                'Admin Login: %s (ID: %d) from IP %s',
                $user_login,
                $user->ID,
                $log_entry['ip_address']
            );

            HelperBox::set_log_message($log_message, 'message');

            // Store login logs
            $login_logs = get_option('helperbox_log_admin_user_login', []);
            array_unshift($login_logs, $log_entry);
            $login_logs = array_slice($login_logs, 0, 30);
            update_option('helperbox_log_admin_user_login', $login_logs, false);
        }
    }

    /**
     * Log admin creation/modification
     *
     * @param string $status   Status (allowed, blocked, etc).
     * @param int    $actor_id Actor user ID.
     * @param array  $userdata User data.
     * @param string $context  Context of the action.
     */
    private function log_admin_creation($status, $actor_id, $userdata, $context = 'creation') {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'status' => $status,
            'actor_id' => $actor_id,
            'actor_login' => get_userdata($actor_id)->user_login ?? 'unknown',
            'target_user' => $userdata['user_login'] ?? $userdata['user_id'] ?? 'unknown',
            'target_email' => $userdata['user_email'] ?? 'unknown',
            'ip_address' => Security_Handler::get_instance()->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'context' => $context,
        ];

        // Store in database for admin viewing
        $logs = get_option('helperbox_log_admin_user_created', []);
        array_unshift($logs, $log_entry);

        // Keep only last 30 entries
        $logs = array_slice($logs, 0, 30);
        update_option('helperbox_log_admin_user_created', $logs, false);

        // Also store in usermeta for the created user
        if (isset($userdata['user_id'])) {
            update_user_meta($userdata['user_id'], '_helperbox_creation_log', $log_entry);
        }
    }

    /**
     * Validate user creation before it happens
     *
     * @param array $data    The user data.
     * @param array $userdata The original user data.
     * @return array Modified user data
     */
    public function validate_user_creation($data, $userdata) {
        // Check if trying to create administrator
        if (isset($userdata['role']) && 'administrator' === $userdata['role']) {
            $this->validate_admin_creation($userdata, 'creation');
        }

        // Check if user is being created with administrator capabilities directly
        if (isset($userdata['cap_key']) || isset($userdata['wp_capabilities'])) {
            $this->validate_admin_creation($userdata, 'capability');
        }

        return $data;
    }

    /**
     * Validate REST API user creation
     *
     * @param mixed           $prepared_user Prepared user data.
     * @param WP_REST_Request $request       Request object.
     * @return mixed Prepared user data or WP_Error
     */
    public function validate_rest_user_creation($prepared_user, $request) {
        if (is_wp_error($prepared_user)) {
            return $prepared_user;
        }

        $roles = $request->get_param('roles');

        if (!empty($roles) && in_array('administrator', (array) $roles, true)) {
            $this->validate_admin_creation(
                [
                    'user_login' => $request->get_param('username'),
                    'user_email' => $request->get_param('email'),
                    'role' => 'administrator'
                ],
                'rest_api'
            );
        }

        return $prepared_user;
    }

    /**
     * Validate administrator user creation
     *
     * @param array  $userdata User data.
     * @param string $context  Context of the validation.
     */
    private function validate_admin_creation($userdata, $context = 'creation') {
        // Get current user
        $current_user = wp_get_current_user();

        // Allow super_admin (authorized user ID) to create admins
        if ($current_user->ID === $this->authorized_user_id) {
            $this->log_admin_creation('allowed', $current_user->ID, $userdata, $context);
            return;
        }

        // Check if current user has specific capability
        if (user_can($current_user, 'create_users') && user_can($current_user, 'promote_users')) {
            // Log but allow - will send notification
            $this->log_admin_creation('allowed_with_notification', $current_user->ID, $userdata, $context);
            return;
        }

        // Block the creation - log it
        $this->log_admin_creation('blocked', $current_user->ID, $userdata, $context);
    }

    /**
     * Notify super_admin when new user is created
     *
     * @param int $user_id User ID.
     */
    public function notify_admin_user_created($user_id) {
        $user = get_userdata($user_id);

        if (!$user) {
            return;
        }

        // Check if user has administrator role
        if (in_array('administrator', (array) $user->roles, true)) {
            $this->send_admin_notification($user, 'created');
        }
    }

    /**
     * Notify when user role is changed to administrator
     *
     * @param int    $user_id  User ID.
     * @param object $old_data Old user data.
     */
    public function notify_admin_role_changed($user_id, $old_data) {
        $user = get_userdata($user_id);

        if (!$user || !$old_data) {
            return;
        }

        $old_roles = isset($old_data->roles) ? $old_data->roles : [];
        $new_roles = $user->roles;

        // Check if administrator role was added
        if (in_array('administrator', $new_roles, true) && !in_array('administrator', $old_roles, true)) {
            $this->send_admin_notification($user, 'role_changed');
        }

        // Check if administrator role was removed
        if (!in_array('administrator', $new_roles, true) && in_array('administrator', $old_roles, true)) {
            $this->send_admin_notification($user, 'role_removed');
        }
    }

    /**
     * Log role assignment
     *
     * @param int   $user_id   User ID.
     * @param string $role     New role.
     * @param array $old_roles Old roles.
     */
    public function log_role_assignment($user_id, $role, $old_roles) {
        if ('administrator' === $role) {
            $this->log_admin_creation(
                'role_assigned',
                get_current_user_id(),
                [
                    'user_id' => $user_id,
                    'user_login' => get_userdata($user_id)->user_login ?? 'unknown'
                ],
                'role_change'
            );
        }
    }

    /**
     * Send notification email to super_admin
     *
     * @param WP_User $user   User object.
     * @param string  $action Action performed.
     */
    private function send_admin_notification($user, $action) {
        // Get super_admin email
        $super_admin = get_userdata($this->authorized_user_id);
        if (!$super_admin) {
            return;
        }

        $current_user = wp_get_current_user();
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');

        // Email subject
        $subject = sprintf(
            '[%s] Security Alert: Administrator User %s - %s',
            $site_name,
            ucfirst(str_replace('_', ' ', $action)),
            $user->user_login
        );

        // Email body
        $message = sprintf(
            "A new administrator user action has been detected on %s\n\n",
            $site_name
        );

        $message .= sprintf("Action: %s\n", ucfirst(str_replace('_', ' ', $action)));
        $message .= sprintf("User ID: %d\n", $user->ID);
        $message .= sprintf("Username: %s\n", $user->user_login);
        $message .= sprintf("Email: %s\n", $user->user_email);
        $message .= sprintf("Display Name: %s\n", $user->display_name);
        $message .= sprintf("Date: %s\n\n", current_time('mysql'));

        $message .= "Performed by:\n";
        $message .= sprintf("User: %s (ID: %d)\n", $current_user->user_login, $current_user->ID);
        $message .= sprintf("Email: %s\n", $current_user->user_email);
        $message .= sprintf("IP Address: %s\n", Security_Handler::get_instance()->get_client_ip());
        $message .= sprintf("User Agent: %s\n\n", $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');

        $message .= "View user profile: {$site_url}/wp-admin/user-edit.php?user_id={$user->ID}\n";
        $message .= "Security log: {$site_url}/wp-admin/options-general.php?helperbox&tab=log\n";

        if ('role_removed' === $action) {
            $message .= "\n⚠️ WARNING: Administrator privileges have been removed. Verify this was intentional.\n";
        }

        // Send email
        wp_mail(
            $super_admin->user_email,
            $subject,
            $message,
            [
                'Content-Type: text/plain; charset=UTF-8',
                'X-Priority: 1 (Highest)'
            ]
        );

        // Also send to site admin email as backup
        $admin_email = get_option('admin_email');
        if ($admin_email !== $super_admin->user_email) {
            wp_mail(
                $admin_email,
                $subject,
                $message,
                ['Content-Type: text/plain; charset=UTF-8']
            );
        }
    }
}
