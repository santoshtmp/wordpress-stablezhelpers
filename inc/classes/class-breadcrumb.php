<?php

/**
 * Helperbox Breadcrumb
 *
 * Provides breadcrumb navigation functionality with customizable settings,
 * including exclusion rules and conditional display options.
 *
 * @package       helperbox
 * @subpackage    Breadcrumb
 * @author        santoshtmp
 * @link          https://github.com/santoshtmp/wordpress-custom-helperbox
 * @since         1.0.0
 * @version       1.0.0
 * 
 */

namespace Helperbox_Plugin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Breadcrumb class
 *
 * Handles breadcrumb generation, display, and admin settings.
 *
 * @since 1.0.0
 */
class Breadcrumb {

    /**
     * Default value for breadcrumb feature (disabled by default).
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_BREADCRUMB_FEATURE = 0;

    /**
     * Settings option group name.
     *
     * @since 1.0.0
     * @var string
     */
    const BREADCRUMB_SETTINGS_OPTION_GROUP = 'helperbox_breadcrumb_settings_group';

    /**
     * Excluded post types.
     *
     * @since 1.0.0
     * @var array
     */
    public static $excludePostType = [];

    /**
     * Excluded post slugs.
     *
     * @since 1.0.0
     * @var array
     */
    public static $excludePostSlug = [];

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks for breadcrumb functionality.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_hooks() {
        add_shortcode('helperbox_breadcrumb_shortcode', [$this, 'get_helperbox_breadcrumb_shortcode_output']);
    }

    /**
     * Check if breadcrumb feature is active.
     *
     * @since 1.0.0
     * @return bool True if breadcrumb feature is enabled, false otherwise.
     */
    public static function is_breadcrumb_feature_active() {
        $breadcrumb_feature = get_option('helperbox_breadcrumb_feature', self::DEFAULT_BREADCRUMB_FEATURE);
        return $breadcrumb_feature === '1';
    }

    /**
     * Get excluded post types from settings.
     *
     * @since 1.0.0
     * @return array Array of excluded post type names.
     */
    public static function get_exclude_post_type() {
        if (empty(self::$excludePostType)) {
            self::$excludePostType = get_option('helperbox_breadcrumb_exclude_post_type', []);
            self::$excludePostType = is_array(self::$excludePostType) ? self::$excludePostType : [];
        }
        return self::$excludePostType;
    }

    /**
     * Get excluded post slugs from settings.
     *
     * @since 1.0.0
     * @return array Array of excluded post slugs.
     */
    public static function get_exclude_post_slug() {
        $value = get_option('helperbox_breadcrumb_exclude_post_slug', '');
        self::$excludePostSlug = array_values(array_filter(array_map('trim', explode("\n", $value))));
        return self::$excludePostSlug;
    }

