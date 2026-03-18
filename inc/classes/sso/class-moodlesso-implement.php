<?php
/**
 * Moodle SSO Integration for WordPress
 *
 * Provides single sign-on functionality between WordPress and Moodle,
 * enabling seamless authentication and session management across both platforms.
 *
 * @package Helperbox
 * @subpackage SSO
 * @since 1.0.0
 * @copyright 2026 https://santoshmagar.com.np/
 * @author santoshmagar.com.np
 */

namespace Helperbox_Plugin\sso;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// =============================================================================
// MOODLE SSO IMPLEMENTATION CLASS
// =============================================================================

if (!class_exists('MoodleSSO_Implement')) {
    /**
     * Moodle SSO Implementation Class
     *
     * Manages the integration between WordPress and Moodle SSO, including:
     * - Login/logout hook handlers
     * - Shortcode registration and rendering
     * - Admin interface for configuration
     * - Session synchronization with Moodle
     *
     * @since 1.0.0
     */
    class MoodleSSO_Implement {

        /**
         * Moodle SSO helper instance
         *
         * @since 1.0.0
         * @var MoodleSSO
         */
        private $sso_helper;

        /**
         * Constructor
         *
         * Initializes the Moodle SSO helper and registers hooks if SSO is enabled.
         *
         * @since 1.0.0
         */
        public function __construct() {
            $this->sso_helper = MoodleSSO::get_instance();
            if ($this->sso_helper->is_enabled()) {
                $this->init_hooks();
            }
        }

        /**
         * Initialize WordPress hooks
         *
         * Registers all necessary action and filter hooks for SSO functionality:
         * - Login/logout handlers
         * - Shortcodes ([moodle_link], [moodle_status])
         * - Admin menu and settings
         * - Footer output
         *
         * @since 1.0.0
         */
        private function init_hooks() {
            // Login/Logout hooks
            add_action('wp_login', [$this, 'handle_wp_login'], 10, 2);
            add_action('init', [$this, 'handle_redirect_to_sso_login'], 100);
            add_filter('logout_url', [$this, 'modify_logout_url'], 10, 2);
            add_action('wp_logout', [$this, 'handle_redirect_to_sso_logout']);

            // Shortcodes
            add_shortcode('moodle_link', [$this, 'moodle_link_shortcode']);
            add_shortcode('moodle_status', [$this, 'moodle_status_shortcode']);

            // Footer output
            add_action('wp_footer', [$this, 'render_shortcodes_sso_status']);

            // Admin menu (if admin)
            if (is_admin()) {
                add_action('admin_menu', [$this, 'register_admin_menu']);
                add_action('admin_init', [$this, 'register_settings']);
            }
        }

        // =============================================================================
        // HOOK HANDLERS
        // =============================================================================

        /**
         * Handle WordPress login - Auto-login to Moodle
         *
         * Sends user session data to Moodle upon successful WordPress login,
         * enabling automatic Moodle authentication.
         *
         * @since 1.0.0
         *
         * @param string   $user_login Username
         * @param \WP_User $user       User object
         * @return void
         */
        public function handle_wp_login($user_login, $user) {
            if (!$this->sso_helper->is_enabled()) {
                return;
            }

            $result = $this->sso_helper->send_session_to_moodle($user);

            if (isset($result['status']) && $result['status']) {
                // Store flag to prevent redirect loop
                set_transient('moodle_sso_redirect_' . $user->ID, true, 60);
            }
        }

        /**
         * Handle SSO redirect after login
         *
         * Redirects user to Moodle SSO login page after WordPress login
         * if the SSO redirect flag exists.
         *
         * @since 1.0.0
         * @return void
         */
        public function handle_redirect_to_sso_login() {
            if (!is_user_logged_in()) {
                return;
            }

            $user = wp_get_current_user();
            $transient_key = 'moodle_sso_redirect_' . $user->ID;

            if (get_transient($transient_key)) {
                delete_transient($transient_key);
                $current_url = home_url(add_query_arg(null, null));
                $this->sso_helper->redirect_to_sso_login($user, $current_url);
            }
        }

        /**
         * Modify logout URL to include Moodle SSO parameters
         *
         * Appends Moodle SSO logout parameters to the WordPress logout URL
         * to enable synchronized logout from both systems.
         *
         * @since 1.0.0
         *
         * @param string $logout_url Logout URL
         * @param string $redirect   Redirect URL after logout
         * @return string Modified logout URL
         */
        public function modify_logout_url($logout_url, $redirect) {
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $logout_url = add_query_arg('moodle_sso_logout', '1', $logout_url);
                $logout_url = add_query_arg('uid', $user->ID, $logout_url);
            }
            return $logout_url;
        }

        /**
         * Handle WordPress logout - Logout from Moodle
         *
         * Processes Moodle logout request when user logs out from WordPress.
         * Redirects user to Moodle logout endpoint for session cleanup.
         *
         * @since 1.0.0
         * @return void
         */
        public function handle_redirect_to_sso_logout() {
            $redirect_url = wp_login_url() . '?loggedout=true';

            if (isset($_REQUEST['redirect_to'])) {
                $redirect_url = esc_url_raw($_REQUEST['redirect_to']);
            }

            // Check for Moodle SSO logout request
            $moodle_sso_logout = isset($_GET['moodle_sso_logout']) ? intval($_GET['moodle_sso_logout']) : 0;
            $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
            if ($moodle_sso_logout === 1 && $uid) {
                $user = get_userdata($uid);
                if ($user) {
                    $this->sso_helper->redirect_to_sso_logout($user, $redirect_url);
                }
            }
        }

        // =============================================================================
        // SHORTCODES
        // =============================================================================

        /**
         * Shortcode: [moodle_link] - Link to Moodle with auto-login
         *
         * Generates a Moodle link with automatic SSO authentication for logged-in users.
         * If SSO is disabled or user is not logged in, displays a standard Moodle login link.
         *
         * @since 1.0.0
         *
         * @param array  $atts    Shortcode attributes {
         *     @type string $redirect_to URL to redirect after Moodle login. Default empty.
         *     @type string $class       CSS class for the link. Default 'moodle-sso-link'.
         *     @type string $target      Link target attribute. Default '_blank'.
         * }
         * @param string $content Shortcode content (link text)
         * @return string HTML anchor link
         */
        public function moodle_link_shortcode($atts, $content = '') {
            $atts = shortcode_atts([
                'redirect_to' => '',
                'class' => 'moodle-sso-link',
                'target' => '_blank'
            ], $atts);

            if (!is_user_logged_in()) {
                return '';
            }

            if (!$this->sso_helper->is_enabled() || !is_user_logged_in()) {
                return '<a href="' . esc_url($this->sso_helper->get_moodle_url() . '/login') . '" class="' . esc_attr($atts['class']) . '">' .
                    esc_html($content ?: 'Login to Moodle') . '</a>';
            }

            $user = wp_get_current_user();

            // Build Moodle URL
            if ($atts['redirect_to']) {
                $redirect_url = esc_url($atts['redirect_to']);
            } else {
                $redirect_url = $this->sso_helper->get_moodle_url() . '/my';
            }

            // Get SSO login URL
            $sso_url = $this->sso_helper->build_login_url($user, $redirect_url)['moodle_login_url'] ?? '';

            return '<a href="' . esc_url($sso_url) . '" class="' . esc_attr($atts['class']) . '" target="' . esc_attr($atts['target']) . '">' .
                esc_html($content ?: 'Go to Moodle') . '</a>';
        }

        /**
         * Shortcode: [moodle_status] - Show Moodle connection status
         *
         * Displays the user's Moodle SSO connection status, including:
         * - Moodle user ID
         * - Last synchronization timestamp
         *
         * @since 1.0.0
         *
         * @param array $atts Shortcode attributes (currently unused)
         * @return string Status HTML with styled div
         */
        public function moodle_status_shortcode($atts) {
            if (!is_user_logged_in()) {
                return '<p>Moodle SSO: Not logged in</p>';
            }

            $user = wp_get_current_user();
            $moodle_user_id = get_user_meta($user->ID, 'moodle_user_id', true);
            $last_sync = get_user_meta($user->ID, 'moodle_last_sync', true);

            if ($moodle_user_id) {
                $status = '<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0;">';
                $status .= '<strong>✓ Moodle Connected</strong><br>';
                $status .= 'Moodle User ID: ' . intval($moodle_user_id) . '<br>';
                if ($last_sync) {
                    $status .= 'Last Sync: ' . date('Y-m-d H:i:s', $last_sync);
                }
                $status .= '</div>';
                return $status;
            } else {
                return '<div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 4px; margin: 10px 0;">
                        <strong>⚠ Moodle Not Connected</strong><br>
                        User has not been synced with Moodle yet.
                        </div>';
            }
        }

        // =============================================================================
        // ADMIN INTERFACE
        // =============================================================================

        /**
         * Register admin menu
         *
         * Adds the Moodle SSO settings page to the WordPress admin menu
         * under Settings > Moodle SSO.
         *
         * @since 1.0.0
         * @return void
         */
        public function register_admin_menu() {
            add_options_page(
                'Moodle SSO',
                'Moodle SSO',
                'manage_options',
                'moodle-sso',
                [$this, 'render_admin_page']
            );
        }

        /**
         * Register settings
         *
         * Registers WordPress options for Moodle SSO configuration:
         * - moodle_sso_enabled: Enable/disable SSO functionality
         * - moodle_url: Moodle site URL
         * - moodle_shared_secret: Shared secret key for encryption
         *
         * @since 1.0.0
         * @return void
         */
        public function register_settings() {
            register_setting('moodle_sso_group', 'moodle_sso_enabled');
            register_setting('moodle_sso_group', 'moodle_url');
            register_setting('moodle_sso_group', 'moodle_shared_secret');
        }

        /**
         * Render admin page
         *
         * Outputs the Moodle SSO settings page with:
         * - Configuration form (enable SSO, Moodle URL, shared secret)
         * - Usage instructions
         * - Shortcode examples
         * - Testing tools
         *
         * @since 1.0.0
         * @return void
         */
        public function render_admin_page() { ?>
            <div class="wrap">
                <h1>Moodle SSO Integration</h1>

                <form method="post" action="options.php">
                    <?php settings_fields('moodle_sso_group'); ?>
                    <?php do_settings_sections('moodle_sso_group'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable SSO</th>
                            <td>
                                <input type="checkbox" name="moodle_sso_enabled" value="1" <?php checked(get_option('moodle_sso_enabled'), 1); ?> />
                                <p class="description">Enable automatic Moodle login when users log into WordPress</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Moodle URL</th>
                            <td>
                                <input type="url" name="moodle_url" value="<?php echo esc_attr(get_option('moodle_url')); ?>" class="regular-text" />
                                <p class="description">Your Moodle site URL (e.g., https://moodle.example.com)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Shared Secret Key</th>
                            <td>
                                <input type="text" name="moodle_shared_secret" value="<?php echo esc_attr(get_option('moodle_shared_secret')); ?>" class="regular-text" />
                                <p class="description">Must match the shared secret in Moodle settings. Generate with: <code>openssl rand -base64 32</code></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(); ?>
                </form>

                <hr>

                <h2>Usage Instructions</h2>
                <ol>
                    <li>Configure the same Shared Secret Key in both WordPress and Moodle</li>
                    <li>Users logging into WordPress will be automatically logged into Moodle</li>
                    <li>Use <code>[moodle_link]</code> shortcode to add "Go to Moodle" links</li>
                    <li>Use <code>[moodle_status]</code> shortcode to show connection status</li>
                </ol>

                <h3>Shortcode Examples</h3>
                <ul>
                    <li><code>[moodle_link]Go to Moodle[/moodle_link]</code> - Link to Moodle dashboard</li>
                    <li><code>[moodle_link course_id="5"]View Course[/moodle_link]</code> - Link to specific course</li>
                    <li><code>[moodle_status]</code> - Show Moodle connection status</li>
                </ul>

                <h3>Testing</h3>
                <p>
                    <a href="<?php echo esc_url($this->sso_helper->get_moodle_url()); ?>" target="_blank" class="button">
                        Visit Moodle Site
                    </a>
                    <a href="<?php echo esc_url($this->sso_helper->get_moodle_url() . '/local/mchelpers/login/sso.php'); ?>" target="_blank" class="button">
                        Test SSO Endpoint
                    </a>
                </p>
            </div>
            <?php
        }

        // =============================================================================
        // FOOTER OUTPUT
        // =============================================================================

        /**
         * Render shortcodes in footer
         *
         * Outputs moodle_link and moodle_status shortcodes before the closing </body> tag.
         * Only renders for logged-in users when SSO is enabled.
         *
         * @since 1.0.0
         * @return void
         */
        public function render_shortcodes_sso_status() {
            // Only render for logged-in users
            if (!is_user_logged_in()) {
                return;
            }

            // Render moodle_link shortcode
            $moodle_link = do_shortcode('[moodle_link redirect_to="/my" class="btn" target="_blank"]Go to Course[/moodle_link]');

            // Render moodle_status shortcode
            $moodle_status = do_shortcode('[moodle_status]');

            // Output in footer
            echo '<div id="moodle-sso-footer" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-top: 2px solid #0073aa;">';
            echo '<div class="moodle-link">' . $moodle_link . '</div>';
            echo '<div class="moodle-status" style="margin-top: 10px;">' . $moodle_status . '</div>';
            echo '</div>';
        }
    }
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
        private function __clone() {}

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
    }
}
