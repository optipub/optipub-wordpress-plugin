<?php

/**
 * Template Part: Functions for post queries.
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Post Date to Display
 */

function optipub_archives_get_date_diff($created_date, $updated_date, $mixed_interval_day)
{
    $date_created_date = new DateTime($created_date);
    $date_updated_date = new DateTime($updated_date);
    $difference = $date_created_date->diff($date_updated_date);

    if ($difference->days >= $mixed_interval_day) {
        $post_date = $updated_date;
    } else {
        $post_date = $created_date;
    }

    return $post_date;
}

function optipub_archives_get_post_date($post, $display_date_type, &$display_date_string, string $display_date_format = 'F j, Y', int $mixed_interval_day = 0, string $posted_string = 'Posted', string $updated_string = 'Updated')
{
    switch ($display_date_type) {
        case 'Posted':
            $post_date = gmdate($display_date_format, strtotime($post->post_date));
            $display_date_string = $posted_string;
            break;
        case 'Posted_GMT':
            $post_date = gmdate($display_date_format, strtotime($post->post_date_gmt));
            $display_date_string = $posted_string;
            break;
        case 'Updated':
            $post_date = gmdate($display_date_format, strtotime($post->post_modified));
            $display_date_string = $updated_string;
            break;
        case 'Updated_GMT':
            $post_date = gmdate($display_date_format, strtotime($post->post_modified_gmt));
            $display_date_string = $updated_string;
            break;
        case 'Mixed':
            $post_date = optipub_archives_get_date_diff($post->post_date, $post->post_modified, $mixed_interval_day);

            if ($post_date == $post->post_date) {
                $display_date_string = $posted_string;
            } else {
                $display_date_string = $updated_string;
            }
            $post_date = gmdate($display_date_format, strtotime($post->post_modified));
            break;
        case 'Mixed_GMT':
            $post_date = optipub_archives_get_date_diff($post->post_date_gmt, $post->post_modified_gmt, $mixed_interval_day);

            if ($post_date == $post->post_date_gmt) {
                $display_date_string = $posted_string;
            } else {
                $display_date_string = $updated_string;
            }
            $post_date = gmdate($display_date_format, strtotime($post->post_modified_gmt));
            break;
        case 'Display_Updated':
            $meta_date = optipub_archives_get_date_diff($post->post_date, $post->post_modified, $mixed_interval_day);

            if ($meta_date == $post->post_date) {
                $display_date_string = $posted_string;
            } else {
                $display_date_string = $updated_string;
            }
            $post_date = gmdate($display_date_format, strtotime($post->post_date));
            break;
        case 'Display_Updated_GMT':
            $meta_date = optipub_archives_get_date_diff($post->post_date_gmt, $post->post_modified_gmt, $mixed_interval_day);

            if ($meta_date == $post->post_date_gmt) {
                $display_date_string = $posted_string;
            } else {
                $display_date_string = $updated_string;
            }
            $post_date = gmdate($display_date_format, strtotime($post->post_date_gmt));
            break;
        default:
            $post_date = gmdate('F j, Y', strtotime($post->post_date));
            $display_date_string = 'Posted';
    }

    return $post_date;
}

/**
 * Excerpt -
 *
 * Limit the excerpt to 200 characters with a whole word at the end
 */
function optipub_archives_get_excerpt($excerpt, $string_length)
{
    if (strlen($excerpt) > $string_length) {
        $excerpt = substr($excerpt, 0, $string_length);
        $excerpt = rtrim($excerpt, " \t\n\r\0\x0B.,");

        $last_space = strrpos($excerpt, ' ');

        if ($last_space !== false) {
            $excerpt = substr($excerpt, 0, $last_space);
        }
        $excerpt = rtrim($excerpt, " \t\n\r\0\x0B.,");
        $excerpt .= '...';
    }

    return $excerpt;
}

/**
 * editor
 */
function optipub_archives_get_editor_string($editor_title, $public_post_statuses = '', $editor_prefix = 'Written by ')
{
    $editor_query_args = [
        'title'          => $editor_title,
        'posts_per_page' => 1,
        'post_type'      => 'editors',
        'post_status'    => $public_post_statuses,
    ];

    $editor_result = get_posts($editor_query_args);

    $resource_editor = isset($editor_result[0]) ? $editor_result[0] : $editor_title;

    $editor_string = $editor_prefix;

    if (isset($resource_editor) && isset($resource_editor->post_name)) {
        $editor_string .= '<a href="' . get_permalink($resource_editor->ID) . '">' . $resource_editor->post_title . '</a>';
    } elseif ($resource_editor) {
        $editor_string .= $resource_editor;
    } else {
        $editor_string .= 'unknown';
    }

    return $editor_string;
}

function optipub_archives_get_editor_info_by_title($editor_title = '')
{
    $editor_query_args = [
        'title'     => $editor_title,
        'post_type' => 'editors',
    ];

    $editor_array = get_posts($editor_query_args);

    return $editor_array[0];
}
