<?php

/**
 * Moodle User Sync
 *
 * Handles user synchronization operations between WordPress and Moodle:
 * - User lifecycle hooks (deletion, removal)
 * - Sync orchestration and validation
 * - Bulk sync operations
 * - Sync status tracking
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
// MOODLE USER SYNC CLASS
// =============================================================================

if (!class_exists(__NAMESPACE__ . '\\Moodle_User_Sync')) {
    /**
     * Moodle User Sync Class
     *
     * Handles synchronization lifecycle and orchestration.
     *
     * @since 1.0.0
     */
    class Moodle_User_Sync {

        /**
         * Singleton instance
         *
         * @since 1.0.0
         * @var Moodle_User_Sync
         */
        private static $instance = null;

        /**
         * Get singleton instance
         *
         * Ensures only one instance of the class exists.
         *
         * @since 1.0.0
         * @return Moodle_User_Sync The singleton instance
         */
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private constructor
         *
         * Prevents direct instantiation. Use get_instance() instead.
         *
         * @since 1.0.0
         */
        private function __construct() {
            $this->register_hooks();
        }

        /**
         * Prevent cloning of the instance
         *
         * @since 1.0.0
         * @return void
         */
        private function __clone() {
        }

        /**
         * Prevent unserialization of the instance
         *
         * @since 1.0.0
         * @return void
         */
        public function __wakeup() {
            throw new \Exception('Cannot unserialize singleton');
        }

        /**
         * Register WordPress hooks
         *
         * @since 1.0.0
         * @return void
         */
        private function register_hooks() {
            // Handle user deletion
            add_action('delete_user', [$this, 'handle_user_deletion'], 10, 1);

            // Handle user suspension when WordPress user is removed from site
            add_action('remove_user_from_blog', [$this, 'handle_user_removal'], 10, 2);
        }

        /**
         * Handle WordPress user deletion
         *
         * Deletes the corresponding Moodle user when a WordPress user is deleted.
         *
         * @since 1.0.0
         *
         * @param int $user_id WordPress user ID
         * @return void
         */
        public function handle_user_deletion($user_id) {
            $user = get_userdata($user_id);
        }

        /**
         * Handle user removal from blog
         *
         * Suspends the Moodle user when removed from WordPress site.
         *
         * @since 1.0.0
         *
         * @param int $user_id WordPress user ID
         * @param int $blog_id Blog ID (not used in single site)
         * @return void
         */
        public function handle_user_removal($user_id, $blog_id = 0) {
            $user = get_userdata($user_id);
        }

        /**
         * Sync WordPress user to Moodle
         *
         * Creates or updates a Moodle user account based on WordPress user data.
         * that adds additional validation and logging.
         *
         * @since 1.0.0
         *
         * @param int|\WP_User $user WordPress user ID or user object
         * @return array{
         *     @type bool   $status Sync status
         *     @type string $message Response message
         *     @type int    $moodle_user_id Moodle user ID if successful
         * }
         */
        public function sync_user_to_moodle($user) {
            // Validate user
            if (!$this->validate_user_for_sync($user)) {
                return [
                    'status' => false,
                    'message' => __('Invalid user for synchronization.', 'helperbox'),
                ];
            }

            // Normalize to user object
            if (is_numeric($user)) {
                $user = get_userdata($user);
            }
            $moodle_integrate = Moodle_Integration::get_instance();
            // Check if Moodle integration is enabled
            if (!$moodle_integrate->is_enabled()) {
                return [
                    'status' => false,
                    'message' => __('Moodle integration is not enabled.', 'helperbox'),
                ];
            }
            // 
            $param = [
                'users' => [
                    'username' => $user->user_login,
                    'firstname' => get_user_meta($user->ID, 'first_name', true) ?: $user->display_name,
                    'lastname' => get_user_meta($user->ID, 'last_name', true) ?: 'N/A',
                    'email' => $user->user_email,
                ]
            ];

            $wsfunction = 'local_mchelpers_user_create';
            $moodle_user_id = $moodle_integrate->get_moodle_user_id($user);
            if ($moodle_user_id) {
                $wsfunction = 'local_mchelpers_user_update';
                $param['users']['id'] = $moodle_user_id;
            } else {
                $param['users']['password'] = $user->user_pass;
            }

            $response = $moodle_integrate->request_webservice($wsfunction, $param);
            if ($response['status']) {
                $moodle_user_id = $response['id'] ?? 0;
                $moodle_integrate->update_moodle_user_id($user, $moodle_user_id);
            }
            return $response;
        }

        /**
         * Bulk sync multiple users
         *
         * @since 1.0.0
         *
         * @param array $user_ids Array of WordPress user IDs
         * @return array{
         *     @type int $success Number of successfully synced users
         *     @type int $failed Number of failed syncs
         *     @type array $results Detailed results per user
         * }
         */
        public function bulk_sync_users($user_ids) {
            $results = [
                'success' => 0,
                'failed' => 0,
                'results' => [],
            ];

            foreach ($user_ids as $user_id) {
                $result = $this->sync_user_to_moodle($user_id);
                $results['results'][$user_id] = $result;

                if ($result['status']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            }

            return $results;
        }

        /**
         * Validate user for sync
         *
         * @since 1.0.0
         *
         * @param int|\WP_User $user WordPress user ID or user object
         * @return bool True if valid, false otherwise
         */
        private function validate_user_for_sync($user) {
            if (is_numeric($user)) {
                $user = get_userdata($user);
            }

            if (!$user || is_wp_error($user)) {
                return false;
            }

            if (!is_a($user, 'WP_User')) {
                return false;
            }

            // Check if user has valid email
            if (empty($user->user_email)) {
                return false;
            }

            return true;
        }


        /**
         * Get sync statistics
         *
         * @since 1.0.0
         *
         * @return array{
         *     @type int $total_synced Total synced users
         *     @type int $total_pending Total pending users
         *     @type int $total_suspended Total suspended users
         * }
         */
        public function get_sync_statistics() {
            global $wpdb;

            // Count synced users
            $total_synced = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'moodle_user_id' AND meta_value != ''"
            );

            // Count suspended users
            $total_suspended = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'moodle_suspended' AND meta_value != '' AND meta_value != '0'"
            );

            // Get total users
            $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
            $total_pending = $total_users - $total_synced;

            return [
                'total_synced' => (int) $total_synced,
                'total_pending' => (int) $total_pending,
                'total_suspended' => (int) $total_suspended,
            ];
        }
    }
}