    /**
     * Get breadcrumb array values for the current page.
     *
     * Generates an array of breadcrumb items based on the current page context
     * (singular, archive, search). Applies exclusion rules and custom conditions.
     *
     * @since 1.0.0
     *
     * @param array $removeCondition Optional. Conditions to remove specific breadcrumbs.
     *                               Accepts 'post_type' and 'url' keys with conditional rules.
     * @return array|null Array of breadcrumb items or null if breadcrumbs are disabled/hidden.
     *
     * @example
     * // Remove breadcrumb for specific post type with meta condition
     * $removeCondition = [
     *     'post_type' => [
     *         'region' => [
     *             [
     *                 'meta_key' => 'type_of_region',
     *                 'meta_value' => 'country',
     *             ]
     *         ]
     *     ],
     *     'url' => ['/url1', '/url2']
     * ];
     */
    public static function helperbox_breadcrumb_value($removeCondition = []) {
        // Check if feature is enabled.
        if (!self::is_breadcrumb_feature_active()) {
            return null;
        }

        // Don't show breadcrumb on front page.
        if (is_front_page()) {
            return null;
        }

        $post_type = get_post_type();
        if (!$post_type) {
            return null;
        }

        // Get remove condition from settings.
        $breadcrumb_remove_condition = get_option('helperbox_breadcrumb_remove_condition', '');
        if (!empty($breadcrumb_remove_condition)) {
            $decoded = json_decode($breadcrumb_remove_condition, true);
            if (is_array($decoded) && !empty($decoded)) {
                $removeCondition = array_merge($removeCondition, $decoded);
            }
        }

        // Check remove conditions.
        if (!empty($removeCondition)) {
            // Check post type based removal.
            if (isset($removeCondition['post_type']) && is_array($removeCondition['post_type'])) {
                $post_type_conditions = $removeCondition['post_type'];
                if (isset($post_type_conditions[$post_type]) && is_array($post_type_conditions[$post_type])) {
                    foreach ($post_type_conditions[$post_type] as $condition) {
                        if (is_array($condition) && isset($condition['meta_key'], $condition['meta_value'])) {
                            $meta_key = $condition['meta_key'];
                            $meta_value = $condition['meta_value'];
                            $get_meta_value = get_post_meta(get_the_ID(), $meta_key, true);
                            if ($get_meta_value == $meta_value) {
                                return null;
                            }
                        }
                    }
                }
            }

            // URL based removal.
            if (isset($removeCondition['url']) && is_array($removeCondition['url']) && !empty($removeCondition['url'])) {
                $current_url = home_url(add_query_arg(null, null));
                $current_path = wp_parse_url($current_url, PHP_URL_PATH);
                $check_urls = [
                    $current_path,
                    $current_url,
                    home_url() . $current_path,
                ];
                if (array_intersect($check_urls, $removeCondition['url'])) {
                    return null;
                }
            }
        }

        // breadcrumbs values
        $breadcrumbs = [
            [
                'title' => __('Home', 'helperbox'),
                'url'   => home_url(),
                'slug'  => '/',
                'type'  => 'home',
            ],
        ];

        if (is_singular()) {
            if (!in_array($post_type, self::get_exclude_post_type(), true)) {
                $post_type_object = get_post_type_object($post_type);
                $archive_link = get_post_type_archive_link($post_type);
                $this_post_type_breadcrumb = [
                    'title' => $post_type_object ? $post_type_object->label : $post_type,
                    'url'   => $archive_link ?: '#',
                    'slug'  => $post_type,
                    'type'  => 'archive',
                ];
                $breadcrumbs[] = $this_post_type_breadcrumb;
            }

            $parent_present = true;
            $child_post_id = get_the_ID();
            $child_breadcrumb = [];

            while ($parent_present) {
                $parent_id = wp_get_post_parent_id($child_post_id);
                if ($parent_id && $parent_id > 0) {
                    $child_post_id = $parent_id;
                    $slug = get_post_field('post_name', $child_post_id);
                    $this_breadcrumb = [
                        'title' => get_the_title($child_post_id),
                        'url'   => get_permalink($child_post_id),
                        'slug'  => $slug,
                        'type'  => 'singular',
                    ];
                    if (!in_array($slug, self::get_exclude_post_slug(), true)) {
                        $child_breadcrumb[] = $this_breadcrumb;
                    }
                } else {
                    $parent_present = false;
                    $current_post_id = get_the_ID();
                    $slug = get_post_field('post_name', $current_post_id);
                    $this_breadcrumb = [
                        'title' => get_the_title($current_post_id),
                        'url'   => get_permalink($current_post_id),
                        'slug'  => $slug,
                        'type'  => 'singular',
                    ];
                    if (!in_array($slug, self::get_exclude_post_slug(), true)) {
                        $child_breadcrumb[] = $this_breadcrumb;
                    }
                }
            }

            $breadcrumbs = array_merge($breadcrumbs, $child_breadcrumb);
        } elseif (is_archive()) {
            $queried_object = get_queried_object();
            if (is_tax()) {
                $post_type_object = get_post_type_object($post_type);
                $archive_link = get_post_type_archive_link($post_type);
                $this_post_type_breadcrumb = [
                    'title' => $post_type_object ? $post_type_object->label : $post_type,
                    'url'   => $archive_link ?: '#',
                    'slug'  => $post_type,
                    'type'  => 'tax',
                ];
                $breadcrumbs[] = $this_post_type_breadcrumb;
                $breadcrumbs[] = [
                    'title' => $queried_object ? $queried_object->name : '',
                    'type'  => 'tax_term',
                ];
            } elseif ($queried_object && isset($queried_object->name)) {
                $post_type = $queried_object->name;
                $archive_link = get_post_type_archive_link($post_type);
                $this_breadcrumb = [
                    'title' => $queried_object->label ?? $post_type,
                    'url'   => $archive_link ?: '#',
                    'slug'  => $post_type,
                    'type'  => 'archive',
                ];
                $breadcrumbs[] = $this_breadcrumb;
            }
        } elseif (is_search()) {
            $this_breadcrumb = [
                'title' => sprintf(__('Search Results for: %s', 'helperbox'), get_search_query()),
                'url'   => get_search_link(),
                'slug'  => '/?s',
                'type'  => 'search',
            ];
            $breadcrumbs[] = $this_breadcrumb;
        }

        return $breadcrumbs;
    }

