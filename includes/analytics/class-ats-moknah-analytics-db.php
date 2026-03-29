<?php


if (!defined('ABSPATH')) exit;

class Analytics_DB
{
    const TABLE_TOTALS = 'ats_moknah_stats';
    const TABLE_DAILY  = 'ats_moknah_stats_daily';
    const REPORT_LIMIT = 100;
    const EXPORT_MAX   = 5000;
    const LISTEN_CAP   = 3600;
    const RATE_LIMIT_WINDOW   = 60;
    const RATE_LIMIT_MAX_HITS = 30;

    public static function activate_hook(): void
    {
        register_activation_hook(dirname(__DIR__) . '/ats-moknah.php', [self::class, 'maybe_create_tables']);
    }

    public static function maybe_create_tables(): void
    {
        global $wpdb;
        $totals = self::qt($wpdb, self::TABLE_TOTALS);
        $daily  = self::qt($wpdb, self::TABLE_DAILY);
        $collate = $wpdb->get_charset_collate();

        $sql_totals = "CREATE TABLE IF NOT EXISTS {$totals} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            plays BIGINT UNSIGNED NOT NULL DEFAULT 0,
            completions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            listen_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id)
        ) {$collate};";

        $sql_daily = "CREATE TABLE IF NOT EXISTS {$daily} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            day DATE NOT NULL,
            impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            plays BIGINT UNSIGNED NOT NULL DEFAULT 0,
            completions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            listen_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_day (post_id, day),
            KEY day_idx (day)
        ) {$collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_totals);
        dbDelta($sql_daily);
    }

    public static function qt($wpdb, string $suffix): string
    {
        $name = $wpdb->prefix . $suffix;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            wp_die(__('Invalid table name.', 'ats-moknah'), 500);
        }
        return '`' . esc_sql($name) . '`';
    }

    public static function date_range_bounds(string $range, string $from, string $to): array
    {
        $tz = wp_timezone();
        $now = new \DateTime('now', $tz);
        $start = $end = null;

        switch ($range) {
            case 'today':
                $start = (clone $now)->setTime(0,0,0);
                $end   = (clone $now)->setTime(23,59,59);
                break;
            case 'this_week':
                $start = (clone $now)->modify('monday this week')->setTime(0,0,0);
                $end   = (clone $start)->modify('+6 days')->setTime(23,59,59);
                break;
            case 'this_month':
                $start = (clone $now)->modify('first day of this month')->setTime(0,0,0);
                $end   = (clone $now)->modify('last day of this month')->setTime(23,59,59);
                break;
            case 'last_month':
                $start = (clone $now)->modify('first day of last month')->setTime(0,0,0);
                $end   = (clone $now)->modify('last day of last month')->setTime(23,59,59);
                break;
            case 'last_3_months':
                $start = (clone $now)->modify('first day of -2 month')->setTime(0,0,0);
                $end   = (clone $now)->modify('last day of this month')->setTime(23,59,59);
                break;
            case 'custom':
                if ($from && $to) {
                    $start = new \DateTime($from . ' 00:00:00', $tz);
                    $end   = new \DateTime($to . ' 23:59:59', $tz);
                }
                break;
            default:
                break; // all time
        }

        return [
            $start ? $start->format('Y-m-d') : null,
            $end   ? $end->format('Y-m-d')   : null,
        ];
    }
}