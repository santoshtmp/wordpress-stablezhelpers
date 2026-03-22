<?php

/**
 * Moodle Post Types and Taxonomies Registration
 *
 * Handles registration of custom post types and taxonomies for Moodle integration.
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
// MOODLE COURSE POST TYPES CLASS
// =============================================================================

if (!class_exists(__NAMESPACE__ . '\\Moodle_Course_Post_Type')) {
    /**
     * Moodle Course Post Type Registration Class
     *
     * Singleton class that handles registration of the Moodle course
     * post type and its associated taxonomy.
     *
     * @since 1.0.0
     */
    class Moodle_Course_Post_Type {

        /**
         * Single instance of the class.
         *
         * @since 1.0.0
         * @var Moodle_Course_Post_Type
         */
        private static $instance = null;

        /**
         * Get instance of the class.
         *
         * @since 1.0.0
         * @return Moodle_Course_Post_Type
         */
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor - private to prevent direct instantiation.
         *
         * @since 1.0.0
         */
        private function __construct() {
            $this->register();
        }

        /**
         * Register all hooks for post types and taxonomies.
         *
         * @since 1.0.0
         */
        private function register() {
            add_action('init', [$this, 'register_post_type_mdl_course']);
            add_action('init', [$this, 'register_taxonomy_mdl_course_category']);

            // Admin columns for mdl_course
            add_filter('manage_mdl_course_posts_columns', [$this, 'mdl_course_custom_columns']);
            add_action('manage_mdl_course_posts_custom_column', [$this, 'mdl_course_custom_column_content'], 10, 2);

            // Admin columns for mdl_course_category
            add_filter('manage_edit-mdl_course_category_columns', [$this, 'mdl_course_category_custom_columns']);
            add_filter('manage_mdl_course_category_custom_column', [$this, 'mdl_course_category_custom_column_content'], 10, 3);
        }

        /**
         * Post Type: Courses :: mdl_course.
         *
         * Registers the custom post type for Moodle courses.
         *
         * @since 1.0.0
         */
        public function register_post_type_mdl_course() {
            $labels = [
                "name" => esc_html__("Courses", "helperbox"),
                "singular_name" => esc_html__("Course", "helperbox"),
                "menu_name" => esc_html__("Moodle Integration", "helperbox"),
                "all_items" => esc_html__("Courses", "helperbox"),
                "add_new" => esc_html__("Add New", "helperbox"),
                "add_new_item" => esc_html__("Add New Course", "helperbox"),
                "edit_item" => esc_html__("Edit Course", "helperbox"),
                "new_item" => esc_html__("New Course", "helperbox"),
                "view_item" => esc_html__("View Course", "helperbox"),
                "view_items" => esc_html__("View Courses", "helperbox"),
                "search_items" => esc_html__("Search Courses", "helperbox"),
                "not_found" => esc_html__("No courses found", "helperbox"),
                "not_found_in_trash" => esc_html__("No courses found in trash", "helperbox"),
                "featured_image" => esc_html__("Course Image", "helperbox"),
                "set_featured_image" => esc_html__("Set course image", "helperbox"),
                "remove_featured_image" => esc_html__("Remove course image", "helperbox"),
                "use_featured_image" => esc_html__("Use as course image", "helperbox"),
                "items_list" => esc_html__("Courses list", "helperbox"),
                "items_list_navigation" => esc_html__("Courses list navigation", "helperbox"),
                "filter_items_list" => esc_html__("Filter courses list", "helperbox"),
                "parent_item_colon" => esc_html__("Parent Course:", "helperbox"),
                "archives" => esc_html__("Course Archives", "helperbox"),
                "attributes" => esc_html__("Course Attributes", "helperbox"),
                "insert_into_item" => esc_html__("Insert into course", "helperbox"),
                "uploaded_to_this_item" => esc_html__("Uploaded to this course", "helperbox"),
                "item_published" => esc_html__("Course published", "helperbox"),
                "item_published_privately" => esc_html__("Course published privately", "helperbox"),
                "item_reverted_to_draft" => esc_html__("Course reverted to draft", "helperbox"),
                "item_scheduled" => esc_html__("Course scheduled", "helperbox"),
                "item_updated" => esc_html__("Course updated", "helperbox"),
                "item_link" => esc_html__("Course Link", "helperbox"),
                "item_link_description" => esc_html__("A link to a course", "helperbox"),
            ];

            $args = [
                "label" => esc_html__("Courses", "helperbox"),
                "labels" => $labels,
                "description" => esc_html__("Moodle courses integration", "helperbox"),
                "public" => true,
                "publicly_queryable" => true,
                "show_ui" => true,
                "show_in_rest" => true,
                "rest_base" => "mdl_courses",
                "rest_controller_class" => "WP_REST_Posts_Controller",
                "rest_namespace" => "wp/v2",
                "has_archive" => true,
                "show_in_menu" => true,
                "show_in_nav_menus" => true,
                "show_in_admin_bar" => true,
                "delete_with_user" => false,
                "exclude_from_search" => false,
                "capability_type" => "post",
                "map_meta_cap" => true,
                "hierarchical" => false,
                "can_export" => true,
                "rewrite" => [
                    "slug" => "mdl-course",
                    "with_front" => true,
                    "feeds" => true,
                    "pages" => true,
                ],
                "query_var" => true,
                "menu_position" => 20,
                "menu_icon" => "dashicons-welcome-learn-more",
                "supports" => [],
                "show_in_graphql" => false,
                "can_import" => false,
                "capabilities" => [
                    "create_posts" => "do_not_allow",
                    "edit_posts" => "edit_posts",
                    "edit_post" => "do_not_allow",
                    "delete_posts" => "do_not_allow",
                    "delete_post" => "do_not_allow",
                    "publish_posts" => "do_not_allow",
                    "read_post" => "read",
                    "read_private_posts" => "read_private_posts",
                ],
            ];

            register_post_type("mdl_course", $args);
        }

        /**
         * Taxonomy: Course categories :: mdl_course_category.
         *
         * Registers the custom taxonomy for Moodle course categories.
         *
         * @since 1.0.0
         */
        public function register_taxonomy_mdl_course_category() {

            $labels = [
                "name" => esc_html__("Course Categories", "helperbox"),
                "singular_name" => esc_html__("Course Category", "helperbox"),
                "menu_name" => esc_html__("Course Categories", "helperbox"),
                "all_items" => esc_html__("All Course Categories", "helperbox"),
                "edit_item" => esc_html__("Edit Course Category", "helperbox"),
                "view_item" => esc_html__("View Course Category", "helperbox"),
                "update_item" => esc_html__("Update Course Category", "helperbox"),
                "add_new_item" => esc_html__("Add New Course Category", "helperbox"),
                "new_item_name" => esc_html__("New Course Category Name", "helperbox"),
                "parent_item" => esc_html__("Parent Course Category", "helperbox"),
                "parent_item_colon" => esc_html__("Parent Course Category:", "helperbox"),
                "search_items" => esc_html__("Search Course Categories", "helperbox"),
                "popular_items" => esc_html__("Popular Course Categories", "helperbox"),
                "separate_items_with_commas" => esc_html__("Separate course categories with commas", "helperbox"),
                "add_or_remove_items" => esc_html__("Add or remove course categories", "helperbox"),
                "choose_from_most_used" => esc_html__("Choose from the most used course categories", "helperbox"),
                "not_found" => esc_html__("No course categories found", "helperbox"),
                "no_terms" => esc_html__("No course categories", "helperbox"),
                "items_list_navigation" => esc_html__("Course categories list navigation", "helperbox"),
                "items_list" => esc_html__("Course categories list", "helperbox"),
                "most_used" => esc_html__("Most Used", "helperbox"),
                "back_to_items" => esc_html__("← Back to Course Categories", "helperbox"),
                "item_link" => esc_html__("Course Category Link", "helperbox"),
                "item_link_description" => esc_html__("A link to a course category", "helperbox"),
            ];

            $args = [
                "label" => esc_html__("Course Categories", "helperbox"),
                "labels" => $labels,
                "public" => true,
                "publicly_queryable" => true,
                "hierarchical" => true,
                "show_ui" => true,
                "show_in_menu" => true,
                "show_in_nav_menus" => true,
                "query_var" => true,
                "rewrite" => [
                    'slug' => 'mdl-course-category',
                    'with_front' => true,
                ],
                "show_admin_column" => true,
                "show_in_rest" => true,
                "show_tagcloud" => true,
                "rest_base" => "mdl_course_categories",
                "rest_controller_class" => "WP_REST_Terms_Controller",
                "rest_namespace" => "wp/v2",
                "show_in_quick_edit" => false,
                "sort" => true,
                "show_in_graphql" => false,
                "capabilities" => [
                    "manage_terms" => "manage_categories",
                    "edit_terms" => "do_not_allow",
                    "delete_terms" => "do_not_allow",
                    "assign_terms" => "do_not_allow",
                ],
            ];
            register_taxonomy("mdl_course_category", ["mdl_course"], $args);
        }

        /**
         * Register custom columns for mdl_course post type list table.
         *
         * @since 1.0.0
         * @param array $columns Existing columns.
         * @return array Modified columns.
         */
        public function mdl_course_custom_columns($columns) {
            $new_columns = [];

            foreach ($columns as $key => $value) {
                $new_columns[$key] = $value;

                // Insert after title column
                if ('title' === $key) {
                    $new_columns['moodle_course_id'] = esc_html__('Moodle Course ID', 'helperbox');
                    $new_columns['moodle_url'] = esc_html__('Moodle URL', 'helperbox');
                }
            }

            return $new_columns;
        }

        /**
         * Populate custom columns for mdl_course post type.
         *
         * @since 1.0.0
         * @param string $column  Column name.
         * @param int    $post_id Post ID.
         */
        public function mdl_course_custom_column_content($column, $post_id) {
            switch ($column) {
                case 'moodle_course_id':
                    $moodle_id = get_post_meta($post_id, 'moodle_course_id', true);
                    echo $moodle_id ? esc_html($moodle_id) : '—';
                    break;

                case 'moodle_url':
                    $moodle_url = get_post_meta($post_id, 'moodle_url', true);
                    if ($moodle_url) {
                        echo '<a href="' . esc_url($moodle_url) . '" target="_blank" rel="noopener">';
                        echo esc_html__('View in Moodle', 'helperbox');
                        echo '</a>';
                    } else {
                        echo '—';
                    }
                    break;
            }
        }

        /**
         * Register custom columns for mdl_course_category taxonomy list table.
         *
         * @since 1.0.0
         * @param array $columns Existing columns.
         * @return array Modified columns.
         */
        public function mdl_course_category_custom_columns($columns) {
            $new_columns = [];

            foreach ($columns as $key => $value) {
                $new_columns[$key] = $value;

                // Insert after description column
                if ('description' === $key) {
                    $new_columns['moodle_category_id'] = esc_html__('Moodle Category ID', 'helperbox');
                }
            }

            return $new_columns;
        }

        /**
         * Populate custom columns for mdl_course_category taxonomy.
         *
         * @since 1.0.0
         * @param string $content Column content.
         * @param string $column  Column name.
         * @param int    $term_id Term ID.
         * @return string Modified column content.
         */
        public function mdl_course_category_custom_column_content($content, $column, $term_id) {
            if ('moodle_category_id' === $column) {
                $moodle_id = get_term_meta($term_id, 'moodle_category_id', true);
                return $moodle_id ? esc_html($moodle_id) : '—';
            }

            return $content;
        }
    }
}
