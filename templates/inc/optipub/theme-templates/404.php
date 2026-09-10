<?php

/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

$text_domain = basename(get_stylesheet_directory());
get_header();
?>

<main id="primary" class="site-main container">

	<section class="error-404 not-found">
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e('Oops! That page can&rsquo;t be found.', 'optipub'); ?></h1>
		</header><!-- .page-header -->

		<div class="page-content">
			<p><?php esc_html_e('It looks like nothing was found at this location. Maybe try one of the links below or a search?', 'optipub'); ?></p>

			<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/search')); ?>">
				<input type="search" id="search-from" class="search-field" value="<?php echo get_search_query(); ?>" name="query" />
				<input type="submit" class="search-submit" value="<?php esc_attr_x('Search', 'submit button', 'optipub'); ?>" />
			</form>

		</div><!-- .page-content -->
	</section><!-- .error-404 -->

</main><!-- #main -->

<?php
get_footer();
