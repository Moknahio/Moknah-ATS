<?php
/**
 * Admin analytics view.
 *
 * Expected vars: $rows, $totals, $total_rows, $total_pages, $paged,
 * $search, $range, $from, $to, $tot_play_rate, $tot_completion_rate
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$ats_export_action   = admin_url('admin-post.php', is_ssl() ? 'https' : 'http'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$ats_range_is_custom = ($range === 'custom'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap ats-analytics-wrap">

    <!-- Header & Export Action -->
    <div class="ats-page-header">
        <div class="ats-title-group">
            <h1 class="wp-heading-inline"><?php esc_html_e('Audio Analytics', 'Moknah-ATS-master'); ?></h1>
            <p class="description"><?php esc_html_e('Track your audio performance, engagement, and completion rates.', 'Moknah-ATS-master'); ?></p>
        </div>

        <div class="ats-header-actions">
            <form method="post" action="<?php echo esc_url($ats_export_action); ?>" class="ats-export-form">
                <input type="hidden" name="action" value="ats_moknah_export_csv">
                <input type="hidden" name="paged" value="<?php echo (int) $paged; ?>">
                <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                <input type="hidden" name="range" value="<?php echo esc_attr($range); ?>">
                <input type="hidden" name="from" value="<?php echo esc_attr($from); ?>">
                <input type="hidden" name="to" value="<?php echo esc_attr($to); ?>">
                <?php wp_nonce_field('ats_moknah_export'); ?>

                <select id="ats-export-scope" name="export_scope">
                    <option value="page"><?php esc_html_e('Export Current Page', 'Moknah-ATS-master'); ?></option>
                    <option value="all"><?php esc_html_e('Export All Pages', 'Moknah-ATS-master'); ?></option>
                </select>
                <button type="submit" class="button">
                    <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Export CSV', 'Moknah-ATS-master'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- KPI Scorecard Grid -->
    <div class="ats-kpi-grid" role="group" aria-label="<?php esc_attr_e('Key performance indicators', 'Moknah-ATS-master'); ?>">
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Impressions', 'Moknah-ATS-master'); ?></div>
            <div class="ats-kpi-val"><?php echo esc_html(number_format_i18n((int) $totals->impressions)); ?></div>
        </div>
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Plays', 'Moknah-ATS-master'); ?></div>
            <div class="ats-kpi-val"><?php echo esc_html(number_format_i18n((int) $totals->plays)); ?></div>
        </div>
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Play Rate', 'Moknah-ATS-master'); ?></div>
            <div class="ats-kpi-val"><?php echo esc_html(number_format_i18n($tot_play_rate, 2)); ?><span class="ats-kpi-unit">%</span></div>
        </div>
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Completion Rate', 'Moknah-ATS-master'); ?></div>
            <div class="ats-kpi-val"><?php echo esc_html(number_format_i18n($tot_completion_rate, 2)); ?><span class="ats-kpi-unit">%</span></div>
        </div>
        <div class="ats-kpi-card">
            <div class="ats-kpi-title"><?php esc_html_e('Total Listen Time', 'Moknah-ATS-master'); ?></div>
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
                    <label class="screen-reader-text" for="ats-search"><?php esc_html_e('Search posts', 'Moknah-ATS-master'); ?></label>
                    <input id="ats-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search audio posts…', 'Moknah-ATS-master'); ?>">
                </div>

                <div class="ats-date-wrapper">
                    <label class="screen-reader-text" for="ats-range"><?php esc_html_e('Date range', 'Moknah-ATS-master'); ?></label>
                    <select id="ats-range" name="range" aria-controls="ats-custom-dates">
                        <option value="all" <?php selected($range, 'all'); ?>><?php esc_html_e('All Time', 'Moknah-ATS-master'); ?></option>
                        <option value="today" <?php selected($range, 'today'); ?>><?php esc_html_e('Today', 'Moknah-ATS-master'); ?></option>
                        <option value="this_week" <?php selected($range, 'this_week'); ?>><?php esc_html_e('This Week', 'Moknah-ATS-master'); ?></option>
                        <option value="this_month" <?php selected($range, 'this_month'); ?>><?php esc_html_e('This Month', 'Moknah-ATS-master'); ?></option>
                        <option value="last_month" <?php selected($range, 'last_month'); ?>><?php esc_html_e('Last Month', 'Moknah-ATS-master'); ?></option>
                        <option value="last_3_months" <?php selected($range, 'last_3_months'); ?>><?php esc_html_e('Last 3 Months', 'Moknah-ATS-master'); ?></option>
                        <option value="custom" <?php selected($range, 'custom'); ?>><?php esc_html_e('Custom Range…', 'Moknah-ATS-master'); ?></option>
                    </select>

                    <div id="ats-custom-dates" class="ats-custom-dates" style="display: <?php echo $ats_range_is_custom ? 'flex' : 'none'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;">
                        <input id="ats-from" type="date" name="from" value="<?php echo esc_attr($from); ?>" max="<?php echo esc_attr(gmdate('Y-m-d')); ?>" title="<?php esc_attr_e('Start Date', 'Moknah-ATS-master'); ?>">
                        <span class="ats-date-sep">&rarr;</span>
                        <input id="ats-to" type="date" name="to" value="<?php echo esc_attr($to); ?>" max="<?php echo esc_attr(gmdate('Y-m-d')); ?>" title="<?php esc_attr_e('End Date', 'Moknah-ATS-master'); ?>">
                    </div>

                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply Filters', 'Moknah-ATS-master'); ?></button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="ats-table-scroll">
            <table class="wp-list-table widefat fixed striped ats-analytics-table" role="table">
                <thead>
                <tr>
                    <th scope="col" data-sort="text" class="ats-col-primary"><?php esc_html_e('Post Title', 'Moknah-ATS-master'); ?></th>
                    <th scope="col" data-sort="int" class="ats-num"><?php esc_html_e('Impressions', 'Moknah-ATS-master'); ?></th>
                    <th scope="col" data-sort="int" class="ats-num"><?php esc_html_e('Plays', 'Moknah-ATS-master'); ?></th>
                    <th scope="col" data-sort="float" class="ats-num"><?php esc_html_e('Play Rate', 'Moknah-ATS-master'); ?></th>
                    <th scope="col" data-sort="int" class="ats-num"><?php esc_html_e('Completions', 'Moknah-ATS-master'); ?></th>
                    <th scope="col" data-sort="float" class="ats-num"><?php esc_html_e('Completion Rate', 'Moknah-ATS-master'); ?></th>
                    <th scope="col" data-sort="float" class="ats-num"><?php esc_html_e('Listen Time', 'Moknah-ATS-master'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows) : foreach ($rows as $ats_r) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
                    <tr>
                        <td class="ats-col-title ats-col-primary">
                            <strong>
                                <a title="<?php echo esc_attr(get_the_title($ats_r->post_id)); ?>" href="<?php echo esc_url(get_edit_post_link($ats_r->post_id)); ?>">
                                    <?php echo esc_html(get_the_title($ats_r->post_id)); ?>
                                </a>
                            </strong>
                        </td>
                        <td class="ats-num"><?php echo esc_html(number_format_i18n((int) $ats_r->impressions)); ?></td>
                        <td class="ats-num"><?php echo esc_html(number_format_i18n((int) $ats_r->plays)); ?></td>
                        <td class="ats-num ats-badge-cell"><span class="ats-badge ats-badge-blue"><?php echo esc_html(number_format((float) $ats_r->play_rate, 1)); ?>%</span></td>
                        <td class="ats-num"><?php echo esc_html(number_format_i18n((int) $ats_r->completions)); ?></td>
                        <td class="ats-num ats-badge-cell"><span class="ats-badge ats-badge-green"><?php echo esc_html(number_format((float) $ats_r->completion_rate, 1)); ?>%</span></td>
                        <td class="ats-num"><?php echo esc_html(number_format($ats_r->listen_seconds / 60, 1)); ?> <small>min</small></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr>
                        <td colspan="7" class="ats-empty-state">
                            <span class="dashicons dashicons-chart-area"></span>
                            <p><?php esc_html_e('No analytics data found for this period.', 'Moknah-ATS-master'); ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                <tr class="ats-table-footer-totals">
                    <th scope="row"><?php esc_html_e('Page Totals', 'Moknah-ATS-master'); ?></th>
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
            $ats_base    = add_query_arg(['page' => 'ats-moknah-analytics', 's' => rawurlencode($search), 'range' => $range, 'from' => $from, 'to' => $to]); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $ats_current = (int) $paged; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $ats_prev    = max(1, $ats_current - 1); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $ats_next    = min($total_pages, $ats_current + 1); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            ?>
            <div class="ats-pagination tablenav" style="justify-self: left"  dir="ltr">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                    <?php
                    /* translators: %s: number of items */
                    printf(esc_html__('%s items', 'Moknah-ATS-master'), esc_html(number_format_i18n($total_rows)));
                    ?>
                    </span>
                    <span class="pagination-links">
                        <a class="first-page button <?php echo $ats_current === 1 ? 'disabled' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" href="<?php echo esc_url(add_query_arg('paged', 1, $ats_base)); ?>">&laquo;</a>
                        <a class="prev-page button <?php echo $ats_current === 1 ? 'disabled' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" href="<?php echo esc_url(add_query_arg('paged', $ats_prev, $ats_base)); ?>">&lsaquo;</a>
                        <span class="paging-input">
                            <?php
                            /* translators: 1: current page number, 2: total pages */
                            printf(esc_html__('%1$s of %2$s', 'Moknah-ATS-master'), (int) $ats_current, (int) $total_pages);
                            ?>
                        </span>
                        <a class="next-page button <?php echo $ats_current === $total_pages ? 'disabled' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" href="<?php echo esc_url(add_query_arg('paged', $ats_next, $ats_base)); ?>">&rsaquo;</a>
                        <a class="last-page button <?php echo $ats_current === $total_pages ? 'disabled' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $ats_base)); ?>">&raquo;</a>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		const range = document.getElementById('ats-range');
		const customWrap = document.getElementById('ats-custom-dates');
		const fromInput = document.getElementById('ats-from');
		const toInput = document.getElementById('ats-to');

		if (!range || !customWrap || !fromInput || !toInput) return;

		const toggleCustom = () => {
			const isCustom = range.value === 'custom';
			customWrap.style.display = isCustom ? 'flex' : 'none';
			if (isCustom) {
				fromInput.setAttribute('required', 'required');
				toInput.setAttribute('required', 'required');
			} else {
				fromInput.removeAttribute('required');
				toInput.removeAttribute('required');
			}
		};

		// Ensure To date is never before From date
		fromInput.addEventListener('change', () => { toInput.min = fromInput.value; });
		toInput.addEventListener('change', () => { fromInput.max = toInput.value; });

		toggleCustom();
		range.addEventListener('change', toggleCustom);
	});
</script>