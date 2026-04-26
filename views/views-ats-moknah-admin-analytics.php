<?php
/**
 * Admin analytics view.
 *
 * Expected vars: $rows, $totals, $total_rows, $total_pages, $paged,
 * $search, $range, $from, $to, $tot_play_rate, $tot_completion_rate
 */
if (!defined('ABSPATH')) exit;
$export_action   = admin_url('admin-post.php', is_ssl() ? 'https' : 'http');
$range_is_custom = ($range === 'custom');
?>
<div class="wrap ats-analytics-wrap">

    <!-- Header & Export Action -->
    <div class="ats-page-header">
        <div class="ats-title-group">
            <h1 class="wp-heading-inline"><?php esc_html_e('Audio Analytics', 'ats-moknah-article-to-speech'); ?></h1>
            <p class="description"><?php esc_html_e('Track your audio performance, engagement, and completion rates.', 'ats-moknah-article-to-speech'); ?></p>
        </div>

        <div class="ats-header-actions">
            <form method="post" action="<?php echo esc_url($export_action); ?>" class="ats-export-form">
                <input type="hidden" name="action" value="atsmoknah_export_csv">
                <input type="hidden" name="paged" value="<?php echo (int) $paged; ?>">
                <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="hidden" name="range" value="<?php echo esc_attr($range); ?>">
                <input type="hidden" name="from" value="<?php echo esc_attr($from); ?>">
                <input type="hidden" name="to" value="<?php echo esc_attr($to); ?>">
                <?php wp_nonce_field('atsmoknah_export'); ?>

                <select id="ats-export-scope" name="export_scope">
                    <option value="page"><?php esc_html_e('Export Current Page', 'ats-moknah-article-to-speech'); ?></option>
                    <option value="all"><?php esc_html_e('Export All Pages', 'ats-moknah-article-to-speech'); ?></option>
                </select>
                <button type="submit" class="button">
                    <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Export CSV', 'ats-moknah-article-to-speech'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- KPI Scorecard Grid -->
    <div class="ats-kpi-grid" role="group" aria-label="<?php esc_attr_e('Key performance indicators', 'ats-moknah-article-to-speech'); ?>">
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Impressions', 'ats-moknah-article-to-speech'); ?></div>
            <div class="ats-kpi-val">
                <?php echo esc_html(number_format_i18n((int) $totals->impressions)); ?>
            </div>        </div>
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Plays', 'ats-moknah-article-to-speech'); ?></div>
            <div class="ats-kpi-val"><?php echo esc_html(number_format_i18n((int) $totals->plays)); ?></div>
        </div>
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Play Rate', 'ats-moknah-article-to-speech'); ?></div>
            <div class="ats-kpi-val"><?php echo esc_html(number_format_i18n($tot_play_rate, 2)); ?><span class="ats-kpi-unit">%</span></div>
        </div>
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Completion Rate', 'ats-moknah-article-to-speech'); ?></div>
            <div class="ats-kpi-val"><?php echo esc_html(number_format_i18n($tot_completion_rate, 2)); ?><span class="ats-kpi-unit">%</span></div>
        </div>
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Total Listen Time', 'ats-moknah-article-to-speech'); ?></div>
            <div class="ats-kpi-val"><?php echo esc_html(number_format_i18n($totals->listen_seconds / 60, 1)); ?><span class="ats-kpi-unit"> min</span></div>
        </div>
    </div>

    <!-- Data Section -->
    <div class="ats-data-container">

        <!-- Filter Bar -->
        <div class="ats-filter-bar">
            <form method="get" action="" class="ats-filter-form" id="ats-filter-form">
                <input type="hidden" name="page" value="ats-moknah-analytics">

                <div class="ats-search-wrapper">
                    <span class="dashicons dashicons-search"></span>
                    <label class="screen-reader-text" for="ats-search"><?php esc_html_e('Search posts', 'ats-moknah-article-to-speech'); ?></label>
                    <input id="ats-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search audio posts…', 'ats-moknah-article-to-speech'); ?>">
                </div>

                <div class="ats-date-wrapper">
                    <label class="screen-reader-text" for="ats-range"><?php esc_html_e('Date range', 'ats-moknah-article-to-speech'); ?></label>
                    <select id="ats-range" name="range" aria-controls="ats-custom-dates">
                        <option value="all" <?php selected($range, 'all'); ?>><?php esc_html_e('All Time', 'ats-moknah-article-to-speech'); ?></option>
                        <option value="today" <?php selected($range, 'today'); ?>><?php esc_html_e('Today', 'ats-moknah-article-to-speech'); ?></option>
                        <option value="this_week" <?php selected($range, 'this_week'); ?>><?php esc_html_e('This Week', 'ats-moknah-article-to-speech'); ?></option>
                        <option value="this_month" <?php selected($range, 'this_month'); ?>><?php esc_html_e('This Month', 'ats-moknah-article-to-speech'); ?></option>
                        <option value="last_month" <?php selected($range, 'last_month'); ?>><?php esc_html_e('Last Month', 'ats-moknah-article-to-speech'); ?></option>
                        <option value="last_3_months" <?php selected($range, 'last_3_months'); ?>><?php esc_html_e('Last 3 Months', 'ats-moknah-article-to-speech'); ?></option>
                        <option value="custom" <?php selected($range, 'custom'); ?>><?php esc_html_e('Custom Range…', 'ats-moknah-article-to-speech'); ?></option>
                    </select>

                    <div id="ats-custom-dates" class="ats-custom-dates" style="display: <?php echo $range_is_custom ? 'flex' : 'none'; ?>;">
                        <input id="ats-from" type="date" name="from" value="<?php echo esc_attr($from); ?>" max="<?php echo esc_attr(gmdate('Y-m-d')); ?>" title="<?php esc_attr_e('Start Date', 'ats-moknah-article-to-speech'); ?>">
                        <span class="ats-date-sep">&rarr;</span>
                        <input id="ats-to" type="date" name="to" value="<?php echo esc_attr($to); ?>" max="<?php echo esc_attr(gmdate('Y-m-d')); ?>" title="<?php esc_attr_e('End Date', 'ats-moknah-article-to-speech'); ?>">
                    </div>

                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply Filters', 'ats-moknah-article-to-speech'); ?></button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="ats-table-scroll">
            <table class="wp-list-table widefat fixed striped ats-analytics-table" role="table">
                <thead>
                <tr>
                    <th scope="col" data-sort="text" class="ats-col-primary"><?php esc_html_e('Post Title', 'ats-moknah-article-to-speech'); ?></th>
                    <th scope="col" data-sort="int" class="ats-num"><?php esc_html_e('Impressions', 'ats-moknah-article-to-speech'); ?></th>
                    <th scope="col" data-sort="int" class="ats-num"><?php esc_html_e('Plays', 'ats-moknah-article-to-speech'); ?></th>
                    <th scope="col" data-sort="float" class="ats-num"><?php esc_html_e('Play Rate', 'ats-moknah-article-to-speech'); ?></th>
                    <th scope="col" data-sort="int" class="ats-num"><?php esc_html_e('Completions', 'ats-moknah-article-to-speech'); ?></th>
                    <th scope="col" data-sort="float" class="ats-num"><?php esc_html_e('Completion Rate', 'ats-moknah-article-to-speech'); ?></th>
                    <th scope="col" data-sort="float" class="ats-num"><?php esc_html_e('Listen Time', 'ats-moknah-article-to-speech'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows) : foreach ($rows as $r) : ?>
                    <tr>
                        <td class="ats-col-title ats-col-primary">
                            <strong>
                                <a title="<?php echo esc_attr(get_the_title($r->post_id)); ?>" href="<?php echo esc_url(get_edit_post_link($r->post_id)); ?>">
                                    <?php echo esc_html(get_the_title($r->post_id)); ?>
                                </a>
                            </strong>
                        </td>
                        <td class="ats-num"><?php echo esc_html(number_format_i18n((int) $r->impressions)); ?></td>
                        <td class="ats-num"><?php echo esc_html(number_format_i18n((int) $r->plays)); ?></td>
                        <td class="ats-num ats-badge-cell"><span class="ats-badge ats-badge-blue"><?php echo number_format((float) $r->play_rate, 1); ?>%</span></td>
                        <td class="ats-num"><?php echo esc_html(number_format_i18n((int) $r->completions)); ?></td>
                        <td class="ats-num ats-badge-cell"><span class="ats-badge ats-badge-green"><?php echo number_format((float) $r->completion_rate, 1); ?>%</span></td>
                        <td class="ats-num"><?php echo esc_html(number_format($r->listen_seconds / 60, 1)); ?> <small>min</small></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr>
                        <td colspan="7" class="ats-empty-state">
                            <span class="dashicons dashicons-chart-area"></span>
                            <p><?php esc_html_e('No analytics data found for this period.', 'ats-moknah-article-to-speech'); ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                <tr class="ats-table-footer-totals">
                    <th scope="row"><?php esc_html_e('Page Totals', 'ats-moknah-article-to-speech'); ?></th>
                    <th class="ats-num"><?php echo esc_html(number_format_i18n((int) $totals->impressions)); ?></th>
                    <th class="ats-num"><?php echo esc_html(number_format_i18n((int) $totals->plays)); ?></th>
                    <th class="ats-num"><?php echo esc_html(number_format($tot_play_rate, 1)); ?>%</th>
                    <th class="ats-num"><?php echo esc_html(number_format_i18n((int) $totals->completions)); ?></th>
                    <th class="ats-num"><?php echo esc_html(number_format($tot_completion_rate, 1)); ?>%</th>
                    <th class="ats-num"><?php echo esc_html(number_format($totals->listen_seconds / 60, 1)); ?> min</th>
                </tr>
                </tfoot>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1) :
            $base = add_query_arg(['page' => 'ats-moknah-analytics', 's' => rawurlencode($search), 'range' => $range, 'from' => $from, 'to' => $to]);
            $current = $paged;
            $prev = max(1, $current - 1);
            $next = min($total_pages, $current + 1);
            ?>
            <div class="ats-pagination tablenav" style="justify-self: left"  dir="ltr">
                <div class="tablenav-pages">

                    <span class="displaying-num"><?php
                        /* translators: %s: total number of items */
                        printf(esc_html__('%s items', 'ats-moknah-article-to-speech'), esc_html(number_format_i18n($total_rows))); ?></span>
                    <span class="pagination-links">
                        <a class="first-page button <?php echo $current === 1 ? 'disabled' : ''; ?>" href="<?php echo esc_url(add_query_arg('paged', 1, $base)); ?>">&laquo;</a>
                        <a class="prev-page button <?php echo $current === 1 ? 'disabled' : ''; ?>" href="<?php echo esc_url(add_query_arg('paged', $prev, $base)); ?>">&lsaquo;</a>
                        <span class="paging-input"><?php
                            /* translators: %1$s: current item number, %2$s total number of items */
                            printf(esc_html__('%1$s of %2$s', 'ats-moknah-article-to-speech'), esc_html($current), esc_html($total_pages)); ?></span>
                        <a class="next-page button <?php echo $current === $total_pages ? 'disabled' : ''; ?>" href="<?php echo esc_url(add_query_arg('paged', $next, $base)); ?>">&rsaquo;</a>
                        <a class="last-page button <?php echo $current === $total_pages ? 'disabled' : ''; ?>" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base)); ?>">&raquo;</a>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

