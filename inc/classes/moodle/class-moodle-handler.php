<?php

/**
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
// Moodle Integration CLASS
// =============================================================================

if (!class_exists(__NAMESPACE__ . '\\Moodle_Handler')) {

    class Moodle_Handler {
        /**
         * Singleton instance
         *
         * @since 1.0.0
         * @var Moodle_Handler
         */
        private static $instance = null;

        /**
         * Get singleton instance
         *
         * Ensures only one instance of the class exists.
         *
         * @since 1.0.0
         * @return Moodle_Handler The singleton instance
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
            //
            $helperbox_external_site_integration = get_option('helperbox_external_site_integration', 'none');
            if ('moodle_site' != $helperbox_external_site_integration) {
                return;
            }

            // initialize integration
            Moodle_Integration::get_instance();

            // initialize Settings
            Moodle_Settings::get_instance();

            // initialize Moodle SSO handler
            Moodle_SSOHandler::get_instance();

            // Register post types and taxonomies
            Moodle_Course_Post_Type::get_instance();

            // initialize User Sync
            Moodle_User_Sync::get_instance();

            // initialize User Admin (admin UI and columns)
            Moodle_User_Admin::get_instance();

            // initialize ajax endpoint
            Moodle_Endpoint_Ajax::get_instance();

            // initialize REST API endpoint
            Moodle_Endpoint_Rest::get_instance();

            // Register Hooks
            add_action('init', [$this, 'init_action']);
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
         * Initialize action hook.
         *
         * @since 1.0.0
         */
        public function init_action() {
            // Additional init logic if needed
        }
    }
}
