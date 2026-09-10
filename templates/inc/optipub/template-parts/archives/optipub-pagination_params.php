<?php

/**
 * Template Part: Pagination using parameters.
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

?>

<!-- Pagination Section -->
<div class="archive_pagi">
	<div class="pagination">
		<?php
        $base_url = home_url($url_path);

// Sanitize GET parameters for pagination - only process required parameters
$query_args = [];

// Define allowed parameters for pagination
$allowed_params = ['search', 'post-type', 'category', 'editor', 'years', 'months', 'sort'];

foreach ($allowed_params as $param) {
    $value = filter_input(INPUT_GET, $param, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($value !== null && $value !== false) {
        if ($param === 'years' || $param === 'months') {
            // Sanitize numeric values
            $query_args[sanitize_key($param)] = absint($value);
        } else {
            // Sanitize text values
            $query_args[sanitize_key($param)] = sanitize_text_field($value);
        }
    }
}

if (empty($query_args)) {
    $paged_string = '?paged=%#%';
} else {
    $paged_string = '&paged=%#%';
}

$base = add_query_arg($query_args, $base_url);

echo wp_kses_post(paginate_links(
    [
        'base'      => $base . '%_%',
        'format'    => $paged_string,
        'current'   => max(1, get_query_var('paged', 1)),
        'total'     => $archive_query->max_num_pages,
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
    ]
));

// Enqueue archives script properly
wp_enqueue_script('optipub-archives', get_template_directory_uri() . '/inc/optipub/template-parts/js/archives.js', [], '1.0.0', true);
?>
	</div>
</div>