<?php

/**
 * The template for displaying single reports.
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<?php wp_head(); ?>
</head>

<body>
	<section class="single_report_section">
		<div class="single_report_container mx-auto">
			<div class="text-center">
				<a href="<?php echo esc_url(get_home_url()); ?>">

					<!-- Configure Website Attribute -->
					<img class="img-responsive center-block my-0" src="<?php echo esc_url(optipub_return_website_attribute('light_600x200')); ?>">

				</a>
			</div>
			<?php while (have_posts()) {
			    the_post(); ?>
				<div class="ribbon ribbon-primary ribbon-both ribbon-heading py-2">
					<h1 class="entry-title mx-3" style="color:#fff;">Special Report: <?php echo esc_html($post->post_title); ?></h1>
				</div>
				<div class="line-height-med px-4 entry-content"><?php the_content(); ?> </div>
			<?php
			} ?>
			<hr>
			<div class="font-13px text-muted text-justify py-2 px-4">
				<p><a href="<?php echo esc_url(get_home_url()); ?>">
						<?php
			            $publicationName = get_option('optipub_selected_publication_name');

if ($publicationName) {
    echo esc_html($publicationName);
}
?></a>, Copyright © <?php echo esc_html(gmdate('Y')); ?>,

					<!-- Configure Website Attributes -->
					<a href="<?php echo esc_url(optipub_return_website_attribute('parent_company_website_url')); ?>">
						<?php echo esc_html(optipub_return_website_attribute('parent_company_business_name_2')); ?>
					</a>. All rights reserved.

				</p>
			</div>
		</div>
	</section>
</body>

</html>