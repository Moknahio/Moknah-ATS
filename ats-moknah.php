<?php
/*
Plugin Name: ATS Moknah
Description: Convert WordPress articles to speech using Moknah TTS API.
Version: 1.1
Requires PHP: 7.4
Requires at least: 5.8
Author: Moknah.io
Author URI: https://moknah.io/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ats-moknah
Plugin URI: https://github.com/Moknahio/Moknah-ATS
*/

if (!defined('ABSPATH')) exit;

if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>
            <strong>ATS Moknah</strong> requires PHP 7.4 or higher.
        </p></div>';
    });
    return;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {

    $settings_link = '<a href="' . admin_url('admin.php?page=ats-moknah') . '">Settings</a>';

    array_unshift($links, $settings_link);

    return $links;
});




$ats_moknah_autoload = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($ats_moknah_autoload)) {
    require_once $ats_moknah_autoload;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-ats-moknah-client.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ats-moknah-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ats-moknah-callback.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-ats-moknah-frontend.php';
\Frontend::init();

// Register admin page
\ATS_Moknah\Admin::register();

// Register REST callback
add_action('plugins_loaded', function () {
    \ATS_Moknah\Callback::register();
});
require_once plugin_dir_path(__FILE__) . 'includes/analytics/class-ats-moknah-analytics.php';
