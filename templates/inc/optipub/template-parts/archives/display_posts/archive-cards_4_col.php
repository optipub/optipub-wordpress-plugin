<?php

/**
 * Template Part: Display posts found from WP_Query
 *
 * 4 columns
 *
 * thumbnail
 * title
 * author
 * date
 * excerpt
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="col-xl-3 col-lg-4 col-md-6 col-12 p-3">
	<div class="card border-0 h-100">
		<div class="image-container-h200">
			<?php if (!empty($thumbnail_path)) { ?>
				<a href="<?php echo esc_url($resource_url); ?>">
					<img class="image-w100-h100" src="<?php echo esc_url($thumbnail_path); ?>"
						alt="<?php echo esc_attr($post->post_title); ?>">
				</a>
			<?php } ?>
		</div>
		<div>
			<h5 class="card-title default-color text-decoration-none font-weight-bold">
				<a href="<?php echo esc_url($resource_url); ?> ">
					<?php echo esc_html($title); ?>
				</a>
			</h5>
			<p class="font-13px text-muted mb-3">
				<?php
                if ($post->post_type == 'articles') {
                    echo esc_html($author_string) . '</br>';
                    echo esc_html($display_date_string) . ' ' . esc_html($post_date) . '</p>';
                }

if (!empty($excerpt)) {
    echo '<p>' . esc_html($excerpt) . '</p>';
}
?>
		</div>
	</div>
</div>