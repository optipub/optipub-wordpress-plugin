<?php

/**
 * Template Part: Variables for OptiPub post-types
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/** $post_id, $title, $resource_url, $post_date, $excerpt, $thumbnail_path */
/** $author_name, (object)$author_info, $author_url, $author_show_on_website, $author_string, $author_image_path */

/**
 * Post Variables
 */
$post_id = $post->ID;
$title = $post->post_title;
$resource_url = get_permalink($post_id);
$post_date = optipub_archives_get_post_date($post, $display_date_type, $display_date_string, $display_date_format, $mixed_interval_day, $posted_string, $updated_string);
$excerpt = optipub_archives_get_excerpt($post->post_excerpt, $excerpt_string_length);

/** Thumbnail */
$thumbnail_path = get_post_meta($post->ID, 'resource_thumbnail_path', true);

if (empty($thumbnail_path)) {
    $thumbnail_path = optipub_return_opengraph_image_url();
}

/**
 * Editor Variables
 */
$author_name = get_post_meta($post->ID, 'resource_editor', true);
$author_info = optipub_archives_get_editor_info_by_title($author_name);
$author_url = ! empty($author_info->ID) ? get_permalink($author_info->ID) : '';
$author_show_on_website = ! empty($author_info->ID) ? get_post_meta($author_info->ID, 'editor_show_on_website', true) : '';
$author_string = optipub_archives_get_editor_string($author_name, $public_post_statuses, $author_prefix);
$author_image_path = ! empty($author_info->ID) ? get_post_meta($author_info->ID, 'resource_thumbnail_path', true) : '';
