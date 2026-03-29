<?php
/**
 * Plugin bootstrap for ATS Moknah Analytics.
 */
namespace ATS_Moknah;

use Analytics_Admin;
use Analytics_DB;
use Analytics_Frontend;
use Analytics_Rest;

if (!defined('ABSPATH')) {
    exit;
}

// Simple PSR-4–style loader for this plugin namespace.
spl_autoload_register(function ($class) {
    if (strpos($class, __NAMESPACE__ . '\\') !== 0) {
        return;
    }
    $rel = str_replace(__NAMESPACE__ . '\\', '', $class);
    $rel = str_replace('\\', '/', $rel);
    $file = __DIR__ . 'class-ats-moknah-analytics.php/' . strtolower($rel) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
require_once plugin_dir_path(__FILE__) . 'class-ats-moknah-analytics-db.php';
require_once plugin_dir_path(__FILE__) . 'class-ats-moknah-analytics-rest.php';
require_once plugin_dir_path(__FILE__) . 'class-ats-moknah-analytics-frontend.php';
require_once plugin_dir_path(__FILE__) . 'class-ats-moknah-analytics-admin.php';
Analytics_DB::activate_hook();

add_action('plugins_loaded', function () {
    Analytics_DB::maybe_create_tables();
    Analytics_Rest::register();
    Analytics_Frontend::register();
    Analytics_Admin::register();
});