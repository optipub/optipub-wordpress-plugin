<?php

if (! defined('ABSPATH')) {
    exit;
}

$query_args = [
    'posts_per_page' => 5,
    'orderby'        => 'post_date',
    'post_type'      => 'articles',
    'post_status'    => ['publish', 'featured'],
];
$query = new WP_Query($query_args);
?>
<div class="card p-1 pt-3 mb-4">
	<h2 class="card-title mb-3 text-center">Latest Articles</h2>
	<div class="list-group">
		<?php
        $counter = 1;

foreach ($query->posts as $article) {
    $title = $article->post_title;
    $resourceUrl = get_permalink($article->ID);
    $style = '';

    if ($counter > 9) {
        $style = ' style="margin-left: -5px !important"';
    } ?>
			<a class="list-group-item p-3" href="<?php echo esc_url($resourceUrl); ?>" target="_blank">
				<div class="d-flex flex-row align-items-center">
					<div>
						<span class="label label-primary margin-right-sm" <?php echo esc_attr($style); ?>> <?php echo esc_html($counter); ?></span>
					</div>
					<div class="flex-grow-1">
						<span class="font-14px font-w-500">
							<?php
                    if (strlen($title) > 100) {
                        $title = substr($title, 0, 100) . '...';
                    }

    echo esc_html($title); ?>
						</span>
					</div>
				</div>
			</a>
		<?php
            ++$counter;
}
?>
	</div>
</div>