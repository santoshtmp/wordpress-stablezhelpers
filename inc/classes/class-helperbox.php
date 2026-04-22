<?php

/**
 * Helperbox
 *
 * @package helperbox
 * 
 */

namespace Helperbox_Plugin;

use Helperbox_Plugin\admin\Settings;
use Helperbox_Plugin\admin\Templates as AdminTemplates;
use Helperbox_Plugin\moodle\Moodle_Handler;
use Helperbox_Plugin\Security\Security_Admin_Settings;
use Helperbox_Plugin\Security\Security_Handler;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Helperbox class
 */
class HelperBox {

    /**
     * construction
     */
    function __construct() {
        // 
        // if (!is_plugin_active('wordpress-custom-helperbox/custom-helperbox.php')) {
        //     return;
        // }

        // 
        if (class_exists(Settings::class)) {
            new Settings();
        }
        if (class_exists(Security_Handler::class)) {
            Security_Handler::get_instance();
        }
        if (class_exists(Assets::class)) {
            new Assets();
        }
        if (class_exists(User_Role::class)) {
            new User_Role();
        }
        if (class_exists(Breadcrumb::class)) {
            new Breadcrumb();
        }
        if (class_exists(Blocks::class)) {
            new Blocks();
        }
        if (class_exists(Block_Patterns::class)) {
            new Block_Patterns();
        }

        // initialize Moodle handler
        if (class_exists(Moodle_Handler::class)) {
            Moodle_Handler::get_instance();
        }

        // General hooks
        add_action('admin_notices', [$this, 'helperbox_admin_notices']);
        add_filter('upload_mimes', [$this, 'helperbox_upload_mimes']);

        // include api path
        $include_dir_paths = [
            HELPERBOX_PATH . 'endpoint/rest',
            HELPERBOX_PATH . 'endpoint/ajax',

        ];
        self::requires_dir_paths_files($include_dir_paths);
    }


    /**
     * requires all ".php" files from dir defined in "include_dir_paths" at first level.
     * @param array $include_dir_paths will be [__DIR__.'/inc'];
     */
    public static function requires_dir_paths_files($include_dir_paths) {
        foreach ($include_dir_paths as $key => $file_path) {
            if (!file_exists($file_path)) {
                continue;
            }
            foreach (new \DirectoryIterator($file_path) as $file) {
                if ($file->isDot() || $file->isDir()) {
                    continue;
                }
                $fileExtension = $file->getExtension(); // Get the current file extension
                if ($fileExtension != "php") {
                    continue;
                }
                // $fileName = $file->getFilename(); // Get the full name of the current file.
                $filePath = $file->getPathname(); // Get the full path of the current file
                if ($filePath) {
                    require_once $filePath;
                }
            }
        }
    }

    /**
     * Admin Notices
     */
    function helperbox_admin_notices() {
        $screen = get_current_screen();

        /**
         * Information Notice in plugin page
         */
        if ($screen && $screen->id == 'plugins') {
            AdminTemplates::get_template_notification_update_status_count();
        }

        /**
         * Information notice in helperbox update status check page 
         */
        if ($screen && $screen->id == 'settings_page_helperbox') {
            AdminTemplates::get_template_notification_file_mod_disable();
        }

        Security_Admin_Settings::get_instance()->show_recent_admin_creation_notice();
    }

    /**
     * =======================================================
     * https://developer.wordpress.org/reference/hooks/upload_mimes/
     * =======================================================
     */
    function helperbox_upload_mimes($mimes) {
        // New allowed mime types.        
        $option = \Helperbox_Plugin\admin\Check_Settings::get_helperbox_mimes_file_types();
        $mimes_file_types = \Helperbox_Plugin\admin\Settings::MIMES_FILE_TYPES;
        foreach ($option as $file_type) {
            $setting = $mimes_file_types[$file_type] ?? '';
            if ($setting) {
                $mimes[$setting['value']] = $setting['mimes_value'];
            }
        }
        // Remove a mime type.
        unset($mimes['exe']);
        return $mimes;
    }


    /**
     * Logs exceptions with backtrace to a secure file.
     *
     * @param \Throwable $throwable
     *   The exception or error to log.
     * $type can be 'error', 'message', 'log' or any other string to categorize the log entry.
     *
     * @return void
     */
    public static function set_log_message($th, $type = ''): void {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/helperbox_log';
        $log_file = $log_dir . '/' . date("Y-m") . '-log.txt';

        // Create directory if it does not exist
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0775, true);
        }

        $log_message = "[" . date("Y-m-d H:i:s") . "] ";

        if (strtolower($type) === 'error') {
            $backtrace = debug_backtrace();
            $initial_error_file = $backtrace[1]['file'] ?? '';
            $initial_error_line = $backtrace[1]['line'] ?? '';

            $log_message .= "ERROR: " . $th->getMessage() . " in " . $th->getFile() . " on line " . $th->getLine();

            if ($initial_error_file && $initial_error_line) {
                $log_message .= " | Initial Error File: {$initial_error_file} on line {$initial_error_line}";
            }

            $log_message .= PHP_EOL;
        } elseif (strtolower($type) === 'message') {
            $log_message .= "MESSAGE: " . $th . PHP_EOL;
        } else {
            $log_message .= $th . PHP_EOL;
        }

        // Write log safely
        if (is_writable($log_dir)) {
            file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
        }
    }


    /**
     * === END ===
     */
}