    /**
     * Get breadcrumb HTML content.
     *
     * Generates and returns the HTML markup for breadcrumb navigation.
     * Uses output buffering to capture the HTML template.
     *
     * @since 1.0.0
     *
     * @param array $removeCondition Optional. Conditions to remove specific breadcrumbs.
     * @return string HTML markup for breadcrumb navigation. Empty string if no breadcrumbs.
     *
     * @example
     * // Display breadcrumbs
     * echo Breadcrumb::get_helperbox_breadcrumb();
     *
     * // Display breadcrumbs with custom removal conditions
     * echo Breadcrumb::get_helperbox_breadcrumb([
     *     'url' => ['/special-page']
     * ]);
     */
    public static function get_helperbox_breadcrumb($removeCondition = []) {
        // Get breadcrumb array value.
        $breadcrumbs = self::helperbox_breadcrumb_value($removeCondition);
        if (empty($breadcrumbs) || !is_array($breadcrumbs)) {
            return '';
        }

        ob_start(); ?>
        <div class="breadcrumb">
            <ul>
                <?php
                $last_key = count($breadcrumbs) - 1;
                foreach ($breadcrumbs as $key => $breadcrumb) {
                    $is_last = ($key === $last_key);
                    $title = isset($breadcrumb['title']) ? esc_html($breadcrumb['title']) : '';
                    $url = isset($breadcrumb['url']) ? esc_url($breadcrumb['url']) : '#';
                ?>
                    <li class="breadcrumb-item<?php echo $is_last ? ' active' : ''; ?>">
                        <?php if ($is_last) : ?>
                            <?php echo $title; ?>
                        <?php else : ?>
                            <a href="<?php echo $url; ?>"><?php echo $title; ?></a>
                        <?php endif; ?>
                    </li>
                <?php } ?>
            </ul>
        </div>
    <?php
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Shortcode handler for displaying breadcrumbs.
     *
     * @since 1.0.0
     * @return string HTML markup for breadcrumb navigation.
     *
     * @example
     * // Usage in post/page content
     * [helperbox_breadcrumb_shortcode]
     *
     * // Usage in PHP template
     * echo do_shortcode('[helperbox_breadcrumb_shortcode]');
     */
    public function get_helperbox_breadcrumb_shortcode_output() {
        return self::get_helperbox_breadcrumb();
    }

    /**
     * Register breadcrumb settings and options fields.
     *
     * Registers all WordPress settings for breadcrumb functionality including:
     * - Feature toggle
     * - Excluded post types
     * - Excluded post slugs
     * - Conditional removal rules (JSON-based)
     *
     * @since 1.0.0
     * @return void
     *
     * @see register_setting()
     */
    public static function register_setting_options_fields() {

        // Breadcrumb feature toggle setting.
        register_setting(
            self::BREADCRUMB_SETTINGS_OPTION_GROUP,
            'helperbox_breadcrumb_feature',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            ]
        );

        // Excluded post types setting.
        register_setting(
            self::BREADCRUMB_SETTINGS_OPTION_GROUP,
            'helperbox_breadcrumb_exclude_post_type',
            [
                'type'              => 'array',
                'sanitize_callback' => [admin\Settings::class, 'sanitize_callback_helperbox_array_text_field'],
                'default'           => [],
            ]
        );

        // Excluded post slugs setting.
        register_setting(
            self::BREADCRUMB_SETTINGS_OPTION_GROUP,
            'helperbox_breadcrumb_exclude_post_slug',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            ]
        );

