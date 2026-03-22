<?php

/**
 * Moodle AJAX Endpoint Handler
 *
 * Handles AJAX requests for Moodle integration features including
 * user synchronization, course data retrieval, and SSO operations.
 *
 * @package Helperbox
 * @subpackage Moodle
 * @since 1.0.0
 * @copyright 2026 https://santoshmagar.com.np/
 * @author santoshmagar.com.np
 */

namespace Helperbox_Plugin\moodle;

use WP_Error;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// =============================================================================
// MOODLE AJAX ENDPOINT CLASS
// =============================================================================

if (!class_exists(__NAMESPACE__ . '\\Moodle_Endpoint_Ajax')) {
    /**
     * Moodle AJAX Endpoint Handler Class
     *
     * Singleton class that handles AJAX requests for Moodle integration:
     * - User synchronization endpoints
     * - Course data retrieval
     * - SSO session management
     * - Error handling and response formatting
     *
     * @since 1.0.0
     */
    class Moodle_Endpoint_Ajax {

        /**
         * Singleton instance
         *
         * @since 1.0.0
         * @var Moodle_Endpoint_Ajax
         */
        private static $instance = null;

        /**
         * AJAX action nonce key.
         *
         * @since 1.0.0
         * @var string
         */
        const NONCE_KEY = 'helperbox_moodle_ajax_nonce';

        /**
         * Get singleton instance
         *
         * Ensures only one instance of the class exists.
         *
         * @since 1.0.0
         * @return Moodle_Endpoint_Ajax The singleton instance
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
         * Registers AJAX action hooks for logged-in and non-logged-in users.
         *
         * @since 1.0.0
         */
        private function __construct() {
            $this->register_ajax_handlers();
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
         * Prevent unserializing of the instance
         *
         * @since 1.0.0
         * @return void
         */
        public function __wakeup() {
            throw new \Exception('Cannot unserialize singleton');
        }

        /**
         * Register AJAX action handlers
         *
         * Registers both privileged and public AJAX endpoints for Moodle operations.
         *
         * @since 1.0.0
         * @return void
         */
        private function register_ajax_handlers() {
            // Privileged AJAX handlers (require login)
            add_action('wp_ajax_helperbox_sync_moodle_user', [$this, 'ajax_sync_moodle_user']);
            add_action('wp_ajax_helperbox_get_moodle_courses', [$this, 'ajax_get_moodle_courses']);
            add_action('wp_ajax_helperbox_get_moodle_user_courses', [$this, 'ajax_get_user_courses']);

            // // Public AJAX handlers (no login required)
            add_action('wp_ajax_nopriv_helperbox_sync_moodle_user', [$this, 'ajax_sync_moodle_user']);
            add_action('wp_ajax_nopriv_helperbox_get_moodle_courses', [$this, 'ajax_get_moodle_courses']);
            add_action('wp_ajax_nopriv_helperbox_get_moodle_user_courses', [$this, 'ajax_get_user_courses']);
        }

        /**
         * Verify AJAX request nonce
         *
         * Validates the nonce for AJAX requests to prevent CSRF attacks.
         *
         * @since 1.0.0
         * @return bool True if nonce is valid, false otherwise
         */
        private function verify_nonce($nonce_key = self::NONCE_KEY, $nonce_action = self::NONCE_KEY) {
            $nonce = isset($_POST[$nonce_key]) ? sanitize_text_field(wp_unslash($_POST[$nonce_key])) : '';
            return wp_verify_nonce($nonce, $nonce_action);
        }

        /**
         * Send JSON response
         *
         * Formats and outputs a JSON response for AJAX requests.
         *
         * @since 1.0.0
         *
         * @param bool   $status  Response status
         * @param mixed  $data    Response data
         * @param string $message Response message
         * @param int    $code    HTTP status code
         * @return void
         */
        private function send_response($status, $data = null, $message = '', $code = 200) {
            wp_send_json(
                [
                    'success' => $status,
                    'data'    => $data,
                    'message' => $message,
                ],
                $code
            );
        }

        /**
         * AJAX handler: Sync user with Moodle
         *
         * Synchronizes WordPress user data with Moodle.
         * Requires administrator privileges.
         *
         * @since 1.0.0
         * @return void
         */
        public function ajax_sync_moodle_user() {
            // Check user capability
            if (!current_user_can('manage_options')) {
                $this->send_response(false, [], __('You do not have permission to perform this action.', 'helperbox'),);
            }

            // Get user ID
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            if (!$user_id) {
                $this->send_response(false, [], __('User ID is required.', 'helperbox'));
            }

            // Get user
            $wp_user = get_user_by('id', $user_id);
            if (!$wp_user) {
                $this->send_response(false, [], __('User not found.', 'helperbox'));
            }

            // Verify nonce
            if (!$this->verify_nonce('nonce', 'sync_moodle_user_' . $user_id)) {
                $this->send_response(false, [], __('Security check failed. Please refresh and try again.', 'helperbox'),);
            }

            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'));
            }

            // Trigger user sync
            $sync = Moodle_User_Sync::get_instance();
            $result = $sync->sync_user_to_moodle($wp_user);

            if ($result['status']) {
                $result['html'] = Moodle_User_Admin::get_instance()->render_moodle_user_column_content('', 'moodle_user_id', $user_id);
                $this->send_response(true, $result, __('User synchronized successfully.', 'helperbox'));
            } else {
                $this->send_response(false, [], $result['message'], $result, 500);
            }
        }

        /**
         * AJAX handler: Get courses from Moodle
         *
         * Retrieves course list from Moodle via WebService API.
         *
         * @since 1.0.0
         * @return void
         */
        public function ajax_get_moodle_courses() {
            // Verify nonce (optional for public endpoint)
            if (isset($_POST[self::NONCE_KEY]) && !$this->verify_nonce()) {
                $this->send_response(false, [], __('Security check failed. Please refresh and try again.', 'helperbox'), [], 403);
            }

            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'));
            }

            // Get category filter (optional)
            $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

            // Fetch courses from Moodle
            $params = [];
            if ($category_id > 0) {
                $params['categoryid'] = $category_id;
            }

            $response = $moodle->request_webservice('core_course_get_courses', $params, WP_REST_Server::READABLE);

            if (isset($response['exception'])) {
                $this->send_response(false, [], $response['message'] ?? __('Failed to fetch courses.', 'helperbox'), [], 500);
            }

            $this->send_response(true, $response, __('Courses retrieved successfully.', 'helperbox'));
        }

        /**
         * AJAX handler: Get user's enrolled courses
         *
         * Retrieves courses that a specific user is enrolled in from Moodle.
         *
         * @since 1.0.0
         * @return void
         */
        public function ajax_get_user_courses() {
            // Get user ID
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
            if (!$user_id) {
                $this->send_response(false, [], __('User ID is required.', 'helperbox'));
            }

            // Get user
            $wp_user = get_user_by('id', $user_id);
            if (!$wp_user) {
                $this->send_response(false, [], __('User not found.', 'helperbox'));
            }

            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'));
            }

            // Get Moodle user ID
            $moodle_user_id = $moodle->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                $this->send_response(false, [], __('Moodle user ID not found for this user.', 'helperbox'));
            }

            // Fetch user courses from Moodle
            $params = [
                'userid' => $moodle_user_id,
            ];

            $response = $moodle->request_webservice('core_enrol_get_users_courses', $params, WP_REST_Server::READABLE);

            if (isset($response['exception'])) {
                $this->send_response(false, [], $response['message'] ?? __('Failed to fetch user courses.', 'helperbox'), [], 500);
            }

            $this->send_response(true, $response, __('User courses retrieved successfully.', 'helperbox'));
        }
    }
}
