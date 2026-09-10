<?php

/**
 * Articles Configurable Options -
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
$post_statuses = ['featured'];

$excerpt_string_length = 200;

/** Order Posts */
$orderby_type_options = ['post_date', 'modified', 'menu_order'];
$orderby_type = 'post_date';

/**
 * Date Format
 */

/** Display Date Format */
$display_date_type_options = ['Posted', 'Posted_GMT', 'Updated', 'Updated_GMT', 'Mixed', 'Mixed_GMT', 'Display_Updated', 'Display_Updated_GMT'];
$display_date_type = 'Posted_GMT';

/** Choose what to display for posted and updated dates */
$posted_string = 'Updated';
$updated_string = 'Updated';

/** Choose what to display in front of editor link or name -- Default: 'Written by ' */
$author_prefix = 'By ';

/** Interval to determine whether to show $updated_string instead of $posted_string */
$mixed_interval_day = 1;

/** See Date Formats - https://www.w3schools.com/php/func_date_date_format.asp */
$display_date_format = 'F j, Y';

/** Placeholder for $posted_string and $updated_string */
$display_date_string = '';

/** Filter Variables */
$archives_filters_options = ['Category', 'Editor', 'Years', 'Months'];
$archives_filters = [];

$filter_editors_show_on_website_only = true;
