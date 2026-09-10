<?php

/**
 * Template Part: Queries for OptiPub post-types
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

// Add Plugin API Class
$optipub_db_query = new Inc\Api\WpDatabaseQuery();

// Verify nonce for form data processing
$nonce_verified = false;

if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'optipub_archive_filter')) {
    $nonce_verified = true;
}

// Filter Parameters - only process if nonce is verified or if it's a GET request without form data
if (
    $nonce_verified
    || (
        !isset($_GET['_wpnonce'])
        && !isset($_GET['search'])
        && !isset($_GET['category'])
        && !isset($_GET['editor'])
        && !isset($_GET['post-type'])
        && !isset($_GET['sort'])
    )
) {
    $selected_year = isset($_GET['years']) ? absint(wp_unslash($_GET['years'])) : '';
    $selected_month = isset($_GET['months']) ? absint(wp_unslash($_GET['months'])) : '';
    $selected_category = isset($_GET['category']) ? sanitize_text_field(wp_unslash($_GET['category'])) : '';
    $selected_editor = isset($_GET['editor']) ? sanitize_text_field(wp_unslash($_GET['editor'])) : '';
    $selected_post_type = isset($_GET['post-type']) ? sanitize_text_field(wp_unslash($_GET['post-type'])) : $url_title_lcase;

    // Search Parameters
    $query_orderby_type = isset($_GET['sort']) && sanitize_text_field(wp_unslash($_GET['sort'])) == 'relevance' ? sanitize_text_field(wp_unslash($_GET['sort'])) : $orderby_type;
    $query_search_value = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : null;
} else {
    // Default values if nonce verification fails
    $selected_year = '';
    $selected_month = '';
    $selected_category = '';
    $selected_editor = '';
    $selected_post_type = $url_title_lcase;
    $query_orderby_type = $orderby_type;
    $query_search_value = null;
}

// Pagination
$paged = absint((get_query_var('paged')) ? get_query_var('paged') : 1);

/** Query Template */
if (in_array($orderby_type, $orderby_type_options)) {
    $query_orderby_type = $orderby_type;
} else {
    $query_orderby_type = 'post_date';
}

$query_sort_order = 'DESC';

if ($query_orderby_type == 'menu_order') {
    $query_sort_order = 'ASC';
}

// Get all Public Post Statuses
$public_post_statuses = get_post_stati(['public' => true], 'names', 'or');

// Make sure to query OptiPub Post Types
$optipub_post_types = $optipub_db_query->getAllOptiPubPostTypes(['editors'], true);
$query_post_type = in_array($selected_post_type, $optipub_post_types) ? $selected_post_type : $optipub_post_types;

if ($url_title_lcase == 'editors') {
    $query_post_type = $url_title_lcase;
}

$query_args = [
    'date_query'     => [],
    'paged'          => $paged,
    'posts_per_page' => $posts_per_page,
    'orderby'        => $query_orderby_type,
    'order'          => $query_sort_order,
    'post_type'      => $query_post_type,
    'post_status'    => ! empty($post_statuses) ? $post_statuses : $public_post_statuses,
];

if ($search_enabled) {
    if ($query_search_value) {
        $query_args['s'] = $query_search_value;
    }
}

if ($selected_year) {
    $query_args['date_query']['year'] = $selected_year;
}

if ($selected_month) {
    $query_args['date_query']['month'] = $selected_month;
}

if ($selected_category) {
    $foundTerms = get_terms([
        'taxonomy'   => 'category',
        'name'       => $selected_category,
        'hide_empty' => false,
    ]);

    if ($foundTerms && !is_wp_error($foundTerms)) {
        $termsToInsert = [];
        $termsToInsert[] = [
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => $foundTerms[0]->term_id,
        ];

        $query_args['tax_query'] = $termsToInsert;
    }
}

if ($selected_editor) {
    $query_args['meta_query'] = [
        [
            'key'     => 'resource_editor', // Your custom field key
            'value'   => $selected_editor,  // The value from URL parameter
            'compare' => '=', // Comparison parameter
        ],
    ];
}

$archive_query = new WP_Query($query_args);
