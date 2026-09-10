<?php

if (! defined('ABSPATH')) {
    exit;
}

/*
 * * * Custom OptiPub Functions For Any Theme
 *
 * [function] optipub_return_current_theme_attribute_from_object($attr_key) - Return an attribute of the current theme from the value of the key parameter.
 * configure [function] optipub_return_website_attribute($attr_key) - Return a company attribute value from the matching array key parameter.
 * configure [function] optipub_return_opengraph_image_url() -  Return the opengraph image link set in this function when called from the theme
 *
 * [shortcode] optipub_social_buttons($content) - (customize $twitter_handle) - OptiPub Social Buttons
 * [action] optipub_categories_meta_tag() - (customize Variable Definitions) - Add a custom meta name to header of posts that includes all OptiPub Tags
 *
 * [action] optipub_theme_enqueue_scripts() - Enqueue Scripts for css / js
 */

/**
 * Return an attribute of the current theme from the value of the key parameter.
 *
 * This is useful to get the TextDomain for language translation, not sure what else it's good for.
 *
 * @param string $attr_key key of theme object for the value you want returned
 *
 * @var object $theme variable to hold the wordpress theme object
 *
 * @return string value of key parameter of theme object or a blank string
 */
function optipub_return_current_theme_attribute_from_object($attr_key)
{
    $theme = wp_get_theme();

    return $theme->get($attr_key) ?? '';
}

/**
* Return a company attribute from the value of the matching array key parameter.
*
* @param string $attr_key key of array for the value you want returned
*
* @var array $company_array key-value array to hold parent company attributes and keys to match
*
* @return string parent company attribute if key matched or a blank string
*/
function optipub_return_website_attribute($attr_key)
{
    $company_attr_array = [
        'business_name' => 'OptiPub, LLC',
        'friendly_name' => 'OptiPub',
        'website_url'   => 'https://www.optipub.com',
        'business_logo' => get_option('optipub_site_logo', ''),
        'light_600x200' => OPTIPUB_PLUGIN_URL . 'public/img/optipub-logo-600-x-200-17.png',
        'dark_888x248'  => OPTIPUB_PLUGIN_URL . 'public/img/optipub-dark-192.png',
    ];

    return $company_attr_array[$attr_key] ?? '';
}

/**
 * Return the opengraph image link set in this function when called from the theme.
 *
 * @var string $og_image_url an opengraph image link to display the opengraph image as a fallback / default image when an image isn't set in the resource
 *
 * @return string opengraph image link
 */
function optipub_return_opengraph_image_url()
{
    return OPTIPUB_PLUGIN_URL . 'public/img/optipub-opengraph-wp-1200x787-903.png';
}

/**
 * Social Button Shortcode to add to posts
 */
function optipub_social_buttons($atts = [], $content = '', $tag = '')
{
    if (is_singular() || is_home()) {
        /**
         * Variable Definitions.
         */
        $twitter_handle = 'optipub-wordpress';

        // Get current page URL
        $sb_url = urlencode(get_permalink());

        // Get current page title
        $sb_title = str_replace(' ', '%20', get_the_title());

        // Construct sharing URL without using any script
        $twitterURL = 'https://twitter.com/intent/tweet?text=' .
            $sb_title .
            '&amp;url=' .
            $sb_url .
            '&amp;via=' . $twitter_handle;
        $facebookURL = 'https://www.facebook.com/sharer/sharer.php?u=' . $sb_url;
        $whatsappURL = 'whatsapp://send?text=' . $sb_title . ' ' . $sb_url;
        $linkedInURL = 'https://www.linkedin.com/shareArticle?mini=true&url=' .
            $sb_url .
            '&amp;title=' .
            $sb_title;

        // Add sharing button at the end of page/page content
        $content .= '<div class="social-box text-center"><div class="social-btn">';
        $content .= '<a class="sbtn s-twitter" href="' .
            $twitterURL .
            '" target="_blank" rel="nofollow"><i class="fab fa-twitter-square"></i></a>';
        $content .= '<a class="sbtn s-facebook" href="' .
            $facebookURL .
            '" target="_blank" rel="nofollow"><i class="fab fa-facebook-square"></i></a>';
        $content .= '<a class="sbtn s-whatsapp" href="' .
            $whatsappURL .
            '" target="_blank" rel="nofollow"><i class="fab fa-whatsapp-square"></i></a>';
        $content .= '<a class="sbtn s-linkedin" href="' .
            $linkedInURL .
            '" target="_blank" rel="nofollow"><i class="fab fa-linkedin"></i></a>';
        $content .= '</div></div>';

        return $content;
    }

    // if not a post/page then don't include sharing button
    return $content;
}
//add_shortcode("social_share", "optipub_social_buttons");

