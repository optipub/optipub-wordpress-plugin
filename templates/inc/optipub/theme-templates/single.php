<?php

/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$post_type = get_post_type($post->ID);

$sidebar_show_hide = get_post_meta($post->ID, 'sidebar_show_hide', true);

if (!empty($sidebar_show_hide) && $sidebar_show_hide == 1) {
    $col_class = 'col-xl-8 ';
} else {
    $col_class = '';
}
?>

<section id="page-content">
	<div class="container">
		<div class="row">
			<?php
            while (have_posts()) {
                the_post();
                ?>
				<div class="<?php echo esc_attr($col_class); ?>col-12">
					<div class="container my-2 my-md-4">
						<?php
                            get_template_part('inc/optipub/template-parts/content/content', get_post_type());

                // If comments are open or we have at least one comment, load up the comment template.
                if (comments_open() || get_comments_number()) {
                    comments_template();
                } ?>
					</div>
				</div>
			<?php } // End of the loop.

if (!empty($sidebar_show_hide) && $sidebar_show_hide == 1) { ?>
				<div class="col-xl-4 col-12 pl-xl-5 my-4">
					<?php get_template_part('inc/optipub/template-parts/sidebars/sidebar', 'single'); ?>
				</div>
			<?php } ?>
		</div>
	</div>
</section>
<?php
// get_sidebar();
get_footer();
