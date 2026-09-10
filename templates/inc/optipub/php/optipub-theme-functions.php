<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * return_optipub_post_variables($post)
 * return_optipub_post_author_variables($post)
 * optipub_determine_post_display_date_by_diff($created_date, $updated_date, $mixed_interval_day)
 * return_optipub_post_display_post_date($post, $display_date_type, &$display_date_string, string $display_date_format = 'F j, Y', int $mixed_interval_day = 0, string $posted_string = 'Posted', string $updated_string = 'Updated')
 * return_optipub_post_concat_excerpt($excerpt, $string_length)
 * return_optipub_editor_display_string($editor_title, $public_post_statuses = '')
 * return_optipub_editor_post_by_title($editor_title = '')
 */
function return_optipub_post_variables($post)
{
    /**
     * Post Variables
     */
    $post_id = $post->ID;
    $title = $post->post_title;
    $resource_url = get_permalink($post_id);
    $post_date = return_optipub_post_display_post_date($post, $display_date_type, $display_date_string, $display_date_format, $mixed_interval_day, $posted_string, $updated_string);
    $excerpt = return_optipub_post_concat_excerpt($post->post_excerpt, $excerpt_string_length);

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

    $author_string = optipub_archives_get_editor_string($author_name, $public_post_statuses);

    $author_image_path = get_post_meta($author_info->ID, 'resource_thumbnail_path', true);

    if (empty($author_image_path)) {
        $author_image_path = optipub_return_opengraph_image_url();
    }

    return [
        'post_id'              => $post_id,
        'post_title'           => $title,
        'resource_url'         => $resource_url,
        'post_date'            => $post_date,
        'excerpt'              => $excerpt,
        'post_thumbnail_url'   => $thumbnail_path,
        'author_name'          => $author_name,
        'author_string'        => $author_string,
        'author_thumbnail_url' => $author_image_path,
    ];
}

function return_optipub_post_author_variables($post)
{
    /**
     * Author Variables
     */
    $author_name = get_post_meta($post->ID, 'resource_editor', true);
    $author_info = optipub_archives_get_editor_info_by_title($author_name);

    $author_string = optipub_archives_get_editor_string($author_name, $public_post_statuses);

    $author_image_path = get_post_meta($author_info->ID, 'resource_thumbnail_path', true);

    if (empty($author_image_path)) {
        $author_image_path = optipub_return_opengraph_image_url();
    }
}

/**
 * Post Date to Display
 */

function optipub_determine_post_display_date_by_diff($created_date, $updated_date, $mixed_interval_day)
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

function return_optipub_post_display_post_date($post, $display_date_type, &$display_date_string, string $display_date_format = 'F j, Y', int $mixed_interval_day = 0, string $posted_string = 'Posted', string $updated_string = 'Updated')
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
            $post_date = gmdate($display_date_format, strtotime($post_date));
            break;
        case 'Mixed_GMT':
            $post_date = optipub_archives_get_date_diff($post->post_date_gmt, $post->post_modified_gmt, $mixed_interval_day);

            if ($post_date == $post->post_date_gmt) {
                $display_date_string = $posted_string;
            } else {
                $display_date_string = $updated_string;
            }
            $post_date = gmdate($display_date_format, strtotime($post_date));
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
function return_optipub_post_concat_excerpt($excerpt, $string_length)
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
function return_optipub_editor_display_string($editor_title, $public_post_statuses = '')
{
    $editor_query_args = [
        'title'          => $editor_title,
        'posts_per_page' => 1,
        'post_type'      => 'editors',
        'post_status'    => $public_post_statuses,
    ];

    $editor_result = get_posts($editor_query_args);

    $resource_editor = isset($editor_result[0]) ? $editor_result[0] : $editor_title;

    $editor_string = 'Written by ';

    if (isset($resource_editor) && isset($resource_editor->post_name)) {
        $editor_string .= '<a href="' . get_permalink($resource_editor->ID) . '">' . $resource_editor->post_title . '</a>';
    } elseif ($resource_editor) {
        $editor_string .= $resource_editor;
    } else {
        $editor_string .= 'unknown';
    }

    return $editor_string;
}

function return_optipub_editor_post_by_title($editor_title = '')
{
    $editor_query_args = [
        'title'          => $editor_title,
        'posts_per_page' => 1,
        'post_type'      => 'editors',
    ];

    $editor_array = get_posts($editor_query_args);

    return $editor_array[0];
}
