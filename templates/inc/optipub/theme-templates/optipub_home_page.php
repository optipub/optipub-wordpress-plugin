<?php

/**
 * Template: OptiPub Home Page
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<section id="featured-articles">
	<div class="container">
		<div class="row">
			<div class="col-xl-8 col-12">
				<?php

                include_once 'inc/optipub/template-parts/archives/pages/variables-optipub_home_articles.php';
include_once 'inc/optipub/template-parts/archives/optipub-query.php';

if (!empty($archive_query->posts)) {
    include_once 'inc/optipub/template-parts/archives/optipub-functions.php';

    /** Loop to Display Each Post Found */
    foreach ($archive_query->posts as $post) {
        include 'inc/optipub/template-parts/archives/optipub-variables.php';
        /** $post_id, $title, $resource_url, $post_date, $excerpt, $thumbnail_path */
        /** $author_name, (object)$author_info, $author_url, $author_show_on_website, $author_string, $author_image_path */

        include 'inc/optipub/template-parts/archives/display_posts/home_page-cards_1_col.php';
    }
}
?>

			</div>
			<div class="col-xl-4 d-none d-xl-block">
				<?php
if (is_active_sidebar('sidebar-subscribe-cta')) {
    dynamic_sidebar('sidebar-subscribe-cta');
}
?>
			</div>
			<div class="col-12 mb-4 mt-0 mt-md-2 mt-lg-5 renewable_button">
				<a href="/archives/" target="_blank">Browse Recent Articles</a>
			</div>
		</div>
	</div>
</section>

<?php
/** SUBSCRIBE CTA SECTION */
if (is_active_sidebar('widget-hero-subscribe-cta-bar')) {
    dynamic_sidebar('widget-hero-subscribe-cta-bar');
}

/** OUR EXPERTS SECTION */
if (is_active_sidebar('widget-home-experts')) {
    dynamic_sidebar('widget-home-experts');
}

/** PREMIUM PUBLICATIONS SECTION */
if (is_active_sidebar('widget-home-premium-publications')) {
    dynamic_sidebar('widget-home-premium-publications');
}

get_footer();
?>