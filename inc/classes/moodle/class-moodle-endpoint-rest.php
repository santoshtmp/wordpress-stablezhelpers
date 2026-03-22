<?php

/**
 * Moodle REST API Endpoint Handler
 *
 * Handles REST API requests for Moodle integration features including
 * course creation, user management, and data synchronization.
 *
 * 
 * https://developer.wordpress.org/rest-api/reference/comments/
 * https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
 * 
 * custom api endpoint and slug
 * 1. https://developer.wordpress.org/apis/
 * 2. https://developer.wordpress.org/rest-api/reference/
 * 3. https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
 * 
 *
 * @package Helperbox
 * @subpackage Moodle
 * @since 1.0.0
 * @copyright 2026 https://santoshmagar.com.np/
 * @author santoshmagar.com.np
 */

namespace Helperbox_Plugin\moodle;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// =============================================================================
// MOODLE REST ENDPOINT CLASS
// =============================================================================

if (!class_exists(__NAMESPACE__ . '\\Moodle_Endpoint_Rest')) {
    /**
     * Moodle REST Endpoint Handler Class
     *
     * Singleton class that handles REST API requests for Moodle operations:
     * - Course creation and management
     * - User synchronization endpoints
     * - Course data retrieval
     * - Error handling and response formatting
     *
     * @since 1.0.0
     */
    class Moodle_Endpoint_Rest {

        /**
         * Singleton instance
         *
         * @since 1.0.0
         * @var Moodle_Endpoint_Rest
         */
        private static $instance = null;

        /**
         * REST API namespace.
         *
         * @since 1.0.0
         * @var string
         */
        const NAMESPACE = 'helperbox/v1';

        /**
         * Get singleton instance
         *
         * Ensures only one instance of the class exists.
         *
         * @since 1.0.0
         * @return Moodle_Endpoint_Rest The singleton instance
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
         * Registers REST API routes.
         *
         * @since 1.0.0
         */
        private function __construct() {
            add_action('rest_api_init', [$this, 'register_routes']);
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
         * Prevent unserializing of the instance
         *
         * @since 1.0.0
         * @return void
         */
        public function __wakeup() {
            throw new \Exception('Cannot unserialize singleton');
        }

        /**
         * Register REST API routes
         *
         * Registers all REST API endpoints for Moodle integration.
         *
         * @since 1.0.0
         * @return void
         */
        public function register_routes() {
            // Course creation endpoint
            register_rest_route(
                self::NAMESPACE,
                '/moodle/course/create',
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_course'],
                    'permission_callback' => [$this, 'check_admin_permissions'],
                    'args'                => $this->get_create_course_args(),
                ]
            );

            // Get courses endpoint
            register_rest_route(
                self::NAMESPACE,
                '/moodle/courses',
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_courses'],
                    'permission_callback' => [$this, 'check_admin_permissions'],
                    'args'                => [
                        'category_id' => [
                            'description' => __('Filter by category ID.', 'helperbox'),
                            'type'        => 'integer',
                            'required'    => false,
                            'minimum'     => 0,
                        ],
                    ],
                ]
            );

            // Get course by ID endpoint
            register_rest_route(
                self::NAMESPACE,
                '/moodle/course/(?P<course_id>\d+)',
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_course'],
                    'permission_callback' => [$this, 'check_admin_permissions'],
                    'args'                => [
                        'course_id' => [
                            'description' => __('Course ID.', 'helperbox'),
                            'type'        => 'integer',
                            'required'    => true,
                        ],
                    ],
                ]
            );

            // Update course endpoint
            register_rest_route(
                self::NAMESPACE,
                '/moodle/course/(?P<course_id>\d+)',
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_course'],
                    'permission_callback' => [$this, 'check_admin_permissions'],
                    'args'                => $this->get_update_course_args(),
                ]
            );

            // Delete course endpoint
            register_rest_route(
                self::NAMESPACE,
                '/moodle/course/(?P<course_id>\d+)',
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_course'],
                    'permission_callback' => [$this, 'check_admin_permissions'],
                    'args'                => [
                        'course_id' => [
                            'description' => __('Course ID.', 'helperbox'),
                            'type'        => 'integer',
                            'required'    => true,
                        ],
                    ],
                ]
            );

            // Get user courses endpoint
            register_rest_route(
                self::NAMESPACE,
                '/moodle/user/(?P<user_id>\d+)/courses',
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_user_courses'],
                    'permission_callback' => [$this, 'check_admin_permissions'],
                    'args'                => [
                        'user_id' => [
                            'description' => __('WordPress user ID.', 'helperbox'),
                            'type'        => 'integer',
                            'required'    => true,
                            'minimum'     => 1,
                        ],
                    ],
                ]
            );

            // Sync user endpoint
            register_rest_route(
                self::NAMESPACE,
                '/moodle/user/sync',
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'sync_user'],
                    'permission_callback' => [$this, 'check_admin_permissions'],
                    'args'                => [
                        'user_id' => [
                            'description' => __('WordPress user ID to sync.', 'helperbox'),
                            'type'        => 'integer',
                            'required'    => true,
                            'minimum'     => 1,
                        ],
                    ],
                ]
            );
        }

        /**
         * Get course creation arguments
         *
         * Defines the accepted parameters for course creation.
         *
         * @since 1.0.0
         * @return array Argument definitions
         */
        private function get_create_course_args() {
            return [
                'shortname' => [
                    'description'       => __('Course short name (required).', 'helperbox'),
                    'type'              => 'string',
                    'required'          => true,
                    'minLength'         => 1,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'fullname' => [
                    'description'       => __('Course full name (required).', 'helperbox'),
                    'type'              => 'string',
                    'required'          => true,
                    'minLength'         => 1,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'summary' => [
                    'description'       => __('Course summary/description.', 'helperbox'),
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'wp_kses_post',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'summaryformat' => [
                    'description'       => __('Summary format (1=HTML, 0=plain text).', 'helperbox'),
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 1,
                    'enum'              => [0, 1],
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'category' => [
                    'description'       => __('Course category ID.', 'helperbox'),
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 1,
                    'minimum'           => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'startdate' => [
                    'description'       => __('Course start date (Unix timestamp).', 'helperbox'),
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 0,
                    'minimum'           => 0,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'enddate' => [
                    'description'       => __('Course end date (Unix timestamp).', 'helperbox'),
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 0,
                    'minimum'           => 0,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'numsections' => [
                    'description'       => __('Number of sections/weeks.', 'helperbox'),
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 10,
                    'minimum'           => 0,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'format' => [
                    'description'       => __('Course format (weeks, topics, etc).', 'helperbox'),
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => 'weeks',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'visible' => [
                    'description'       => __('Course visibility (1=visible, 0=hidden).', 'helperbox'),
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 1,
                    'enum'              => [0, 1],
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'enablecompletion' => [
                    'description'       => __('Enable completion tracking (1=yes, 0=no).', 'helperbox'),
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 0,
                    'enum'              => [0, 1],
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
            ];
        }

        /**
         * Get course update arguments
         *
         * Defines the accepted parameters for course updates.
         *
         * @since 1.0.0
         * @return array Argument definitions
         */
        private function get_update_course_args() {
            $args = $this->get_create_course_args();
            // Make all fields optional for updates
            foreach ($args as $key => &$value) {
                $value['required'] = false;
            }
            // Remove course_id as it's in the URL
            unset($args['course_id']);
            return $args;
        }

        /**
         * Check admin permissions
         *
         * Verifies that the current user has administrator privileges.
         *
         * @since 1.0.0
         * @return bool True if user has permission, false otherwise
         */
        public function check_admin_permissions() {
            return current_user_can('manage_options');
        }

        /**
         * Send JSON response
         *
         * Formats and outputs a JSON response for REST API requests.
         *
         * @since 1.0.0
         *
         * @param bool   $status  Response status
         * @param mixed  $data    Response data
         * @param string $message Response message
         * @param int    $code    HTTP status code
         * @return WP_REST_Response
         */
        private function send_response($status, $data = null, $message = '', $code = 200) {
            return new WP_REST_Response(
                [
                    'success' => $status,
                    'data'    => $data,
                    'message' => $message,
                ],
                $code
            );
        }

        /**
         * REST API handler: Create a course in Moodle
         *
         * Creates a new course in Moodle via WebService API.
         *
         * @since 1.0.0
         *
         * @param WP_REST_Request $request The REST request object
         * @return WP_REST_Response|WP_Error
         */
        public function create_course(WP_REST_Request $request) {
            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                return $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'), 503);
            }

            // Get course parameters (already sanitized by sanitize_callback in args)
            $params = [
                'courses' => [
                    [
                        'shortname'        => $request->get_param('shortname'),
                        'fullname'         => $request->get_param('fullname'),
                        'summary'          => $request->get_param('summary') ?? '',
                        'summaryformat'    => $request->get_param('summaryformat') ?? 1,
                        'category'         => $request->get_param('category') ?? 1,
                        'startdate'        => $request->get_param('startdate') ?? 0,
                        'enddate'          => $request->get_param('enddate') ?? 0,
                        'numsections'      => $request->get_param('numsections') ?? 10,
                        'format'           => $request->get_param('format') ?? 'weeks',
                        'visible'          => $request->get_param('visible') ?? 1,
                        'enablecompletion' => $request->get_param('enablecompletion') ?? 0,
                    ],
                ],
            ];

            // Call Moodle WebService
            $response = $moodle->request_webservice('core_course_create_courses', $params, WP_REST_Server::CREATABLE);

            // Check for errors
            if (isset($response['exception'])) {
                return $this->send_response(
                    false,
                    [],
                    $response['message'] ?? __('Failed to create course.', 'helperbox'),
                    500
                );
            }

            // Check for warnings
            if (isset($response['warnings']) && !empty($response['warnings'])) {
                $warning_messages = array_map(function ($warning) {
                    return $warning['message'] ?? 'Unknown warning';
                }, $response['warnings']);

                return $this->send_response(
                    true,
                    $response,
                    __('Course created with warnings: ', 'helperbox') . implode(', ', $warning_messages),
                    201
                );
            }

            return $this->send_response(
                true,
                $response,
                __('Course created successfully.', 'helperbox'),
                201
            );
        }

        /**
         * REST API handler: Get courses from Moodle
         *
         * Retrieves course list from Moodle via WebService API.
         *
         * @since 1.0.0
         *
         * @param WP_REST_Request $request The REST request object
         * @return WP_REST_Response|WP_Error
         */
        public function get_courses(WP_REST_Request $request) {
            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                return $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'), 503);
            }

            // Get category filter (optional)
            $category_id = $request->get_param('category_id');

            // Fetch courses from Moodle
            $params = [];
            if ($category_id && $category_id > 0) {
                $params['categoryid'] = $category_id;
            }

            $response = $moodle->request_webservice('core_course_get_courses', $params, WP_REST_Server::READABLE);

            if (isset($response['exception'])) {
                return $this->send_response(
                    false,
                    [],
                    $response['message'] ?? __('Failed to fetch courses.', 'helperbox'),
                    500
                );
            }

            return $this->send_response(
                true,
                $response,
                __('Courses retrieved successfully.', 'helperbox')
            );
        }

        /**
         * REST API handler: Get a single course from Moodle
         *
         * Retrieves a specific course from Moodle by ID.
         *
         * @since 1.0.0
         *
         * @param WP_REST_Request $request The REST request object
         * @return WP_REST_Response|WP_Error
         */
        public function get_course(WP_REST_Request $request) {
            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                return $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'), 503);
            }

            $course_id = $request->get_param('course_id');

            // Fetch course from Moodle
            $params = [
                'options' => [
                    'uservisible' => true,
                ],
                'courseid' => $course_id,
            ];

            $response = $moodle->request_webservice('core_course_get_course', $params, WP_REST_Server::READABLE);

            if (isset($response['exception'])) {
                return $this->send_response(
                    false,
                    [],
                    $response['message'] ?? __('Failed to fetch course.', 'helperbox'),
                    500
                );
            }

            return $this->send_response(
                true,
                $response,
                __('Course retrieved successfully.', 'helperbox')
            );
        }

        /**
         * REST API handler: Update a course in Moodle
         *
         * Updates an existing course in Moodle via WebService API.
         *
         * @since 1.0.0
         *
         * @param WP_REST_Request $request The REST request object
         * @return WP_REST_Response|WP_Error
         */
        public function update_course(WP_REST_Request $request) {
            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                return $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'), 503);
            }

            $course_id = $request->get_param('course_id');

            // Build course update parameters
            $course_params = ['id' => $course_id];

            // Add only provided parameters
            $optional_params = [
                'shortname',
                'fullname',
                'summary',
                'summaryformat',
                'category',
                'startdate',
                'enddate',
                'numsections',
                'format',
                'visible',
                'enablecompletion',
            ];

            foreach ($optional_params as $param) {
                if ($request->has_param($param)) {
                    $course_params[$param] = $request->get_param($param);
                }
            }

            $params = [
                'courses' => [$course_params],
            ];

            // Call Moodle WebService
            $response = $moodle->request_webservice('core_course_update_courses', $params, WP_REST_Server::CREATABLE);

            // Check for errors
            if (isset($response['exception'])) {
                return $this->send_response(
                    false,
                    [],
                    $response['message'] ?? __('Failed to update course.', 'helperbox'),
                    500
                );
            }

            return $this->send_response(
                true,
                $response,
                __('Course updated successfully.', 'helperbox')
            );
        }

        /**
         * REST API handler: Delete a course from Moodle
         *
         * Deletes a course from Moodle via WebService API.
         *
         * @since 1.0.0
         *
         * @param WP_REST_Request $request The REST request object
         * @return WP_REST_Response|WP_Error
         */
        public function delete_course(WP_REST_Request $request) {
            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                return $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'), 503);
            }

            $course_id = $request->get_param('course_id');

            $params = [
                'courseids' => [$course_id],
            ];

            // Call Moodle WebService
            $response = $moodle->request_webservice('core_course_delete_courses', $params, WP_REST_Server::CREATABLE);

            // Check for errors
            if (isset($response['exception'])) {
                return $this->send_response(
                    false,
                    [],
                    $response['message'] ?? __('Failed to delete course.', 'helperbox'),
                    500
                );
            }

            return $this->send_response(
                true,
                $response,
                __('Course deleted successfully.', 'helperbox')
            );
        }

        /**
         * REST API handler: Get user's enrolled courses
         *
         * Retrieves courses that a specific user is enrolled in from Moodle.
         *
         * @since 1.0.0
         *
         * @param WP_REST_Request $request The REST request object
         * @return WP_REST_Response|WP_Error
         */
        public function get_user_courses(WP_REST_Request $request) {
            // Get user ID
            $user_id = $request->get_param('user_id');
            if (!$user_id) {
                return $this->send_response(false, [], __('User ID is required.', 'helperbox'), 400);
            }

            // Get user
            $wp_user = get_user_by('id', $user_id);
            if (!$wp_user) {
                return $this->send_response(false, [], __('User not found.', 'helperbox'), 404);
            }

            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                return $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'), 503);
            }

            // Get Moodle user ID
            $moodle_user_id = $moodle->get_moodle_user_id($wp_user);
            if (!$moodle_user_id) {
                return $this->send_response(false, [], __('Moodle user ID not found for this user.', 'helperbox'), 404);
            }

            // Fetch user courses from Moodle
            $params = [
                'userid' => $moodle_user_id,
            ];

            $response = $moodle->request_webservice('core_enrol_get_users_courses', $params, WP_REST_Server::READABLE);

            if (isset($response['exception'])) {
                return $this->send_response(
                    false,
                    [],
                    $response['message'] ?? __('Failed to fetch user courses.', 'helperbox'),
                    500
                );
            }

            return $this->send_response(
                true,
                $response,
                __('User courses retrieved successfully.', 'helperbox')
            );
        }

        /**
         * REST API handler: Sync user with Moodle
         *
         * Synchronizes WordPress user data with Moodle.
         *
         * @since 1.0.0
         *
         * @param WP_REST_Request $request The REST request object
         * @return WP_REST_Response|WP_Error
         */
        public function sync_user(WP_REST_Request $request) {
            // Get user ID
            $user_id = $request->get_param('user_id');
            if (!$user_id) {
                return $this->send_response(false, [], __('User ID is required.', 'helperbox'), 400);
            }

            // Get user
            $wp_user = get_user_by('id', $user_id);
            if (!$wp_user) {
                return $this->send_response(false, [], __('User not found.', 'helperbox'), 404);
            }

            // Get Moodle integration instance
            $moodle = Moodle_Integration::get_instance();
            if (!$moodle->is_enabled()) {
                return $this->send_response(false, [], __('Moodle integration is not enabled.', 'helperbox'), 503);
            }

            // Trigger user sync
            $sync = Moodle_User_Sync::get_instance();
            $result = $sync->sync_user_to_moodle($wp_user);

            if ($result['status']) {
                return $this->send_response(
                    true,
                    $result,
                    __('User synchronized successfully.', 'helperbox')
                );
            } else {
                return $this->send_response(
                    false,
                    [],
                    $result['message'] ?? __('Failed to sync user.', 'helperbox'),
                    500
                );
            }
        }
    }
}
