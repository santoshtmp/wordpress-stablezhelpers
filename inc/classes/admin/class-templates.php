<?php

/**
 * Helperbox admin Templates
 *
 * @package helperbox
 * 
 */

namespace Helperbox_Plugin\admin;

use Helperbox_Plugin\User_Role;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Templates class
 */
class Templates {

  
  

    /**
     * 
     */
    public static function get_template_helperbox_available_update_list() {

        if (!current_user_can('manage_options')) {
            return;
        }

        include_once ABSPATH . 'wp-admin/includes/update.php';
        // core
        wp_version_check();
        $core_updates   = get_site_transient('update_core');
        // plugin
        $installed_plugins = get_plugins();
        wp_update_plugins();
        $plugin_updates = get_site_transient('update_plugins');
        // theme
        $installed_themes = wp_get_themes();
        wp_update_themes();
        $theme_updates  = get_site_transient('update_themes');    ?>
        <div class="wrap">

            <h2 class="wp-heading-inline">Available Update Versions Status</h2>
            <hr class="wp-header-end">

            <!-- WordPress Core -->
            <h3>WordPress Core</h3>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th>Current Version</th>
                        <td><?php echo esc_html($GLOBALS['wp_version']); ?></td>
                    </tr>
                    <?php if (!empty($core_updates->updates[0]->current)) : ?>
                        <tr>
                            <th>Available Version</th>
                            <td><?php echo esc_html($core_updates->updates[0]->current); ?></td>
                        </tr>
                    <?php else : ?>
                        <tr>
                            <th>Status</th>
                            <td>Up to date</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Plugins -->
            <h3 style="margin-top:30px;">Plugin Updates</h3>
            <?php
            if (!empty($plugin_updates->response)) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>S.N.</th>
                            <th>Plugin</th>
                            <th>Current Version</th>
                            <th>New Version</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $countplugin = 0;
                        foreach ($plugin_updates->response as $key => $plugin) :
                            $countplugin++;
                        ?>
                            <tr>
                                <td><?php echo esc_html($countplugin); ?></td>
                                <td><?php echo esc_html($installed_plugins[$key]['Name']); ?></td>
                                <td><?php echo esc_html($installed_plugins[$key]['Version']); ?></td>
                                <td><?php echo esc_html($plugin->new_version); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No plugin updates available.</p>
            <?php endif; ?>

            <!-- Themes -->
            <h3 style="margin-top:30px;">Theme Updates</h3>
            <?php
            if (!empty($theme_updates->response)) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>S.N.</th>
                            <th>Theme</th>
                            <th>Current Version</th>
                            <th>New Version</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $counttheme = 0;
                        foreach ($theme_updates->response as $key => $theme) :
                            $counttheme++;
                        ?>
                            <tr>
                                <td><?php echo esc_html($counttheme); ?></td>
                                <td><?php echo esc_html($installed_themes[$key]['Name']); ?></td>
                                <td><?php echo esc_html($installed_themes[$key]['Version']); ?></td>
                                <td><?php echo esc_html($theme['new_version']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No theme updates available.</p>
            <?php endif; ?>

            <p style="margin-top:20px; color:#666;">
                Updates are shown for reference only. File modifications are disabled.
            </p>
        </div>
    <?php
    }

    /**
     * 
     */
    public static function get_template_notification_update_status_count() {
        // check setting
        if (get_option('helperbox_disallow_file', '1') != '1') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }

        include_once ABSPATH . 'wp-admin/includes/update.php';
        // plugin
        wp_update_plugins();
        $plugin_updates = get_site_transient('update_plugins');
        // theme
        wp_update_themes();
        $theme_updates  = get_site_transient('update_themes');
        // count
        $plugin_count = count($plugin_updates->response);
        $theme_count = count($theme_updates->response);

    ?>
        <div class="notice notice-success ">
            <p>
                <strong>HelperBox:</strong>
                <a href="/wp-admin/options-general.php?page=helperbox&tab=security&check_update_status=true" target="_blank">
                    Check available update versions status
                </a>
            <ul>
                <li> <?php echo $plugin_count; ?> Plugin update available</li>
                <li> <?php echo $theme_count; ?> Theme update available</li>
            </ul>
            </p>
        </div>
        <?php
    }

    /**
     * 
     */
    public static function get_template_notification_file_mod_disable() {
        $check_update_status = $_GET['check_update_status'] ?? 'false';
        $active_tab = $_GET['tab'] ?? 'general';
        if ($active_tab == 'security' && $check_update_status == 'true'):
        ?>
            <div class="notice notice-success ">
                <p>Updates are shown for reference only. File modifications are disabled.</p>
                <p>To apply updates, uncheck "Disallow file modifications through admin interface" option from Helperbox security settings</p>
                <p>
                    <a class="wp-core-ui button" href="/wp-admin/options-general.php?page=helperbox&tab=security">Check Helper Box Security Settings</a>
                </p>
            </div>
<?php
        endif;
    }
    /**
     * ==== END ====
     */
}
