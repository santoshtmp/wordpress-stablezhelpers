<?php

/**
 * Helperbox Enqueue assets
 *
 * @package helperbox
 * 
 */

namespace Helperbox_Plugin;

use Helperbox_Plugin\admin\Settings;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Assets Class
 * 
 * https://developer.wordpress.org/reference/functions/wp_enqueue_script/
 * https://developer.wordpress.org/reference/functions/wp_enqueue_style/
 * 
 */
class Assets {

    /**
     * construction
     */
    function __construct() {
        add_action('init', [$this, 'helperbox_register_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'helperbox_enqueue_scripts']);
        add_action('enqueue_block_editor_assets', [$this, 'helperbox_enqueue_block_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'helperbox_enqueue_block_assets']);
        add_action('admin_enqueue_scripts', [$this, 'helperbox_admin_enqueue_scripts'], 20);
        add_action('login_enqueue_scripts', [$this, 'helperbox_login_enqueue_scripts'], 10);

        // Also add script to backend gutenberg
        // $styleURL = '';
        // add_editor_style($styleURL);
    }

    /**
     * Register all scripts and styles
     * 
     * Hooked to: init
     *
     * @return void
     */
    public function helperbox_register_scripts() {

        // // dataTables https://datatables.net/
        // wp_register_script(
        //     'dataTables',
        //     'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js',
        // );

        // wp_register_style(
        //     'dataTables',
        //     'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css'
        // );

        // ace-editor
        wp_register_script(
            'helperbox-ace-editor',
            'https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.14/ace.js',
            ['jquery'],
            '1.0',
            [
                'in_footer' => true,
                'strategy' => 'defer'
            ]
        );
        wp_register_script(
            'helperbox-ace-ext-beautify',
            'https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.14/ext-beautify.js',
            ['jquery'],
            '1.0',
            [
                'in_footer' => true,
                'strategy' => 'defer'
            ]
        );
    }


    /**
     * Enqueue assets for frontend only.
     * https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/
     *
     * Hooked to: wp_enqueue_scripts
     *
     * @return void
     */
    public function helperbox_enqueue_scripts() {
    }

    /**
     * Enqueue assets for editor admin only.
     * https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
     *
     * Hooked to: enqueue_block_editor_assets
     *
     * @return void
     */
    public function helperbox_enqueue_block_editor_assets() {
    }

    /**
     * Enqueue assets for both frontend and backend editor.
     * https://developer.wordpress.org/reference/hooks/enqueue_block_assets/
     *
     * Hooked to: enqueue_block_assets
     *
     * @return void
     */
    public function helperbox_enqueue_block_assets() {
    }

    /**
     * Enqueue admin area scripts and styles
     * https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
     * 
     * Hooked to: admin_enqueue_scripts
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function helperbox_admin_enqueue_scripts($hook) {

        // get current screen object
        $screen = get_current_screen();

        // 
        if (file_exists(HELPERBOX_PATH . 'assets/build/css/admin.css')) {
            wp_enqueue_style(
                'helperbox-admin-style',
                HELPERBOX_URL . 'assets/build/css/admin.css',
                [],
                null,
                'screen'
            );
        }

        // 
        if (file_exists(HELPERBOX_PATH . 'assets/build/js/admin.js')) {
            wp_enqueue_script(
                'helperbox-admin-script',
                HELPERBOX_URL . 'assets/build/js/admin.js',
                ['jquery', 'wp-color-picker', 'media-views'],
                filemtime(HELPERBOX_PATH . 'assets/build/js/admin.js'),
                array(
                    'strategy' => 'defer',
                    'in_footer' => true,
                )
            );
        }

        // Only load on your settings page
        if ('settings_page_helperbox' == $hook) {
            $active_tab = $_GET['tab'] ?? 'general';
            if ($active_tab === 'breadcrumb'):
                wp_enqueue_script('helperbox-ace-editor');
                wp_enqueue_script('helperbox-ace-ext-beautify');
            elseif ($active_tab === 'adminlogin'):
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
                // Enqueue media uploader
                // https://developer.wordpress.org/reference/functions/wp_enqueue_media/
                wp_enqueue_media();

                // Initialize the color picker
                wp_add_inline_script(
                    'helperbox-admin-script',
                    '
                    jQuery(document).ready(function($){
                        $("#helperbox_adminlogin_bgcolor").wpColorPicker();
                    });
                '
                );
            endif;


            // localize script 
            wp_localize_script('helperbox-admin-script', 'helperboxJS', [
                'settings_page_helperbox' => true,
                'active_tab' => $active_tab
            ]);
        }

        // 
        if ('users' == $screen->base && 'users.php' == $hook) {
            wp_enqueue_script(
                'helperbox-moodle-integraton',
                HELPERBOX_URL . 'assets/build/js/moodle-integraton.js',
                ['jquery'],
                filemtime(HELPERBOX_PATH . 'assets/build/js/moodle-integraton.js'),
                array(
                    'strategy' => 'defer',
                    'in_footer' => true,
                )
            );

            // Localize script
            wp_localize_script(
                'helperbox-moodle-integraton',
                'helperboxMoodleJs',
                [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'userid'  => get_current_user_id(),
                ]
            );
        }
    }

    /**
     * Enqueue custom login page scripts and styles
     * https://developer.wordpress.org/reference/hooks/login_enqueue_scripts/
     * 
     * Hooked to: login_enqueue_scripts
     *
     * @return void
     */
    public function helperbox_login_enqueue_scripts() {

        // check setting
        if (get_option('helperbox_custom_adminlogin', '1') != '1') {
            return;
        }

        $loginstyle = "";

        // logo
        $image_ids = get_option('helperbox_adminlogin_logo', []);
        $image_ids = is_array($image_ids) ? $image_ids : [];
        if ($image_ids && isset($image_ids[0]) && $custom_logo_id = $image_ids[0]) {
            $url = $custom_logo_id ? wp_get_attachment_image_src($custom_logo_id, 'full') : '';
        } else {
            $custom_logo_id = get_theme_mod('custom_logo');
            $url = $custom_logo_id ? wp_get_attachment_image_src($custom_logo_id, 'full') : '';
        }
        if ($url) {
            $loginstyle .= ".helperbox-login #login h1 a, .login h1 a { background-image: url('" . $url[0] . "'); } ";
            $loginstyle .= "\n";
        }

        // BG color
        $bgcolor = get_option('helperbox_adminlogin_bgcolor', Settings::DEFAULT_LOGIN_BG);
        $bgcolor =   empty($bgcolor) ? Settings::DEFAULT_LOGIN_BG : $bgcolor;
        $loginstyle .= ".helperbox-login-style{ background-color: " . esc_attr($bgcolor) . " !important; } ";

        // BG images
        $image_ids = get_option('helperbox_adminlogin_bgimages', []);
        $image_ids = is_array($image_ids) ? $image_ids : [];
        if ($image_ids) {
            $styleImageURL = [];
            foreach ($image_ids as $image_id) {
                $imageURL = wp_get_attachment_image_url($image_id, 'large');
                $styleImageURL[] = 'url("' . $imageURL . '")';
            }
            $loginstyle .= "\n";
            $loginstyle .= ".helperbox-login-style{ background-image: " . implode(", ", $styleImageURL) . " !important; background-size: cover;  }";
        }

        // FORM BG Color
        $formbgcolor = get_option('helperbox_adminlogin_formbgcolor', Settings::DEFAULT_FORMLOGIN_BG);
        $formbgcolor = empty($formbgcolor) ? Settings::DEFAULT_FORMLOGIN_BG : $formbgcolor;
        $loginstyle .= ".helperbox-login-style .helperbox-login{ background-color: " . esc_attr($formbgcolor) . " !important; } ";
        $loginstyle .= ".helperbox-login-style form{ background-color: " . esc_attr($formbgcolor) . " !important; } ";

        // Add style css
        if (file_exists(HELPERBOX_PATH . '/assets/build/css/login.css')) {
            wp_enqueue_style(
                'helperbox-login',
                HELPERBOX_URL . '/assets/build/css/login.css'
            );
        }

        // Add script js
        if (file_exists(HELPERBOX_PATH . '/assets/build/js/login.js')) {
            wp_enqueue_script(
                'helperbox-login',
                HELPERBOX_URL . '/assets/build/js/login.js',
                ['jquery'],
                filemtime(HELPERBOX_PATH . '/assets/build/js/login.js'),
                array(
                    'strategy' => 'defer',
                    'in_footer' => true,
                )
            );
        }

        // Add setting login style
        wp_add_inline_style('helperbox-login', $loginstyle);

        // Add body class
        add_filter('login_body_class', function ($classes) {
            $bodyClass = 'helperbox-login-style';
            $classes[] = $bodyClass;
            return $classes;
        });

        // localize script 
        $bodyClass = 'helperbox-login-style';
        wp_localize_script('helperbox-login', 'helperboxJS', [
            'bodyClass' => $bodyClass,
            'login_headerurl' => home_url(),
        ]);

        // Logo URL
        add_filter('login_headerurl', function () {
            return home_url();
        }, 10);
    }
}
