<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * All In One SEO Plugin Filters for OptiPub.
 *
 * AIO SEO - [filter] optipub_aioseo_filter_title($title) - Filters the Title
 * AIO SEO - [filter] optipub_aioseo_filter_description($description) - Filters the Description
 * AIO SEO - [filter] optipub_aioseo_filter_keywords($keywords) - Filters for Meta Keywords - Filters OptiPub Tags into keywords meta property
 * AIO SEO - [filter] optipub_aioseo_filter_canonical_url($url) -  * AIO SEO - Filters for Meta Canonical Url.
 * AIO SEO - [filter] optipub_aioseo_facebook_tags_remove($meta) - Remove Facebook Meta Tags.
 */

/* BOTH DO THE SAME THING
* OptiPub - [action] optipub_aio_remove_header_comments() - Remove AIO SEO branded commented lines in header.
* OptiPub - [action] optipub_remove_aioseo_comments($buffer) + optipub_buffer_start() + optipub_buffer_end() - Remove AIO SEO branded commented lines in header.
/*

/**
* AIO SEO - Filters the title.
*
* @param string $title the current page's generated title
*
* @var string $page_meta_title OptiPub meta resource_meta_title
*
* @return string the filtered title
*/
function optipub_aioseo_filter_title($title)
{
    global $post;

    if (is_singular() && isset($post->ID)) {
        $postID = $post->ID;
        $page_meta_title = get_post_meta($postID, 'resource_meta_title', true);

        if (! empty($page_meta_title)) {
            return $page_meta_title;
        }
    }

    return $title;
}
add_filter('aioseo_title', 'optipub_aioseo_filter_title');

/**
 * AIO SEO - Filters the description.
 *
 * @param string $description the current page's generated meta description
 *
 * @var string $page_meta_description OptiPub meta resource_meta_description
 *
 * @return string the filtered meta description
 */
function optipub_aioseo_filter_description($description)
{
    global $post;

    if (is_singular() && isset($post->ID)) {
        $postID = $post->ID;
        $page_meta_description = get_post_meta($postID, 'resource_meta_description', true);

        if (! empty($page_meta_description)) {
            return $page_meta_description;
        }
    }

    return $description;
}
add_filter('aioseo_description', 'optipub_aioseo_filter_description');

/**
 * AIO SEO - Filters for Meta Keywords - Filters OptiPub Tags into keywords meta property.
 *
 * @param string $keywords The current page's generated meta keywords
 *
 * @var string $page_meta_keywords OptiPub meta resource_meta_keywords
 *
 * @return string the filtered meta keywords
 */
function optipub_aioseo_filter_keywords($keywords)
{
    global $post;

    if (is_singular() && isset($post->ID)) {
        $postID = $post->ID;
        $page_meta_keywords = get_post_meta($postID, 'resource_meta_keywords', true);

        if (! empty($page_meta_keywords)) {
            return $page_meta_keywords;
        }
    }

    return $keywords;
}
//add_filter('aioseo_keywords', 'optipub_aioseo_filter_keywords');

/**
 * AIO SEO - Filters for Meta Canonical Url.
 *
 * @param string $url The current page's canonical url
 *
 * @var string $page_canonical_url OptiPub meta canonical url
 *
 * @return string The filtered canonical url
 */
function optipub_aioseo_filter_canonical_url($url)
{
    global $post;

    if (is_singular() && isset($post->ID)) {
        $postID = $post->ID;
        $page_canonical_url = get_post_meta($postID, 'canonical_url', true);

        if (! empty($page_canonical_url)) {
            return $page_canonical_url;
        }
    }

    return $url;
}
add_filter('aioseo_canonical_url', 'optipub_aioseo_filter_canonical_url');

/**
 * AIO SEO - Remove Facebook Meta Tags.
 *
 * @param string $meta The current header og:site_name meta tag
 *
 * @return string The unset og:site_name meta tag
 */
function optipub_aioseo_facebook_tags_remove($meta)
{
    unset($meta['og:site_name']);

    return $meta;
}
//add_filter('aioseo_facebook_tags', 'optipub_aioseo_facebook_tags_remove');

/**
 * OptiPub - Remove AIO SEO branded commented lines in header.
 */
function optipub_aio_remove_header_comments()
{
    ob_start(
        function ($o) {
            return preg_replace('/\n?<!-.*?All in One SEO.*?->/mi', '', $o);
        }
    );
    ob_end_flush();
}
//add_action('get_header', 'optipub_aio_remove_header_comments');

/**
 * OptiPub - Remove AIO SEO branded commented lines in header.
 *
 *function optipub_remove_aioseo_comments($buffer)
 *{
 *   $pattern = '/\n?<!-.*?All in One SEO.*?->/';
 *
 *    return preg_replace($pattern, '', $buffer);
 *}
 *
 *
 *function optipub_buffer_start()
 *{
 *   ob_start('optipub_remove_aioseo_comments');
 *}
 *
 *function optipub_buffer_end()
 *{
 *   ob_end_flush();
 *}
 *
 * #add_action('get_header', 'optipub_buffer_start', 1);
 * #add_action('wp_head', 'optipub_buffer_end', 1000);
 */
