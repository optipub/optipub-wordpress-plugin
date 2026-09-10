<?php

/**
 * Template: OptiPub Subscribe Page
 *
 * @package wealthdaily
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

wp_enqueue_style(
    'optipub-subscribers',
    OPTIPUB_PLUGIN_URL . 'public/css/subscribers.css',
    [],
    '1.0.0'
);

?>



<!-- Archive Section -->
<section id="subscribers-page">
	<div class="container mb-4">
		<div class="card">
			<div class="card-body">
				<?php
                if (is_user_logged_in()) {
                    $user_id = get_current_user_id();
                    $subscriptions = get_user_meta($user_id, 'optipub_subscriptions', true);

                    if (!empty($subscriptions)) {
                        // Sort active subscriptions by updated_at
                        if (!empty($subscriptions['active'])) {
                            usort($subscriptions['active'], function ($a, $b) {
                                return strtotime($b->updated_at) - strtotime($a->updated_at);
                            });

                            echo '<h3 class="mb-4">Active Subscriptions</h3>';

                            foreach ($subscriptions['active'] as $subscription) {
                                $publication = $subscription->publication;
                                ?>
								<div class="row mb-4">
									<div class="col-md-8 mx-auto">
										<?php
                                                        // Get the publication term by publication ID
                                                        $terms = get_terms([
                                                            'taxonomy'   => 'optipub_publication',
                                                            'hide_empty' => false,
                                                            'meta_query' => [
                                                                [
                                                                    'key'     => 'publication_id',
                                                                    'value'   => $publication->id,
                                                                    'compare' => '=',
                                                                ],
                                                            ],
                                                        ]);

                                $publication_slug = !empty($terms) ? $terms[0]->slug : '';
                                $card_class = $publication_slug ? 'card publication-card' : 'card';
                                ?>
										<div class="<?php echo esc_attr($card_class); ?>">
											<?php if ($publication_slug) { ?>
												<a href="<?php echo esc_url(home_url($publication_slug)); ?>" class="text-decoration-none">
												<?php } ?>
												<div class="card-body">
													<div class="d-flex align-items-center">
														<div class="flex-grow-1">
															<div class="d-flex align-items-center mb-2">
																<img src="<?php echo esc_url($publication->fav_icon_asset_path); ?>"
																	alt="Favicon"
																	style="width: 32px; height: 32px;"
																	class="me-2">
																<h4 class="mb-0 text-dark flex-grow-1">
																	<?php if ($publication_slug) { ?>
																		<a href="<?php echo esc_url(home_url($publication_slug)); ?>" class="text-decoration-none text-dark">
																			<?php echo esc_html($publication->name); ?>
																		</a>
																	<?php } else { ?>
																		<?php echo esc_html($publication->name); ?>
																	<?php } ?>
																</h4>
																<?php if ($publication_slug) { ?>
																	<div class="d-flex gap-2">
																		<a href="<?php echo esc_url(home_url($publication_slug)); ?>"
																			class="badge bg-primary text-decoration-none">View Publication</a>
																		<a href="<?php echo esc_url(home_url($publication_slug . '/portfolios')); ?>"
																			class="badge bg-info text-decoration-none">View Portfolios</a>
																		<?php
                                                                // Get publication meta
                                                                $publication_meta = get_term_meta($terms[0]->term_id, 'publication_page_settings', true);

																    $article_page_url = '';

																    if (!empty($publication_meta['Articles'])) {
																        $article_slug = $publication_meta['Articles'];
																        $article_page = get_page_by_path($article_slug);

																        if ($article_page) {
																            $page_publication_id = get_post_meta($article_page->ID, 'publication_id', true);

																            if ($page_publication_id == $publication->id) {
																                $article_page_url = $article_slug;
																            }
																        }
																    }

																    if ($article_page_url) { ?>
																			<a href="<?php echo esc_url(home_url($article_page_url)); ?>"
																				class="badge bg-success text-decoration-none">View Resources</a>
																		<?php } ?>
																	</div>
																<?php } ?>
															</div>
															<div class="d-flex justify-content-between align-items-center">
																<?php if ($publication_slug) { ?>
																	<a href="<?php echo esc_url(home_url($publication_slug)); ?>" class="text-decoration-none">
																		<img src="<?php echo esc_url($publication->asset_path); ?>"
																			alt="Logo"
																			style="max-height: 60px;">
																	</a>
																<?php } else { ?>
																	<img src="<?php echo esc_url($publication->asset_path); ?>"
																		alt="Logo"
																		style="max-height: 60px;">
																<?php } ?>
																<small class="text-muted">
																	Updated: <?php echo esc_html(gmdate('F j, Y', strtotime($subscription->updated_at))); ?>
																</small>
															</div>
														</div>
													</div>
												</div>
												<?php if ($publication_slug) { ?>
												</a>
											<?php } ?>
										</div>
									</div>
								</div>
							<?php
                            }
                        }

                        // Sort inactive subscriptions by updated_at
                        if (!empty($subscriptions['inactive'])) {
                            usort($subscriptions['inactive'], function ($a, $b) {
                                return strtotime($b->updated_at) - strtotime($a->updated_at);
                            });

                            echo '<h3 class="mb-4 mt-5">Inactive Subscriptions</h3>';

                            foreach ($subscriptions['inactive'] as $subscription) {
                                $publication = $subscription->publication;
                                ?>
								<div class="row mb-4">
									<div class="col-md-8 mx-auto">
										<?php
                                            // Get the publication term by publication ID
                                            $terms = get_terms([
                                                'taxonomy'   => 'optipub_publication',
                                                'hide_empty' => false,
                                                'meta_query' => [
                                                    [
                                                        'key'     => 'publication_id',
                                                        'value'   => $publication->id,
                                                        'compare' => '=',
                                                    ],
                                                ],
                                            ]);

                                $publication_slug = !empty($terms) ? $terms[0]->slug : '';
                                $card_class = $publication_slug ? 'card publication-card' : 'card';
                                ?>
										<div class="<?php echo esc_attr($card_class); ?>">
											<?php if ($publication_slug) { ?>
												<a href="<?php echo esc_url(home_url($publication_slug)); ?>" class="text-decoration-none">
												<?php } ?>
												<div class="card-body">
													<div class="d-flex align-items-center">
														<div class="flex-grow-1">
															<div class="d-flex align-items-center mb-2">
																<img src="<?php echo esc_url($publication->fav_icon_asset_path); ?>"
																	alt="Favicon"
																	style="width: 32px; height: 32px;"
																	class="me-2">
																<h4 class="mb-0 text-dark flex-grow-1">
																	<?php if ($publication_slug) { ?>
																		<a href="<?php echo esc_url(home_url($publication_slug)); ?>" class="text-decoration-none text-dark">
																			<?php echo esc_html($publication->name); ?>
																		</a>
																	<?php } else { ?>
																		<?php echo esc_html($publication->name); ?>
																	<?php } ?>
																</h4>
															</div>
															<div class="d-flex justify-content-between align-items-center">
																<?php if ($publication_slug) { ?>
																	<a href="<?php echo esc_url(home_url($publication_slug)); ?>" class="text-decoration-none">
																		<img src="<?php echo esc_url($publication->asset_path); ?>"
																			alt="Logo"
																			style="max-height: 60px;">
																	</a>
																<?php } else { ?>
																	<img src="<?php echo esc_url($publication->asset_path); ?>"
																		alt="Logo"
																		style="max-height: 60px;">
																<?php } ?>
																<small class="text-muted">
																	Updated: <?php echo esc_html(gmdate('F j, Y', strtotime($subscription->updated_at))); ?>
																</small>
															</div>
														</div>
													</div>
												</div>
												<?php if ($publication_slug) { ?>
												</a>
											<?php } ?>
										</div>
									</div>
								</div>
				<?php
                            }
                        }
                    } else {
                        echo '<p>No subscriptions found.</p>';
                    }
                } else {
                    // Load the login template
                    include(get_template_directory() . '/templates/inc/optipub/theme-templates/optipub_login.php');
                }
?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
