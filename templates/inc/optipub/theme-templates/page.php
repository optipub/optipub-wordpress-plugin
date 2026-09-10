<?php

/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
$sidebar_show_hide = get_post_meta($post->ID, 'sidebar_show_hide', true);

if (!empty($sidebar_show_hide) && $sidebar_show_hide == 1) {
    $col_class = 'col-xl-8';
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
				<div class="<?php echo esc_attr($col_class); ?> col-12">
					<div class="container my-2 my-md-4">
						<?php
                            //the_content();
                            get_template_part('inc/optipub/template-parts/content/content', 'page');

                // If comments are open or we have at least one comment, load up the comment template.
                if (comments_open() || get_comments_number()) {
                    comments_template();
                }
                ?>
					</div>
				</div>
			<?php
            } // End of the loop.

if (!empty($sidebar_show_hide) && $sidebar_show_hide == 1) { ?>
				<div class="col-xl-4 pl-xl-5 my-4 col-12">
					<?php

        /**
         * These widgets are examples
         *
         * Remove these or replace the widget IDs with IDs that exist in your theme
         */

        /* DISABLED *

    if (is_active_sidebar('sidebar-premium-publications')) {
        dynamic_sidebar('sidebar-premium-publications');
    }

    if (is_active_sidebar("sidebar-related-articles")) {
        dynamic_sidebar("sidebar-related-articles");
    }

    if (is_active_sidebar("sidebar-latest-articles")) {
        dynamic_sidebar("sidebar-latest-articles");
    }

    */

    ?>
				</div>
			<?php }
?>
		</div>
	</div>
</section>
<?php get_footer();
