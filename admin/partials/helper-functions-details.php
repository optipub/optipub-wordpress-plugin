<?php
defined('ABSPATH') or die('Direct access not allowed');
?>

<li>
    <input type="checkbox" id="helper-functions" name="helper-functions" value="helper-functions">
    <span>Helper Functions (inc/optipub/php) - use these files to add functionality to your theme.<a href="#" style="float: right;" data-toggle-target="#details-function">[Details]</a></span>
    <div id="details-function" class="details-dropdown">
        <ul class="details-section-lists">
            <li><strong><span class="dashicons dashicons-arrow-down"></span><a href="#" class="file-details-toggle" data-toggle-target="#file-helper-functions">helper-functions.php</a></strong> - Includes many helper functions for OptiPub content.</li>
            <div id="file-helper-functions" class="details-section-dropdown">
                <ul style="list-style: circle;">
                    <li><strong>optipub_get_post_meta( $post_id, $key )</strong><br>[function] - Get post meta value.</li>
                    <li><strong>optipub_get_post_meta_array( $post_id, $key )</strong><br>[function] - Get post meta array value.</li>
                    <li><strong>optipub_get_post_meta_date( $post_id, $key )</strong><br>[function] - Get post meta date value.</li>
                    <li><strong>optipub_get_post_meta_date_formatted( $post_id, $key, $format )</strong><br>[function] - Get post meta date value formatted.</li>
                    <li><strong>optipub_get_post_meta_date_time( $post_id, $key )</strong><br>[function] - Get post meta date time value.</li>
                    <li><strong>optipub_get_post_meta_date_time_formatted( $post_id, $key, $format )</strong><br>[function] - Get post meta date time value formatted.</li>
                    <li><strong>optipub_get_post_meta_time( $post_id, $key )</strong><br>[function] - Get post meta time value.</li>
                    <li><strong>optipub_get_post_meta_time_formatted( $post_id, $key, $format )</strong><br>[function] - Get post meta time value formatted.</li>
                    <li><strong>optipub_get_post_meta_url( $post_id, $key )</strong><br>[function] - Get post meta url value.</li>
                    <li><strong>optipub_get_post_meta_email( $post_id, $key )</strong><br>[function] - Get post meta email value.</li>
                    <li><strong>optipub_get_post_meta_phone( $post_id, $key )</strong><br>[function] - Get post meta phone value.</li>
                    <li><strong>optipub_get_post_meta_address( $post_id, $key )</strong><br>[function] - Get post meta address value.</li>
                    <li><strong>optipub_get_post_meta_image( $post_id, $key )</strong><br>[function] - Get post meta image value.</li>
                    <li><strong>optipub_get_post_meta_file( $post_id, $key )</strong><br>[function] - Get post meta file value.</li>
                    <li><strong>optipub_get_post_meta_video( $post_id, $key )</strong><br>[function] - Get post meta video value.</li>
                    <li><strong>optipub_get_post_meta_audio( $post_id, $key )</strong><br>[function] - Get post meta audio value.</li>
                </ul>
            </div>

            <li><strong><span class="dashicons dashicons-arrow-down"></span><a href="#" class="file-details-toggle" data-toggle-target="#file-link-functions">link-modification-functions.php</a></strong> <span style="color: green;">configure</span> - Includes many functions to modify links in the_content.</li>
            <div id="file-link-functions" class="details-section-dropdown">
                <ul style="list-style: circle;">
                    <li><strong>optipub_modify_the_content_links( $content )</strong><br>[filter] <span style="color: green;">configure</span> - Configure the link and internal link modification functions you want enabled and what domains and sudomains to run them on.</li>
                    <li style="margin-top: 1rem; margin-bottom: 1rem; margin-left: -15px;"><strong>Modifications for All Links</strong> - these functions modify all links in the_content</li>
                    <ul style="list-style: inside;">
                        <li><strong>optipub_modify_all_links_href_http_with_https( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Replace http:// with https:// for All &lt;a href&gt; link elements in the_content.</li>
                        <li><strong>optipub_modify_all_links_img_http_with_https( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Replace http:// with https:// for All &lt;img src&gt; link elements in the_content.</li>
                    </ul>
                    <li style="margin-top: 1rem; margin-bottom: 1rem; margin-left: -15px;"><strong>Modifications for Internal Links</strong> - these functions modify only links with the domains configured in 'optipub_modify_the_content_links_internal_links'.</li>
                    <ul style="list-style: inside;">
                        <li><strong>optipub_modify_internal_links_post_id_slug( $content, $domain )</strong><br>[function] <span style="color: red;">disabled</span> - Replaces legacy internal target link slug structures [post_name]/[id]</li>
                        <li><strong>optipub_modify_internal_links_resource_post_id_slug( $content, $domain )</strong><br>[function] <span style="color: red;">disabled</span> - Replaces legacy internal target link slug structures [resource]/[post_name]/[id]</li>
                        <li><strong>optipub_modify_internal_links_slug_structure( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Replaces legacy internal target link slug structures.</li>
                        <li><strong>optipub_modify_internal_links_legacy_subscribes( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Replaces old subscribe links with new subscribe links.</li>
                        <li><strong>optipub_modify_internal_links_legacy_subscribes_subdomain( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Replaces subdomain for legacy subscribe links.</li>
                        <li><strong>optipub_modify_internal_links_contraction_hyphens( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Removes hyphens that replaced apostrophes from word contractions.</li>
                        <li><strong>optipub_modify_internal_links_plus_sign_replacements( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Replaces plus signs with hyphens.</li>
                        <li><strong>optipub_modify_internal_links_large_number_hyphens( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Removes hyphens from numbers that have commas in them.</li>
                        <li><strong>optipub_modify_internal_links_add_trailing_slash( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Add trailing slash to end of links.</li>
                        <li><strong>optipub_modify_internal_links_remove_trailing_slash( $content )</strong><br>[function] <span style="color: red;">disabled</span> - Removes trailing slash from end of links.</li>
                    </ul>
                </ul>
            </div>

            <li><strong><span class="dashicons dashicons-arrow-down"></span><a href="#" class="file-details-toggle" data-toggle-target="#file-yoast-functions">yoast-functions.php</a></strong> - Use only with 'Yoast SEO' Plugin Active - Includes many Yoast filters to correct SEO meta tags with OptiPub content.</li>
            <div id="file-yoast-functions" class="details-section-dropdown">
                <ul style="list-style: circle;">
                    <li><strong>optipub_yoast_add_optipub_post_types( $array )</strong><br>[filter] - Add Custom Post Types so yoast displays publish date correctly.</li>
                    <li><strong>optipub_yoast_filter_title( $title )</strong><br>[filter] - Filters the title.</li>
                    <li><strong>optipub_yoast_filter_description( $description )</strong><br>[filter] - Filters the description.</li>
                    <li><strong>optipub_yoast_filter_og_image( $image )</strong><br>[filter] - Filters the opengraph image.</li>
                    <li><strong>optipub_yoast_filter_author( $author )</strong><br>[filter] - Filters the meta author.</li>
                    <li><strong>optipub_yoast_filter_canonical_url( $url )</strong><br>[filter] - Filters the meta canonical.</li>
                    <li><strong>optipub_sitemap_exclude_taxonomy( $excluded, $taxonomy )</strong><br>[filter] - Exclude a taxonomy from XML sitemaps.</li>
                </ul>
            </div>

            <li><strong><span class="dashicons dashicons-arrow-down"></span><a href="#" class="file-details-toggle" data-toggle-target="#file-aio-seo-functions">aioseo-functions.php</a></strong> - Use only with 'All in One SEO' Plugin Active - Includes many AIO SEO filters to correct SEO meta tags with OptiPub content.</li>
            <div id="file-aio-seo-functions" class="details-section-dropdown">
                <ul style="list-style: circle;">
                    <li><strong>optipub_aioseo_filter_title( $title )</strong><br>[filter] - Filters the title.</li>
                    <li><strong>optipub_aioseo_filter_description( $description )</strong><br>[filter] - Filters the description.</li>
                    <li><strong>optipub_aioseo_filter_keywords( $keywords )</strong><br>[filter] <span style="color: red;">disabled</span> - Filters for Meta Keywords.</li>
                    <li><strong>optipub_aioseo_filter_canonical_url( $url )</strong><br>[filter] - Filters for Meta Canonical Url.</li>
                    <li><strong>optipub_aioseo_facebook_tags_remove( $meta )</strong><br>[action] <span style="color: red;">disabled</span> - Remove Facebook Meta Tags.</li>
                    <li><strong>optipub_remove_aioseo_comments( $buffer )</strong><br>[action] <span style="color: red;">disabled</span> - Remove AIO SEO commented lines in header.</li>
                </ul>
            </div>
        </ul>
    </div>
</li> 