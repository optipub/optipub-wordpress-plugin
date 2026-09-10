<?php

/**
 * OptiPub
 *
 * @package           OptiPub
 * @author            OptiPub
 * @copyright         2024 OptiPub
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       OptiPub
 * Description:       A plugin that syncs content from OptiPub API and builds a client website based on the selected publication.
 * Version:           1.0.25
 * Requires PHP:      7.4
 * Author:            OptiPub
 * Author URI:        https://www.optipub.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       optipub
 */

// If this file is called directly, abort
defined('ABSPATH') or die('Direct access not allowed');

// Define plugin constants for paths and URLs
if (!defined('OPTIPUB_PLUGIN_FILE')) {
    define('OPTIPUB_PLUGIN_FILE', __FILE__);
}

if (!defined('OPTIPUB_PLUGIN_DIR')) {
    define('OPTIPUB_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('OPTIPUB_PLUGIN_URL')) {
    define('OPTIPUB_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Require once the Composer Autoload
if (file_exists(OPTIPUB_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once OPTIPUB_PLUGIN_DIR . 'vendor/autoload.php';
}

// Near the top where you include other files
require_once OPTIPUB_PLUGIN_DIR . 'inc/Functions/UserRoles.php';

// Plugin activation/deactivation hooks
register_activation_hook(OPTIPUB_PLUGIN_FILE, function ($network_wide) {
    OptiPub\Base\Activate::activate($network_wide);
});

register_deactivation_hook(OPTIPUB_PLUGIN_FILE, function () {
    OptiPub\Base\Deactivate::deactivate();
});

// Initialize plugin
if (class_exists('OptiPub\\Init')) {
    OptiPub\Init::register_services();
}

// Initialize global UserAccess instance
if (class_exists('OptiPubWP\\PubAccess\\UserAccess')) {
    global $optipub_user_access;
    $optipub_user_access = new OptiPubWP\PubAccess\UserAccess();
}

// Store plugin path globally
$GLOBALS['optipub_plugin_path'] = plugin_dir_path(__FILE__);

function optipub_redirect_favicon_ico(): void
{
    if (is_admin() || wp_is_json_request() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI'])
        ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
        : '';
    $path = trim(strtolower(wp_parse_url($request_uri, PHP_URL_PATH) ?? ''), '/');

    if ($path !== 'favicon.ico') {
        return;
    }

    $favicon_url = get_option(OptiPub\Base\Constants::OPTIPUB_SITE_FAVICON_SRC, '');

    if (empty($favicon_url) || !is_string($favicon_url)) {
        return;
    }

    $favicon_host = wp_parse_url($favicon_url, PHP_URL_HOST);

    if (!empty($favicon_host)) {
        $GLOBALS['optipub_favicon_redirect_host'] = $favicon_host;
        add_filter('allowed_redirect_hosts', 'optipub_allow_favicon_redirect_host');
    }

    $redirected = wp_safe_redirect(esc_url_raw($favicon_url), 302, 'OptiPub');

    if (!empty($favicon_host)) {
        remove_filter('allowed_redirect_hosts', 'optipub_allow_favicon_redirect_host');
        unset($GLOBALS['optipub_favicon_redirect_host']);
    }

    if ($redirected) {
        exit;
    }
}
add_action('plugins_loaded', 'optipub_redirect_favicon_ico', PHP_INT_MIN);

function optipub_allow_favicon_redirect_host(array $hosts): array
{
    $favicon_host = isset($GLOBALS['optipub_favicon_redirect_host'])
        ? (string) $GLOBALS['optipub_favicon_redirect_host']
        : '';

    if ($favicon_host !== '' && !in_array($favicon_host, $hosts, true)) {
        $hosts[] = $favicon_host;
    }

    return $hosts;
}

/**
 * Disable WordPress wpautop filter
 */
function optipub_remove_wpautop_filter(): void
{
    if (!is_admin()) {
        remove_filter('the_content', 'wpautop');
        remove_filter('the_excerpt', 'wpautop');
    }
}
add_action('wp_loaded', 'optipub_remove_wpautop_filter');

/**
 * Disable WordPress Built-In Redirection
 *
 * Canonical redirects are handled by OptiPub
 */
remove_filter('template_redirect', 'redirect_canonical');

/**
 * Redirect old website links using "post_type/post_name/id" url structure
 * compatible with new root type url structure.
 */
function optipub_redirect_to_meta_permalinks(): void
{
    // Get permalink structure
    $permalink_structure = get_option('permalink_structure');
    $permalink_structure = trim($permalink_structure, '/');
    $permalink_structure = explode('/', $permalink_structure);

    // Create full URL from server request
    $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
        '://' . (isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '') . (isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '');

    $url = wp_parse_url($link);
    $url['path'] = isset($url['path']) ? strtolower($url['path']) : '';

    // Get public optipub post types
    $post_types = get_post_types([
        'public'   => true,
        '_builtin' => false,
    ], 'names', 'and');

    // Remove excluded post types
    $except_types = ['editors', 'page'];
    $post_types = array_diff($post_types, $except_types);

    optipub_handle_legacy_urls($url, $post_types);
}
/*add_action('parse_request', 'optipub_redirect_to_meta_permalinks', 1);*/

/**
 * Handle legacy URL structures and redirects
 */
function optipub_handle_legacy_urls(array $url, array $post_types): void
{
    // Regular expression for old slug type ('post_type'/'post_name'/'optipub_page_id')
    $regex_match = '#^[\/](?P<post_type>[^\/]+)[\/](?P<post_name>[a-z0-9_\-]+)[\/]?(?P<optipub_page_id>\d+)?[\/]?$#';
    preg_match($regex_match, $url['path'], $matches);

    if ($matches) {
        optipub_handle_matched_url($matches, $url, $post_types);
    }
}

/**
 * Handle matched legacy URLs
 */
function optipub_handle_matched_url(array $matches, array $url, array $post_types): void
{
    // Skip archive pages
    if ($matches['post_name'] === 'page') {
        return;
    }

    // Fix report type
    $matches['post_type'] = $matches['post_type'] === 'report' ? 'reports' : $matches['post_type'];

    if (!in_array($matches['post_type'], $post_types)) {
        return;
    }

    $post = optipub_find_matching_post($matches);

    if ($post) {
        optipub_perform_redirect($post, $url);
    } else {
        optipub_perform_fallback_redirect($matches['post_name'], $url);
    }
}

/**
 * Find matching post from legacy URL
 */
function optipub_find_matching_post(array $matches)
{
    if (isset($matches['optipub_page_id'])) {
        $post_id = OptiPub\Api\WpDatabaseQuery::get_post_id_by_meta_key_and_value(
            'optipub_page_id',
            $matches['optipub_page_id'],
            $matches['post_type']
        );

        if ($post_id) {
            return get_post($post_id);
        }
    }

    $matches['post_name'] = trim($matches['post_name'], '-');

    return get_page_by_path($matches['post_name'], OBJECT, $matches['post_type']);
}

/**
 * Perform redirect for matched post
 */
function optipub_perform_redirect($post, array $url): void
{
    $redirect_url = home_url('/' . $post->post_name . '/');

    if (isset($url['query'])) {
        $redirect_url .= '?' . $url['query'];
    }
    wp_redirect($redirect_url, 301);
    exit;
}

/**
 * Perform fallback redirect when no post found
 */
function optipub_perform_fallback_redirect(string $post_name, array $url): void
{
    $redirect_url = home_url('/' . $post_name . '/');

    if (isset($url['query'])) {
        $redirect_url .= '?' . $url['query'];
    }
    wp_redirect($redirect_url, 301);
    exit;
}

/**
 * Add CPT post names to main query
 *
 * with optipub rewrite rules
 */
function optipub_rewrite_rules_main_query($query)
{
    // Only modify the main query on the front end.
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }

    // Retrieve the query vars set by the rewrite rule
    $global_prefix = $query->get('global_prefix') ?? '';
    $publication_slug = $query->get('optipub_publication');
    $parent_path = $query->get('parent_path') ?? '';

    if ($query->get('name')) {
        $post_name = $query->get('name');

        // Restrict to the allowed post types.
        $query->set('post_type', optipub_get_optipub_post_types());
    } elseif ($query->get('pagename')) {
        $post_name = $query->get('pagename');

        // Restrict to the allowed post types.
        $query->set('post_type', 'page');
    } else {
        return;
    }

    /*
    if ($query->get('post_type') === 'editors') {
        // Determine if 'editors' post type is requested
        $query->set( 'post_type', 'editors' );
    }
    */

    // Update tax query based on publication slug
    if ($publication_slug) {
        if ($global_prefix !== get_option(OptiPub\Base\Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX)) {
            return;
        }

        // Set tax_query to fetch posts with the publication slug
        $tax_query = [
            [
                'taxonomy' => OptiPub\Base\Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                'field'    => 'slug',
                'terms'    => $publication_slug,
            ],
        ];
        $query->set('tax_query', $tax_query);
    } else {
        // When no publication slug is present, use the primary publication
        $tax_query = [
            [
                'taxonomy'   => OptiPub\Base\Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                'meta_key'   => 'publication_primary',
                'meta_value' => '1',
            ],
        ];
        $query->set('tax_query', $tax_query);
    }
}
add_action('pre_get_posts', 'optipub_rewrite_rules_main_query');

function optipub_get_optipub_post_types()
{
    $post_types = OptiPub\Api\WpDatabaseQuery::getAllOptiPubPostTypes(
        [],
        false,
        false,
        true
    );
    $post_types[] = 'page'; // Add pages to the types we handle

    return $post_types;
}

/**
 * Add CPT post names to main query
 *
 * This function is necessary to handle urls without post_type and
 * to support the same post_name across multiple publications
 */
function optipub_add_cpt_post_names_to_main_query2($query): void
{
    global $wpdb;

    if (!$query->is_main_query() || is_admin() || empty($query->query['name'])) {
        return;
    }

    $post_name = $query->query['name'];

    // Get current URL to determine if we're in a publication context
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $path_parts = explode('/', trim($request_uri, '/'));

    // Get all publications
    $publications = get_terms([
        'taxonomy'   => OptiPub\Base\Constants::OPTIPUB_PUBLICATION_TAXONOMY,
        'hide_empty' => false,
    ]);

    // Check if we're in a non-primary publication context
    $current_publication_id = null;
    $publication_prefix = get_option(OptiPub\Base\Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX);

    if (!empty($path_parts[0])) {
        if ($publication_prefix && $path_parts[0] === $publication_prefix) {
            // URL format: /prefix/pubslug/post-name
            if (!empty($path_parts[1])) {
                foreach ($publications as $pub) {
                    if ($pub->slug === $path_parts[1]) {
                        $current_publication_id = get_term_meta($pub->term_id, 'publication_id', true);
                        break;
                    }
                }
            }
        } else {
            // URL format: /pubslug/post-name (no prefix)
            foreach ($publications as $pub) {
                if ($pub->slug === $path_parts[0]) {
                    $current_publication_id = get_term_meta($pub->term_id, 'publication_id', true);
                    break;
                }
            }
        }
    }

    // If no publication context was found, assume primary publication
    if ($current_publication_id === null) {
        // Find the primary publication
        foreach ($publications as $pub) {
            $is_primary = get_term_meta($pub->term_id, 'publication_primary', true);

            if ($is_primary === '1') {
                $current_publication_id = get_term_meta($pub->term_id, 'publication_id', true);
                break;
            }
        }
    }

    // Prepare query with publication context if available
    $meta_query = '';

    if ($current_publication_id) {
        $meta_query = $wpdb->prepare(" AND ID IN (
            SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = 'publication_id' AND meta_value = %s
        )", $current_publication_id);
    }

    // Query posts with the given name and publication context
    $sql = "SELECT ID, post_type 
        FROM {$wpdb->posts} 
        WHERE post_status = 'publish' AND post_name = %s {$meta_query}
        LIMIT 1";
    $args = [$post_name];

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $result = $wpdb->get_row($wpdb->prepare($sql, ...$args));

    if (!$result) {
        return;
    }

    // Get OptiPub post types
    $post_types = OptiPub\Api\WpDatabaseQuery::getAllOptiPubPostTypes(
        ['page', 'editors'], // Exclude page
        false,    // Don't require posts
        false,    // Don't include built-in types
        true      // Only OptiPub types
    );

    if (in_array($result->post_type, $post_types)) {
        $query->set('post_type', $result->post_type);
        $query->set('p', $result->ID); // Use exact post ID to avoid ambiguity
        $query->is_single = true;
        $query->is_page = false;
    }
}
/*add_action('pre_get_posts', 'optipub_add_cpt_post_names_to_main_query2');*/

/**
 * Process OptiPub redirects
 */
function optipub_do_redirect(): void
{
    if (is_admin() || wp_is_json_request() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return;
    }

    global $wpdb;
    $redirects_table = $wpdb->prefix . 'optipub_redirects';

    $current_url = optipub_get_current_url();
    $url = wp_parse_url($current_url);
    $url['path'] = isset($url['path']) ? ltrim(strtolower($url['path']), '/') : '';

    if (preg_match('/^wp-/', $url['path'])) {
        return;
    }

    optipub_process_redirects($url, $redirects_table);
}
add_action('init', 'optipub_do_redirect', 10);

/**
 * Get current URL
 */
function optipub_get_current_url(): string
{
    $protocol = isset($_SERVER['HTTPS']) && sanitize_text_field(wp_unslash($_SERVER['HTTPS'])) === 'on' ? 'https' : 'http';

    return $protocol . '://' . (isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '') . (isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '');
}

/**
 * Process redirects from database
 */
function optipub_process_redirects(array $url, string $redirects_table): void
{
    global $wpdb;

    // Try non-regex redirects first
    $redirect = optipub_find_non_regex_redirect($url, $redirects_table);

    // If no non-regex redirect found, try regex redirects
    if (!$redirect) {
        $redirect = optipub_find_regex_redirect($url, $redirects_table);
    }

    if ($redirect) {
        optipub_perform_full_redirect($redirect, $url);
    }
}

/**
 * Find non-regex redirect
 */
function optipub_find_non_regex_redirect(array $url, string $redirects_table)
{
    global $wpdb;

    $rtrim_path = rtrim($url['path'], '/');
    $host = $url['host'] ?? '';
    $scheme = $url['scheme'] ?? 'https';

    $query = "SELECT from_url, to_url, code FROM {$redirects_table} 
         WHERE (from_url = %s OR from_url = %s OR from_url = %s) 
         AND regex = 0 LIMIT 1";
    $args = [
        $rtrim_path,
        $host ? $host . '/' . $rtrim_path : $rtrim_path,
        $host ? $scheme . '://' . $host . '/' . $rtrim_path : $rtrim_path,
    ];

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    return $wpdb->get_row($wpdb->prepare($query, ...$args));
}

/**
 * Find regex redirect
 */
function optipub_find_regex_redirect(array $url, string $redirects_table)
{
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $redirects = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: Is it prepared already.
        $wpdb->prepare("SELECT from_url, to_url, code FROM {$redirects_table} WHERE regex = 1")
    );

    $host = $url['host'] ?? '';
    $scheme = $url['scheme'] ?? 'http';
    $path = $url['path'] ?? '';

    foreach ($redirects as $redirect) {
        $pattern = "<{$redirect->from_url}>";
        $test_urls = [
            $path,
            $host ? $host . '/' . $path : $path,
            $host ? $scheme . '://' . $host . '/' . $path : $path,
        ];

        foreach ($test_urls as $test_url) {
            if (preg_match($pattern, $test_url, $matches)) {
                $redirect->to_url = optipub_process_regex_matches($redirect->to_url, $matches);

                return $redirect;
            }
        }
    }
}

/**
 * Process regex matches for redirect URL
 */
function optipub_process_regex_matches(string $to_url, array $matches): string
{
    $subpattern_count = substr_count($to_url, '$');

    for ($i = 1; $i <= $subpattern_count; $i++) {
        $to_url = str_replace('$' . $i, $matches[$i], $to_url);
    }

    return $to_url;
}

/**
 * Perform OptiPub redirect
 */
function optipub_perform_full_redirect($redirect, array $url): void
{
    $redirect_url = optipub_build_redirect_url($redirect->to_url, $url);

    if ($redirect->code === '301') {
        header('HTTP/1.1 301 Moved Permanently');
    } elseif ($redirect->code === '307') {
        header('HTTP/1.1 307 Temporary Redirect');
    } elseif ($redirect->code === '308') {
        header('HTTP/1.1 308 Permanent Redirect');
    }

    header('Location: ' . $redirect_url, true, $redirect->code);
    exit;
}

/**
 * Build redirect URL
 */
function optipub_build_redirect_url(string $to_url, array $url): string
{
    if (!preg_match("#^([^:\/?\\#]+:\/\/)#", $to_url)) {
        if (!preg_match("#^([^:\/?\\#]+:\/\/)?(([^\/?\\#]+)(\.)([^\/?\\#.]+))#", $to_url)) {
            $to_url = $url['scheme'] . '://' . $url['host'] . '/' . $to_url;
        } else {
            $to_url = $url['scheme'] . '://' . $to_url;
        }
    }

    if (isset($url['query'])) {
        $to_url .= '?' . $url['query'];
    }

    return $to_url;
}

/**
 * Remove OptiPub ad macros from content
 */
function optipub_remove_ad_macro(string $content): string
{
    return preg_replace(
        '/<div[^>]*op-macro[^>]*>\{\$ad(_\d+)+\}<\/div>/i',
        '',
        $content
    );
}
add_filter('the_content', 'optipub_remove_ad_macro', 999);
add_filter('the_content_feed', 'optipub_remove_ad_macro', 999);

/**
 * Remove OptiPub custom content macros from content
 */
function optipub_remove_cc_macro(string $content): string
{
    $content = preg_replace('/<[^>]*>\{\$custom_[^\}]*\}<\/[^>]*>/i', '', $content);

    return preg_replace('/\{\$custom_[^\}]*\}/i', '', $content);
}
add_filter('the_content', 'optipub_remove_cc_macro', 999);
add_filter('the_content_feed', 'optipub_remove_cc_macro', 999);

/* REMOVE THIS */
/**
 * Set theme's home page template
 */
/*
function optipub_custom_home_page_template(string $template): string
{
    if (is_page('home')) {
        $home_template = get_stylesheet_directory() . get_option(OptiPub\Base\Constants::OPTIPUB_HOME_TEMPLATE);
        return $home_template ?: $template;
    }
    return $template;
}
add_filter('template_include', 'optipub_custom_home_page_template');
*/

/**
 * Preserve custom post status on update
 */
function optipub_preserve_custom_post_status(array $data, array $postarr): array
{
    // Skip if no post ID (new post) or if we're not updating post_status
    if (empty($postarr['ID']) || !isset($data['post_status'])) {
        return $data;
    }

    $previous_status = get_post_status($postarr['ID']);
    $new_status = $data['post_status'];

    // If post was featured and is being set to publish, preserve featured status
    if ($previous_status === 'featured' && $new_status === 'publish') {
        $data['post_status'] = 'featured';
    }

    return $data;
}
add_filter('wp_insert_post_data', 'optipub_preserve_custom_post_status', 10, 2);

/**
 * Add OptiPub meta box to post types
 */
function optipub_add_custom_meta_box(): void
{
    $post_types = ['articles', 'reports', 'resources', 'editors', 'videos', 'faqs', 'page'];

    foreach ($post_types as $post_type) {
        add_meta_box(
            'custom-meta-box',
            'OptiPub Post Meta',
            'optipub_custom_meta_box_callback',
            $post_type,
            'side',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'optipub_add_custom_meta_box');

/**
 * Render OptiPub meta box content
 */
function optipub_custom_meta_box_callback($post): void
{
    $meta_data = get_post_meta($post->ID);
    $content_type = optipub_get_content_type($post->post_type);
    $optipub_id = $meta_data['optipub_page_id'][0] ?? '';
    $featured_image = optipub_get_featured_image($meta_data);

    $edit_link = rtrim(get_option(OptiPub\Base\Constants::OPTIPUB_BASE_API_URL), '/') .
        '/content/' . $content_type . '/' . $optipub_id . '/edit';

    include plugin_dir_path(__FILE__) . 'admin/partials/meta-box.php';
}

/**
 * Get content type for meta box
 */
function optipub_get_content_type(string $post_type): string
{
    $types = [
        'videos'  => 'videos',
        'faqs'    => 'faqs',
        'page'    => 'pages',
        'editors' => 'authors',
        'default' => 'resources',
    ];

    return $types[$post_type] ?? $types['default'];
}

/**
 * Get featured image for meta box
 */
function optipub_get_featured_image(array $meta_data): string
{
    $image_keys = ['resource_thumbnail_path', 'editor_website_image_path', 'video_thumbnail_path'];

    foreach ($image_keys as $key) {
        if (!empty($meta_data[$key][0])) {
            return $meta_data[$key][0];
        }
    }

    return '';
}

/**
 * Register OptiPub widgets and sidebars
 */
function optipub_plugin_widgets_init(): void
{
    $optipub_widgets = get_option(OptiPub\Base\Constants::OPTIPUB_WIDGET_OPTION, []);

    foreach ($optipub_widgets as $widget) {
        $classname = $widget['class'] ?? $widget['id'];

        register_sidebar([
            'name'          => $widget['name'],
            'id'            => $widget['id'],
            'description'   => $widget['description'] ?? '',
            'class'         => $classname,
            'before_widget' => $widget['before_widget'] ?? '',
            'after_widget'  => $widget['after_widget'] ?? '',
            'before_title'  => $widget['before_title'] ?? '',
            'after_title'   => $widget['after_title'] ?? '',
        ]);

        register_widget(new OptiPub\Pages\Admin\OptiPubWidgets($widget['id'], $widget['name']));
    }
}
add_action('widgets_init', 'optipub_plugin_widgets_init');

/**
 * Enqueue OptiPub scripts
 */
function optipub_enqueue_scripts(): void
{
    $optipub_url = get_option(OptiPub\Base\Constants::OPTIPUB_BASE_API_URL);

    if ($optipub_url) {
        wp_enqueue_script(
            'optipub-elements-js',
            $optipub_url . '/elements/js/elements.js',
            [],
            '1.0.0',
            true
        );

        wp_script_add_data('optipub-elements-js', 'strategy', 'defer');
    }
}
// Enqueue after theme assets so a slow elements bundle cannot delay critical UI scripts.
add_action('wp_enqueue_scripts', 'optipub_enqueue_scripts', 20);

/**
 * Add non-blocking attributes to the OptiPub elements script
 */
function optipub_add_charset_to_script(string $tag, string $handle): string
{
    if ($handle === 'optipub-elements-js') {
        if (strpos($tag, ' charset=') === false) {
            $tag = str_replace('<script ', '<script charset="utf-8" ', $tag);
        }

        // Fallback for WordPress versions that do not render the strategy data.
        if (strpos($tag, ' defer') === false) {
            $tag = str_replace('<script ', '<script defer ', $tag);
        }
    }

    return $tag;
}
add_filter('script_loader_tag', 'optipub_add_charset_to_script', 10, 2);

/**
 * Get OptiPub SDK URL from configured OptiPub Domain option
 */
function optipub_get_sdk_url_from_domain_option(): string
{
    $optipub_url = get_option(OptiPub\Base\Constants::OPTIPUB_BASE_API_URL);

    if (empty($optipub_url)) {
        return '';
    }

    $sdk_hostname = wp_parse_url($optipub_url, PHP_URL_HOST);

    if (empty($sdk_hostname)) {
        return '';
    }

    return 'https://' . $sdk_hostname . '/sdk/sdk.js';
}

/**
 * Add OptiPub SDK tracking tag to head
 */
function optipub_add_sdk_tracking_tag_to_head(): void
{
    $sdk_url = optipub_get_sdk_url_from_domain_option();

    if (empty($sdk_url)) {
        return;
    }

    ?>
    <!--- Optipub Tracking Tag --->
    <script>
    (function (window, document, tag, url, name, a, m) {
        window[name] = window[name] || function () {
            (window[name].q = window[name].q || []).push(arguments);
        };
        window[name].l = 1 * new Date();
        a = document.createElement(tag);
        m = document.getElementsByTagName(tag)[0];
        a.async = 1;
        a.src = url;
        m.parentNode.insertBefore(a, m);
    })(window, document, 'script', <?php echo wp_json_encode($sdk_url); ?>, 'optipub');
    </script>
    <!--- End Of Optipub Tracking Tag --->
<?php
}
add_action('wp_head', 'optipub_add_sdk_tracking_tag_to_head', 0);

/**
 * Remove legacy favicon MIME type from configured head tags.
 */
function optipub_remove_legacy_favicon_type(string $head_tags): string
{
    $updated_tags = preg_replace(
        '/\\s+type\\s*=\\s*(?:"image\\/x-icon"|\'image\\/x-icon\'|image\\/x-icon)(?=\\s|>|\\/)/i',
        '',
        $head_tags
    );

    return is_string($updated_tags) ? $updated_tags : $head_tags;
}

/**
 * Add OptiPub website tags to head
 */
function optipub_add_website_tags_to_head(): void
{
    $website_tags = get_option(OptiPub\Base\Constants::OPTIPUB_SELECTED_DOMAIN_WEBSITE_TAGS);

    if ($website_tags) {
        // Define allowed HTML tags and attributes for website tracking tags
        $allowed_tags = [
            'script' => [
                'src'         => true,
                'type'        => true,
                'async'       => true,
                'defer'       => true,
                'charset'     => true,
                'crossorigin' => true,
            ],
            'meta' => [
                'name'     => true,
                'content'  => true,
                'property' => true,
                'charset'  => true,
            ],
            'link' => [
                'rel'         => true,
                'href'        => true,
                'type'        => true,
                'sizes'       => true,
                'crossorigin' => true,
            ],
            'style' => [
                'type'  => true,
                'media' => true,
            ],
            'noscript' => [],
        ];

        $sanitized_website_tags = wp_kses($website_tags, $allowed_tags);

        echo optipub_remove_legacy_favicon_type($sanitized_website_tags) . "\n";
    }
}
add_action('wp_head', 'optipub_add_website_tags_to_head', 999);

/**
 * Add Google Tag Manager tags to head
 */
function optipub_add_gtm_tags()
{
    $data = get_option(OptiPub\Base\Constants::OPTIPUB_SELECTED_PUBLICATION_DATA);

    // Check if the data structure exists before accessing
    if (!is_object($data) || !isset($data->google_tag_manager_containers) || !is_array($data->google_tag_manager_containers) || empty($data->google_tag_manager_containers)) {
        return;
    }

    $container_ids = [];

    // Process each Web container
    foreach ($data->google_tag_manager_containers as $container) {
        if (is_object($container) && isset($container->type) && $container->type === 'Web' && isset($container->container_id) && !empty($container->container_id)) {
            $container_id = esc_js($container->container_id);
            $container_ids[] = $container_id;

            // Add body noscript for this container (JavaScript disabled fallback)
            add_action('wp_body_open', function () use ($container_id) {
                ?>
                <!-- Google Tag Manager (noscript) -->
                <noscript>
                    <iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($container_id); ?>"
                        height="0"
                        width="0"
                        style="display:none;visibility:hidden">
                    </iframe>
                </noscript>
                <!-- End Google Tag Manager (noscript) -->
<?php
            }, 1);
        }
    }

    if (!empty($container_ids)) {
        add_action('wp_enqueue_scripts', function () use ($container_ids) {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'optipub-gtm',
                plugin_dir_url(__FILE__) . 'public/js/gtm.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('optipub-gtm', 'optipubGTM', [
                'containers' => $container_ids
            ]);
        });
    }
}
add_action('init', 'optipub_add_gtm_tags');

/**
 * Set OptiPub visitor cookie
 */
function optipub_set_visitor_cookie_init(): void
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External traffic from OptiPub campaigns, nonce verification not applicable
    if (isset($_GET['utm_optipub'], $_GET['identifier'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External traffic from OptiPub campaigns, input is sanitized
        $identifier = sanitize_text_field(wp_unslash($_GET['identifier']));
        setcookie('optipub_visitor', $identifier, time() + DAY_IN_SECONDS, '/');
        $_COOKIE['optipub_visitor'] = $identifier;
    }
}
add_action('init', 'optipub_set_visitor_cookie_init');

/**
 * Remove wpautop and wptexturize filters
 */

function optipub_remove_content_filters(): void
{
    if (!is_admin()) {
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'wptexturize');
        remove_filter('the_excerpt', 'wpautop');
        remove_filter('the_excerpt', 'wptexturize');
    }
}
/*add_action('init', 'remove_content_filters', 999);*/

/**
 * Disable autop for pages specifically
 */
/*
 function optipub_disable_autop_for_pages($content) {
    if (is_page()) {
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'wptexturize');
    }
    return $content;
}
add_filter('the_content', 'disable_autop_for_pages', 0);
*/

function optipub_get_primary_publication_id(): int
{
    // Check if taxonomy is registered
    if (!taxonomy_exists(OptiPub\Base\Constants::OPTIPUB_PUBLICATION_TAXONOMY)) {
        return 0;
    }

    $primary_publication = get_terms([
        'taxonomy'   => OptiPub\Base\Constants::OPTIPUB_PUBLICATION_TAXONOMY,
        'hide_empty' => false,
        'meta_query' => [
            [
                'key'     => 'publication_primary',
                'value'   => '1',
                'compare' => '=',
            ],
        ],
    ]);

    // Check if we got valid results
    if (is_wp_error($primary_publication) || empty($primary_publication)) {
        return 0;
    }

    // Get publication_id from term meta
    $publication_id = get_term_meta($primary_publication[0]->term_id, 'publication_id', true);

    return (int) ($publication_id ?: 0);
}

/**
 * Get publication term data by publication ID
 *
 * @param int    $publication_id The OptiPub publication ID
 * @param string $meta_key       Optional specific meta key to retrieve
 * @return mixed Term object with meta, specific meta value if key provided, or false if not found
 *
 * no meta key - return data is $term->name and $term->meta['meta_key'][0]
 * meta key - return data is $termmeta
 */
function optipub_get_publication_data(int $publication_id, string $meta_key = ''): mixed
{
    $terms = get_terms([
        'taxonomy'   => OptiPub\Base\Constants::OPTIPUB_PUBLICATION_TAXONOMY,
        'hide_empty' => false,
        'meta_query' => [
            [
                'key'     => 'publication_id',
                'value'   => $publication_id,
                'compare' => '=',
            ],
        ],
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return false;
    }

    // Return specific meta value if key provided
    if ($meta_key) {
        return get_term_meta($terms[0]->term_id, $meta_key, true);
    }

    // Get all meta
    $term_meta = get_term_meta($terms[0]->term_id);

    // Add meta to term object
    $terms[0]->meta = $term_meta;

    return $terms[0];
}
