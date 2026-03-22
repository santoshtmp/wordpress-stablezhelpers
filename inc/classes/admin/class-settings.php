<?php

/**
 * Helperbox Admin Settings
 *
 * Handles all admin settings pages, registration, and rendering for the Helperbox plugin.
 * Provides settings for general options, breadcrumb navigation, admin login customization,
 * security features, and logging.
 *
 * @package helperbox
 * @since   1.0.0
 */

namespace Helperbox_Plugin\admin;

use Helperbox_Plugin\Breadcrumb;
use Helperbox_Plugin\Security\Security_Admin_Settings;
use Helperbox_Plugin\User_Role;

/**
 * Reference:
 * @link https://developer.wordpress.org/reference/functions/register_setting/
 * @link https://developer.wordpress.org/reference/hooks/admin_menu/
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 *
 * Manages plugin settings pages, options registration, sanitization, and rendering.
 * Implements tabs for different feature groups (General, Breadcrumb, Admin Login, Security, Log).
 *
 * @since 1.0.0
 */
class Settings {

    /**
     * Admin page slug identifier.
     *
     * @since 1.0.0
     * @var string
     */
    public const ADMIN_PAGE_SLUG = 'helperbox';

    /**
     * Custom theme templates directory path.
     *
     * @since 1.0.0
     * @var string
     */
    public const CUSTOM_THEME_TEMP_DIR = '/';

    /**
     * Default value for custom admin login feature (enabled by default).
     *
     * @since 1.0.0
     * @var int
     */
    public const DEFAULT_CUSTOM_LOGINADMIN = 1;

    /**
     * Default background color for login page.
     *
     * @since 1.0.0
     * @var string
     */
    public const DEFAULT_LOGIN_BG = '#f1f1f1';

    /**
     * Default background color for login form.
     *
     * @since 1.0.0
     * @var string
     */
    public const DEFAULT_FORMLOGIN_BG = '#fff';

    /**
     * Allowed MIME file types configuration.
     *
     * @since 1.0.0
     * @var array[] {
     *     @type string $value       File extension value.
     *     @type string $mimes_value MIME type string.
     *     @type string $label       Human-readable label.
     * }
     */
    public const MIMES_FILE_TYPES = [
        'svg'  => [
            'value'       => 'svg',
            'mimes_value' => 'image/svg+xml',
            'label'       => 'SVG',
        ],
        'json' => [
            'value'       => 'json',
            'mimes_value' => 'application/json',
            'label'       => 'JSON',
        ],
        'doc'  => [
            'value'       => 'doc',
            'mimes_value' => 'application/msword',
            'label'       => 'DOC',
        ],
        'mp4'  => [
            'value'       => 'mp4',
            'mimes_value' => 'video/mp4',
            'label'       => 'Video MP4',
        ],
    ];

    /**
     * External site options.
     *
     * @since 1.0.0
     * @var array[] {
     *     @type string $value External site value.
     *     @type string $label Human readable label.
     * }
     */
    public const EXTERNAL_SITE = [
        'none'       => [
            'value' => 'none',
            'label' => 'None',
        ],
        'moodle_site' => [
            'value' => 'moodle_site',
            'label' => 'Moodle Site',
        ],
    ];

    /**
     * Constructor
     *
     * Registers WordPress hooks for settings page, plugin action links, and option updates.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_filter('plugin_action_links_' . helperbox_basename, [$this, 'helperbox_settings_link']);
        add_action('admin_init', [$this, 'admin_init_action']);
        add_action('admin_menu', [$this, 'helperbox_submenu']);
        add_action('update_option_helperbox_disable_phpexecution_upload_dir', [$this, 'update_option_helperbox_disable_phpexecution_upload_dir'], 10, 2);
    }

    /**
     * Get the URL for the settings page.
     *
     * @since 1.0.0
     * @return string URL query string for the settings page.
     *
     * @example
     * // Get settings page URL
     * $url = Settings::get_settings_page_url();
     * // Returns: 'options-general.php?page=helperbox'
     */
    public static function get_settings_page_url() {
        return 'options-general.php?page=' . self::ADMIN_PAGE_SLUG;
    }

