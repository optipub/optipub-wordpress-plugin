<?php

/**
 * Template Part: Display posts found from WP_Query
 *
 * 1 column
 *
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

<div class="card mb-4" style="border-width: 5px;">
	<div class="card-body">
		<div class="card-title border-bottom-3">
			<h5 class="mt-0 text-lg-center default-color font-weight-bold">
				<a href="<?php echo esc_url($resource_url); ?>"><?php echo esc_html($title); ?>
				</a>
			</h5>
			<p class="font-16px px-3 my-2" style="line-height: 25px;">
				<?php echo esc_html($excerpt); ?>
			</p>
		</div>
	</div>
</div>