        // Conditional removal rules setting.
        register_setting(
            self::BREADCRUMB_SETTINGS_OPTION_GROUP,
            'helperbox_breadcrumb_remove_condition',
            [
                'type' => 'string',
                'sanitize_callback' => function ($value) {
                    if (empty($value)) {
                        return '';
                    }

                    $decoded = json_decode(trim($value), true);

                    // Invalid JSON → do not save.
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        add_settings_error(
                            'helperbox_breadcrumb_remove_condition',
                            'invalid_json',
                            __('Invalid JSON format. Breadcrumb condition was not saved.', 'helperbox')
                        );
                        return '';
                    }

                    // Re-encode to normalize formatting.
                    return wp_json_encode($decoded);
                },
                'default' => '',
            ]
        );
    }

    /**
     * Render breadcrumb settings fields in admin.
     *
     * Outputs the HTML form fields for breadcrumb configuration in the WordPress admin.
     * Includes feature toggle, post type exclusions, slug exclusions, and JSON-based conditions.
     *
     * @since 1.0.0
     * @return void
     *
     * @see settings_fields()
     */
    public static function render_settings_fields() {
        settings_fields(self::BREADCRUMB_SETTINGS_OPTION_GROUP);
        $breadcrumb_feature = get_option('helperbox_breadcrumb_feature', self::DEFAULT_BREADCRUMB_FEATURE); ?>
        <table class="form-table form-table-breadcrumb" table-tab="breadcrumb">
            <tr>
                <th scope="row">
                    <label for="helperbox_breadcrumb_feature">
                        <?php esc_html_e('Activate Helperbox Breadcrumb', 'helperbox'); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="checkbox"
                        name="helperbox_breadcrumb_feature"
                        id="helperbox_breadcrumb_feature"
                        value="1"
                        <?php checked($breadcrumb_feature, '1'); ?>>
                    <div class="description">
                        <p><?php esc_html_e('Default: unchecked', 'helperbox'); ?></p>
                    </div>
                </td>
            </tr>
            <?php if (self::is_breadcrumb_feature_active()) : ?>
                <tr>
                    <th scope="row">
                        <label for="helperbox_breadcrumb_exclude_post_type">
                            <?php esc_html_e('Exclude post type', 'helperbox'); ?>
                        </label>
                    </th>
                    <td>
                        <?php
                        $option = get_option('helperbox_breadcrumb_exclude_post_type', []);
                        $option = is_array($option) ? $option : [];

                        $post_types = get_post_types(['public' => true], 'objects');
                        unset($post_types['attachment']);
                        foreach ($post_types as $post_type) :
                        ?>
                            <label for="post-type-<?php echo esc_attr($post_type->name); ?>">
                                <input
                                    type="checkbox"
                                    name="helperbox_breadcrumb_exclude_post_type[]"
                                    id="post-type-<?php echo esc_attr($post_type->name); ?>"
                                    value="<?php echo esc_attr($post_type->name); ?>"
                                    <?php checked(in_array($post_type->name, $option, true)); ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </label>
                        <?php endforeach; ?>

                        <div class="description">
                            <p><?php esc_html_e('This will exclude the selected post type from breadcrumbs list.', 'helperbox'); ?></p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="helperbox_breadcrumb_exclude_post_slug">
                            <?php esc_html_e('Exclude Post Items', 'helperbox'); ?>
                        </label>
                    </th>
                    <td>
                        <textarea
                            id="helperbox_breadcrumb_exclude_post_slug"
                            name="helperbox_breadcrumb_exclude_post_slug"
                            rows="5"
                            cols="50"
                            class="regular-text"><?php echo esc_textarea(get_option('helperbox_breadcrumb_exclude_post_slug', '')); ?></textarea>
                        <div class="description">
                            <p><?php esc_html_e('This will exclude the selected post item slugs from breadcrumbs list. Enter one post slug per line.', 'helperbox'); ?></p>
                            <p>
                                <?php esc_html_e('Example value:', 'helperbox'); ?>
                                <?php echo '<pre>regional-overview' . "\n" . 'digital-id-region</pre>'; ?>
                            </p>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="helperbox_breadcrumb_remove_condition">
                            <?php esc_html_e('Remove breadcrumb based on given conditions.', 'helperbox'); ?>
                        </label>
                    </th>
                    <td>
                        <?php
                        $breadcrumb_remove_condition = get_option('helperbox_breadcrumb_remove_condition', '');
                        $placeholderRemoveCondition = [
                            'url' => [
                                '/url/path...'
                            ],
                            'post_type' => [
                                'post_type_to_check...' => [
                                    [
                                        'meta_key' => 'meta_key_to_check...',
                                        'meta_value' => 'meta_value_to_check...',
                                    ]
                                ]
                            ]
                        ];
                        $exampleValue = [
                            'post_type' => [
                                'region' => [
                                    [
                                        'meta_key' => 'type_of_region',
                                        'meta_value' => 'region',
                                    ],
                                    [
                                        'meta_key' => 'type_of_region',
                                        'meta_value' => 'sub-region',
                                    ]
                                ],
                                'digital-id' => [
                                    [
                                        'meta_key' => 'type_of_region',
                                        'meta_value' => 'region',
                                    ],
                                    [
                                        'meta_key' => 'type_of_region',
                                        'meta_value' => 'sub-region',
                                    ]
                                ]
                            ]
                        ];
                        ?>
                        <textarea
                            id="helperbox_breadcrumb_remove_condition"
                            name="helperbox_breadcrumb_remove_condition"
                            style="display: none;">
                            <?php echo esc_textarea(trim($breadcrumb_remove_condition)); ?>
                        </textarea>
                        <div id="helperbox_breadcrumb_remove_condition_editor"><?php echo esc_textarea(trim($breadcrumb_remove_condition)); ?></div>
                        <br>
                        <p class="button" id="set_jsonformat_remove_condition"><?php esc_html_e('Format JSON value', 'helperbox'); ?></p>
                        <div class="description">
                            <p><?php esc_html_e('This will exclude/remove breadcrumb item based on the given conditions pages.', 'helperbox'); ?></p>
                            <p><?php esc_html_e('Enter conditions in valid JSON format as:', 'helperbox'); ?></p>
                            <pre><?php echo esc_html(json_encode($placeholderRemoveCondition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                            <p><?php esc_html_e('Example value:'); ?></p>
                            <pre><?php echo esc_html(json_encode($exampleValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>

                            <p><?php esc_html_e('To display breadcrumb:', 'helperbox'); ?></p>
                            <ol>
                                <li>
                                    <?php esc_html_e('Using shortcode', 'helperbox'); ?>
                                    <pre>[helperbox_breadcrumb_shortcode]</pre>
                                </li>
                                <li>
                                    <?php esc_html_e('Using PHP function', 'helperbox'); ?>
                                    <pre>\Helperbox_Plugin\Breadcrumb::get_helperbox_breadcrumb();</pre>
                                </li>
                            </ol>
                        </div>

                    </td>
                </tr>
            <?php endif; ?>
        </table>
<?php
    }
}
