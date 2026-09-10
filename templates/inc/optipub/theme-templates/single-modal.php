<?php

/**
 * The template for displaying single modal.
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<!doctype html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php
    $optipub_site_favicon = get_option('optipub_site_favicon');

if (!empty($optipub_site_favicon)) {
    echo '<link rel="icon" href="' . esc_url($optipub_site_favicon) . '">';
}
?>
	<?php wp_head(); ?>
</head>

<body style="background: #000;">
	<?php
while (have_posts()) {
    the_post();
    the_content(); ?>
	<?php
}
?>
</body>
