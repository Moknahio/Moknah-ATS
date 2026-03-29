<?php


if (!defined('ABSPATH')) exit;

class Analytics_Frontend
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        if (!is_singular('post')) return;

        wp_enqueue_script(
            'ats-moknah-player-analytics',
            plugin_dir_url(__FILE__) . '../../assets/js/player-analytics.js',
            ['wp-api'],
            '1.0',
            true
        );

        wp_localize_script('ats-moknah-player-analytics', 'atsMoknahAnalytics', [
            'restUrl' => esc_url_raw(rest_url('ats-moknah/v1/analytics')),
            'nonce' => wp_create_nonce('wp_rest'),
            'postId' => get_the_ID(),
        ]);
    }
}