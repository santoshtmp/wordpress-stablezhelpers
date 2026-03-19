<?php

/**
 * Moodle SSO Integration for WordPress
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
// MOODLE SSO HANDLER CLASS
// =============================================================================

if (!class_exists('MoodleSSOHandler')) {
    /**
     * Moodle SSO Handler Class
     *
     * Manages the integration between WordPress and Moodle SSO, including:
     * - Login/logout hook handlers
     * - Shortcode registration and rendering
     * - Admin interface for configuration
     * - Session synchronization with Moodle
     *
     * @since 1.0.0
     */
    class MoodleSSOHandler {

        /**
         * Singleton instance
         *
         * @since 1.0.0
         * @var MoodleSSOHandler
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
         * @return MoodleSSOHandler The singleton instance
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
         * Initializes the Moodle SSO helper and registers hooks if SSO is enabled.
         *
         * @since 1.0.0
         */
        private function __construct() {
            $this->sso_helper = MoodleSSO::get_instance();
            if ($this->sso_helper->is_enabled()) {
                $this->register_hooks();
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
         * Prevent unserialization of the instance
         *
         * @since 1.0.0
         * @return void
         */
        public function __wakeup() {
            throw new \Exception('Cannot unserialize singleton');
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
        private function register_hooks() {
            // Login/Logout hooks
            add_action('wp_login', [$this, 'handle_wp_login'], 10, 2);
            add_action('init', [$this, 'handle_redirect_to_sso_login'], 100);
            add_filter('logout_url', [$this, 'modify_logout_url'], 10, 2);
            add_action('wp_logout', [$this, 'handle_redirect_to_sso_logout']);

            // Shortcodes
            add_shortcode('moodle_link', [$this, 'moodle_link_shortcode']);
            add_shortcode('moodle_status', [$this, 'moodle_status_shortcode']);

            //
            add_action('admin_init', [$this, 'handle_sync_moodle_user_action']);
        }

        /**
         * Handle manual Moodle user sync action
         *
         * Processes the re-sync action from the user list page.
         *
         * @since 1.0.0
         * @return void
         */
        public function handle_sync_moodle_user_action() {
            // Check if this is our sync action
            if (!isset($_GET['action']) || $_GET['action'] !== 'sync_moodle_user') {
                return;
            }

            // Check user capability
            if (!current_user_can('list_users')) {
                wp_die(__('You do not have permission to perform this action.', 'helperbox'));
            }

            // Get and validate user ID
            $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
            if (!$user_id) {
                wp_die(__('Invalid user ID.', 'helperbox'));
            }

            // Verify nonce
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
            if (!wp_verify_nonce($nonce, 'sync_moodle_user_' . $user_id)) {
                wp_die(__('Security check failed.', 'helperbox'));
            }

            // Get user
            $user = get_userdata($user_id);
            if (!$user) {
                wp_die(__('User not found.', 'helperbox'));
            }

            // Sync user to Moodle
            $result = MoodleUserSync::get_instance()->sync_user_to_moodle($user);

            if (!$result['status']) {
                wp_die($result['message']);
            }

            // Redirect back with status
            $redirect_url = remove_query_arg(['action', 'user_id', '_wpnonce']);
            if (isset($result['status']) && $result['status']) {
                $redirect_url = add_query_arg('sync_status', 'success', $redirect_url);
            } else {
                $redirect_url = add_query_arg('sync_status', 'error', $redirect_url);
            }

            wp_safe_redirect($redirect_url);
            exit;
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
            if (!$sso_url) {
                return '';
            }
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

        /**
         * Render shortcodes Example
         *
         * Outputs moodle_link and moodle_status shortcodes before the closing </body> tag.
         * Only renders for logged-in users when SSO is enabled.
         *
         * @since 1.0.0
         * @return void
         */
        public static function render_shortcodes_sso_example() {
            // Only render for logged-in users
            if (!is_user_logged_in()) {
                return;
            }

            // Render moodle_link shortcode
            $moodle_link = do_shortcode('[moodle_link redirect_to="/my" class="btn" target="_blank"]Go to Course[/moodle_link]');

            // Render moodle_status shortcode
            $moodle_status = do_shortcode('[moodle_status]');

            // Output in footer
            echo '<div id="moodle-sso-status" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-top: 2px solid #0073aa;">';
            echo '<div class="moodle-status" style="margin-top: 10px;">' . $moodle_status . '</div>';
            echo '<div class="moodle-link">' . $moodle_link . '</div>';
            echo '</div>';
        }

        /**
         * 
         */
        public static function get_user_list() {
            // Check user capability
            if (!current_user_can('list_users')) {
                return '<p>' . esc_html__('You do not have permission to view this list.', 'helperbox') . '</p>';
            }

            // Get pagination params
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $per_page = 20;
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

            // Check sync status
            $sync_status = isset($_GET['sync_status']) ? sanitize_text_field($_GET['sync_status']) : '';
            $sync_notice = '';
            if ($sync_status === 'success') {
                $sync_notice = '<div id="message" class="updated notice is-dismissible"><p>' .
                    esc_html__('User successfully synced with Moodle.', 'helperbox') . '</p></div>';
            } elseif ($sync_status === 'error') {
                $sync_notice = '<div id="message" class="error notice is-dismissible"><p>' .
                    esc_html__('Failed to sync user with Moodle. Please try again.', 'helperbox') . '</p></div>';
            }

            // Build user query args
            $args = [
                'number' => $per_page,
                'paged' => $paged,
                'orderby' => 'display_name',
                'order' => 'ASC',
            ];

            // Add search
            if (!empty($search)) {
                $args['search'] = '*' . $search . '*';
                $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
            }

            $user_query = new \WP_User_Query($args);
            $users = $user_query->get_results();
            $total_users = $user_query->get_total();
            $total_pages = ceil($total_users / $per_page);

            // Build table HTML
            ob_start();
?>
            <div class="moodle-sso-user-list wrap">
                <h3><?php esc_html_e('Moodle SSO Users', 'helperbox'); ?></h3>
                <?php echo $sync_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                ?>

                <!-- Search Form -->
                <form class="search-form" method="get" style="margin: 20px 0;">
                    <input type="hidden" name="page" value="helperbox">
                    <input type="hidden" name="tab" value="userlist">
                    <label for="moodle-user-search">
                        <span class="screen-reader-text"><?php esc_html_e('Search Users', 'helperbox'); ?></span>
                        <input type="search" id="moodle-user-search" name="s" value="<?php echo esc_attr($search); ?>" class="regular-text" />
                    </label>
                    <input type="submit" class="button" value="<?php esc_attr_e('Search Users', 'helperbox'); ?>">
                    <?php if (!empty($search)) : ?>
                        <a href="?page=helperbox&tab=userlist" class="button"><?php esc_html_e('Clear Search', 'helperbox'); ?></a>
                    <?php endif; ?>
                </form>

                <!-- Users Table -->
                <table class="wp-list-table widefat fixed striped table-view-list users">
                    <thead>
                        <tr>
                            <th scope="col" class="column-username"><?php esc_html_e('Username', 'helperbox'); ?></th>
                            <th scope="col" class="column-name"><?php esc_html_e('Name', 'helperbox'); ?></th>
                            <th scope="col" class="column-email"><?php esc_html_e('Email', 'helperbox'); ?></th>
                            <th scope="col" class="column-role"><?php esc_html_e('Role', 'helperbox'); ?></th>
                            <th scope="col" class="column-moodle-id"><?php esc_html_e('Moodle User ID', 'helperbox'); ?></th>
                            <th scope="col" class="column-last-sync"><?php esc_html_e('Last Sync', 'helperbox'); ?></th>
                            <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'helperbox'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)) : ?>
                            <tr>
                                <td colspan="7" class="no-items">
                                    <p><?php esc_html_e('No Moodle SSO users found.', 'helperbox'); ?></p>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($users as $user) :
                                $moodle_user_id = get_user_meta($user->ID, 'moodle_user_id', true);
                                $last_sync = get_user_meta($user->ID, 'moodle_last_sync', true);
                                $roles = implode(', ', array_map('ucwords', $user->roles));
                            ?>
                                <tr id="user-<?php echo esc_attr($user->ID); ?>">
                                    <td class="username column-username">
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
                                                <?php echo esc_html($user->user_login); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td class="name column-name">
                                        <?php echo esc_html($user->display_name); ?>
                                    </td>
                                    <td class="email column-email">
                                        <a href="mailto:<?php echo esc_attr($user->user_email); ?>">
                                            <?php echo esc_html($user->user_email); ?>
                                        </a>
                                    </td>
                                    <td class="role column-role">
                                        <?php echo esc_html($roles); ?>
                                    </td>
                                    <td class="moodle-id column-moodle-id">
                                        <?php if ($moodle_user_id) : ?>
                                            <code><?php echo intval($moodle_user_id); ?></code>
                                        <?php else : ?>
                                            <span class="na"><?php esc_html_e('—', 'helperbox'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="last-sync column-last-sync">
                                        <?php if ($last_sync) : ?>
                                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync)); ?>
                                        <?php else : ?>
                                            <span class="na"><?php esc_html_e('Never', 'helperbox'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions column-actions">
                                        <a href="?page=helperbox&tab=userlist&action=sync_moodle_user&user_id=<?php echo esc_attr($user->ID); ?>&_wpnonce=<?php echo esc_attr(wp_create_nonce('sync_moodle_user_' . $user->ID)); ?>" class="button button-small">
                                            <?php esc_html_e('Re-sync', 'helperbox'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php
                                printf(
                                    /* translators: %d: Total users count */
                                    esc_html(_n('%d item', '%d items', $total_users, 'helperbox')),
                                    intval($total_users)
                                );
                                ?>
                            </span>
                            <span class="pagination-links">
                                <?php
                                $current_page = $paged;
                                $base_url = remove_query_arg(['paged']);
                                $base_url = add_query_arg(['page' => 'helperbox', 'tab' => 'userlist'], $base_url);
                                ?>

                                <?php if ($current_page === 1) : ?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                                <?php else : ?>
                                    <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>"><span class="screen-reader-text"><?php esc_html_e('Go to first page', 'helperbox'); ?></span><span aria-hidden="true">«</span></a>
                                    <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', max(1, $current_page - 1), $base_url)); ?>"><span class="screen-reader-text"><?php esc_html_e('Go to previous page', 'helperbox'); ?></span><span aria-hidden="true">‹</span></a>
                                <?php endif; ?>

                                <span class="paging-input">
                                    <label for="current-page-selector" class="screen-reader-text"><?php esc_html_e('Current Page', 'helperbox'); ?></label>
                                    <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($current_page); ?>" size="4" aria-describedby="table-paging" />
                                    <span class="tablenav-paging-text">
                                        <?php
                                        printf(
                                            /* translators: 1: Current page, 2: Total pages */
                                            esc_html__('%1$s of %2$s', 'helperbox'),
                                            '<span class="current-page">' . intval($current_page) . '</span>',
                                            '<span class="total-pages">' . intval($total_pages) . '</span>'
                                        );
                                        ?>
                                    </span>
                                </span>

                                <?php if ($current_page === $total_pages) : ?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                                <?php else : ?>
                                    <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $base_url)); ?>"><span class="screen-reader-text"><?php esc_html_e('Go to next page', 'helperbox'); ?></span><span aria-hidden="true">›</span></a>
                                    <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base_url)); ?>"><span class="screen-reader-text"><?php esc_html_e('Go to last page', 'helperbox'); ?></span><span aria-hidden="true">»</span></a>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

<?php
            return ob_get_clean();
        }
    }
}
