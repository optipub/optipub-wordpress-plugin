<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reports Configurable Options
 */

/** Archives Post Type */
$show_images = true;
$url_path = '/investment-opportunities/';
$url_title = 'Investment Opportunities';
$url_title_lcase = 'reports';

/** Query Options */
$search_enabled = false;
$search_post_types = false;
$filters_enabled = false;
$posts_per_page = -1;
$post_statuses = [];

$excerpt_string_length = 110;

/** Order Posts */
$orderby_type_options = ['post_date', 'modified', 'menu_order'];
$orderby_type = 'menu_order';

/**
 * Date Format
 */

/** Display Date Format */
$display_date_type_options = ['Posted', 'Posted_GMT', 'Updated', 'Updated_GMT', 'Mixed', 'Mixed_GMT', 'Display_Updated', 'Display_Updated_GMT'];
$display_date_type = 'Updated';

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
$archives_filters_options = ['Category', 'Editor', 'Years', 'Months'];
$archives_filters = ['Category', 'Year', 'Month'];

$filter_editors_show_on_website_only = true;
