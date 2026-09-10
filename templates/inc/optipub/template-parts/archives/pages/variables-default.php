<?php

/**
 * Archives Configurable Options - Default
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Archives Post Type */
$show_images = true;
$url_path = '/archives/';
$url_title = 'Articles';
$url_title_lcase = 'articles';

/** Query Options */
$search_enabled = false;
$search_post_types = false;
$filters_enabled = true;
$posts_per_page = 5;
$post_statuses = [];

$excerpt_string_length = 200;

/**
 * OptiPub syncs OptiPub 'Display Date' to post_date / post_date_gmt
 *
 * menu_order (Rank) only works for Reports, Videos and Editors
 */
$orderby_type_options = ['post_date', 'modified', 'menu_order'];
$orderby_type = 'post_date';

/**
 * Date Format
 */

/**
 * Posted - Always show post_date and "Posted"
 * Updated - Always showed post_modified and "Updated"
 * Mixed - Show post_date and "Posted" or post_modified and "Updated"
 * Display_Updated - Always show post_date, but show whether it is "Posted" or "Updated"
 */
$display_date_type_options = ['Posted', 'Posted_GMT', 'Updated', 'Updated_GMT', 'Mixed', 'Mixed_GMT', 'Display_Updated', 'Display_Updated_GMT'];
$display_date_type = 'Display_Updated_GMT';

/** Choose what to display for posted and updated dates */
$posted_string = 'Posted';
$updated_string = 'Updated';

/** Choose what to display in front of editor link or name -- Default: 'Written by ' */
$author_prefix = null;

/** Interval to determine whether to show $updated_string instead of $posted_string */
$mixed_interval_day = 1;

/** See Date Formats - https://www.w3schools.com/php/func_date_date_format.asp */
$display_date_format = 'F j, Y';

/** Placeholder for $posted_string and $updated_string */
$display_date_string = '';

/** Filter Variables */
$archives_filters_options = ['Post Type', 'Category', 'Editor', 'Years', 'Months'];
$archives_filters = ['Category', 'Editor', 'Year', 'Month'];

// Only show editors that are 'show_on_website' in Editor Dropdown -- (plugin only syncs these right now anyway so this does nothing)
$filter_editors_show_on_website_only = true;
