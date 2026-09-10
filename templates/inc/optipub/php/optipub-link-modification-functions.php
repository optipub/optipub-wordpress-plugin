<?php

if (! defined('ABSPATH')) {
    exit;
}

/*
 * * * OptiPub Link Modification Functions
 * * * Make sure you know what YOU are doing and what these FUNCTIONS are doing before using them  * * *
 *
 *
 * Modify Links in the_content filter
 * configure [filter] optipub_modify_the_content_links_internal_links( $content ) - Configure (uncomment) the link and internal link modification functions you want to enabled and what domains and sudomains to run them on.
 *
 * All Link Modification functions
 * [function] optipub_modify_all_links_href_http_with_https($content) - Replace http:// with https:// for ALL <a href> link elements in the_content.
 * [function] optipub_modify_all_links_img_http_with_https( $content ) - Replace http:// with https:// for ALL <img src> link elements in the_content.
 *
 * Internal Link Modification functions
 * [function] optipub_modify_internal_links_post_id_slug( $content, $domain ) - Replaces legacy internal target link slug structures - <post_name>/<optipub_id>
 * [function] optipub_modify_internal_links_resource_post_id_slug( $content, $domain ) - Replaces legacy internal target link slug structures - <post_type>/<post_name>/<optipub_id>
 * [function] optipub_modify_internal_links_legacy_subscribes( $content, $domain, $old_subdomain, $new_subdomain ) - Replaces old subscribe links with new subscribe links
 * [function] optipub_modify_internal_links_legacy_subscribes_subdomain( $content, $domain, $old_subdomain, $new_subdomain ) - Replaces subdomain for legacy subscribe links
 * [function] optipub_modify_internal_links_contraction_hyphens( $content, $domain ) - Removes hyphens that replaced apostrophes from word contractions
 * [function] optipub_modify_internal_links_plus_sign_replacements( $content, $domain ) - Replaces plus signs with hyphens
 * [function] optipub_modify_internal_links_large_number_hyphens( $content, $domain ) - Removes hyphens from numbers that have commas in them
 * [function] optipub_modify_internal_links_add_trailing_slash( $content, $domain ) - Add trailing slash to end of links
 * [function] optipub_modify_internal_links_remove_trailing_slash( $content, $domain ) - Removes trailing slash from end of links
 *
 */

function get_server_name(): string
{
    if (defined('WP_CLI') && WP_CLI) {
        return wp_parse_url(get_site_url(), PHP_URL_HOST) ?: 'localhost';
    }

    return sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'] ?? '')) ?: wp_parse_url(get_site_url(), PHP_URL_HOST) ?: 'localhost';
}

/**
 * Run content modification functions.
 *
 * @param string $content content of the wp_post to change
 *
 * @var string $old_subdomain subdomain to replace in internal link modification functions
 * @var string $new_subdomain subdomain to return in internal link modification functions
 * @var array  $domains domains to target in internal link modification functions
 *
 * @return string content of the wp_post after link modifications
 */
function optipub_modify_the_content_links_internal_links($content)
{
    /**
     * the_content link modifications - all links.
     */

    //$content = optipub_modify_all_links_href_http_with_https($content);
    //$content = optipub_modify_all_links_img_http_with_https($content);

    /**
     * the_content link modifications - internal links.
     */
    $old_subdomain = 'oldsubdomain';
    $new_subdomain = 'newsubdomain';

    // (Array) domains that will be modified by enabled modification functions
    $domains = [
        get_server_name(),
    ];

    /* uncomment these functions to enable them. You may need to modify the functions or add your own to this filter*/
    foreach ($domains as $domain) {
        //$content = optipub_modify_internal_links_post_id_slug($content, $domain);
        //$content = optipub_modify_internal_links_resource_post_id_slug($content, $domain);
        //$content = optipub_modify_internal_links_legacy_subscribes($content, $domain, $old_subdomain, $new_subdomain);
        //$content = optipub_modify_internal_links_legacy_subscribes_subdomain($content, $domain, $old_subdomain, $new_subdomain);
        //$content = optipub_modify_internal_links_contraction_hyphens($content, $domain);
        //$content = optipub_modify_internal_links_plus_sign_replacements($content, $domain);
        //$content = optipub_modify_internal_links_large_number_hyphens($content, $domain);

        /* Only enable 1 of these, either add trailing slash or remove trailing slash to internal links*/
        //$content = optipub_modify_internal_links_add_trailing_slash($content, $domain);
        //$content = optipub_modify_internal_links_remove_trailing_slash($content, $domain);
    }

    return $content;
}
add_filter('the_content', 'optipub_modify_the_content_links_internal_links', 999);
add_filter('the_content_feed', 'optipub_modify_the_content_links_internal_links', 999);

