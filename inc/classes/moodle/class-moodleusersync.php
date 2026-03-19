<?php

/**
 * User Synchronization with Moodle
 *
 * Handles synchronization of WordPress users with Moodle, including:
 * - Creating/updating Moodle user accounts
 * - Syncing user profile fields
 * - Managing user enrollment in courses
 * - Tracking sync status and history
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
// USER SYNC CLASS
// =============================================================================

if (!class_exists('MoodleUserSync')) {
    /**
     * User Synchronization Class
     *
     * Handles bidirectional synchronization between WordPress and Moodle users.
     *
     * @since 1.0.0
     */
    class MoodleUserSync {

        /**
         * Singleton instance
         *
         * @since 1.0.0
         * @var MoodleUserSync
         */
        private static $instance = null;

        /**
         * Moodle SSO helper instance
         *
         * @since 1.0.0
         * @var MoodleSSO
         */
        private $sso_helper;

        /**
         * Get singleton instance
         *
         * Ensures only one instance of the class exists.
         *
         * @since 1.0.0
         * @return MoodleUserSync The singleton instance
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
            $this->sso_helper = MoodleSSO::get_instance();
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
         * Sync WordPress user to Moodle
         *
         * Creates or updates a Moodle user account based on WordPress user data.
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
            if (is_numeric($user)) {
                $user = get_userdata($user);
            }

            if (!$user || is_wp_error($user)) {
                return [
                    'status' => false,
                    'message' => __('Invalid user.', 'helperbox'),
                ];
            }

            if (!$this->sso_helper->is_enabled()) {
                return [
                    'status' => false,
                    'message' => __('Moodle SSO is not enabled.', 'helperbox'),
                ];
            }

            // Check if user already has a Moodle ID
            $moodle_user_id = get_user_meta($user->ID, 'moodle_user_id', true);

            if ($moodle_user_id) {
                return $this->update_moodle_user($user, $moodle_user_id);
            } else {
                return $this->create_moodle_user($user);
            }
        }

        /**
         * Create a new Moodle user
         *
         * @since 1.0.0
         *
         * @param \WP_User $user WordPress user object
         * @return array{
         *     @type bool   $status Creation status
         *     @type string $message Response message
         *     @type int    $moodle_user_id Moodle user ID if successful
         * }
         */
        private function create_moodle_user($user) {
            // Build user data for Moodle
            $user_data = [
                'username' => $user->user_login,
                'password' => wp_generate_password(32),
                'firstname' => get_user_meta($user->ID, 'first_name', true) ?: $user->display_name,
                'lastname' => get_user_meta($user->ID, 'last_name', true) ?: '',
                'email' => $user->user_email,
                'auth' => 'manual',
                'suspended' => 0,
            ];
            // Send to Moodle via web service or API
            $result = $this->send_user_to_moodle($user_data);

            if ($result['status']) {
                // Store Moodle user ID
                update_user_meta($user->ID, 'moodle_user_id', $result['moodle_user_id']);
                update_user_meta($user->ID, 'moodle_last_sync', time());

                return [
                    'status' => true,
                    'message' => __('User successfully created in Moodle.', 'helperbox'),
                    'moodle_user_id' => $result['moodle_user_id'],
                ];
            }

            return $result;
        }

        /**
         * Update existing Moodle user
         *
         * @since 1.0.0
         *
         * @param \WP_User $user WordPress user object
         * @param int      $moodle_user_id Existing Moodle user ID
         * @return array{
         *     @type bool   $status Update status
         *     @type string $message Response message
         * }
         */
        private function update_moodle_user($user, $moodle_user_id) {
            // Build updated user data
            $user_data = [
                'id' => $moodle_user_id,
                'username' => $user->user_login,
                'firstname' => get_user_meta($user->ID, 'first_name', true) ?: $user->display_name,
                'lastname' => get_user_meta($user->ID, 'last_name', true) ?: '',
                'email' => $user->user_email,
            ];

            // Send update to Moodle
            $result = $this->update_user_in_moodle($user_data);

            if ($result['status']) {
                update_user_meta($user->ID, 'moodle_last_sync', time());

                return [
                    'status' => true,
                    'message' => __('User successfully updated in Moodle.', 'helperbox'),
                ];
            }

            return $result;
        }

        /**
         * Send user data to Moodle via web service
         *
         * @since 1.0.0
         *
         * @param array $user_data User data to send
         * @return array{
         *     @type bool   $status Creation status
         *     @type string $message Response message
         *     @type int    $moodle_user_id Moodle user ID if successful
         * }
         */
        private function send_user_to_moodle($user_data) {
            // TODO: Implement Moodle web service API call
            // This would typically use Moodle's core_user_create_users function
            return [
                'status' => false,
                'message' => __('Moodle user creation not yet implemented.', 'helperbox'),
            ];
        }

        /**
         * Update user data in Moodle via web service
         *
         * @since 1.0.0
         *
         * @param array $user_data Updated user data
         * @return array{
         *     @type bool   $status Update status
         *     @type string $message Response message
         * }
         */
        private function update_user_in_moodle($user_data) {
            // TODO: Implement Moodle web service API call
            // This would typically use Moodle's core_user_update_users function
            return [
                'status' => false,
                'message' => __('Moodle user update not yet implemented.', 'helperbox'),
            ];
        }

        /**
         * Enroll user in Moodle course
         *
         * @since 1.0.0
         *
         * @param int $user_id WordPress user ID
         * @param int $course_id Moodle course ID
         * @param string $role Role to enroll as (default: student)
         * @return array{
         *     @type bool   $status Enrollment status
         *     @type string $message Response message
         * }
         */
        public function enroll_user_in_course($user_id, $course_id, $role = 'student') {
            $moodle_user_id = get_user_meta($user_id, 'moodle_user_id', true);

            if (!$moodle_user_id) {
                return [
                    'status' => false,
                    'message' => __('User is not synced with Moodle.', 'helperbox'),
                ];
            }

            // TODO: Implement Moodle enrollment API call
            return [
                'status' => false,
                'message' => __('Course enrollment not yet implemented.', 'helperbox'),
            ];
        }

        /**
         * Get user sync status
         *
         * @since 1.0.0
         *
         * @param int $user_id WordPress user ID
         * @return array{
         *     @type bool  $is_synced Whether user is synced
         *     @type int   $moodle_user_id Moodle user ID if synced
         *     @type int   $last_sync Timestamp of last sync
         *     @type array $meta Additional sync metadata
         * }
         */
        public function get_sync_status($user_id) {
            $moodle_user_id = get_user_meta($user_id, 'moodle_user_id', true);
            $last_sync = get_user_meta($user_id, 'moodle_last_sync', true);

            return [
                'is_synced' => !empty($moodle_user_id),
                'moodle_user_id' => $moodle_user_id ?: null,
                'last_sync' => $last_sync ?: null,
                'meta' => [],
            ];
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
    }
}
