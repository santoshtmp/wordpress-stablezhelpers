<?php

/**
 * Moodle User Admin
 *
 * Handles admin UI for Moodle user integration:
 * - Admin columns in users list table
 * - Column styles and scripts
 * - AJAX handlers for user actions
 * - Admin notices and feedback
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
// MOODLE USER ADMIN CLASS
// =============================================================================

if (!class_exists(__NAMESPACE__ . '\\Moodle_User_Admin')) {
    /**
     * Moodle User Admin Class
     *
     * Handles all admin UI operations for Moodle user integration.
     *
     * @since 1.0.0
     */
    class Moodle_User_Admin {

        /**
         * Singleton instance
         *
         * @since 1.0.0
         * @var Moodle_User_Admin
         */
        private static $instance = null;

        /**
         * Moodle Integration helper instance
         *
         * @since 1.0.0
         * @var Moodle_Integration
         */
        private $moodle_integration;

        /**
         * Get singleton instance
         *
         * Ensures only one instance of the class exists.
         *
         * @since 1.0.0
         * @return Moodle_User_Admin The singleton instance
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
            $this->moodle_integration = Moodle_Integration::get_instance();
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
            // Admin user list table columns
            add_filter('manage_users_columns', [$this, 'add_moodle_user_column']);
            add_filter('manage_users_custom_column', [$this, 'render_moodle_user_column_content'], 10, 3);
            add_action('admin_head', [$this, 'add_moodle_column_styles']);
        }

        /**
         * Add Moodle User ID column to users list table
         *
         * @since 1.0.0
         *
         * @param array $columns Existing columns
         * @return array Modified columns
         */
        public function add_moodle_user_column($columns) {
            // Insert after username column
            $new_columns = [];
            foreach ($columns as $key => $value) {
                $new_columns[$key] = $value;
                if ('username' === $key) {
                    $new_columns['moodle_user_id'] = __('Moodle User ID', 'helperbox');
                }
            }
            return $new_columns;
        }

        /**
         * Render Moodle User ID column content
         *
         * @since 1.0.0
         *
         * @param string $value     Current column value
         * @param string $column_name Column name
         * @param int    $user_id   User ID
         * @return string Column content
         */
        public function render_moodle_user_column_content($value, $column_name, $user_id) {
            if ('moodle_user_id' !== $column_name) {
                return $value;
            }
            $nonce = wp_create_nonce('sync_moodle_user_' . $user_id);
            $moodle_user_id = $this->moodle_integration->get_moodle_user_id($user_id);
            // $last_sync = get_user_meta($user_id, 'moodle_last_sync', true);

            if (!$moodle_user_id) {
                // User not synced - show sync button
                return sprintf(
                    '<div class="moodle-user-status not-synced">
                        <span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
                        <span style="margin-left: 5px;">%1$s</span>
                        <button class="button button-small sync-moodle-user" data-user-id="%2$d" data-nonce="%3$s" style="margin-left: 8px;">
                            <span class="dashicons dashicons-update"></span> %4$s
                        </button>
                    </div>',
                    esc_html__('Not synced', 'helperbox'),
                    esc_attr($user_id),
                    esc_attr($nonce),
                    esc_html__('Sync', 'helperbox')
                );
            }

            // User is synced - show ID and actions
            // $moodle_url = rtrim($this->moodle_integration->get_moodle_url(), '/');
            // $view_url = $moodle_url . '/user/view.php?id=' . $moodle_user_id;

            return sprintf(
                '<div class="moodle-user-status not-synced">
                        <span style="margin-left: 5px;">%1$s </span>
                        <button class="button button-small sync-moodle-user" data-user-id="%2$d" data-nonce="%3$s" style="margin-left: 8px;">
                            <span class="dashicons dashicons-update"></span> %4$s
                        </button>
                    </div>',
                esc_attr($moodle_user_id),
                esc_attr($user_id),
                esc_attr($nonce),
                esc_html__('Re-Sync', 'helperbox')
            );
        }

        /**
         * Add custom styles for Moodle column
         *
         * @since 1.0.0
         * @return void
         */
        public function add_moodle_column_styles() {
            $screen = get_current_screen();
            if ('users' !== $screen->base) {
                return;
            } ?>
            <style>
                .moodle-user-status {
                    line-height: 1.4;
                }

                .moodle-user-status .dashicons {
                    vertical-align: middle;
                    font-size: 16px;
                    width: 16px;
                    height: 16px;
                }

                .moodle-user-status.not-synced {
                    color: #666;
                }

                .moodle-user-status.synced {
                    color: #46b450;
                }

                .column-moodle_user_id {
                    width: 250px;
                }
            </style>
<?php
        }
    }
}
