<?php

/**
 * Moodle Integration for WordPress
 *
 * Provides single sign-on functionality between WordPress and Moodle,
 * enabling seamless authentication and session management across both platforms.
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
// Moodle Integration CLASS
// =============================================================================

if (!class_exists(__NAMESPACE__ . '\\Moodle_Integration')) {
    /**
     * Moodle Integration Helper Class
     *
     * Singleton class that handles core SSO functionality:
     * - Encryption/decryption of SSO data
     * - Session synchronization with Moodle
     * - Building login/logout URLs
     * - User redirection to Moodle
     *
     * @since 1.0.0
     */
    class Moodle_Integration {

        /**
         * Current external system identifier.
         *
         * @since 1.0.0
         * @var string
         */
        const CURRENT_EXTERNAL_SYSTEM = 'wordpress';

        /**
         * Single instance of this class
         *
         * @since 1.0.0
         * @var Moodle_Integration
         */
        private static $instance = null;

        /**
         * Moodle Integration enabled status
         *
         * @since 1.0.0
         * @var bool
         */
        private $enabled = false;

        /**
         * Moodle Auto SSO enabled status
         *
         * @since 1.0.0
         * @var bool
         */
        private $auto_sso = false;

        /**
         * Moodle site URL
         *
         * @since 1.0.0
         * @var string
         */
        private $moodle_url = '';

        /**
         * Shared secret key for encryption
         *
         * @since 1.0.0
         * @var string
         */
        private $moodle_shared_secret = '';

        /**
         * wstoken
         *
         * @since 1.0.0
         * @var string
         */
        private $wstoken = '';

        /**
         * WebService response format.
         *
         * @since 1.0.0
         * @var string
         */
        private $wsrestformat = 'json';


        /**
         * API endpoint path.
         *
         * @var string
         */
        private $wsendpoint = '/webservice/rest/server.php';

        /**
         * Auth endpoint path.
         *
         * @var string
         */
        private $authendpoint = '/local/mchelpers/login/sso.php';

        /**
         * Check if Moodle integration is enabled
         *
         * @since 1.0.0
         * @return bool True if enabled, false otherwise
         */
        public function is_enabled() {
            return $this->enabled;
        }

        /**
         * Check if Auto SSO is enabled
         *
         * @since 1.0.0
         * @return bool True if enabled, false otherwise
         */
        public function is_auto_sso() {
            return $this->auto_sso;
        }

        /**
         * Get Moodle URL
         *
         * @since 1.0.0
         * @return string Moodle site URL (trimmed of trailing slash)
         */
        public function get_moodle_url() {
            return rtrim($this->moodle_url, '/');
        }

        /**
         * Get Moodle Webservice API URL
         *
         * @since 1.0.0
         * @return string Moodle site webservice URL
         */
        public function get_api_webservice_url($wsfunction, $param = []) {
            return add_query_arg(
                array_merge(
                    [
                        'moodlewsrestformat' => $this->wsrestformat,
                        'wstoken' => $this->wstoken,
                        'wsfunction' => $wsfunction,
                    ],
                    $param
                ),
                $this->get_moodle_url() . $this->wsendpoint
            );
        }

        /**
         * Get shared secret key
         *
         * @since 1.0.0
         * @return string Shared secret key
         */
        public function get_shared_secret() {
            return $this->moodle_shared_secret;
        }

        /**
         * Get API token
         *
         * @since 1.0.0
         * @return string API token
         */
        public function get_api_token() {
            return $this->wstoken;
        }

        /**
         * Set the API token.
         *
         * @param string $token Web service token.
         * @return void
         */
        public function set_api_token($wstoken) {
            $this->wstoken = $wstoken;
        }

        /**
         * Get singleton instance
         *
         * @since 1.0.0
         * @return Moodle_Integration Single instance of this class
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
         * Loads SSO configuration from WordPress options.
         *
         * @since 1.0.0
         */
        private function __construct() {
            if (function_exists('get_option')) {
                $this->enabled = (bool) get_option('enabled_moodle_integration', 0);
                $this->auto_sso = (bool) get_option('moodle_auto_sso', 0);
                $this->moodle_url = trim(get_option('moodle_url', ''));
                $this->moodle_shared_secret = get_option('moodle_shared_secret', '');
                $this->wstoken = get_option('moodle_api_token', '');
            }
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
         * Encrypt data using AES-128-CTR
         *
         * Encrypts data for secure transmission to Moodle using
         * AES-128-CTR encryption with SHA256 hashed key.
         *
         * @since 1.0.0
         *
         * @param array|string $data Data to encrypt (array or query string)
         * @param string       $key  Encryption key
         * @return string Base64-encoded encrypted data with IV
         */
        private function encrypt_data($data, $key) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }

            $enc_method = 'AES-128-CTR';
            $enc_key = hash('sha256', $key, true);
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($enc_method));
            $encrypted = openssl_encrypt($data, $enc_method, $enc_key, 0, $iv);
            return base64_encode($encrypted . '::' . bin2hex($iv));
        }

        /**
         * Get Moodle user ID for WordPress user
         *
         * Retrieves the Moodle user ID associated with a WordPress user.
         * Checks user meta data 'moodle_user_id'
         *
         * @since 1.0.0
         *
         * @param \WP_User|int $wp_user WordPress user object or user ID
         * @return int|false Moodle user ID if found, false otherwise
         */
        public function get_moodle_user_id($wp_user) {
            if (!$this->enabled) {
                return false;
            }

            // Normalize to user ID
            if (is_object($wp_user) && isset($wp_user->ID)) {
                $user_id = $wp_user->ID;
            } elseif (is_numeric($wp_user)) {
                $user_id = (int) $wp_user;
            } else {
                return false;
            }

            // Check user meta
            $moodle_user_id = get_user_meta($user_id, 'moodle_user_id', true);
            if ($moodle_user_id) {
                return intval($moodle_user_id);
            }

            return false;
        }

        /**
         * Update Moodle user ID for WordPress user.
         *
         * Stores the Moodle user ID in WordPress user meta for future reference.
         *
         * @since 1.0.0
         *
         * @param \WP_User|int $wp_user      WordPress user object or user ID
         * @param int          $moodle_user_id Moodle user ID to store
         * @return bool|int Meta ID if updated, false on failure
         */
        public function update_moodle_user_id($wp_user, int $moodle_user_id) {
            if (!$this->enabled || !$moodle_user_id) {
                return false;
            }

            // Normalize to user ID
            if (is_object($wp_user) && isset($wp_user->ID)) {
                $user_id = $wp_user->ID;
            } elseif (is_numeric($wp_user)) {
                $user_id = (int) $wp_user;
            } else {
                return false;
            }

            return update_user_meta($user_id, 'moodle_user_id', $moodle_user_id);
        }



        /**
         * Generate one-time verification hash
         *
         * Creates a secure hash for SSO verification containing:
         * - External user ID (WordPress)
         * - Moodle user ID
         * - Timestamp
         * - Random nonce
         *
         * @since 1.0.0
         *
         * @param int $wp_user_id      WordPress user ID
         * @param int $moodle_user_id  Moodle user ID
         * @return string SHA256 hash
         */
        private function generate_hash($wp_user_id, $moodle_user_id) {
            $data = [
                'external_system' => self::CURRENT_EXTERNAL_SYSTEM,
                'external_user_id' => $wp_user_id,
                'moodle_user_id' => $moodle_user_id,
                'timestamp' => time(),
                'nonce' => bin2hex(random_bytes(16))
            ];
            return hash('sha256', serialize($data) . $this->get_shared_secret());
        }

        /**
         * Prepare encrypted SSO data
         *
         * Prepares and encrypts user data for transmission to Moodle,
         * including user IDs, username, timestamp, and verification hash.
         * Also stores the hash in user meta for later verification.
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user        WordPress user object
         * @param int      $moodle_user_id Moodle user ID
         * @return string Encrypted SSO data
         */
        private function prepare_sso_data($wp_user, $moodle_user_id) {
            $hash = $this->generate_hash($wp_user->ID, $moodle_user_id);

            // Store hash in user meta for later use
            update_user_meta($wp_user->ID, '_moodle_sso_hash', $hash);
            update_user_meta($wp_user->ID, '_moodle_sso_hash_time', time());

            $data = http_build_query([
                'moodle_user_id' => $moodle_user_id,
                'external_user_id' => $wp_user->ID,
                'username' => $wp_user->user_login,
                'one_time_hash' => $hash,
                'timestamp' => time()
            ]);

            return $this->encrypt_data($data, $this->get_shared_secret());
        }

        /**
         * Send session data to Moodle
         *
         * Sends encrypted user session data to Moodle's SSO endpoint
         * for automatic authentication. Updates user meta with Moodle
         * user ID and last sync timestamp on success.
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user WordPress user object
         * @return array {
         *     @type bool   $status  Operation status
         *     @type string $message Result message
         * }
         */
        public function send_session_to_moodle($wp_user) {
            $result_data = [
                'status' => false,
                'message' => '',
            ];

            // Check if SSO is enabled
            if (!$this->enabled) {
                $result_data['message'] = 'Moodle SSO is disabled';
                return $result_data;
            }

            // Validate user object
            if (!($wp_user && $wp_user->ID)) {
                $result_data['message'] = 'Current user is not defined';
                return $result_data;
            }

            // Get Moodle user ID
            $moodle_user_id = $this->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                $result_data['message'] = 'Moodle SSO: Could not find Moodle user ID for user: ' . $wp_user->user_login;
                return $result_data;
            }

            // Prepare encrypted SSO data
            $encrypted_data = $this->prepare_sso_data($wp_user, $moodle_user_id);

            // Send to Moodle via wp_remote_post
            $response = wp_remote_post($this->get_moodle_url() . '/local/mchelpers/login/sso.php', [
                'method' => 'POST',
                'timeout' => 15,
                'body' => ['mch_data' => $encrypted_data],
                'sslverify' => false
            ]);

            if (is_wp_error($response)) {
                $result_data['message'] = 'Moodle SSO Error: ' . $response->get_error_message();
                return $result_data;
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);
            if (isset($result['status']) && $result['status']) {
                // Store Moodle user info for future reference
                update_user_meta($wp_user->ID, 'moodle_user_id', $moodle_user_id);
                update_user_meta($wp_user->ID, 'moodle_last_sync', time());

                $result_data['status'] = true;
                $result_data['message'] = 'Moodle SSO: Session sent to Moodle successfully.';
                return $result_data;
            } else {
                $result_data['message'] = 'Moodle SSO: Session storage failed - ' . ($result['error'] ?? 'Unknown error');
                return $result_data;
            }
        }

        /**
         * Build authentication URL for external system (login/logout)
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user       WordPress user object
         * @param string   $action        Action type: 'login' or 'logout'
         * @param string   $redirect_url  URL to redirect after action (optional)
         * @return array {
         *     @type bool   $status       Operation status
         *     @type string $url          Generated Moodle SSO URL (if successful)
         *     @type string $message      Error message (if failed)
         * }
         */
        public function build_auth_url($wp_user, $action, $redirect_url = '') {
            $build_data = [
                'status' => false,
                'url' => '',
                'message' => ''
            ];

            // Validate action
            if (!in_array($action, ['login', 'logout'])) {
                $build_data['message'] = 'Invalid action type. Use "login" or "logout".';
                return $build_data;
            }

            // Get Moodle user ID
            $moodle_user_id = $this->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                $build_data['message'] = 'Moodle SSO: Could not find Moodle user ID for user: ' . $wp_user->user_login;
                return $build_data;
            }

            // Handle hash
            $hash = get_user_meta($wp_user->ID, '_moodle_sso_hash', true);
            if ($action === 'login') {
                // Generate hash if not exists
                if (!$hash) {
                    $this->send_session_to_moodle($wp_user);
                    $hash = get_user_meta($wp_user->ID, '_moodle_sso_hash', true);
                }
            } else {
                // Logout requires existing hash
                if (!$hash) {
                    $build_data['message'] = 'Moodle SSO: Could not find SSO hash value created at login time.';
                    return $build_data;
                }
            }

            // Build parameters
            $params = [
                'userid' => $moodle_user_id,
                'action' => $action,
                'verify_code' => $hash,
            ];

            if (!empty($redirect_url)) {
                $params['redirect_to'] = $redirect_url;
            }

            // Build URL
            $build_data['status'] = true;
            $build_data['url'] = $this->get_moodle_url() . $this->authendpoint . '?' . http_build_query($params);

            return $build_data;
        }

        /**
         * Redirect user to Moodle for SSO login or logout.
         *
         * Redirects the WordPress user to Moodle's SSO endpoint for authentication
         * (login or logout). Uses stored hash for verification and cleans up
         * session data on logout.
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user     WordPress user object
         * @param string   $action      Action type: 'login' or 'logout'
         * @param string   $redirect_to URL to redirect after Moodle action (optional)
         * @return bool|void False if redirect fails, otherwise exits after redirect
         */
        public function redirect_to_moodle_sso($wp_user, $action, $redirect_to = '') {
            if (!$this->enabled) {
                return false;
            }

            $moodle_user_id = $this->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                return false;
            }

            // Get Moodle SSO URL.
            $result = $this->build_auth_url($wp_user, $action, $redirect_to);
            $url = $result['url'] ?? '';
            if (!$url) {
                return false;
            }

            // Clean up user meta if action is logout.
            if ($action === 'logout') {
                delete_user_meta($wp_user->ID, '_moodle_sso_hash');
                delete_user_meta($wp_user->ID, '_moodle_sso_hash_time');
            }

            // Redirect to Moodle for SSO.
            wp_redirect($url);
            exit;
        }

        /**
         * Request Moodle WebService API.
         *
         * Makes a request to Moodle's WebService API using the configured
         * endpoint and token. Supports both GET and POST methods.
         *
         * @since 1.0.0
         *
         * @param string $wsfunction WebService function name to call
         * @param array  $params     Parameters to pass to the WebService function
         * @param int    $method     HTTP method (WP_REST_Server::CREATABLE for POST, otherwise GET)
         * @return array {
         *     Response data or error information.
         *     @type bool   $status  Operation status
         *     @type mixed  $id      Response ID (if applicable)
         *     @type string $message Error message (if failed)
         *     @type mixed  $data    Response data on success
         * }
         */
        public function request_webservice($wsfunction, $params = [], $method = WP_REST_Server::CREATABLE) {
            $result_data = [
                'status' => false,
                'id' => 0,
                'message' => '',
            ];

            // Check Moodle API token.
            if (empty($this->wstoken)) {
                $result_data['message'] = __('Moodle API token is not configured.', 'helperbox');
                return $result_data;
            }

            // Check Moodle REST API format.
            if (empty($this->wsrestformat)) {
                $result_data['message'] = __('Moodle REST API format is not configured.', 'helperbox');
                return $result_data;
            }

            // Check Moodle REST endpoint.
            if (empty($this->wsendpoint)) {
                $result_data['message'] = __('Moodle REST endpoint is not configured.', 'helperbox');
                return $result_data;
            }

            // Make POST request.
            if ($method == WP_REST_Server::CREATABLE) {
                $response = wp_remote_post(
                    $this->get_api_webservice_url($wsfunction),
                    [
                        'method'      => 'POST',
                        'body'        => $params,
                        'timeout'     => 30,
                        'sslverify'   => false,
                        'blocking'    => true,
                        'redirection' => 5,
                    ]
                );
            } else {
                // Make GET request.
                $response = wp_remote_get(
                    $this->get_api_webservice_url($wsfunction, $params),
                    [
                        'timeout'   => 30,
                        'sslverify' => false,
                    ]
                );
            }

            // Check WP error.
            if (is_wp_error($response)) {
                $result_data['message'] = 'WP Error: ' . $response->get_error_message();
                return $result_data;
            }

            // Get response code and body.
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            // Check status code.
            if (200 !== $code) {
                $result_data['message'] = sprintf(
                    __('Moodle API returned status code %d.', 'helperbox'),
                    $code
                );
                return $result_data;
            }

            // Decode JSON response.
            $response_data = json_decode($body, true);

            // Check JSON decode error.
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result_data['message'] = __('Invalid JSON response from Moodle API.', 'helperbox');
                return $result_data;
            }

            // Check for Moodle exception in response.
            if (isset($response_data['exception'])) {
                $result_data['message'] = __('Moodle API exception: ' . ($response_data['message'] ?? 'unknown'), 'helperbox');
                return $result_data;
            }

            return $response_data;
        }
    }
}