/**
 * Add a custom meta tag to header of posts that includes all OptiPub Tags for post.
 */
function optipub_categories_meta_tag()
{
    // Check if we are on a single post page
    if (is_single()) {
        /**
         * Variable Definitions.
         */
        $meta_name = 'SEO';

        // Get the categories of the current post
        $categories = get_the_category();
        $category_names = [];

        // Extract the name of each category
        foreach ($categories as $category) {
            $category_names[] = $category->name;
        }

        // Create a comma-separated list of category names
        $categories_content = implode(', ', $category_names);

        // Output the meta tag
        echo '<meta name="' . esc_attr($meta_name) . '" content="' . esc_attr($categories_content) . '">' . "\n";
    }
}
//add_action('wpseo_head', 'optipub_categories_meta_tag', 5);

/**
* Enqueue js and css files for Bootstrap Framework.
*
* Make sure you have these files in the specified location before uncommenting anything
*
* Or customize for a different framework or your own files if you want
*/
function optipub_theme_enqueue_scripts()
{
    /**
     * Variable Definitions.
     */

    /* 1.0.0 */
    $op_path = '/inc/optipub';

    /* 3.2.1, 3.7.1 */
    $jq_version = '3.7.1';
    $jq_path = '/inc/jquery/jquery-' . $jq_version;

    /* 5.15.3, 5.15.4 */
    $fa_version = '5.15.4';
    $fa_path = '/inc/fontawesome/fontawesome-' . $fa_version;

    /* 4.0.0, 4.6.2, 5.3.2 */
    $bs_version = '4.6.2';
    $bs_path = '/inc/bootstrap/bootstrap-' . $bs_version . '/dist';

    /**
     * CSS Style enqueue.
     */

    /* Font Awsome */
    wp_enqueue_style('optipub-fontawesome', get_template_directory_uri() . $fa_path . '/css/all.min.css', [], $fa_version);

    /* Bootstrap */
    wp_enqueue_style('optipub-bootstrap', get_template_directory_uri() . $bs_path . '/css/bootstrap.min.css', [], $bs_version);

    /* OptiPub */
    wp_enqueue_style('optipub-cookiealert', get_template_directory_uri() . $op_path . '/css/cookiealert.css', [], _S_VERSION);
    wp_enqueue_style('optipub-style', get_stylesheet_uri(), [], _S_VERSION);
    wp_style_add_data('optipub-rtl', 'rtl', 'replace');

    /**
     * JavaScript enqueue.
     */

    /* JQuery */
    wp_enqueue_script('optipub-jquery-slim', get_template_directory_uri() . $jq_path . '/jquery-' . $jq_version . '.slim.min.js', [], $jq_version, true);

    /* Fontawesome */
    /*wp_enqueue_script('optipub-fontawesome', get_template_directory_uri() . $fa_path . '/js/all.min.js', array(), $fa_version, true);*/

    /* Bootstrap */
    wp_enqueue_script('optipub-bootstrap-bundle', get_template_directory_uri() . $bs_path . '/js/bootstrap.bundle.min.js', [], $bs_version, true);

    /* Popper */
    /* wp_enqueue_script( 'optipub-popper', get_template_directory_uri() . '/js/popper.min.js', array(), _S_VERSION, true ); */

    /** OptiPub */
    wp_enqueue_script('optipub-cookiealert', get_template_directory_uri() . $op_path . '/js/cookiealert.js', [], _S_VERSION, true);
    wp_enqueue_script('optipub-accordion', get_template_directory_uri() . $op_path . '/js/faqs-accordion.js', ['jquery'], _S_VERSION, true);
    wp_enqueue_script('optipub-navigation', get_template_directory_uri() . $op_path . '/js/navigation.js', [], _S_VERSION, true);
    wp_enqueue_script('optipub-accordion', get_template_directory_uri() . $op_path . '/js/accordion.js', ['jquery'], _S_VERSION, true);

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
//add_action('wp_enqueue_scripts', 'optipub_theme_enqueue_scripts');
