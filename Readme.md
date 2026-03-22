# Custom Helper Box

[![WordPress Plugin](https://img.shields.io/badge/WordPress-6.5%2B-blue)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-green)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-orange)](http://www.gnu.org/licenses/gpl-2.0.html)

- **Contributors:** santoshtmp7
- **Tags:** settings, functions, security, moodle, admin, custom-post-type, block-patterns
- **Author:** santoshtmp7
- **Author URI:** https://github.com/santoshtmp
- **Plugin URI:** https://github.com/santoshtmp/wordpress-custom-helperbox
- **Requires WP:** 6.5
- **Requires at least:** 6.8
- **Tested up to:** 6.8
- **Requires PHP:** 8.0
- **Text Domain:** helperbox
- **Stable tag:** 1.0
- **Version:** 1.0
- **License:** GPLv2 or later
- **License URI:** http://www.gnu.org/licenses/gpl-2.0.html

## Description

Custom Helper Box is a comprehensive developer-oriented WordPress plugin that provides a collection of reusable helper functions, UI enhancements, security improvements, and third-party integrations to streamline WordPress development and site management.

## Features

### 🛡️ Security Features
- **Admin User Monitoring:** Track admin user creation and manage admin accounts
- **Security Handler:** Centralized security management
- **User Role Management:** Enhanced user role controls and permissions

### 🎨 Block & Template Features
- **Custom Blocks:** Register and manage custom Gutenberg blocks
- **Block Patterns:** Pre-designed block patterns for quick content creation
- **Custom Page Templates:** Support for custom theme templates in configurable directories
- **Breadcrumb Navigation:** Built-in breadcrumb functionality

### 🔗 Moodle Integration
- **Single Sign-On (SSO):** Seamless authentication between WordPress and Moodle
- **Course Management:** Custom post type for Moodle courses
- **User Synchronization:** Sync users between WordPress and Moodle
- **AJAX Endpoints:** Moodle integration AJAX handlers

### ⚙️ Admin & Settings
- **Settings Page:** Centralized settings interface for plugin configuration
- **Check Settings:** Validate and verify plugin settings
- **Template Management:** Admin templates for notifications and updates
- **REST API Endpoints:** Custom REST API routes for extended functionality

### 📁 File & Media
- **Extended MIME Types:** Configurable allowed file upload types
- **Media Upload Controls:** Enhanced media upload security

### 🔧 Developer Tools
- **Logging System:** File-based logging with backtrace for debugging
- **Helper Functions:** Reusable utility functions
- **Autoloader:** PSR-4 compatible class autoloading
- **Redirect Handler:** Custom redirect management

## Installation

1. Upload the `wordpress-custom-helperbox` folder to the `/wp-content/mu-plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress (for regular plugins)
   - Note: As an mu-plugin, it will be automatically activated
3. Configure settings under **Settings → Helper Box** in WordPress admin

## Configuration

After installation, access the plugin settings:

1. Navigate to **Settings → Helper Box** in WordPress admin
2. Configure the following sections:
   - **General Settings:** Basic plugin configuration
   - **Admin Login Settings:** Admin-related security settings
   - **Security Settings:** Security features and monitoring
   - **Moodle Integration:** Moodle LMS connection settings
   - **File Types:** Allowed MIME types for uploads

## Directory Structure

```
wordpress-custom-helperbox/
├── assets/                 # Static assets (CSS, JS, images)
├── block-patterns/         # Custom Gutenberg block patterns
├── blocks/                 # Custom Gutenberg blocks
├── endpoint/               # API endpoints
│   ├── ajax/              # AJAX handlers
│   └── rest/              # REST API routes
├── inc/                    # Core includes
│   ├── classes/           # PHP classes
│   │   ├── admin/         # Admin functionality
│   │   ├── api/           # API-related classes
│   │   ├── moodle/        # Moodle integration
│   │   └── security/      # Security features
│   └── helpers/           # Helper functions
├── test/                   # Test files
├── custom-helperbox.php    # Main plugin file
└── README.md               # This file
```

## Screenshots

![General Settings](./assets/screenshot/general-setting.png)
*General Settings Page*

![Admin Login Settings](./assets/screenshot/admin-login-setting.png)
*Admin Login Settings Page*

![Security Settings](./assets/screenshot/security-setting.png)
*Security Settings Page*

## Usage Examples

### Logging
```php
use Helperbox_Plugin\HelperBox;

// Log an error
HelperBox::set_log_message($exception, 'error');

// Log a message
HelperBox::set_log_message('Custom message', 'message');

// Log general information
HelperBox::set_log_message('Debug info', 'debug');
```

### Constants
The plugin defines the following constants for use throughout your code:

- `HELPERBOX_URL` - Plugin directory URL
- `HELPERBOX_PATH` - Plugin directory path
- `helperbox_basename` - Plugin basename
- `HELPERBOX_IMG_URL` - Plugin images directory URL

## Requirements

- WordPress 6.5 or higher
- PHP 8.0 or higher
- MySQL 5.6 or higher (or MariaDB 10.1 or higher)

## Changelog

### Version 1.0
- Initial release
- Security features and admin monitoring
- Moodle integration with SSO support
- Custom blocks and block patterns
- Settings interface
- File upload management
- Logging system

## Upgrade Notice

### Version 1.0
Initial release of Custom Helper Box.

## Frequently Asked Questions

### Is this a must-use plugin?
Yes, this plugin is designed to work as an mu-plugin (must-use plugin) and will be automatically activated when placed in the `wp-content/mu-plugins/` directory.

### Can I disable certain features?
Yes, most features can be configured or disabled through the plugin settings page.

### How do I configure Moodle integration?
Navigate to **Settings → Helper Box → Moodle Integration** and enter your Moodle site URL, token, and other required credentials.

## Support

For issues, feature requests, or contributions, please visit the [GitHub repository](https://github.com/santoshtmp/wordpress-custom-helperbox).

## License

This plugin is licensed under the GNU General Public License v2 or later.

For more information, see [http://www.gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html).

---

**Note:** This plugin is intended for developers and advanced WordPress users. Please test in a staging environment before deploying to production.
