<?php

/**
 * Template Part: Filters for OptiPub post-type archive pages
 *
 * @package optipub
 * @since 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

$nonce_verified = false;

if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'optipub_archive_filter')) {
    $nonce_verified = true;
}

if ($nonce_verified || (!isset($_GET['_wpnonce']) && !isset($_GET['search']) && !isset($_GET['category']) && !isset($_GET['editor']) && !isset($_GET['post-type']) && !isset($_GET['sort']))) {
    $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $url_parts = wp_parse_url($current_url);
    $url_path = isset($url_parts['path']) ? $url_parts['path'] : '';

    $url_title = basename($url_path);
    $url_title_lcase = strtolower($url_title);

    $query_search_value = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
    $selected_post_type = isset($_GET['post-type']) ? sanitize_text_field(wp_unslash($_GET['post-type'])) : '';
    $selected_category = isset($_GET['category']) ? sanitize_text_field(wp_unslash($_GET['category'])) : '';
    $selected_editor = isset($_GET['editor']) ? sanitize_text_field(wp_unslash($_GET['editor'])) : '';
    $selected_year = isset($_GET['years']) ? absint(wp_unslash($_GET['years'])) : '';
    $selected_month = isset($_GET['months']) ? absint(wp_unslash($_GET['months'])) : '';
} else {
    $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $url_parts = wp_parse_url($current_url);
    $url_path = isset($url_parts['path']) ? $url_parts['path'] : '';
    $url_title = basename($url_path);
    $url_title_lcase = strtolower($url_title);
    $query_search_value = '';
    $selected_post_type = '';
    $selected_category = '';
    $selected_editor = '';
    $selected_year = '';
    $selected_month = '';
}

$optipub_post_types = get_option('optipub_post_types', ['articles', 'reports', 'videos', 'resources']);

$archives_filters = get_option('optipub_archives_filters', ['category', 'editor', 'years', 'months']);

$search_enabled = get_option('optipub_search_enabled', true);
$search_post_types = get_option('optipub_search_post_types', true);

$query_categories = $optipub_db_query->getAllPostTypeCategories($query_post_type, $selected_year, $selected_month);
$query_editors = $optipub_db_query->getAllPostTypeEditors($query_post_type, $selected_year, $selected_month, $filter_editors_show_on_website_only);
$query_years = $optipub_db_query->getAllPostTypeYears($query_post_type);
$query_months = $optipub_db_query->getAllPostTypeMonths($query_post_type);

$filters_selected = $_GET;
unset($filters_selected['search']);
unset($filters_selected['post-type']);
unset($filters_selected['paged']);

$filters_selected_empty = empty($query_search_value) && empty($selected_post_type) && empty($selected_category) && empty($selected_editor) && empty($selected_year) && empty($selected_month);

if (count($archives_filters) < 4) {
    $large_col = 'col-md-4';
    $small_col = 'col-md-3';
} else {
    $large_col = 'col-md-3';
    $small_col = 'col-md-2';
}

if ($search_post_types) {
    $search_col = 'col-md-8';
} else {
    $search_col = 'col-md-10';
}

wp_enqueue_script('jquery');
wp_enqueue_script(
    'optipub-archive-filters',
    OPTIPUB_PLUGIN_URL . 'public/js/archive-filters.js',
    ['jquery'],
    '1.0.0',
    true
);

wp_localize_script('optipub-archive-filters', 'optipubFilters', [
    'filtersSelectedEmpty' => $filters_selected_empty,
    'searchEnabled'        => $search_enabled
]);

?>

<section id="<?php echo esc_attr($url_title_lcase); ?>-filters">
	<div class="container mb-0">
		<div class="card border-bottom-0">
			<div id="card-filters" class="card-body d-none">
				<div class="row">
					<div class="col d-flex align-items-center">
						<h3 class="entry-title p-3">
							<?php $publication_name = get_option('optipub_selected_publication_name');

if ($publication_name) {
    echo esc_html($publication_name) . ' ';
}
echo esc_html($url_title);
?>
						</h3>
						<a class="font-14px font-w-600 pl-2 pb-1" href="#" onclick="toggleFilters('', event)">[Filters]</a>
					</div>
				</div>
				<form action="<?php echo esc_url($url_path); ?>" method="get" role="form" id="archives-filters-form"
					class="no-padding">
					<?php wp_nonce_field('optipub_archive_filter', '_wpnonce'); ?>
					<?php if ($search_enabled) { ?>
						<div id="archives-search" class="row h-100 mb-4 font-13px text-muted">
							<div class="col-12 <?php echo esc_attr($search_col); ?>">
								<label class="mb-0" for="search">Search</label>
								<input type="search" class="form-control" name="search" placeholder="Search"
									value="<?php echo esc_attr($query_search_value); ?>">
							</div>
							<?php if ($search_post_types) { ?>
								<div class="col-12 col-md-2 pt-3 pt-md-0">
									<label class="mb-0" for="archives-category">Type</label>
									<select class="form-control input-sm" id="archives-post-type" name="post-type">
										<option value="">All</option>
										<?php
            foreach ($optipub_post_types as $post_type) {
                $is_selected = $selected_post_type && $selected_post_type == $post_type ? 'selected' : '';
                echo '<option value="' . esc_attr($post_type) . '" ' . esc_attr($is_selected) . '>' . esc_html(ucfirst($post_type)) . '</option>';
            }
							    ?>
									</select>
								</div>
							<?php } ?>
							<?php if ($search_enabled) {
							    // Show Apply Button for Search when filter button is hidden
							    ?>
								<div id="archives-filters-search"
									class="col-12 col-md-2 d-flex flex-column justify-content-end pt-4 pt-md-0">
									<input type="submit" class="btn btn-primary archives_button w-100 mt-auto" value="Apply">
								</div>
							<?php } ?>
						</div>
					<?php } ?>
					<div id="archives-filters" class="row d-none d-lg-flex h-100 mb-4 font-13px text-muted">
						<?php if (in_array('category', $archives_filters)) { ?>
							<div class="col-12 <?php echo esc_attr($large_col); ?>">
								<label class="mb-0" for="archives-category">Category</label>
								<select class="form-control input-sm" id="archives-category" name="category">
									<option value="">All</option>
									<?php
							            foreach ($query_categories as $category) {
							                $is_selected = $selected_category && $selected_category == $category->name ? 'selected' : '';
							                echo '<option value="' . esc_attr($category->name) . '" ' . esc_attr($is_selected) . '>' . esc_html($category->name) . '</option>';
							            }
						    ?>
								</select>
							</div>
						<?php } ?>
						<?php if (in_array('editor', $archives_filters)) { ?>
							<div class="col-12 <?php echo esc_attr($large_col); ?> pt-3 pt-md-0">
								<label class="mb-0" for="archives-editor">Editor</label>
								<select class="form-control input-sm" id="archives-editor" name="editor">
									<option value="">All</option>
									<?php
						    foreach ($query_editors as $editor) {
						        $is_selected = $selected_editor && $selected_editor == $editor ? 'selected' : '';
						        echo '<option value="' . esc_attr($editor) . '" ' . esc_attr($is_selected) . '>' . esc_html($editor) . '</option>';
						    }
						    ?>
								</select>
							</div>
						<?php } ?>
						<?php if (in_array('years', $archives_filters)) { ?>
							<div class="col-12 <?php echo esc_attr($small_col); ?> pt-3 pt-md-0">
								<label class="mb-0" for="archives-year">Year</label>
								<select class="form-control input-sm" id="archives-year" name="years">
									<option value="" selected>All</option>
									<?php
						    foreach ($query_years as $year) {
						        $is_selected = $selected_year && $selected_year == $year->year ? 'selected' : '';
						        echo '<option value="' . esc_attr($year->year) . '" ' . esc_attr($is_selected) . '>' . esc_html($year->year) . '</option>';
						    }
						    ?>
								</select>
							</div>
						<?php } ?>
						<?php if (in_array('months', $archives_filters)) { ?>
							<div class="col-12 <?php echo esc_attr($small_col); ?> pt-3 pt-md-0">
								<label class="mb-0" for="archives-month">Month</label>
								<select class="form-control" id="archives-month" name="months">
									<option value="" selected>All</option>
									<?php
						    foreach ($query_months as $month) {
						        $is_selected = $selected_month && $selected_month == $month->month_number ? 'selected' : '';
						        echo '<option value="' . esc_attr($month->month_number) . '" ' . esc_attr($is_selected) . '>' . esc_html($month->month_name) . '</option>';
						    }
						    ?>
								</select>
							</div>
						<?php } ?>

						<div class="col-12 col-md-2 d-flex flex-column justify-content-end pt-4 pt-md-0">
							<input type="submit" class="btn btn-primary archives_button w-100 mt-auto" value="Apply">
						</div>
					</div>
				</form>
				<div class="row">
					<div class="col-lg-12">
						<?php
                        if ($_GET) {
                            $display_post_type = $selected_post_type ?? $url_title_lcase;
                            $display_string = 'Displaying <strong>(' . $archive_query->found_posts . ') ' . $selected_category . '</strong>';
                            $display_string .= in_array($display_post_type, $optipub_post_types) ? ' ' . ucfirst($display_post_type) : ' Results';
                            $display_string .= !empty($query_search_value) ? ' for "' . $query_search_value . '"' : '';

                            if ($selected_month || $selected_year) {
                                $display_string .= ' from <strong>';

                                if ($selected_month) {
                                    $dateTime = DateTime::createFromFormat('!m', $selected_month);
                                    $monthName = $dateTime->format('F');
                                    $display_string .= $monthName . ' ';
                                }

                                if ($selected_year) {
                                    $display_string .= $selected_year;
                                }
                                $display_string .= '</strong>';
                            }
                        } else {
                            $display_string = 'Recent ' . in_array($url_title_lcase, $optipub_post_types) ? $url_title : 'Results';
                        }
?>
						<h4 class="dark-gray mb-4">
							<?php echo wp_kses_post($display_string); ?>
						</h4>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>