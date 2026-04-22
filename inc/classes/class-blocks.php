<?php

/**
 * Block Types
 *
 * @package Helperbox
 */

namespace Helperbox_Plugin;

use Helperbox_Plugin\admin\Check_Settings;
use WP_Block_Type_Registry;

/*
 * Blocks Class 
 * 
 */

class Blocks {


    /**
     * construction
     */
    public function __construct() {

        add_action('init', [$this, 'init_action']);
        add_action('enqueue_block_editor_assets', [$this, 'block_editor_assets']);
        add_filter('block_categories_all', [$this, 'add_block_category']);
    }

    /**
     * Init Action
     * 
     * Hooked to: init
     *
     * @return void
     */
    public function init_action() {
        $this->register_block_types();
        self::register_block_assets();
    }

    /**
     * Adding a new (custom) block category
     * https://developer.wordpress.org/reference/hooks/block_categories_all/
     */
    function add_block_category($block_categories) {
        // show the category at the top
        array_unshift(
            $block_categories,
            [
                'slug' => 'helperbox_blocks',
                'title' => 'Helperbox Blocks'
            ]
        );
        return $block_categories;
    }

    /**
     * Register Block Types
     * https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/
     * https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
     * https://developer.wordpress.org/block-editor/reference-guides/packages/packages-create-block/
     * https://www.advancedcustomfields.com/resources/acf-block-configuration-via-block-json/
     * 
     * npx @wordpress/create-block@latest todo-list --variant dynamic --no-plugin
     * 
     */
    public function register_block_types() {
        // Ensure the function exists.
        if (function_exists('register_block_type')) {
            // Register a block.
            $block_json_file = glob(HELPERBOX_PATH . 'blocks/*/block.json');
            foreach ($block_json_file as $file) {
                // Register the block using block.json
                $metadata = wp_json_file_decode($file, array('associative' => true));
                // Skip ACF blocks
                if (isset($metadata['acf'])) {
                    register_block_type($file);
                } else {
                    $block_arg = [];
                    $block_dir = dirname($file);
                    $block_basename = basename($block_dir);
                    // Get template path if exists
                    $template_path = self::get_block_template_path($metadata, $block_basename);
                    // Set render callback if template path found
                    if ($template_path) {
                        $block_arg['render_callback'] = static function ($attributes, $content, $block) use ($template_path) {
                            ob_start();
                            require $template_path;
                            return ob_get_clean();
                        };
                    }
                    register_block_type($block_dir, $block_arg);
                }
            }
        }
    }

    /**
     * Get Block Template Path
     * 
     * @param array $blockmetadata
     * @param string block_basename
     * @return string
     */
    public static function get_block_template_path($blockmetadata, $block_basename) {
        $block_template_dir = HELPERBOX_PATH . 'blocks/' . $block_basename;
        if (isset($blockmetadata['render']) && ! empty($blockmetadata['render'])) {
            $template_path = $block_template_dir . "/" . remove_block_asset_path_prefix($blockmetadata['render']);
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        $template_path = $block_template_dir . "/" . 'template.php';
        if (file_exists($template_path)) {
            return $template_path;
        }

        return '';
    }

    /**
     * Enqueue Block Editor Assets
     * 
     * https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
     *
     * Hooked to: enqueue_block_editor_assets
     *
     * @return void
     */
    public static function block_editor_assets() {
        $asset_path = 'assets/build/blocks/helperbox-blocks.js';
        wp_enqueue_script(
            'helperbox-blocks',
            HELPERBOX_URL . $asset_path,
            [
                // 'jquery',
                // 'wp-dom-ready',
                // 'wp-blocks',
                // 'wp-element',
                // 'wp-editor',
                // 'wp-block-editor',
            ],
            filemtime(HELPERBOX_PATH . $asset_path),
            array(
                'strategy' => 'defer',
                'in_footer' => true,
            )
        );

        wp_localize_script(
            'helperbox-blocks',
            'helperbox',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'path' => HELPERBOX_PATH,
                'url' => HELPERBOX_URL,
            ]
        );
    }

    /**
     * Register Block Assets
     * 
     * @return void
     */
    public static function register_block_assets() {
        $block_assets_dir = HELPERBOX_PATH . 'assets/build/blocks/';
        if (file_exists($block_assets_dir)) {
            foreach (new \DirectoryIterator($block_assets_dir) as $block) {
                if ($block->isDot() || $block->isFile()) {
                    continue;
                }
                if ($block->isDir()) {
                    $block_name = basename($block);
                    foreach (new \DirectoryIterator($block_assets_dir . $block_name) as $file) {
                        if ($file->isDot() || $file->isDir()) {
                            continue;
                        }
                        $file_ext =  $file->getExtension();
                        $file_path = $file->getPathname();
                        $file_name = $file->getBasename('.' . $file_ext);
                        $file_url = HELPERBOX_URL . str_replace(HELPERBOX_PATH, '', $file_path);
                        // block style
                        if ($file_ext == 'css') {
                            wp_register_style(
                                $block_name . '-' . $file_name,
                                $file_url,
                                [],
                                filemtime($file_path),
                                'all'
                            );
                        }
                        // block script
                        if ($file_ext == 'js') {
                            wp_register_script(
                                $block_name . '-' . $file_name,
                                $file_url,
                                ['jquery'],
                                filemtime($file_path),
                                [
                                    'in_footer' => true,
                                    'strategy' => 'defer',
                                ]
                            );
                        }
                    }
                }
            }
        }
    }


    /**
     * == END OF CLASS ==
     */
}
