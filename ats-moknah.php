<?php
/*
Plugin Name: ATS Moknah
Description: Convert WordPress articles to speech using Moknah TTS API.
Version: 1.0
Requires PHP: 7.4
Requires at least: 5.8
Author: Moknah.io
Author URI: https://moknah.io/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: Moknah
Plugin URI: https://moknah.io/
*/

if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>
            <strong>ATS Moknah</strong> requires PHP 7.4 or higher.
        </p></div>';
    });
    return;
}


if (!defined('ABSPATH')) exit;
$autoload = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
require_once plugin_dir_path(__FILE__) . 'includes/class-ats-moknah-client.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ats-moknah-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ats-moknah-callback.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-ats-moknah-frontend.php';
\ATS_Moknah\Frontend::init();

// Register admin page
\ATS_Moknah\Admin::register();

// Register REST callback
add_action('plugins_loaded', function () {
    \ATS_Moknah\Callback::register();
});