    /**
     * Add settings link to plugin action links on plugins.php page.
     *
     * @since 1.0.0
     *
     * @param array $links Existing plugin action links.
     * @return array Modified action links with settings link added.
     */
    public function helperbox_settings_link($links) {
        $settings_link = '<a href="' . self::get_settings_page_url() . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Initialize admin settings on admin_init hook.
     *
     * Registers settings for general options and admin login customization.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_init_action() {

        // Register option page setting group
        $this->register_general_setting_options_fields();
        Breadcrumb::register_setting_options_fields();
        $this->register_adminlogin_setting_options_fields();
        Security_Admin_Settings::register_setting_options_fields();

        // Register meta boxes
        $this->register_meta_boxes();
    }

    /**
     * Register meta boxes.
     */
    private function register_meta_boxes() {
        add_meta_box(
            'helperbox_general_settings',
            __('Custom Helper Box Settings', 'helperbox'),
            [$this, 'callback_render_metabox_helperbox_general_settings'],
            'helperbox_settings_page',
            'normal',
            'default'
        );
    }

    /**
     * Register admin submenu page.
     *
     * Adds the Helperbox settings page under WordPress Settings menu.
     *
     * @since 1.0.0
     * @return void
     *
     * @see add_options_page()
     */
    public function helperbox_submenu() {
        add_options_page(
            __('Custom Helper Box', 'helperbox'), // Page title.
            __('Custom Helper Box', 'helperbox'), // Menu title.
            'manage_options',                     // Capability required.
            self::ADMIN_PAGE_SLUG,                // Menu slug.
            [$this, 'callback_helperbox_admin_setting_page_content'] // Callback.
        );
    }

    /**
     * Register general settings options fields.
     *
     * Registers WordPress settings for:
     * - Allowed MIME file types
     * - Custom theme templates directory
     * - User role name
     * - Remote block patterns loading
     *
     * @since 1.0.0
     * @return void
     *
     * @see register_setting()
     */
    private function register_general_setting_options_fields() {
        $helperbox_general_settings_group = 'helperbox_general_settings_group';

        // Allowed file types setting.
        register_setting(
            $helperbox_general_settings_group,
            'helperbox_mimes_file_types',
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_callback_helperbox_array_text_field'],
                'default'           => [],
            ]
        );

        // Custom theme templates directory setting.
        register_setting(
            $helperbox_general_settings_group,
            'helperbox_custom_theme_templates_dir',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );

        // User role name setting.
        register_setting(
            $helperbox_general_settings_group,
            'helperbox_user_role_name',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );

        // Remote block patterns setting.
        register_setting(
            $helperbox_general_settings_group,
            'helperbox_load_remote_block_patterns',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            ]
        );

