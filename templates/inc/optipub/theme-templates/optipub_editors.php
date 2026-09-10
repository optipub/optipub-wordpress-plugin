<?php

/**
 * Template: OptiPub Editors Archive Page
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

include_once 'inc/optipub/template-parts/archives/pages/variables-optipub_editors.php';

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
                        /** Hide These OptiPub Editors from Displaying on Website */
                        if ($post->post_name == 'hide-editor-1' || $post->post_name == 'hide-editor-2') {
                            continue;
                        }

                        include 'inc/optipub/template-parts/archives/optipub-variables.php';
                        /** $post_id, $title, $resource_url, $post_date, $excerpt, $author_name, $author_string, $thumbnail_path */

                        include 'inc/optipub/template-parts/archives/display_posts/editors-cards_3_col.php';
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

			</div>
		</div>
	</div>
</section>
<?php
get_footer();
