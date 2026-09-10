<?php

/**
 * The template for displaying single videos.
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<section class="single-videos-section">
	<div class="container">
		<div class="row">
			<div class="col-xl-8 col-lg-12 col-md-12 col-sm-12 col-xs-12 mb-4">
				<div class="single-videos-class">
					<?php while (have_posts()) {
					    the_post();

					    include_once 'inc/optipub/template-parts/archives/pages/variables-optipub_videos.php';
					    include_once 'inc/optipub/template-parts/archives/optipub-functions.php';
					    $public_post_statuses = get_post_stati(['public' => true], 'names', 'or');
					    include_once 'inc/optipub/template-parts/archives/optipub-variables.php';
					    /** $post_id, $title, $resource_url, $post_date, $excerpt, $thumbnail_path */
					    /** $author_name, (object)$author_info, $author_url, $author_show_on_website, $author_string, $author_image_path */

					    ?>

						<h1 class="entry-title "><?php echo esc_html($title); ?></h1>

						<?php
					        if (isset($post->post_excerpt)) { ?>

							<p class="card-subtitle entry-text-muted mb-4"><?php echo esc_html($post->post_excerpt); ?></p>

						<?php }
					        $topBlurb = get_post_meta($post_id, 'video_top_blurb', true);

					    if (isset($topBlurb)) {
					        ?>

							<div class="card-block">
								<p class="card-text"><?php echo esc_html($topBlurb); ?></p>
								<p class="pb-2"></p>
							</div>

						<?php } ?>

						<div class="card-video embed-responsive embed-responsive-16by9">
							<?php get_template_part('inc/optipub/template-parts/videos/videos_handler'); ?>
						</div>

						<?php
					        $bottomBlurb = get_post_meta($post_id, 'video_bottom_blurb', true);

					    if (isset($bottomBlurb)) {
					        ?>

							<div class="card-block">
								<p class="py-2"></p>
								<p class="card-text"><?php echo esc_html($bottomBlurb); ?></p>
							</div>

						<?php } ?>

						<div class="center mt-4">
							<a class="btn btn-default" href="<?php echo esc_url(get_home_url(null, 'videos', 'https')); ?>">Back to Videos</a>
						</div>
					<?php } ?>
				</div>
			</div>

			<div class="col-xl-4 col-12 pl-xl-5 my-4">
				<?php get_template_part('inc/optipub/template-parts/sidebars/sidebar', 'videos'); ?>
			</div>
		</div>
	</div>
</section>
<?php get_footer(); ?>