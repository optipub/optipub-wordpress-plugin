<?php

/**
 * Template Part: Display posts found from WP_Query
 *
 * 3 columns
 *
 * thumbnail
 * title
 * excerpt
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="col-xl-4 col-md-6 col-12 p-3">
	<div class="card border-0 h-100">
		<div>
			<?php if ($show_images) { ?>
				<a href="<?php echo esc_url($resource_url); ?>">
					<img class="image-h150px pr-3 float-left" src="<?php echo esc_url($thumbnail_path); ?>"
						alt="<?php echo esc_attr($post->post_title); ?>">
				</a>
			<?php } ?>
			<h5 class="card-title default-color text-decoration-none font-weight-bold">
				<a href="<?php echo esc_url($resource_url); ?> ">
					<?php echo esc_html($title); ?>
				</a>
			</h5>
			<p>
				<?php
                if (!empty($excerpt)) {
                    echo esc_html($excerpt);
                }
?>
			</p>
		</div>
	</div>
</div>