<?php
/**
 * Seed Moodle Integration Data
 * 
 * Usage:
 * 1. Via WP-CLI: wp eval-file wp-content/mu-plugins/wordpress-custom-helperbox/scripts/seed-moodle-data.php
 * 2. Or include in functions.php temporarily and load a page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

echo "=== Seeding Moodle Data ===\n\n";

// ============================================
// 1. Create/Update Course Categories (Taxonomy)
// ============================================

$categories = [
    [
        'name'        => 'Technology',
        'slug'        => 'technology',
        'description' => 'Technology and IT related courses',
        'moodle_id'   => '1',
    ],
    [
        'name'        => 'Business',
        'slug'        => 'business',
        'description' => 'Business and management courses',
        'moodle_id'   => '2',
    ],
];

$created_categories = [];

foreach ($categories as $category) {
    // Check if term already exists
    $term = get_term_by('slug', $category['slug'], 'mdl_course_category');
    
    if (!$term) {
        $result = wp_insert_term($category['name'], 'mdl_course_category', [
            'slug'        => $category['slug'],
            'description' => $category['description'],
        ]);
        
        if (is_wp_error($result)) {
            echo "❌ Failed to create category '{$category['name']}': " . $result->get_error_message() . "\n";
        } else {
            $created_categories[] = $result['term_id'];
            // Add moodle_category_id meta
            update_term_meta($result['term_id'], 'moodle_category_id', $category['moodle_id']);
            echo "✅ Created category: {$category['name']} (ID: {$result['term_id']}, Moodle ID: {$category['moodle_id']})\n";
        }
    } else {
        $created_categories[] = $term->term_id;
        
        // Update existing term
        $update_result = wp_update_term($term->term_id, 'mdl_course_category', [
            'name'        => $category['name'],
            'description' => $category['description'],
        ]);
        
        // Update moodle_category_id meta
        update_term_meta($term->term_id, 'moodle_category_id', $category['moodle_id']);
        
        if (is_wp_error($update_result)) {
            echo "⚠️  Category exists: {$category['name']} (ID: {$term->term_id}) - Update skipped\n";
        } else {
            echo "🔄 Updated category: {$category['name']} (ID: {$term->term_id}, Moodle ID: {$category['moodle_id']})\n";
        }
    }
}

echo "\n";

// ============================================
// 2. Create/Update Courses (Post Type)
// ============================================

$courses = [
    [
        'post_title'   => 'Introduction to Python Programming',
        'post_content' => 'Learn Python programming from scratch. This course covers basics to intermediate concepts.',
        'post_status'  => 'publish',
        'post_type'    => 'mdl_course',
        'meta_input'   => [
            'moodle_course_id' => '101',
            'moodle_url'       => 'https://moodle.example.com/course/view.php?id=101',
        ],
        'terms'        => [$created_categories[0] ?? ''], // Technology
    ],
    [
        'post_title'   => 'Project Management Fundamentals',
        'post_content' => 'Master the basics of project management. Learn planning, execution, and monitoring.',
        'post_status'  => 'publish',
        'post_type'    => 'mdl_course',
        'meta_input'   => [
            'moodle_course_id' => '201',
            'moodle_url'       => 'https://moodle.example.com/course/view.php?id=201',
        ],
        'terms'        => [$created_categories[1] ?? ''], // Business
    ],
];

foreach ($courses as $index => $course) {
    // Check if course already exists by title
    $existing = get_posts([
        'post_type'      => 'mdl_course',
        'post_title'     => $course['post_title'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);
    
    if (empty($existing)) {
        $post_id = wp_insert_post($course);
        
        if (is_wp_error($post_id)) {
            echo "❌ Failed to create course '{$course['post_title']}': " . $post_id->get_error_message() . "\n";
        } else {
            // Set terms
            if (!empty($course['terms'][0])) {
                wp_set_object_terms($post_id, $course['terms'], 'mdl_course_category');
            }
            
            echo "✅ Created course: {$course['post_title']} (ID: {$post_id})\n";
        }
    } else {
        $post_id = $existing[0];
        
        // Update existing course
        $course['ID'] = $post_id;
        $update_result = wp_update_post($course, true);
        
        if (is_wp_error($update_result)) {
            echo "⚠️  Course exists: {$course['post_title']} (ID: {$post_id}) - Update failed: " . $update_result->get_error_message() . "\n";
        } else {
            echo "🔄 Updated course: {$course['post_title']} (ID: {$post_id})\n";
        }
        
        // Update terms
        if (!empty($course['terms'][0])) {
            wp_set_object_terms($post_id, $course['terms'], 'mdl_course_category');
        }
        
        // Update meta
        foreach ($course['meta_input'] as $meta_key => $meta_value) {
            update_post_meta($post_id, $meta_key, $meta_value);
        }
    }
}

echo "\n=== Seeding Complete ===\n";

// Flush rewrite rules
flush_rewrite_rules();
echo "✅ Rewrite rules flushed\n";
