<?php


if (!defined('ABSPATH')) exit;

class Analytics_Rest
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('ats-moknah/v1', '/analytics', [
            'methods' => 'POST',
            'permission_callback' => [self::class, 'verify_permission'],
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
                'event' => ['type' => 'string', 'required' => true],
                'listen_seconds' => ['type' => 'number', 'required' => false],
            ],
            'callback' => [self::class, 'handle_event'],
        ]);
    }

    public static function verify_permission(\WP_REST_Request $request): bool
    {
        if (!is_user_logged_in()) return false;
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) return false;
        return current_user_can('edit_posts');
    }

    private static function check_rate_limit(): bool
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'ats_moknah_rl_' . md5($ip);
        $count = (int) wp_cache_get($key, 'ats_moknah');
        $count += 1;
        wp_cache_set($key, $count, 'ats_moknah', Analytics_DB::RATE_LIMIT_WINDOW);
        return $count <= Analytics_DB::RATE_LIMIT_MAX_HITS;
    }

    public static function handle_event(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!self::check_rate_limit()) {
            return new \WP_REST_Response(['error' => 'rate_limited'], 429);
        }

        global $wpdb;
        Analytics_DB::maybe_create_tables();

        $post_id = (int) $request->get_param('post_id');
        $event   = sanitize_key($request->get_param('event'));
        $seconds = max(0, min((int) $request->get_param('listen_seconds'), Analytics_DB::LISTEN_CAP));

        if (!get_post($post_id)) {
            return new \WP_REST_Response(['error' => 'invalid_post'], 400);
        }

        $totals_table = Analytics_DB::qt($wpdb, Analytics_DB::TABLE_TOTALS);
        $daily_table  = Analytics_DB::qt($wpdb, Analytics_DB::TABLE_DAILY);
        $now   = current_time('mysql');
        $tz    = wp_timezone();
        $day   = (new \DateTime('now', $tz))->format('Y-m-d');

        // ensure rows
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$totals_table} (post_id, impressions, plays, completions, listen_seconds, updated_at)
             VALUES (%d,0,0,0,0,%s)
             ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
            $post_id, $now
        ));
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$daily_table} (post_id, day, impressions, plays, completions, listen_seconds, updated_at)
             VALUES (%d,%s,0,0,0,0,%s)
             ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
            $post_id, $day, $now
        ));

        $inc_impr = ($event === 'impression') ? 1 : 0;
        $inc_play = ($event === 'play') ? 1 : 0;
        $inc_comp = ($event === 'complete') ? 1 : 0;
        $inc_secs = ($seconds > 0) ? $seconds : 0;

        if ($inc_impr || $inc_play || $inc_comp || $inc_secs) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$totals_table} SET impressions = impressions + %d,
                    plays = plays + %d, completions = completions + %d,
                    listen_seconds = listen_seconds + %d, updated_at = %s
                 WHERE post_id = %d",
                $inc_impr, $inc_play, $inc_comp, $inc_secs, $now, $post_id
            ));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$daily_table} SET impressions = impressions + %d,
                    plays = plays + %d, completions = completions + %d,
                    listen_seconds = listen_seconds + %d, updated_at = %s
                 WHERE post_id = %d AND day = %s",
                $inc_impr, $inc_play, $inc_comp, $inc_secs, $now, $post_id, $day
            ));
        }

        if (!empty($wpdb->last_error)) {
            error_log('ATS Moknah analytics write error: ' . $wpdb->last_error);
        }

        return new \WP_REST_Response(['ok' => true]);
    }
}