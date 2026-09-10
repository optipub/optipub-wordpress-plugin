<?php

/**
 * Template Name: OptiPub Reports Page
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

include_once 'inc/optipub/template-parts/archives/pages/variables-optipub_reports.php';

include_once 'inc/optipub/template-parts/archives/optipub-query.php';

/** Filters Template */
if ($filters_enabled || $search_enabled) {
    $archive_border_css = ' border-top-0';
    include_once 'inc/optipub/template-parts/archives/optipub-filters.php';
}

?>

<section id="page-content">
	<div class="container">
		<?php
        if (!empty($archive_query->posts)) {
            ?>
			<div class="row flex-lg-row-reverse">
				<div class="col-lg-4 col-md-10 mx-md-auto my-0 my-lg-4 pl-lg-5 pl-xl-0">
					<h2 class="d-lg-none d-xl-none" style="font-size: 2.5rem; margin: 20px 0px;">Reports</h2>

				<?php
                    include_once 'inc/optipub/template-parts/archives/optipub-functions.php';

            /** Loop to Display Each Post Found */
            foreach ($archive_query->posts as $post) {
                include 'inc/optipub/template-parts/archives/optipub-variables.php';
                /** $post_id, $title, $resource_url, $post_date, $excerpt, $thumbnail_path */
                /** $author_name, (object)$author_info, $author_url, $author_show_on_website, $author_string, $author_image_path */

                include 'inc/optipub/template-parts/archives/display_posts/reports-custom.php';
            }
        }
?>
				</div>
				<div class="col-lg-8 col-12">
					<div class="container my-4 mx-0 mx-md-5 mx-lg-0">
						<?php

        /**
         * This widget is an example for adding page content using OptiPub Custom Content
         *
         * Remove or replace this widget ID with ID that exist in your theme
         */

        /* DISABLED *

            if (is_active_sidebar('content-report-archive-content')) {
dynamic_sidebar('content-report-archive-content');
            }

    */

?>
					</div>
				</div>
			</div>
	</div>
	</div>
</section>
<?php
get_footer();
?>