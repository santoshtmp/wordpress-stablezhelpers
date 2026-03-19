<?php

/**
 * Moodle SSO for WordPress
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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// =============================================================================
// Moodle SSO CLASS
// =============================================================================

if (!class_exists('MoodleSSO')) {
    /**
     * Moodle SSO Helper Class
     *
     * Singleton class that handles core SSO functionality:
     * - Encryption/decryption of SSO data
     * - Session synchronization with Moodle
     * - Building login/logout URLs
     * - User redirection to Moodle
     *
     * @since 1.0.0
     */
    class MoodleSSO {

        /**
         * Settings option group name.
         *
         * @since 1.0.0
         * @var string
         */
        const MOODLESSO_SETTINGS_OPTION_GROUP = 'helperbox_moodlesso_settings_group';

        /**
         * Single instance of this class
         *
         * @since 1.0.0
         * @var MoodleSSO
         */
        private static $instance = null;

        /**
         * SSO enabled status
         *
         * @since 1.0.0
         * @var bool
         */
        private $enabled = false;

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
        private $shared_secret = '';

        /**
         * Check if SSO is enabled
         *
         * @since 1.0.0
         * @return bool True if SSO is enabled, false otherwise
         */
        public function is_enabled() {
            return $this->enabled;
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
         * Get shared secret key
         *
         * @since 1.0.0
         * @return string Shared secret key
         */
        public function get_shared_secret() {
            return $this->shared_secret;
        }

        /**
         * Get singleton instance
         *
         * @since 1.0.0
         * @return MoodleSSO Single instance of this class
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
                $this->enabled = (bool) get_option('moodle_sso_enabled', 0);
                $this->moodle_url = trim(get_option('moodle_url', ''));
                $this->shared_secret = get_option('moodle_shared_secret', '');
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
         * Get Moodle user ID by WordPress user email
         *
         * Retrieves the Moodle user ID associated with a WordPress user.
         * Checks user meta for previously mapped user IDs.
         *
         * Note: You can customize this method to match your user mapping logic
         * (e.g., match by email, username, or custom field).
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user WordPress user object
         * @return int|false Moodle user ID if found, false otherwise
         */
        public function get_moodle_user_id($wp_user) {
            if (!$this->enabled) {
                return false;
            }

            // Option 1: Check user meta (if previously mapped)
            $moodle_user_id = get_user_meta($wp_user->ID, 'moodle_user_id', true);
            if ($moodle_user_id) {
                return intval($moodle_user_id);
            }

            return false;
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
         * Build login URL for external system
         *
         * Constructs the Moodle SSO login URL with verification parameters.
         * If no hash exists, triggers session creation to generate one.
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user       WordPress user object
         * @param string   $redirect_url  URL to redirect after Moodle login (optional)
         * @return array {
         *     @type bool   $status            Operation status
         *     @type string $moodle_login_url  Moodle SSO login URL (if successful)
         *     @type string $message           Error message (if failed)
         * }
         */
        public function build_login_url($wp_user, $redirect_url = '') {
            $build_data =  [
                'status' => false,
                'moodle_login_url' => '',
                'message' => ''
            ];

            // Get Moodle user ID
            $moodle_user_id = $this->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                $build_data['message'] = 'Moodle SSO: Could not find Moodle user ID for user: ' . $wp_user->user_login;
                return $build_data;
            }

            // Get or generate SSO hash
            $hash = get_user_meta($wp_user->ID, '_moodle_sso_hash', true);
            if (!$hash) {
                // Generate new hash
                $this->send_session_to_moodle($wp_user);
                $hash = get_user_meta($wp_user->ID, '_moodle_sso_hash', true);
            }

            // Build URL parameters
            $params = [
                'login_id' => $moodle_user_id,
                'verify_code' => $hash,
            ];
            if (!empty($redirect_url)) {
                $params['redirect_to'] = $redirect_url;
            }

            // Build final Moodle SSO login URL
            $build_data['status'] = true;
            $build_data['moodle_login_url'] = $this->get_moodle_url() . '/local/mchelpers/login/sso.php?' . http_build_query($params);
            return $build_data;
        }

        /**
         * Build logout URL for external system
         *
         * Constructs the Moodle SSO logout URL with verification parameters.
         * Requires an existing hash from login time for verification.
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user       WordPress user object
         * @param string   $redirect_url  URL to redirect after Moodle logout (optional)
         * @return array {
         *     @type bool   $status             Operation status
         *     @type string $moodle_logout_url  Moodle SSO logout URL (if successful)
         *     @type string $message            Error message (if failed)
         * }
         */
        public function build_logout_url($wp_user, $redirect_url = '') {
            $build_data =  [
                'status' => false,
                'moodle_logout_url' => '',
                'message' => ''
            ];

            // Get Moodle user ID
            $moodle_user_id = $this->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                $build_data['message'] = 'Moodle SSO: Could not find Moodle user ID for user: ' . $wp_user->user_login;
                return $build_data;
            }

            // Get SSO hash from login time
            $hash = get_user_meta($wp_user->ID, '_moodle_sso_hash', true);
            if (!$hash) {
                $build_data['message'] = 'Moodle SSO: Could not find SSO hash value created at login time.';
                return $build_data;
            }

            // Build URL parameters
            $params = [
                'logout_id' => $moodle_user_id,
                'verify_code' => $hash,
            ];
            if (!empty($redirect_url)) {
                $params['redirect_to'] = $redirect_url;
            }

            // Build final Moodle SSO logout URL
            $build_data['status'] = true;
            $build_data['moodle_logout_url'] = $this->get_moodle_url() . '/local/mchelpers/login/sso.php?' . http_build_query($params);
            return $build_data;
        }

        /**
         * Redirect user to Moodle for SSO login
         *
         * Redirects the WordPress user to Moodle's SSO login endpoint
         * after successful WordPress authentication. Uses stored hash
         * for verification.
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user         WordPress user object
         * @param string   $redirect_back_url URL to redirect after Moodle login (optional)
         * @return bool|void False if redirect fails, otherwise exits after redirect
         */
        public function redirect_to_sso_login($wp_user, $redirect_back_url = '') {
            if (!$this->enabled) {
                return false;
            }

            $moodle_user_id = $this->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                return false;
            }

            $moodle_sso_hash_time = get_user_meta($wp_user->ID, '_moodle_sso_hash_time', true);
            if (!$moodle_sso_hash_time) {
                return false;
            }

            // Get Moodle SSO login URL
            $moodle_login_url = $this->build_login_url($wp_user, $redirect_back_url);
            $login_url = $moodle_login_url['moodle_login_url'] ?? '';

            if (!$login_url) {
                return false;
            }

            // Redirect to Moodle for SSO
            wp_redirect($login_url);
            exit;
        }


        /**
         * Logout from Moodle
         *
         * Redirects the WordPress user to Moodle's SSO logout endpoint
         * to clear Moodle session. Cleans up stored hash data after logout.
         *
         * @since 1.0.0
         *
         * @param \WP_User $wp_user         WordPress user object
         * @param string   $redirect_back_url URL to redirect after Moodle logout (optional)
         * @return bool|void False if redirect fails, otherwise exits after redirect
         */
        public function redirect_to_sso_logout($wp_user, $redirect_back_url = '') {
            if (!$this->enabled) {
                return false;
            }

            $moodle_user_id = $this->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                return false;
            }

            $moodle_sso_hash_time = get_user_meta($wp_user->ID, '_moodle_sso_hash_time', true);
            if (!$moodle_sso_hash_time) {
                return false;
            }

            // Get Moodle SSO logout URL
            $moodle_logout_url = $this->build_logout_url($wp_user, $redirect_back_url);
            $logout_url = $moodle_logout_url['moodle_logout_url'] ?? '';
            if (!$logout_url) {
                return false;
            }

            // Clean up user meta
            delete_user_meta($wp_user->ID, '_moodle_sso_hash');
            delete_user_meta($wp_user->ID, '_moodle_sso_hash_time');

            // Redirect to Moodle for SSO logout
            wp_redirect($logout_url);
            exit;
        }

        /**
         * Register Moodle SSO settings options and fields.
         *
         * Registers WordPress settings for Moodle SSO integration:
         * - moodle_sso_enabled: Enable/disable SSO functionality
         * - moodle_auto_sso: Enable automatic SSO on WordPress login
         * - moodle_url: Moodle site URL
         * - moodle_shared_secret: Shared secret key for encryption
         *
         * @since 1.0.0
         * @return void
         *
         * @see register_setting()
         */
        public function register_setting_options_fields() {

            // Register SSO enabled setting.
            register_setting(
                self::MOODLESSO_SETTINGS_OPTION_GROUP,
                'moodle_sso_enabled',
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
        }

        /**
         * Render Moodle SSO settings form fields.
         *
         * Outputs HTML form for configuring Moodle SSO integration including:
         * - SSO enable/disable toggle
         * - Auto SSO option
         * - Moodle site URL
         * - Shared secret key
         * - Usage instructions and shortcode examples
         *
         * @since 1.0.0
         * @return void
         *
         * @see settings_fields()
         */
        public function render_settings_fields() {
            settings_fields(self::MOODLESSO_SETTINGS_OPTION_GROUP); ?>
            <div class="wrap">
                <h3>Moodle SSO Integration</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Moodle SSO</th>
                        <td>
                            <input type="checkbox" name="moodle_sso_enabled" value="1" <?php checked(get_option('moodle_sso_enabled'), 1); ?> />
                            <p class="description">Enable Moodle SSO.</p>
                        </td>
                    </tr>
                    <?php if ($this->is_enabled()) : ?>
                        <tr class="moodle-sso-field">
                            <th scope="row">Auto SSO</th>
                            <td>
                                <input type="checkbox" name="moodle_auto_sso" value="1" <?php checked(get_option('moodle_auto_sso'), 1); ?> />
                                <p class="description">Automatically redirect users to Moodle SSO on WordPress login/logout.</p>
                            </td>
                        </tr>
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
                                    <li><code>[moodle_link redirect_to="/my"]View Dashboard[/moodle_link]</code> - Link to specific moodle url</li>
                                    <li><code>[moodle_status]</code> - Show Moodle connection status</li>
                                </ul>
                            </td>
                        </tr>
                        <tr class="moodle-sso-description-info">
                            <th>Testing</th>
                            <td>
                                <?php MoodleSSOHandler::render_shortcodes_sso_example(); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
                <script>
                    jQuery(document).ready(function($) {
                        $('input[name="moodle_sso_enabled"]').on('change', function() {
                            var isChecked = $(this).is(':checked');
                            $('.moodle-sso-field').toggle(isChecked);
                            $('.moodle-sso-description-info').toggle(isChecked);
                            $('.moodle-sso-field input.required-field').each(function() {
                                this.required = isChecked;
                            });
                        });
                    });
                </script>

            </div>
<?php
        }
    }
}