/**
 * Replace http:// with https:// for ALL <a href> elements in the_content.
 */
function optipub_modify_all_links_href_http_with_https($content)
{
    // Replace for href attributes
    $pattern_href = '/href=["\']http:\/\/([^"\']+)["\']/i';
    $replacement_href = 'href="https://$1"';

    return preg_replace($pattern_href, $replacement_href, $content);
}

/**
 * Replace http:// with https:// for ALL <img src> elements in the_content.
 */
function optipub_modify_all_links_img_http_with_https($content)
{
    // Replace for img src attributes
    $pattern_img = '/<img([^>]+)src=["\']http:\/\/([^"\']+)["\']/i';
    $replacement_img = '<img${1}src="https://${2}"';

    return preg_replace($pattern_img, $replacement_img, $content);
}

/*
 *
 * Below Functions fix legacy and broken internal target link structures in 'the_content' to work with Wordpress link structures.
 *
 * You can compare your old internal links with your wordpress sitemap to determine if you need to use any of these functions.
 *
 */

/**
 * Replaces legacy internal target link slug structures.
 *
 * Example: www.yourdomain.com/<resource>?/<post_name>/<post_id>
 *
 * Wordpress: www.yourdomain.com/<post_name>/
 */
function optipub_modify_internal_links_post_id_slug($content, $domain)
{
    $pattern_href = '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '\/-?([^\/\s"\'<>]+)-?\/\d+\/?["\']/i';
    $replacement_href = 'href="https://www.' . $domain . '/$4/"';

    return preg_replace($pattern_href, $replacement_href, $content);
}

/**
 * Replaces legacy internal target link slug structures.
 *
 * Example: www.yourdomain.com/<resource>?/<post_name>/<post_id>
 *
 * Wordpress: www.yourdomain.com/<post_name>/
 */
function optipub_modify_internal_links_resource_post_id_slug($content, $domain)
{
    $pattern_href = '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '\/(articles|resources|reports|videos)\/-?([^\s"\'<>]+)-?\/\d+\/?["\']/i';
    $replacement_href = 'href="https://www.' . $domain . '/$5/"';

    return preg_replace($pattern_href, $replacement_href, $content);
}

/**
 * Replaces old subscribe links with new subscribe links.
 *
 * Changes
 * secure.domain.com/subscribe/<effort_id>
 * to
 * secure2.domain.com/o/op/<effort_id>
 *
 *
 * You may need to heavily modify this if it is useful at all.
 *
 * You should most likely leave this commented out or remove it.
 */
function optipub_modify_internal_links_legacy_subscribes($content, $domain, $old_subdomain, $new_subdomain)
{
    $pattern_subscribe = '/href=["\']((http|https):\/\/)?' . $old_subdomain . '\.' . $domain . '\/subscribe\/(\d+)\/?(\?[^"\']*)?["\']/i';
    $replacement_subscribe = 'href="https://' . $new_subdomain . '.' . $domain . '/o/op/$3$4"';

    return preg_replace($pattern_subscribe, $replacement_subscribe, $content);
}

/**
 * Replace subscribe links subdomain in <a> elements.
 *
 * Changes
 * secure.domain.com/o/op/
 * to
 * secure2.domain.com/o/op/<id_num>
 */
function optipub_modify_internal_links_legacy_subscribes_subdomain($content, $domain, $old_subdomain, $new_subdomain)
{
    $pattern_secure = '/href=["\']((http|https):\/\/)?' . $old_subdomain . '\.' . $domain . '\/o\/(op|web)/i';
    $replacement_secure = 'href="https://' . $new_subdomain . '.' . $domain . '/o/$3';

    return preg_replace($pattern_secure, $replacement_secure, $content);
}

/**
 * Removes '-' that replaced ''' in word contractions.
 *
 * Wordpress removes apostrophes in contractions without adding a hyphen
 *
 * Example: www.yourdomain.com/i-m-glad-this-is-here-this-ll-be-helpful/
 *
 * Wordpress: www.yourdomain.com/im-glad-this-is-here-thisll-be-helpful/
 */
function optipub_modify_internal_links_contraction_hyphens($content, $domain)
{
    /**
     * Pattern 1 - Matches Internal URL Paths containing word contraction single letter endings:  -d, -l, -m, -s, or -t (example: can't)
     * Pattern 2 - Matches Internal URL paths containing word contraction two letter endings: -ll, -re, or -ve (example: should've)
     * Pattern 3 - Matches Internal URL paths containing word contraction for I: /i- or -i followed by either -m, -d, -ll, or -ve (example: I'm).
     */
    $patterns_contractions = [
        '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '\/([^\?"\']*[a-z][a-z](-[dlmst][\/-])[^\?"\']*)+(\?[^"\']*)?["\']/i',
        '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '\/([^\?"\']*(-ll[\/-]|-re[\/-]|-ve[\/-])[^\?"\']*)(\?[^"\']*)?["\']/i',
        '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '([^\?"\']*[\/-]i(-[md][\/-]|-ll[\/-]|-ve[\/-])[^\?"\']*)(\?[^"\']*)?["\']/i',
    ];

    foreach ($patterns_contractions as $index => $pattern) {
        $content = preg_replace_callback($pattern, function ($matches) use ($domain, $index) {
            // Check if post_name exists
            $op_post_types = optipub_return_optipub_post_types();
            $post_slug = explode('/', $matches[4]);

            if (2 == $index) {
                $post_object = get_page_by_path($post_slug[1], OBJECT, $op_post_types);

                if ('/' === $matches[4][0]) {
                    $matches[4] = substr($matches[4], 1);
                }
            } else {
                $post_object = get_page_by_path($post_slug[0], OBJECT, $op_post_types);
            }

            if ($post_object) {
                return $matches[0];
            }

            // Remove hyphen
            $replaced = str_replace($matches[5], '%%', $matches[4]);
            $replaced_segment = substr($matches[5], 1);

            // Insert replacement
            $replaced = str_replace('%%', $replaced_segment, $replaced);

            // Construct the entire URL
            return 'href="' .
                (isset($matches[1]) ? $matches[1] : '') .
                (isset($matches[3]) ? $matches[3] : '') .
                $domain . '/' .
                $replaced .
                (isset($matches[6]) ? $matches[6] : '') .
                '"';
        }, $content);
    }

    return $content;
}

/**
 * Replaces '+' with '-' before query parameters in internal links.
 *
 * This is usually done by user error
 *
 * Example: www.domain.com/my+users+make+a+lot+of+errors
 *
 * Wordpress: www.domain.com/my-users-make-a-lot-of-errors
 */
function optipub_modify_internal_links_plus_sign_replacements($content, $domain)
{
    $pattern_plus = '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '\/([^\?"\']+\+[^\?"\']*)+(\?[^"\']*)?["\']/i';

    return preg_replace_callback($pattern_plus, function ($matches) use ($domain) {
        // Replace + with - only in the matched part
        $replaced = str_replace('+', '-', $matches[4]);

        // Construct the entire URL
        return 'href="' .
            (isset($matches[1]) ? $matches[1] : '') .
            (isset($matches[3]) ? $matches[3] : '') .
            $domain . '/' .
            $replaced .
            (isset($matches[5]) ? $matches[5] : '') .
            '"';
    }, $content);
}

/**
 * Removes the '-' what replaces ',' in numbers like 1,000 in Titles.
 *
 * Example: www.domain.com/i-have-1-000-000-dollars
 *
 * Wordpress: www.domain.com/i-have-1000000-dollars
 */
function optipub_modify_internal_links_large_number_hyphens($content, $domain)
{
    $pattern_digits = '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '\/([^\?"\']*(\d-\d)[^\?"\']*)(\?[^"\']*)?["\']/i';

    return preg_replace_callback($pattern_digits, function ($matches) use ($domain) {
        // Remove - between numbers
        $replaced_segment = str_replace('-', '', $matches[5]);

        // Check if post_name exists
        $op_post_types = optipub_return_optipub_post_types();
        $post_slug = explode('/', $matches[4]);
        $post_object = get_page_by_path($post_slug[0], OBJECT, $op_post_types);

        if ($post_object) {
            return $matches[0];
        }

        // Remove hyphen between numbers
        $replaced = str_replace($matches[5], '%%', $matches[4]);
        $replaced_segment = str_replace('-', '', $matches[5]);

        // Insert replacement
        $replaced = str_replace('%%', $replaced_segment, $replaced);

        // Construct the entire URL
        return 'href="' .
            (isset($matches[1]) ? $matches[1] : '') .
            (isset($matches[3]) ? $matches[3] : '') .
            $domain . '/' .
            $replaced .
            (isset($matches[6]) ? $matches[6] : '') .
            '"';
    }, $content);
}

/**
 * For SEO purposes you should make sure all links end with a trailing slash, or without a trailing slash so search engines don't see these as separate links.
 *
 * You can create a Regex Redirect in OptiPub to redirect links to the chosen link.
 *
 * And you can use one of these filters so your internal links in the_content contain the chosen structure.
 *
 * -- Create a 'Regex Redirect' in OptiPub > Content > Redirects --
 *
 * - optipub redirects parse out the query parameters before redirecting do you don't have to worry about whats after the ?
 * - these expressions will make sure to not modify uris ending with a file extension
 *
 * -- redirect w/o trailing slash to trailing slash --
 * From
 * ^([a-zA-Z0-9_+~\-\/]+[^\/])$
 * To
 * /$1/
 *
 * -- redirect w/ trailing slash to w/o traliing slash --
 * From
 * ^([a-zA-Z0-9_+~\-\/]+([\/])$
 * To
 * /$1
 */

/**
 * Add trailing slash to internal links without it.
 *
 * unless link ends with a file extension
 */
function optipub_modify_internal_links_add_trailing_slash($content, $domain)
{
    $pattern_slash = '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '(\/[a-zA-Z0-9_+~\-\/]*[^\/])?(\?[^"\']*)?["\']/i';
    $replacement_slash = 'href="https://www.' . $domain . '$4/$5"';

    return preg_replace($pattern_slash, $replacement_slash, $content);
}

/**
 * Remove trailing slash from internal links.
 */
function optipub_modify_internal_links_remove_trailing_slash($content, $domain)
{
    $pattern_slash = '/href=["\']((http|https):\/\/)?(www\.)?' . $domain . '(\/[a-zA-Z0-9_+~\-\/]*([\/]))?(\?[^"\']*)?["\']/i';
    $replacement_slash = 'href="https://www.' . $domain . '$4/$6"';

    return preg_replace($pattern_slash, $replacement_slash, $content);
}
