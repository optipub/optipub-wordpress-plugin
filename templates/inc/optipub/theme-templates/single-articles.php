<?php

/**
 * The template for displaying single articles.
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<section id="page-content">
	<div class="container">
		<div class="row">
			<div class="col-xl-8 col-12">
				<?php
                while (have_posts()) {
                    the_post();
                    include_once 'inc/optipub/template-parts/archives/pages/variables-optipub_archive.php';
                    include_once 'inc/optipub/template-parts/archives/optipub-functions.php';
                    $public_post_statuses = get_post_stati(['public' => true], 'names', 'or');
                    include_once 'inc/optipub/template-parts/archives/optipub-variables.php';
                    /** $post_id, $title, $resource_url, $post_date, $excerpt, $thumbnail_path */
                    /** $author_name, (object)$author_info, $author_url, $author_show_on_website, $author_string, $author_image_path */

                    ?>
					<div class="container mt-2 mt-md-4 mb-5">
						<h1 class="entry-title "><?php echo esc_html($title); ?></h1>
						<div class="author-section mb-4">
							<div class="author-image">
								<?php if (!empty($author_image_path)) { ?>
									<a href="<?php echo esc_url($author_url); ?>">
										<img src="<?php echo esc_url($author_image_path); ?>" alt="">
									</a>
								<?php } ?>
								<div class="author_details row align-items-center">
									<div class="ml-4">
										<p class="m-0 text-muted font-14px">
											<?php echo esc_html($author_string); ?>
										</p>
										<p class="m-0 text-muted font-14px"><?php echo esc_html($display_date_string . ' ' . $post_date); ?></p>
									</div>

									<div class="social_share_section ml-4 mt-2 mt-sm-0">
										<!-- Social Media Share Buttons -->
										<?php echo do_shortcode('[social_share]'); ?>
									</div>
								</div>
							</div>
						</div>
						<div class="entry-content">
							<?php the_content(); ?>
						</div>
						<!-- Social Media Share Buttons -->
						<?php echo do_shortcode('[social_share]'); ?>
					</div>
				<?php } ?>

				<div class="below_post d-none d-xl-block">
					<?php

                        /**
                         * These widgets are examples
                         *
                         * Remove these or replace the widget IDs with IDs that exist in your theme
                         */

                        /* DISABLED *

                        if (is_active_sidebar('subscribe-cta-below-post')) {
                          dynamic_sidebar('subscribe-cta-below-post');
                        }

                                        */

?>
				</div>
			</div>
			<div class="col-xl-4 col-12 pl-xl-5 my-4">
				<?php get_template_part('inc/optipub/template-parts/sidebars/sidebar', 'articles'); ?>
			</div>
		</div>
	</div>
</section>
<?php get_footer(); ?>