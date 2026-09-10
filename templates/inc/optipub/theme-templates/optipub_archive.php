<?php

/**
 * Template: OptiPub Articles Archive Page
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

include_once 'inc/optipub/template-parts/archives/pages/variables-optipub_archive.php';

include_once 'inc/optipub/template-parts/archives/optipub-query.php';

/** Filters Template */
$archive_border_css = '';

if ($filters_enabled || $search_enabled) {
    $archive_border_css = ' border-top-0';

    include_once 'inc/optipub/template-parts/archives/optipub-filters.php';
}

?>

<!-- Archive Section -->
<section id="<?php echo esc_attr($url_title_lcase); ?>-archive">
	<div class="container mb-4">
		<div class="card<?php echo esc_attr($archive_border_css); ?>">
			<div class="card-body">

				<?php
                if (!empty($archive_query->posts)) {
                    echo '<div class="row">';

                    include_once 'inc/optipub/template-parts/archives/optipub-functions.php';

                    /** Loop to Display Each Post Found */
                    foreach ($archive_query->posts as $post) {
                        include 'inc/optipub/template-parts/archives/optipub-variables.php';
                        /** $post_id, $title, $resource_url, $post_date, $excerpt, $author_name, $author_string, $thumbnail_path */

                        include 'inc/optipub/template-parts/archives/display_posts/archive-cards_4_col.php';
                    }

                    echo '</div>';
                } else {
                    ?>
					<!-- No Posts Found -->
					<div class="row">
						<div class="mx-auto">
							<h1 class="card-title mx-auto">No <?php echo esc_html($url_title); ?> Found</h1>
						</div>
					</div>

				<?php } ?>

				<!-- Pagination Section -->
				<?php include_once 'inc/optipub/template-parts/archives/optipub-pagination_params.php'; ?>

			</div>
		</div>
	</div>
</section>
<?php
get_footer();
