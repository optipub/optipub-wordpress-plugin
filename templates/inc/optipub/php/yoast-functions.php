<?php

/**
 * Yoast SEO Plugin Filters for OptiPub.
 *
 * Yoast SEO - [filter] optipub_yoast_add_optipub_post_types( $array ) - Add Custom Post Types to show publish date.
 * Yoast SEO - [filter] optipub_yoast_filter_title( $title ) - Filters the title.
 * Yoast SEO - [filter] optipub_yoast_filter_description( $description ) - Filters the description.
 * Yoast SEO - [filter] optipub_yoast_filter_og_image( $image ) - Filters the opengraph image.
 * Yoast SEO - [filter] optipub_yoast_filter_author( $author ) - Filters the meta author.
 * Yoast SEO - [filter] optipub_yoast_filter_canonical_url( $url ) - Filters the meta canonical.
 *
 * Yoast SEO - [filter] optipub_sitemap_exclude_taxonomy( $excluded, $taxonomy ) - Exclude a taxonomy from XML sitemaps.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Yoast SEO - Add Custom Post Types to show publish date.
 *
 * @param array $array Current post_types
 *
 * @var array $optipub_post_types OptiPub public post types
 *
 * @return array filtered post_types
 */
function optipub_yoast_add_optipub_post_types($array)
{
    global $post;

    $except_types = ['editors', 'page'];
    $optipub_post_types = optipub_return_optipub_post_types($except_types);

    if (in_array($post->post_type, $optipub_post_types)) {
        return true;
    }

    return false;
}
add_filter('wpseo_opengraph_show_publish_date', 'optipub_yoast_add_optipub_post_types');

/**
 * Yoast SEO - Filters the title.
 *
 * @param string $title the current page's generated title
 *
 * @var string $page_meta_title OptiPub meta resource_meta_title
 *
 * @return string the filtered title
 */
function optipub_yoast_filter_title($title)
{
    global $post;

    if (is_singular() && isset($post->ID)) {
        $post_id = $post->ID;
        $page_meta_title = get_post_meta($post_id, 'resource_meta_title', true);

        if (! empty($page_meta_title)) {
            return $page_meta_title;
        }
    }

    return $title;
}
add_filter('wpseo_title', 'optipub_yoast_filter_title');
add_filter('wpseo_opengraph_title', 'optipub_yoast_filter_title');

/**
 * Yoast SEO - Filters the description.
 *
 * @param string $description the current page's generated meta description
 *
 * @var string $page_meta_description OptiPub meta resource_meta_description
 *
 * @return string the filtered meta description
 */
function optipub_yoast_filter_description($description)
{
    global $post;

    if (is_singular() && isset($post->ID)) {
        $post_id = $post->ID;
        $page_meta_description = get_post_meta($post_id, 'resource_meta_description', true);

        if (! empty($page_meta_description)) {
            return $page_meta_description;
        }
    }

    return $description;
}
add_filter('wpseo_metadesc', 'optipub_yoast_filter_description');
add_filter('wpseo_opengraph_desc', 'optipub_yoast_filter_description');

/**
 * Yoast SEO - Filters the opengraph image.
 *
 * @param string $image the current page's generated meta opengraph image
 *
 * @var string $page_meta_image OptiPub meta resource_thumbnail_path
 *
 * @return string the filtered meta opengraph image
 */
function optipub_yoast_filter_og_image($image)
{
    global $post;

    $og_image = get_option(OptiPub\Base\Constants::OPTIPUB_OPENGRAPH_IMAGE_LINK);

    if (isset($post->ID)) {
        $post_id = $post->ID;
        $page_meta_image = get_post_meta($post_id, 'resource_thumbnail_path', true);

        if (! empty($page_meta_image)) {
            return $page_meta_image;
        }
    }

    if (! empty($og_image)) {
        return $og_image;
    }

    return $image;
}
add_filter('wpseo_opengraph_image', 'optipub_yoast_filter_og_image');

/**
 * Yoast SEO - Add an extra opengraph image.
 *
 * @param object $image_container The image container object.
 *
 * @var string $page_meta_image OptiPub meta resource_thumbnail_path
 *
 * @return void
 */
function optipub_yoast_add_og_images($image_container)
{
    global $post;

    $og_image = get_option(OptiPub\Base\Constants::OPTIPUB_OPENGRAPH_IMAGE_LINK);

    if (is_singular() && isset($post->ID)) {
        $post_id = $post->ID;
        $page_meta_image = get_post_meta($post_id, 'resource_thumbnail_path', true);

        if (! empty($page_meta_image)) {
            $image_container->add_image_by_url($page_meta_image);
        }
    }

    if (! empty($og_image)) {
        $image_container->add_image_by_url($og_image);
    }
}
add_filter('wpseo_add_opengraph_images', 'optipub_yoast_add_og_images');

/**
 * Yoast SEO - Filters the meta author.
 *
 * @param string $author the current page's generated meta opengraph image
 *
 * @var string $page_meta_author OptiPub meta resource_editor
 *
 * @return string the filtered meta opengraph image
 */
function optipub_yoast_filter_author($author)
{
    global $post;

    if (is_singular() && isset($post->ID)) {
        $post_id = $post->ID;
        $page_meta_author = get_post_meta($post_id, 'resource_editor', true);

        if (! empty($page_meta_author)) {
            return $page_meta_author;
        }
    }
}
add_filter('wpseo_meta_author', 'optipub_yoast_filter_author');
add_filter('wpseo_opengraph_author_facebook', 'optipub_yoast_filter_author');

/**
 * Yoast SEO - Filters the meta canonical.
 *
 * @param string $url the current page's generated meta canonical url
 *
 * @var string $page_meta_url OptiPub meta canonical_url
 *
 * @return string The filtered meta canonical url
 */
function optipub_yoast_filter_canonical_url($url)
{
    global $post;

    if (is_front_page() || is_home()) {
        $page_canonical_url = home_url($path = '/', $scheme = 'https');

        return $page_canonical_url;
    }

    if (is_singular() && isset($post->ID)) {
        $post_id = $post->ID;

        $page_canonical_url = get_post_meta($post_id, 'canonical_url', true);

        if (! empty($page_canonical_url)) {
            // Check if canonical url has a domain
            $parsed_url = wp_parse_url($page_canonical_url);

            if (empty($parsed_url['host'])) {
                $page_canonical_url = trim($page_canonical_url);

                // Add 'https://' and the current domain to the URL
                $http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';

                if (!empty($http_host)) {
                    $page_canonical_url = 'https://' . $http_host . '/' . $page_canonical_url . '/';
                }
            }

            return $page_canonical_url;
        }
    }

    return $url;
}
add_filter('wpseo_canonical', 'optipub_yoast_filter_canonical_url');
add_filter('wpseo_opengraph_url', 'optipub_yoast_filter_canonical_url');

/**
 * Exclude a taxonomy from XML sitemaps.
 *
 * @param bool   $excluded whether the taxonomy is excluded by default
 * @param string $taxonomy the taxonomy to exclude
 *
 * @return bool whether or not a given taxonomy should be excluded
 */
function optipub_yoast_sitemap_exclude_taxonomy($excluded, $taxonomy)
{
    return 'category' === $taxonomy;
}
add_filter('wpseo_sitemap_exclude_taxonomy', 'optipub_yoast_sitemap_exclude_taxonomy', 10, 2);
