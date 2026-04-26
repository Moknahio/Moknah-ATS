<?php
/**
 * Plugin bootstrap for ATS Moknah Analytics.
 */
namespace AtsMoknahPlugin;

use AtsMoknahAnalyticsAdmin;
use AtsMoknahAnalyticsDb;
use AtsMoknahAnalyticsFrontend;
use AtsMoknahAnalyticsRest;

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
require_once plugin_dir_path(__FILE__) . 'class-ats-moknah-analytics-admin.php';
AtsMoknahAnalyticsDb::activate_hook();

add_action('plugins_loaded', function () {
    AtsMoknahAnalyticsDb::maybe_create_tables();
    AtsMoknahAnalyticsRest::register();
    AtsMoknahAnalyticsAdmin::register();
});