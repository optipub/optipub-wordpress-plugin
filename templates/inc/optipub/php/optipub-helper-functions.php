<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * General OptiPub Functions.
 *
 * [filter] add_filter('xmlrpc_enabled') - Disable XML-RPC
 *
 * [function] optipub_return_optipub_post_types($except_types = []) - Excludes passed optipub custom post types.
 * [action] optipub_collapse_navigation() - Make sure navigation is collapsed.
 *
 * [filter] optipub_custom_page_template($template) - Change template for specific pages.
 *
 * Page Slugs -
 * [action] optipub_update_page_slug() - Change page slugs.
 * [filter] optipub_change_post_types_slug( $args, $post_type ) - Change Post Type Slugs - ONLY NEEDED If using post_type in permalink.
 *
 * RSS Feed -
 * [filter] optipub_feed_request($qv) - Add all post types to RSS Feed.
 * [filter] optipub_rss_post_thumbnail( $content ) - Add the post thumbnail, if available, before the content in feeds.
 */

/**
 * Disable XML-RPC.
 */
add_filter('xmlrpc_enabled', '__return_false');

/**
 * Function that returns all optipub custom post types.
 */
function optipub_return_optipub_post_types($except_types = [])
{
    // create array of only public optipub post_types
    $args = [
        'public'   => true,
        '_builtin' => false,
    ];
    $output = 'names';
    $operator = 'and';

    $post_types = get_post_types($args, $output, $operator);

    // remove optipub post_types we dont want to redirect to root
    return array_diff($post_types, $except_types);
}

/**
 * Bug - OptiPub FAQ expands bootstrap navbar.
 *
 * This function makes sure navigation is closed on load
 */
function optipub_collapse_navigation()
{
    if (!is_admin()) {
        wp_enqueue_script(
            'optipub-navigation-collapse',
            OPTIPUB_PLUGIN_URL . 'public/js/navigation-collapse.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'optipub_collapse_navigation');

function optipub_custom_page_template($template)
{
    if (is_page('home')) {
        return get_stylesheet_directory() . '/optipub_home_page.php';
    }

    return $template;
}
//add_filter('template_include', 'optipub_custom_page_template');

/**
 * Get page by title using WP_Query (replacement for deprecated get_page_by_title)
 *
 * @param string $title The page title to search for
 * @return WP_Post|null The page object or null if not found
 */
function optipub_get_page_by_title($title)
{
    $query = new WP_Query([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'title'          => $title,
        'posts_per_page' => 1,
    ]);

    if ($query->have_posts()) {
        $query->the_post();
        $page = get_post();
        wp_reset_postdata();

        return $page;
    }
}

/**
 * Change page slugs.
 *
 * Make sure to also add resource to change_post_type_slug function below - ONLY If using post_type in permalinks
 */
function optipub_update_page_slug()
{
    $page_array = [
        'Resources' => optipub_get_page_by_title('Resources'),
        'Reports'   => optipub_get_page_by_title('Reports'),
    ];

    $new_page_array = [
        'Resources' => 'Investor Resources',
        'Reports'   => 'Investment Opportunities',
    ];

    foreach ($page_array as $key => $val) {
        if (isset($val->post_title)) {
            if ($val->post_title !== $new_page_array[$key]) {
                $post_id = $val->ID;
                $post_title = $new_page_array[$key];
                $post_name = sanitize_title($new_page_array[$key]);
                $post_type = $val->post_type;
                $post_excerpt = str_replace($key, $new_page_array[$key], $val->post_excerpt);

                if ($post_title = 'Investment Opportunities') {
                    $post_excerpt = 'Investment opportunities are specific investment options that offer the possibility of generating a return on investment.';
                }

                if ('page' == get_post_type($post_id) && 'page' == $post_type) {
                    $update_page = [
                        'ID'           => $post_id,
                        'post_title'   => $post_title,
                        'post_name'    => $post_name,
                        'post_type'    => $post_type,
                        'post_excerpt' => $post_excerpt,
                    ];

                    wp_update_post($update_page, true);
                }
            }
        }
    }
}
/*
add_action( 'init', 'optipub_update_page_slug' );
*/

/**
 * Change Post Type Slugs - ONLY NEEDED If using post_type in permalink
 * reports to investment-opportunities
 * resources to investor-resources.
 */
function optipub_change_post_types_slug($args, $post_type)
{
    $slug_array = [
        'resources' => 'investor-resources',
        'reports'   => 'investment-opportunities',
    ];

    if (array_key_exists($post_type, $slug_array)) {
        $args['rewrite']['slug'] = $slug_array[$post_type];
    }

    return $args;
}
/* UNCOMMENT IF USING POST_TYPE IN PERMALINKS */
/*
add_filter( 'register_post_type_args', 'optipub_change_post_types_slug', 20, 2 );
*/

/**
 * Add all post types to RSS Feed.
 */
function optipub_feed_request($qv)
{
    if (isset($qv['feed']) && ! isset($qv['post_type'])) {
        $qv['post_type'] = optipub_return_optipub_post_types();
    }

    return $qv;
}
add_filter('request', 'optipub_feed_request');

/**
 * Add the post thumbnail, if available, before the content in feeds.
 *
 * @param string $content the post content
 *
 * @return string
 */
function optipub_rss_post_thumbnail($content)
{
    global $post;

    if (is_singular()) {
        $postID = $post->ID;
        $page_meta_image = get_post_meta($postID, 'resource_thumbnail_path', true);

        if (! empty($page_meta_image)) {
            $content = '<p><img class="card-img" src="' . $page_meta_image . '"></p>' . $content;
        }
    }

    return $content;
}
add_filter('the_excerpt_rss', 'optipub_rss_post_thumbnail');
add_filter('the_content_feed', 'optipub_rss_post_thumbnail');
