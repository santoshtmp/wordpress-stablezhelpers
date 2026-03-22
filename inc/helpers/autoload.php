<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}


spl_autoload_register(function ($class) {

    $prefix = 'Helperbox_Plugin\\';
    $base_dir = HELPERBOX_PATH . 'inc/classes/';

    // Only load our own classes
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    // Remove namespace prefix
    $relative_class = substr($class, strlen($prefix));

    // Convert namespace to file path (handles sub-namespaces like moodle\Moodle_Integration)
    $parts = explode('\\', $relative_class);

    // Convert each part to lowercase with dash prefix for class files
    $path_parts = [];
    foreach ($parts as $index => $part) {
        if ($index === count($parts) - 1) {
            // Last part is the class name - add class- prefix
            $path_parts[] = 'class-' . str_replace('_', '-', strtolower($part));
        } else {
            // Namespace directories
            $path_parts[] = str_replace('_', '-', strtolower($part));
        }
    }
    
    $file = $base_dir . implode('/', $path_parts) . '.php';

    // check if file exist
    if (file_exists($file)) {
        require_once $file;
    }

});


