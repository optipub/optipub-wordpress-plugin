<?php

/**
 * The template for displaying the sidebar for single pages
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * These widgets are examples
 *
 * Remove these or replace the widget IDs with IDs that exist in your theme
 */

/* DISABLED *

the_post();

$post_type = $post->post_type;

if ($post_type != 'editors') {

    if (is_active_sidebar('sidebar-subscribe-cta')) {
    dynamic_sidebar('sidebar-subscribe-cta');
    }
}

if (is_active_sidebar('sidebar-premium-publications')) {
    dynamic_sidebar('sidebar-premium-publications');
}

if (is_active_sidebar('sidebar-related-articles')) {
    dynamic_sidebar('sidebar-related-articles');
}

if ($post_type != 'editors') {
    if (is_active_sidebar('sidebar-latest-articles')) {
        dynamic_sidebar('sidebar-latest-articles');
    }
}

* DISABLED */
