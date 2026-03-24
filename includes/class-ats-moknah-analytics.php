<?php

namespace ATS_Moknah;

if (!defined('ABSPATH')) {
    exit;
}

class Analytics
{
    const TABLE               = 'ats_moknah_stats';
    const REPORT_CACHE_KEY    = 'ats_moknah_analytics_report';
    const REPORT_LIMIT        = 100;
    const LISTEN_CAP          = 3600; // cap seconds per event (1 hour)
    const RATE_LIMIT_WINDOW   = 60;   // seconds
    const RATE_LIMIT_MAX_HITS = 30;   // per IP per window

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_assets']);
        add_action('admin_menu', [self::class, 'register_report_page']);
    }

    public static function activate(): void
    {
        global $wpdb;
        $table           = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            plays BIGINT UNSIGNED NOT NULL DEFAULT 0,
            completions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            listen_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function register_routes(): void
    {
        register_rest_route('ats-moknah/v1', '/analytics', [
            'methods'             => 'POST',
            'permission_callback' => [self::class, 'verify_permission'],
            'args'                => [
                'post_id'        => ['type' => 'integer', 'required' => true],
                'event'          => ['type' => 'string', 'required' => true],
                'listen_seconds' => ['type' => 'number', 'required' => false],
            ],
            'callback' => [self::class, 'handle_event'],
        ]);
    }

    public static function verify_permission(\WP_REST_Request $request): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return false;
        }
        return current_user_can('edit_posts');
    }

    private static function check_rate_limit(): bool
    {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key   = 'ats_moknah_rl_' . md5($ip);
        $count = (int) wp_cache_get($key, 'ats_moknah');
        $count += 1;
        wp_cache_set($key, $count, 'ats_moknah', self::RATE_LIMIT_WINDOW);

        return $count <= self::RATE_LIMIT_MAX_HITS;
    }

    public static function handle_event(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!self::check_rate_limit()) {
            return new \WP_REST_Response(['error' => 'rate_limited'], 429);
        }

        global $wpdb;

        $post_id = (int) $request->get_param('post_id');
        $event   = sanitize_key($request->get_param('event'));
        $seconds = max(0, min((int) $request->get_param('listen_seconds'), self::LISTEN_CAP));

        if (!get_post($post_id)) {
            return new \WP_REST_Response(['error' => 'invalid_post'], 400);
        }

        $table = $wpdb->prefix . self::TABLE;
        $now   = current_time('mysql');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic table name from prefix + constant
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO `{$table}` (post_id, impressions, plays, completions, listen_seconds, updated_at)
                 VALUES (%d, 0, 0, 0, 0, %s)
                 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
                $post_id,
                $now
            )
        );

        // Fixed-column arithmetic increments to satisfy PreparedSQL.NotPrepared
        $inc_impr = ($event === 'impression') ? 1 : 0;
        $inc_play = ($event === 'play') ? 1 : 0;
        $inc_comp = ($event === 'complete') ? 1 : 0;
        $inc_secs = ($seconds > 0) ? $seconds : 0;

        if ($inc_impr || $inc_play || $inc_comp || $inc_secs) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic table name from prefix + constant
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE `{$table}` SET
                        impressions     = impressions + %d,
                        plays           = plays + %d,
                        completions     = completions + %d,
                        listen_seconds  = listen_seconds + %d,
                        updated_at      = %s
                     WHERE post_id = %d",
                    $inc_impr,
                    $inc_play,
                    $inc_comp,
                    $inc_secs,
                    $now,
                    $post_id
                )
            );

            wp_cache_delete(self::REPORT_CACHE_KEY, 'ats_moknah');
        }

        return new \WP_REST_Response(['ok' => true]);
    }

    public static function enqueue_frontend_assets(): void
    {
        if (!is_singular('post')) {
            return;
        }

        wp_enqueue_script(
            'ats-moknah-player-analytics',
            plugin_dir_url(__FILE__) . '../assets/player-analytics.js',
            ['wp-api'],
            '1.0',
            true
        );

        wp_localize_script('atsMoknahAnalytics', 'atsMoknahAnalytics', [
            'restUrl' => esc_url_raw(rest_url('ats-moknah/v1/analytics')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'postId'  => get_the_ID(),
        ]);
    }

    public static function register_report_page(): void
    {
        add_submenu_page(
            'ats-moknah',
            __('Analytics', 'ats-moknah'),
            __('Analytics', 'ats-moknah'),
            'manage_options',
            'ats-moknah-analytics',
            [self::class, 'render_report_page']
        );
    }

    public static function render_report_page(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $rows = wp_cache_get(self::REPORT_CACHE_KEY, 'ats_moknah');
        if (false === $rows) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic table name from prefix + constant
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, impressions, plays, completions, listen_seconds,
                            (CASE WHEN impressions > 0 THEN ROUND(plays * 100 / impressions, 2) ELSE 0 END) AS play_rate,
                            (CASE WHEN plays > 0 THEN ROUND(completions * 100 / plays, 2) ELSE 0 END) AS completion_rate
                     FROM `{$table}`
                     ORDER BY updated_at DESC
                     LIMIT %d",
                    self::REPORT_LIMIT
                )
            );
            wp_cache_set(self::REPORT_CACHE_KEY, $rows, 'ats_moknah', MINUTE_IN_SECONDS);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ATS Moknah Analytics', 'ats-moknah'); ?></h1>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Post', 'ats-moknah'); ?></th>
                    <th><?php esc_html_e('Impressions', 'ats-moknah'); ?></th>
                    <th><?php esc_html_e('Plays', 'ats-moknah'); ?></th>
                    <th><?php esc_html_e('Play Rate %', 'ats-moknah'); ?></th>
                    <th><?php esc_html_e('Completions', 'ats-moknah'); ?></th>
                    <th><?php esc_html_e('Completion Rate %', 'ats-moknah'); ?></th>
                    <th><?php esc_html_e('Listen Time (min)', 'ats-moknah'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows) : foreach ($rows as $r) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url(get_edit_post_link($r->post_id)); ?>">
                                <?php echo esc_html(get_the_title($r->post_id)); ?></a></td>
                        <td><?php echo (int) $r->impressions; ?></td>
                        <td><?php echo (int) $r->plays; ?></td>
                        <td><?php echo number_format((float) $r->play_rate, 2); ?></td>
                        <td><?php echo (int) $r->completions; ?></td>
                        <td><?php echo number_format((float) $r->completion_rate, 2); ?></td>
                        <td><?php echo number_format($r->listen_seconds / 60, 1); ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No data yet.', 'ats-moknah'); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}