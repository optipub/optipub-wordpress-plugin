<?php

/**
 * Template Part: Display posts in section on home page
 *
 * 1 column
 *
 * thumbnail
 * title
 * author
 * date
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="mb-5">
	<div class="row">
		<div class="col-sm-5">
			<a href="<?php echo esc_url($resource_url); ?>">
				<img class="card-img image-min-h200" src="<?php echo esc_url($thumbnail_path); ?>" alt="<?php echo esc_attr($title); ?>">
			</a>
		</div>
		<div class="col-sm-7">
			<div>
				<h5 class="default-color text-decoration-none font-weight-bold mt-0">
					<a href="<?php echo esc_url($resource_url); ?>"><?php echo esc_html($title); ?></a>
				</h5>
				<p><?php echo esc_html($excerpt); ?></p>
				<p class="text-muted font-14px mb-0"><?php echo esc_html($author_string); ?>
					<br><?php echo esc_html($display_date_string) . ' ' . esc_html($post_date); ?>
				</p>
			</div>
		</div>
	</div>
</div>