<?php

if (! defined('ABSPATH')) {
    exit;
}

$current_post_id = get_the_ID();
$categories = wp_get_post_categories($current_post_id);

$current_post_type = get_post_type($current_post_id);

$widget_title = 'Trending Articles';

if ('editors' == $current_post_type) {
    $editor_name = get_the_title();
    $widget_title = 'Latest from ' . $editor_name;

    $query_args = [
        'posts_per_page' => 10,
        'orderby'        => 'post_date',
        'post_type'      => 'articles',
        'post_status'    => ['publish', 'featured'],
        'meta_query'     => [
            [
                'key'     => 'resource_editor',
                'value'   => $editor_name,
                'compare' => '=',
            ],
        ],
    ];
} elseif (is_single() && ! in_array(1, $categories)) {
    $widget_title = 'Related Articles';
    $query_args = [
        'posts_per_page' => 5,
        'orderby'        => 'post_date',
        'post_type'      => 'articles',
        'post_status'    => ['publish', 'featured'],
        'category__in'   => $categories,
        'post__not_in'   => [$current_post_id],
    ];
} else {
    $query_args = [
        'posts_per_page' => 5,
        'orderby'        => 'post_date',
        'post_type'      => 'articles',
        'post_status'    => ['publish', 'featured'],
    ];
}

$query = new WP_Query($query_args);
?>
<div id="related-articles" class="card p-1 pt-3 mb-4">
  <h2 class="card-title mb-3 text-center"><?php echo esc_html($widget_title); ?></h2>
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
            <span class="label label-primary margin-right-sm"<?php echo esc_attr($style); ?>> <?php echo esc_html($counter); ?></span>
          </div>
          <div class="flex-grow-1">
            <span class="font-14px font-w-500 text-muted">
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
