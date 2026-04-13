<?php

if (!defined('ABSPATH')) {
    exit;
}

class Analytics_Admin
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);

        // This is the missing hook that makes the Export button work!
        add_action('admin_post_ats_moknah_export_csv', [self::class, 'export_csv']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            'Moknah-ATS-master',
            __('Analytics', 'Moknah-ATS-master'),
            __('Analytics', 'Moknah-ATS-master'),
            'manage_options',
            'ats-moknah-analytics',
            [self::class, 'render_page']
        );
    }

    public static function enqueue_admin_assets($hook): void
    {
        if ($hook !== 'ats-moknah_page_ats-moknah-analytics') {
            return;
        }
        // Adjust paths if necessary depending on your final directory structure
        wp_enqueue_style('ats-moknah-admin-analytics', plugin_dir_url(__DIR__) . '../assets/css/admin-analytics.css', [], '1.0');
        wp_enqueue_script('ats-moknah-admin-analytics', plugin_dir_url(__DIR__) . '../assets/js/admin-analytics.js', [], '1.0', true);
    }

    public static function render_page(): void
    {
        global $wpdb;
        Analytics_DB::maybe_create_tables();

        $totals_table = Analytics_DB::qt($wpdb, Analytics_DB::TABLE_TOTALS);
        $daily_table  = Analytics_DB::qt($wpdb, Analytics_DB::TABLE_DAILY);

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display filters, no data mutation.
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $range  = sanitize_key($_GET['range'] ?? 'all');
        $from   = sanitize_text_field(wp_unslash($_GET['from'] ?? ''));
        $to     = sanitize_text_field(wp_unslash($_GET['to'] ?? ''));
        $paged  = max(1, (int) wp_unslash($_GET['paged'] ?? 1));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        [$date_start, $date_end] = Analytics_DB::date_range_bounds($range, $from, $to);

        $per    = Analytics_DB::REPORT_LIMIT;
        $offset = ($paged - 1) * $per;

        $rows = [];
        $totals = (object)['impressions'=>0,'plays'=>0,'completions'=>0,'listen_seconds'=>0];
        $total_rows = 0;
        $total_pages = 1;

        if ($range !== 'all') {
            $where  = "WHERE p.post_type = %s AND p.post_status <> %s";
            $params = ['post', 'trash'];
            if ($search !== '') { $where .= " AND p.post_title LIKE %s"; $params[] = '%' . $wpdb->esc_like($search) . '%'; }
            if ($date_start && $date_end) { $where .= " AND d.day BETWEEN %s AND %s"; $params[] = $date_start; $params[] = $date_end; }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total_rows = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM (
                        SELECT d.post_id FROM {$daily_table} d
                        JOIN {$wpdb->posts} p ON p.ID = d.post_id
                        {$where}
                        GROUP BY d.post_id
                    ) sub",
                    $params
                )
            );
            $total_pages = max(1, (int) ceil($total_rows / $per));

            if ($total_rows > 0) {
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT d.post_id,
                               SUM(d.impressions) impressions,
                               SUM(d.plays) plays,
                               SUM(d.completions) completions,
                               SUM(d.listen_seconds) listen_seconds,
                               MAX(d.updated_at) updated_at,
                               (CASE WHEN SUM(d.impressions) > 0 THEN ROUND(SUM(d.plays)*100/SUM(d.impressions),2) ELSE 0 END) AS play_rate,
                               (CASE WHEN SUM(d.plays) > 0 THEN ROUND(SUM(d.completions)*100/SUM(d.plays),2) ELSE 0 END) AS completion_rate
                         FROM {$daily_table} d
                         JOIN {$wpdb->posts} p ON p.ID = d.post_id
                         {$where}
                         GROUP BY d.post_id
                         ORDER BY updated_at DESC
                         LIMIT %d OFFSET %d",
                        array_merge($params, [$per, $offset])
                    )
                );

                $totals = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT SUM(d.impressions) AS impressions,
                               SUM(d.plays) AS plays,
                               SUM(d.completions) AS completions,
                               SUM(d.listen_seconds) AS listen_seconds
                         FROM {$daily_table} d
                         JOIN {$wpdb->posts} p ON p.ID = d.post_id
                         {$where}",
                        $params
                    )
                );
            }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $where  = "WHERE p.post_type = %s AND p.post_status <> %s";
            $params = ['post', 'trash'];
            if ($search !== '') { $where .= " AND p.post_title LIKE %s"; $params[] = '%' . $wpdb->esc_like($search) . '%'; }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total_rows = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$totals_table} t
                     JOIN {$wpdb->posts} p ON p.ID = t.post_id {$where}",
                    $params
                )
            );
            $total_pages = max(1, (int) ceil($total_rows / $per));

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT t.post_id, t.impressions, t.plays, t.completions, t.listen_seconds, t.updated_at,
                           (CASE WHEN t.impressions > 0 THEN ROUND(t.plays * 100 / t.impressions, 2) ELSE 0 END) AS play_rate,
                           (CASE WHEN t.plays > 0 THEN ROUND(t.completions * 100 / t.plays, 2) ELSE 0 END) AS completion_rate
                     FROM {$totals_table} t
                     JOIN {$wpdb->posts} p ON p.ID = t.post_id
                     {$where}
                     ORDER BY t.updated_at DESC
                     LIMIT %d OFFSET %d",
                    array_merge($params, [$per, $offset])
                )
            );

            $totals = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT SUM(t.impressions) AS impressions,
                           SUM(t.plays) AS plays,
                           SUM(t.completions) AS completions,
                           SUM(t.listen_seconds) AS listen_seconds
                     FROM {$totals_table} t
                     JOIN {$wpdb->posts} p ON p.ID = t.post_id
                     {$where}",
                    $params
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $tot_play_rate       = ($totals->impressions > 0) ? round($totals->plays * 100 / $totals->impressions, 2) : 0;
        $tot_completion_rate = ($totals->plays > 0) ? round($totals->completions * 100 / $totals->plays, 2) : 0;

        // Render view (Path adjusted to point directly into the views directory)
        include dirname(__DIR__, 2) . '/views/views-ats-moknah-admin-analytics.php';
    }

    public static function export_csv(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'Moknah-ATS-master'), 403);
        }
        check_admin_referer('ats_moknah_export');

        global $wpdb;
        Analytics_DB::maybe_create_tables();

        $totals_table = Analytics_DB::qt($wpdb, Analytics_DB::TABLE_TOTALS);
        $daily_table  = Analytics_DB::qt($wpdb, Analytics_DB::TABLE_DAILY);

        $paged  = max(1, (int) wp_unslash($_POST['paged'] ?? 1));
        $scope  = (sanitize_key(wp_unslash($_POST['export_scope'] ?? 'page')) === 'all') ? 'all' : 'page';
        $per    = ($scope === 'all') ? Analytics_DB::EXPORT_MAX : Analytics_DB::REPORT_LIMIT;
        $offset = ($scope === 'all') ? 0 : (($paged - 1) * Analytics_DB::REPORT_LIMIT);

        $search = sanitize_text_field(wp_unslash($_POST['s'] ?? ''));
        $range  = sanitize_key($_POST['range'] ?? 'all');
        $from   = sanitize_text_field(wp_unslash($_POST['from'] ?? ''));
        $to     = sanitize_text_field(wp_unslash($_POST['to'] ?? ''));
        [$date_start, $date_end] = Analytics_DB::date_range_bounds($range, $from, $to);

        $rows = [];
        $totals = null;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($range !== 'all') {
            $where = "WHERE p.post_type = %s AND p.post_status <> %s";
            $params = ['post', 'trash'];
            if ($search !== '') { $where .= " AND p.post_title LIKE %s"; $params[] = '%' . $wpdb->esc_like($search) . '%'; }
            if ($date_start && $date_end) { $where .= " AND d.day BETWEEN %s AND %s"; $params[] = $date_start; $params[] = $date_end; }

            // Get Rows
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT d.post_id,
                            SUM(d.impressions) impressions,
                            SUM(d.plays) plays,
                            SUM(d.completions) completions,
                            SUM(d.listen_seconds) listen_seconds,
                            MAX(d.updated_at) updated_at,
                            (CASE WHEN SUM(d.impressions) > 0 THEN ROUND(SUM(d.plays)*100/SUM(d.impressions),2) ELSE 0 END) AS play_rate,
                            (CASE WHEN SUM(d.plays) > 0 THEN ROUND(SUM(d.completions)*100/SUM(d.plays),2) ELSE 0 END) AS completion_rate
                     FROM {$daily_table} d
                     JOIN {$wpdb->posts} p ON p.ID = d.post_id
                     {$where}
                     GROUP BY d.post_id
                     ORDER BY updated_at DESC
                     LIMIT %d OFFSET %d",
                    array_merge($params, [$per, $offset])
                ),
                ARRAY_A
            );

            // Get Totals for Summary
            $totals = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(d.impressions) AS impressions,
                        SUM(d.plays) AS plays,
                        SUM(d.completions) AS completions,
                        SUM(d.listen_seconds) AS listen_seconds
                 FROM {$daily_table} d
                 JOIN {$wpdb->posts} p ON p.ID = d.post_id
                 {$where}",
                $params
            ));
        } else {
            $where = "WHERE p.post_type = %s AND p.post_status <> %s";
            $params = ['post', 'trash'];
            if ($search !== '') { $where .= " AND p.post_title LIKE %s"; $params[] = '%' . $wpdb->esc_like($search) . '%'; }

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT t.post_id, t.impressions, t.plays, t.completions, t.listen_seconds, t.updated_at,
                            (CASE WHEN t.impressions > 0 THEN ROUND(t.plays * 100 / t.impressions, 2) ELSE 0 END) AS play_rate,
                            (CASE WHEN t.plays > 0 THEN ROUND(t.completions * 100 / t.plays, 2) ELSE 0 END) AS completion_rate
                     FROM {$totals_table} t
                     JOIN {$wpdb->posts} p ON p.ID = t.post_id
                     {$where}
                     ORDER BY t.updated_at DESC
                     LIMIT %d OFFSET %d",
                    array_merge($params, [$per, $offset])
                ),
                ARRAY_A
            );

            $totals = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(t.impressions) AS impressions,
                        SUM(t.plays) AS plays,
                        SUM(t.completions) AS completions,
                        SUM(t.listen_seconds) AS listen_seconds
                 FROM {$totals_table} t
                 JOIN {$wpdb->posts} p ON p.ID = t.post_id
                 {$where}",
                $params
            ));
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // KPI Calculations
        $tot_play_rate       = ($totals && $totals->impressions > 0) ? round($totals->plays * 100 / $totals->impressions, 2) : 0;
        $tot_completion_rate = ($totals && $totals->plays > 0) ? round($totals->completions * 100 / $totals->plays, 2) : 0;
        $tot_listen_min      = $totals ? round($totals->listen_seconds / 60, 1) : 0;

        // Smart Filename
        $date_label = ($range === 'all') ? 'All_Time' : ($date_start . '_to_' . $date_end);
        $filename   = 'Audio_Analytics_Report_' . $date_label . '_' . gmdate('Ymd_His') . '.csv';

        // Headers to force download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        $out = fopen('php://output', 'w');

        // UTF-8 BOM for full Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // --- 1. REPORT METADATA ---
        fputcsv($out, ['REPORT SUMMARY:', 'ATS Moknah Audio Analytics']);
        fputcsv($out, ['Generated On:', gmdate('Y-m-d H:i:s') . ' UTC']);

        $range_text = ($range === 'all') ? 'All Time' : sprintf('%s to %s', $date_start, $date_end);
        fputcsv($out, ['Date Range:', $range_text]);

        if (!empty($search)) {
            fputcsv($out, ['Search Query:', $search]);
        }
        fputcsv($out, ['Scope:', ($scope === 'all') ? 'All Records' : 'Current Page Only']);
        fputcsv($out, []);

        // --- 2. EXECUTIVE TOTALS ---
        fputcsv($out, ['OVERALL KPIs']);
        fputcsv($out, ['Total Impressions', 'Total Plays', 'Overall Play Rate', 'Total Completions', 'Overall Completion Rate', 'Total Listen Time (min)']);
        fputcsv($out, [
            $totals ? (int) $totals->impressions : 0,
            $totals ? (int) $totals->plays : 0,
            $tot_play_rate . '%',
            $totals ? (int) $totals->completions : 0,
            $tot_completion_rate . '%',
            $tot_listen_min
        ]);
        fputcsv($out, []);
        fputcsv($out, []);

        // --- 3. DATA TABLE ---
        fputcsv($out, ['POST DATA']);
        fputcsv($out, [
            'Post ID',
            'Post Title',
            'Impressions',
            'Plays',
            'Play Rate (%)',
            'Completions',
            'Completion Rate (%)',
            'Listen Time (min)',
            'Last Event (UTC)'
        ]);

        if (!empty($rows)) {
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['post_id'],
                    html_entity_decode(get_the_title($r['post_id']), ENT_QUOTES, 'UTF-8'),
                    (int) $r['impressions'],
                    (int) $r['plays'],
                    number_format((float) $r['play_rate'], 2),
                    (int) $r['completions'],
                    number_format((float) $r['completion_rate'], 2),
                    number_format($r['listen_seconds'] / 60, 1),
                    $r['updated_at'],
                ]);
            }
        } else {
            fputcsv($out, ['No data available for the selected criteria.']);
        }

        fclose($out);
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }
}