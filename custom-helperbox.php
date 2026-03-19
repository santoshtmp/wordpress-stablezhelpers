<?php

/*
Plugin Name: Custom Helper Box
Description: Custom Helper Box provides the custom functions and features.
Contributors: santoshtmp7
Plugin URI: https://github.com/santoshtmp/wordpress-custom-helperbox
Tags: settings, functions, security
Version: 1.0
Author: santoshtmp7
Author URI: 
Requires WP: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Text Domain: helperbox
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

use Helperbox_Plugin\HelperBox;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// define helperbox constant named
if (!defined('HELPERBOX_URL')) {
    define('HELPERBOX_URL', plugin_dir_url(__FILE__));
}
if (!defined('HELPERBOX_PATH')) {
    define('HELPERBOX_PATH', plugin_dir_path(__FILE__));
}
if (!defined('helperbox_basename')) {
    define('helperbox_basename', plugin_basename(__FILE__));
}
if (!defined('HELPERBOX_IMG_URL')) {
    define('HELPERBOX_IMG_URL', plugin_dir_url(__FILE__) . 'assets/images/');
}

// autoload classes
require_once __DIR__ . '/inc/helpers/autoload.php';

// initialize the main class
if (class_exists(HelperBox::class)) {
    new HelperBox();
}