        // External site integration setting.
        register_setting(
            $helperbox_general_settings_group,
            'helperbox_external_site_integration',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'none',
            ]
        );
    }

    /**
     * Register admin login settings options fields.
     *
     * Registers WordPress settings for custom login page customization:
     * - Custom login feature toggle
     * - Background color
     * - Form background color
     * - Background images
     * - Logo images
     *
     * @since 1.0.0
     * @return void
     *
     * @see register_setting()
     */
    private function register_adminlogin_setting_options_fields() {
        $helperbox_adminlogin_settings_group = 'helperbox_adminlogin_settings_group';

        // Custom login feature toggle.
        register_setting(
            $helperbox_adminlogin_settings_group,
            'helperbox_custom_adminlogin',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => true,
            ]
        );

        // Login background color.
        register_setting(
            $helperbox_adminlogin_settings_group,
            'helperbox_adminlogin_bgcolor',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_hex_color',
                'default'           => self::DEFAULT_LOGIN_BG,
            ]
        );

        // Login form background color.
        register_setting(
            $helperbox_adminlogin_settings_group,
            'helperbox_adminlogin_formbgcolor',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_hex_color',
                'default'           => self::DEFAULT_FORMLOGIN_BG,
            ]
        );

        // Background images setting.
        register_setting(
            $helperbox_adminlogin_settings_group,
            'helperbox_adminlogin_bgimages',
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_callback_helperbox_image_ids'],
                'default'           => [],
            ]
        );

        // Logo images setting.
        register_setting(
            $helperbox_adminlogin_settings_group,
            'helperbox_adminlogin_logo',
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_callback_helperbox_image_ids'],
                'default'           => [],
            ]
        );
    }

    /**
     * Render general settings form fields.
     *
     * Outputs HTML form fields for general plugin settings including file types,
     * theme templates, user roles, and block patterns.
     *
     * @since 1.0.0
     * @return void
     *
     * @see settings_fields()
     */
    public static function render_general_settings_fields() {
        settings_fields('helperbox_general_settings_group'); ?>
        <table class="form-table form-table-general" table-tab="general">
            <tr>
                <th scope="row">
                    <label for="helperbox_mimes_file_types">
                        <?php esc_html_e('Allow File Type', 'helperbox'); ?>
                    </label>
                </th>
                <td>
                    <?php
                    $option = Check_Settings::get_helperbox_mimes_file_types();
                    foreach (Settings::MIMES_FILE_TYPES as $file) :
                    ?>
                        <label for="helperbox_mimes_file_types-<?php echo esc_attr($file['value']); ?>">
                            <input
                                type="checkbox"
                                name="helperbox_mimes_file_types[]"
                                value="<?php echo esc_attr($file['value']); ?>"
                                id="helperbox_mimes_file_types-<?php echo esc_attr($file['value']); ?>"
                                <?php checked(in_array($file['value'], $option, true)); ?>>
                            <?php echo esc_html($file['label']); ?>
                        </label>
                    <?php endforeach; ?>

                    <div class="description">
                        <p><?php esc_html_e('This will allow extra file type extensions.', 'helperbox'); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="helperbox_custom_theme_templates_dir">
                        <?php esc_html_e('Custom theme template dir', 'helperbox'); ?>
                    </label>
                </th>
                <td>
                    <?php
                    $value = get_option('helperbox_custom_theme_templates_dir', Settings::CUSTOM_THEME_TEMP_DIR);
                    ?>
                    <input
                        type="text"
                        name="helperbox_custom_theme_templates_dir"
                        id="helperbox_custom_theme_templates_dir"
                        value="<?php echo esc_attr($value); ?>"
                        class="regular-text">
                    <div class="description">
                        <p>
                            <?php esc_html_e('This will define the active theme custom template dir. Example:', 'helperbox'); ?>
                            <code>app/templates</code>.
                        </p>
                        <p><?php esc_html_e('If empty, default root directory will be used.', 'helperbox'); ?></p>
                        <?php
                        $templates_dir = get_stylesheet_directory() . '/' . trim($value, '/');
                        if (is_dir($templates_dir)) {
                            echo '<p>' . sprintf(
                                esc_html__('Theme template is located at template dir: %s', 'helperbox'),
                                '<code>' . esc_html(str_replace(ABSPATH, '', $templates_dir)) . '</code>'
                            ) . '</p>';
                        } else {
                            echo '<p>' . sprintf(
                                esc_html__('Incorrect template directory. There is no such directory: %s', 'helperbox'),
                                '<code>' . esc_html(str_replace(ABSPATH, '', $templates_dir)) . '</code>'
                            ) . '</p>';
                        }
                        ?>
                        <p><?php printf(esc_html__('Default: %s', 'helperbox'), esc_html(Settings::CUSTOM_THEME_TEMP_DIR)); ?></p>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="helperbox_user_role_name">
                        <?php esc_html_e('Helperbox Client Role Name', 'helperbox'); ?>
                    </label>
                </th>
                <td>
                    <?php
                    $value = get_option('helperbox_user_role_name', User_Role::CLIENT_NAME);
                    ?>
                    <input
                        type="text"
                        name="helperbox_user_role_name"
                        id="helperbox_user_role_name"
                        value="<?php echo esc_attr($value); ?>"
                        class="regular-text">
                    <div class="description">
                        <p><?php esc_html_e('Change client role name.', 'helperbox'); ?></p>
                        <p><?php printf(esc_html__('Default: %s', 'helperbox'), esc_html(User_Role::CLIENT_NAME)); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="helperbox_load_remote_block_patterns">
                        <?php esc_html_e('Should load remote block patterns', 'helperbox'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        name="helperbox_load_remote_block_patterns"
                        id="helperbox_load_remote_block_patterns"
                        value="1"
                        <?php checked(get_option('helperbox_load_remote_block_patterns', '')); ?>>
                    <div class="description">
                        <p><?php esc_html_e('Default: unchecked', 'helperbox'); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="helperbox_external_site_integration">
                        <?php esc_html_e('External site integration', 'helperbox'); ?>
                    </label>
                </th>
                <td>
                    <?php
                    $value = get_option('helperbox_external_site_integration', 'none');
                    foreach (self::EXTERNAL_SITE as $method) :
                    ?>
                        <label for="helperbox_external_site_integration-<?php echo esc_attr($method['value']); ?>">
                            <input
                                type="radio"
                                name="helperbox_external_site_integration"
                                id="helperbox_external_site_integration-<?php echo esc_attr($method['value']); ?>"
                                value="<?php echo esc_attr($method['value']); ?>"
                                <?php checked($value, $method['value']); ?>>
                            <?php echo esc_html($method['label']); ?>
                        </label>
                        <br>
                    <?php endforeach; ?>
                    <div class="description">
                        <p><?php esc_html_e('Select the external site option.', 'helperbox'); ?></p>
                        <p><?php esc_html_e('Default: None', 'helperbox'); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="col">
                    <p><?php esc_html_e('Other available add-on plugins:', 'helperbox'); ?></p>
                </th>
                <td>
                    <ol>
                        <li>
                            <?php esc_html_e('"Post Title Required" available at', 'helperbox'); ?>
                            <a href="https://wordpress.org/plugins/post-title-required/" target="_blank"><?php esc_html_e('wordpress.org', 'helperbox'); ?></a>
                            <?php esc_html_e('and', 'helperbox'); ?>
                            <a href="https://github.com/santoshtmp/wordpress-post-title-required" target="_blank"><?php esc_html_e('Github', 'helperbox'); ?></a>
                        </li>
                        <li>
                            <?php esc_html_e('"Citation Note" available at', 'helperbox'); ?>
                            <a href="https://wordpress.org/plugins/citation-note/" target="_blank"><?php esc_html_e('wordpress.org', 'helperbox'); ?></a>
                            <?php esc_html_e('and', 'helperbox'); ?>
                            <a href="https://github.com/santoshtmp/wordpress-citation-note" target="_blank"><?php esc_html_e('Github', 'helperbox'); ?></a>
                        </li>
                        <li>
                            <?php esc_html_e('"CSF - Custom Search Filter" available at', 'helperbox'); ?>
                            <a href="https://github.com/santoshtmp/wordpress-custom-search-filter" target="_blank"><?php esc_html_e('Github', 'helperbox'); ?></a>
                        </li>
                        <li>
                            <?php esc_html_e('"Restore & Clean Media" available at', 'helperbox'); ?>
                            <a href="https://github.com/santoshtmp/wordpress-restore-media-clean-data" target="_blank"><?php esc_html_e('Github', 'helperbox'); ?></a>
                        </li>
                    </ol>
                </td>
            </tr>
        </table>
    <?php
    }

    /**
     * Render admin login settings form fields.
     *
     * Outputs HTML form fields for custom login page settings including colors,
     * background images, and logo configuration.
     *
     * @since 1.0.0
     * @return void
     *
     * @see settings_fields()
     */
    public static function render_adminlogin_settings_fields() {
        settings_fields('helperbox_adminlogin_settings_group');
        $custom_adminlogin = get_option('helperbox_custom_adminlogin', Settings::DEFAULT_CUSTOM_LOGINADMIN);
    ?>
        <table class="form-table form-table-adminlogin" table-tab="adminlogin">
            <tr class="tr-helperbox_custom_adminlogin">
                <th scope="row">
                    <label for="helperbox_custom_adminlogin">
                        <?php esc_html_e('Custom Login Page', 'helperbox'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        name="helperbox_custom_adminlogin"
                        id="helperbox_custom_adminlogin"
                        value="1"
                        <?php checked($custom_adminlogin); ?>>
                    <div class="description">
                        <p><?php esc_html_e('This will give options to customize login page.', 'helperbox'); ?></p>
                        <p><?php esc_html_e('Default: checked', 'helperbox'); ?></p>
                    </div>
                </td>
            </tr>
            <?php if ($custom_adminlogin === '1') : ?>
                <tr class="tr-helperbox_adminlogin_bgcolor">
                    <th scope="row">
                        <label for="helperbox_adminlogin_bgcolor">
                            <?php esc_html_e('Background Color', 'helperbox'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="helperbox_adminlogin_bgcolor"
                            id="helperbox_adminlogin_bgcolor"
                            value="<?php echo esc_attr(get_option('helperbox_adminlogin_bgcolor', Settings::DEFAULT_LOGIN_BG)); ?>"
                            class="helperbox-color-picker" />
                        <p class="description">
                            <?php printf(esc_html__('Choose the background color for the login page. Default: %s', 'helperbox'), esc_html(Settings::DEFAULT_LOGIN_BG)); ?>
                        </p>
                    </td>
                </tr>
                <tr class="tr-helperbox_adminlogin_bgimages">
                    <th scope="row">
                        <label for="helperbox_adminlogin_bgimages"><?php esc_html_e('Background Images', 'helperbox'); ?></label>
                    </th>
                    <td>
                        <div class="helperbox-bg-images-upload">

                            <div class="helperbox-media-preview helperbox_adminlogin_bgimages-preview">
                                <?php
                                $image_ids = get_option('helperbox_adminlogin_bgimages', []);
                                $image_ids = is_array($image_ids) ? $image_ids : [];
                                foreach ($image_ids as $image_id) {
                                    echo "<div class='selected-image selected-image-" . esc_attr($image_id) . "' >";
                                    echo wp_get_attachment_image(
                                        $image_id,
                                        'thumbnail',
                                        false,
                                        ['style' => 'margin:5px;']
                                    );
                                    echo '<input type="hidden" name="helperbox_adminlogin_bgimages[]" value="' . esc_attr($image_id) . '" />';
                                    echo '<a href="#" class="remove-image button button-secondary button-small" data-attachment-id="' . esc_attr($image_id) . '" title="' . esc_attr__('Remove image', 'helperbox') . '">×</a>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <p>
                                <button type="button" class="button button-secondary" id="helperbox_adminlogin_bgimages_addBtn" field-name="helperbox_adminlogin_bgimages"><?php esc_html_e('Upload / Select Image', 'helperbox'); ?></button>
                                <button type="button" class="button button-link-delete helperbox-delete-all-media" field-name="helperbox_adminlogin_bgimages" style="display: none;"><?php esc_html_e('Remove All', 'helperbox'); ?></button>
                            </p>
                            <p class="description">
                                <?php esc_html_e('Select images to use as background on the login page.', 'helperbox'); ?>
                            </p>
                        </div>
                    </td>
                </tr>
                <tr class="tr-helperbox_adminlogin_logo">
                    <th scope="row">
                        <label for="helperbox_adminlogin_logo"><?php esc_html_e('Login Form Logo', 'helperbox'); ?></label>
                    </th>
                    <td>
                        <div class="helperbox-bg-images-upload">

                            <div class="helperbox-media-preview helperbox_adminlogin_logo-preview">
                                <?php
                                $image_ids = get_option('helperbox_adminlogin_logo', []);
                                $image_ids = is_array($image_ids) ? $image_ids : [];
                                foreach ($image_ids as $image_id) {
                                    echo "<div class='selected-image selected-image-" . esc_attr($image_id) . "' >";
                                    echo wp_get_attachment_image(
                                        $image_id,
                                        'thumbnail',
                                        false,
                                        ['style' => 'margin:5px;']
                                    );
                                    echo '<input type="hidden" name="helperbox_adminlogin_logo[]" value="' . esc_attr($image_id) . '" />';
                                    echo '<a href="#" class="remove-image button button-secondary button-small" data-attachment-id="' . esc_attr($image_id) . '" title="' . esc_attr__('Remove image', 'helperbox') . '">×</a>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <p>
                                <button type="button" class="button button-secondary" id="helperbox_adminlogin_logo_addBtn" field-name="helperbox_adminlogin_logo"><?php esc_html_e('Upload / Select Image', 'helperbox'); ?></button>
                                <button type="button" class="button button-link-delete helperbox-delete-all-media" field-name="helperbox_adminlogin_logo" style="display: none;"><?php esc_html_e('Remove All', 'helperbox'); ?></button>
                            </p>
                            <p class="description">
                                <?php esc_html_e('Select images to use as login page logo. If empty, logo defined by theme will be used.', 'helperbox'); ?>
                            </p>
                        </div>
                    </td>
                </tr>
                <tr class="tr-helperbox_adminlogin_formbgcolor">
                    <th scope="row">
                        <label for="helperbox_adminlogin_formbgcolor">
                            <?php esc_html_e('Form Background Color', 'helperbox'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="helperbox_adminlogin_formbgcolor"
                            id="helperbox_adminlogin_formbgcolor"
                            value="<?php echo esc_attr(get_option('helperbox_adminlogin_formbgcolor', Settings::DEFAULT_FORMLOGIN_BG)); ?>"
                            class="helperbox-color-picker" />
                        <p class="description">
                            <?php printf(esc_html__('Choose the background color for the login form. Default: %s', 'helperbox'), esc_html(Settings::DEFAULT_FORMLOGIN_BG)); ?>
                        </p>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    <?php
    }

    /**
     * Sanitize array of text fields.
     *
     * Used for sanitizing arrays of user input such as post types, file types, etc.
     *
     * @since 1.0.0
     *
     * @param mixed $input The input value to sanitize.
     * @return array Sanitized array of text values. Empty array if input is not an array.
     *
     * @example
     * // Sanitize array of post types
     * $sanitized = Settings::sanitize_callback_helperbox_array_text_field(['post', 'page', 'product']);
     */
    public static function sanitize_callback_helperbox_array_text_field($input) {
        if (!is_array($input)) {
            return [];
        }
        return array_map('sanitize_text_field', $input);
    }

    /**
     * Sanitize background image IDs.
     *
     * Converts input to integers and validates that each ID corresponds to a valid image attachment.
     *
     * @since 1.0.0
     *
     * @param mixed $input The input value (array or comma-separated string of image IDs).
     * @return array|void Array of valid image attachment IDs. Void if input is empty.
     *
     * @example
     * // Sanitize image IDs
     * $sanitized = $this->sanitize_callback_helperbox_image_ids([123, 456, 789]);
     */
    public static function sanitize_callback_helperbox_image_ids($input) {
        if (!$input || null === $input) {
            return;
        }
        if (is_string($input)) {
            $input = explode(',', $input);
        }
        $sanitized = array_map('absint', $input); // Convert to integers.
        // Verify each ID is a valid image attachment.
        return array_filter($sanitized, function ($id) {
            return wp_attachment_is_image($id);
        });
    }

    /**
     * Render the settings page content.
     *
     * Callback function to display the admin settings page with meta boxes and tab navigation.
     *
     * @since 1.0.0
     * @return void
     */
    public function callback_helperbox_admin_setting_page_content() {
        $check_update_status = sanitize_text_field($_GET['check_update_status'] ?? 'false');
        $setting_group_tab = sanitize_text_field($_GET['tab'] ?? 'general');
    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Custom Helper Box', 'helperbox'); ?></h1>
            <?php if ($setting_group_tab === 'security' && $check_update_status === 'true') : ?>
                <?php Templates::get_template_helperbox_available_update_list(); ?>
            <?php endif; ?>
            <?php if ($setting_group_tab != 'userlist-test') : ?>
                <form method="post" action="options.php" class="helperbox-setting-form-<?php echo esc_attr($setting_group_tab); ?>">
                    <div id="poststuff">
                        <div id="post-body" class="metabox-holder columns-2">
                            <div id="post-body-content">
                                <?php
                                do_meta_boxes('helperbox_settings_page', 'normal', null);
                                submit_button();
                                ?>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Render settings box with tab navigation.
     *
     * Displays the tab navigation and renders the appropriate settings panel based on active tab.
     *
     * @since 1.0.0
     * @return void
     */
    public function callback_render_metabox_helperbox_general_settings() {
        $setting_group_tab = sanitize_text_field($_GET['tab'] ?? 'general');
        $tabs = [
            'general'    => __('General', 'helperbox'),
            'breadcrumb' => __('Breadcrumb', 'helperbox'),
            'adminlogin' => __('Admin login', 'helperbox'),
            'security'   => __('Security', 'helperbox'),
            'log'        => __('Log', 'helperbox'),
        ]; ?>
        <h3 class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_value => $tab_name) : ?>
                <a href="?page=helperbox&tab=<?php echo esc_attr($tab_value); ?>"
                    class="nav-tab <?php echo ($setting_group_tab === $tab_value) ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_name); ?>
                </a>
            <?php endforeach; ?>
        </h3>
<?php
        // 
        switch ($setting_group_tab):
            case 'general':
                self::render_general_settings_fields();
                break;
            case 'breadcrumb':
                Breadcrumb::render_settings_fields();
                break;
            case 'adminlogin':
                self::render_adminlogin_settings_fields();
                break;
            case 'security':
                Security_Admin_Settings::render_settings_fields();
                break;
            case 'log':
                Security_Admin_Settings::render_security_log_page();
                break;
            default:
                self::render_general_settings_fields();
        endswitch;
    }

    /**
     * Handle .htaccess updates when PHP execution setting changes.
     *
     * Runs only when 'helperbox_disable_phpexecution_upload_dir' option is updated.
     * Adds or removes PHP execution denial rules in uploads/.htaccess file.
     *
     * @since 1.0.0
     *
     * @param mixed $old_value The old option value.
     * @param mixed $new_value The new option value.
     * @return void
     *
     * @see update_option()
     */
    public function update_option_helperbox_disable_phpexecution_upload_dir($old_value, $new_value) {
        if ($old_value === $new_value) {
            return; // Nothing changed.
        }

        $uploads_dir = WP_CONTENT_DIR . '/uploads';
        $htaccess_file = $uploads_dir . '/.htaccess';
        $htaccess_content = <<<HTACCESS
# ---- Start Edit by Custom Helperbox ----
# Disable PHP execution in uploads directory
php_flag engine off
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>
# ---- End Edit by Custom Helperbox ----
HTACCESS;

        // Add .htaccess rules when enabling.
        if (true === $new_value || '1' === $new_value) {
            // Create uploads directory if it doesn't exist.
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }

            // Append or replace rules in .htaccess.
            if (file_exists($htaccess_file)) {
                $current = file_get_contents($htaccess_file);
                // Remove old block if exists.
                $current = preg_replace(
                    '/# ---- Start Edit by Custom Helperbox ----.*# ---- End Edit by Custom Helperbox ----\s*/s',
                    '',
                    $current
                );
                $current .= $htaccess_content . "\n";
                file_put_contents($htaccess_file, $current);
            } else {
                file_put_contents($htaccess_file, $htaccess_content);
            }
        }

        // Remove .htaccess rules when disabling.
        if (false === $new_value || '' === $new_value || '0' === $new_value) {
            if (file_exists($htaccess_file)) {
                $current = file_get_contents($htaccess_file);
                // Remove the custom block.
                $current = preg_replace(
                    '/# ---- Start Edit by Custom Helperbox ----.*# ---- End Edit by Custom Helperbox ----\s*/s',
                    '',
                    $current
                );

                if (trim($current) === '') {
                    // Delete file if empty.
                    unlink($htaccess_file);
                } else {
                    // Save without the custom block.
                    file_put_contents($htaccess_file, $current);
                }
            }
        }
    }
}
