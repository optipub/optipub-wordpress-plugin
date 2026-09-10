<?php

namespace OptiPub\Base;

if (!defined('ABSPATH')) {
    exit;
}

class RewriteUrlForCPT extends BaseController
{
    // Cache for OptiPub post types to avoid multiple calls during sync
    private $cached_optipub_post_types = null;

    public function register()
    {
        // OptiPub CPT Permalink Constructor - output
        add_filter('post_type_link', [$this, 'optipub_cpt_permalinks'], 10, 3);
        // OptiPub Rewrite Rules - input
        add_action('init', [$this, 'modify_post_type_rewrite_rules'], 10);
        // OptiPub Query Vars - input
        add_filter('query_vars', [$this, 'register_optipub_query_vars']);
        // Filter main query to handle publication-specific pages
        add_action('pre_get_posts', [$this, 'filter_main_query']);
        // Modify WordPress slug behavior for posts
        add_filter('wp_unique_post_slug', [$this, 'optipub_unique_post_slug'], 10, 6);
    }

    public function register_optipub_query_vars($vars)
    {
        $vars[] = 'optipub_parent_path';

        return $vars;
    }

    /**
     * Create rewrite rules for handling incoming requests with proper publication hierarchy
     */
    public function modify_post_type_rewrite_rules(): void
    {
        // Register rewrite tags
        add_rewrite_tag('%optipub_parent_path%', '([^/]+)');

        // Get all OptiPub post types
        $optipub_post_types = \OptiPub\Api\WpDatabaseQuery::getAllOptiPubPostTypes(
            [],
            false,
            false,
            true
        );
        $optipub_post_types[] = 'page';

        // Get all publications to check their page settings
        $publications = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
        ]);

        // Collect all post types that use post type in URL from all publications
        $post_types_with_prefix = [];

        foreach ($publications as $publication) {
            $page_settings = get_term_meta($publication->term_id, 'publication_page_settings', true);
            $page_settings = $page_settings ? json_decode($page_settings, true) : [];

            foreach ($optipub_post_types as $post_type) {
                if (isset($page_settings[$post_type]['cptUsePostTypeInUrl']) && $page_settings[$post_type]['cptUsePostTypeInUrl']) {
                    $post_types_with_prefix[$post_type] = true;
                }
            }
        }

        // Only create rewrite rules for post types that use post type in URL
        foreach ($post_types_with_prefix as $post_type => $use_prefix) {
            add_rewrite_rule(
                '^' . $post_type . '/([^/]+)/?$',
                'index.php?post_type=' . $post_type . '&name=$matches[1]',
                'top'
            );

            add_rewrite_rule(
                '^(.+)/' . $post_type . '/([^/]+)/?$',
                'index.php?optipub_parent_path=$matches[1]&post_type=' . $post_type . '&name=$matches[2]',
                'top'
            );
        }

        // Rule to capture full path and name (for post types without prefix)
        // Exclude category URLs, tag URLs, and other WordPress built-in URLs to prevent conflicts
        $permalink_category_base = trim(get_option('category_base', 'category'), '/');
        $permalink_tag_base = trim(get_option('tag_base', 'tag'), '/');

        add_rewrite_rule(
            '^(?!' . preg_quote($permalink_category_base, '/') . '/)(?!' . preg_quote($permalink_tag_base, '/') . '/)(?!author/)(?!date/)(?!search/)(.+)/([^/]+)/?$',
            'index.php?optipub_parent_path=$matches[1]&name=$matches[2]',
            'top'
        );
    }

    /**
     * Transforms permalinks to use the proper publication hierarchy
     */
    public function optipub_cpt_permalinks($post_link, $post, $leavename)
    {
        // Also check for page post type
        $optipub_post_types = \OptiPub\Api\WpDatabaseQuery::getAllOptiPubPostTypes(
            [], // No longer exclude editors
            false,
            false,
            true
        );
        $optipub_post_types[] = 'page'; // Add pages to the types we handle

        if (!in_array($post->post_type, $optipub_post_types)) {
            return $post_link;
        }

        // Get post's publication
        $terms = wp_get_object_terms($post->ID, Constants::OPTIPUB_PUBLICATION_TAXONOMY);

        if (empty($terms)) {
            // No publication, return standard URL but remove post type prefix
            return home_url('/' . $post->post_name . '/');
        }

        $page_settings = get_term_meta($terms[0]->term_id, 'publication_page_settings', true);
        $page_settings = $page_settings ? json_decode($page_settings, true) : [];
        $cpt_use_post_type_in_url = $page_settings[$post->post_type]['cptUsePostTypeInUrl'] ?? false;

        // For primary publication, keep at root level
        $is_primary = get_term_meta($terms[0]->term_id, 'publication_primary', true);

        if ($is_primary === '1') {
            if ($cpt_use_post_type_in_url) {
                return home_url("/{$post->post_type}/{$post->post_name}/");
            }

            return home_url("/{$post->post_name}/");
        }

        // Special handling for editors post type
        if ($post->post_type === 'editors') {
            // Get the editors page slug from taxonomy term meta
            $editors_slug = isset($page_settings['editors']['pageSlug']) ? $page_settings['editors']['pageSlug'] : 'editors';

            // Build path with publication slug and editors page slug
            $path = '/' . $terms[0]->slug . '/' . $editors_slug . '/' . $post->post_name;

            return home_url($path . '/');
        }

        // For other post types, get the path from parent hierarchy
        $path = $cpt_use_post_type_in_url ? "/{$post->post_type}/{$post->post_name}" : "/{$post->post_name}";

        // Build parent path
        if ($post->post_parent) {
            $parent_ids = array_reverse(get_post_ancestors($post->ID));

            $parent_path = '';

            foreach ($parent_ids as $parent_id) {
                $parent = get_post($parent_id);

                if ($parent) {
                    $parent_path .= '/' . $parent->post_name;
                }
            }

            // Full path including parent structure
            $path = $parent_path . $path;
        }

        return home_url("{$path}/");
    }

    public function filter_main_query($query)
    {
        // Only modify the main query on the front end
        if (is_admin() || !$query->is_main_query() || !is_singular()) {
            return;
        }

        // Get the parent path and name from the URL
        $parent_path = get_query_var('optipub_parent_path');
        $page_name = get_query_var('name');
        $post_type = get_query_var('post_type');

        if (empty($page_name)) {
            return;
        }

        // If post_type is set in query vars, this is a post type prefix URL
        if (!empty($post_type)) {
            // Check if this post type is allowed to use post type in URL
            $post = get_page_by_path($page_name, OBJECT, $post_type);

            if ($post) {
                // Get post's publication to check settings
                $terms = wp_get_object_terms($post->ID, Constants::OPTIPUB_PUBLICATION_TAXONOMY);

                if (!empty($terms)) {
                    $page_settings = get_term_meta($terms[0]->term_id, 'publication_page_settings', true);
                    $page_settings = $page_settings ? json_decode($page_settings, true) : [];
                    $cpt_use_post_type_in_url = $page_settings[$post_type]['cptUsePostTypeInUrl'] ?? false;

                    // If this post type should NOT use post type in URL, return 404
                    if (!$cpt_use_post_type_in_url) {
                        $query->set_404();

                        return;
                    }
                }
            }

            // Check if this is a hierarchical URL with post type prefix
            if (!empty($parent_path)) {
                // URL format: /parent-path/post-type/post-name/
                // Split parent path to check if it contains post type
                $path_parts = array_filter(explode('/', $parent_path));
                $last_path_part = end($path_parts);

                // If the last part of parent path is the post type, remove it from parent path
                if ($last_path_part === $post_type) {
                    array_pop($path_parts);
                    $parent_path = implode('/', $path_parts);
                }
            }

            // Get the specific post by post type and name
            if ($post) {
                $query->set('post__in', [$post->ID]);
                $query->set('post_type', $post_type);
            } else {
                $query->set_404();
            }

            return;
        }

        // Split the parent path into parts
        $path_parts = array_filter(explode('/', $parent_path));

        // Get all posts with this name
        $all_posts = get_posts([
            'post_type'      => 'any',
            'name'           => $page_name,
            'posts_per_page' => -1,
            'post_status'    => get_post_stati(['public' => true]),
        ]);

        // Filter out posts that should use post type in URL
        $filtered_posts = [];

        foreach ($all_posts as $post) {
            $terms = wp_get_object_terms($post->ID, Constants::OPTIPUB_PUBLICATION_TAXONOMY);

            if (!empty($terms)) {
                $page_settings = get_term_meta($terms[0]->term_id, 'publication_page_settings', true);
                $page_settings = $page_settings ? json_decode($page_settings, true) : [];
                $cpt_use_post_type_in_url = $page_settings[$post->post_type]['cptUsePostTypeInUrl'] ?? false;

                // Only include posts that should NOT use post type in URL
                if (!$cpt_use_post_type_in_url) {
                    $filtered_posts[] = $post;
                }
            } else {
                // Posts without publication can use non-prefix URLs
                $filtered_posts[] = $post;
            }
        }

        // Find the post that matches the parent hierarchy
        $matching_post = null;

        foreach ($filtered_posts as $post) {
            // If this is a root URL request (no parent path)
            if (empty($path_parts)) {
                // Only match if post has no parent (root post)
                if (!$post->post_parent) {
                    $matching_post = $post;
                    break;
                }
                continue;
            }

            // For directory URLs, verify the parent chain
            $current_post = $post;
            $path_matches = true;

            // Work backwards through the path
            foreach (array_reverse($path_parts) as $path_part) {
                // Get parent post
                $parent = get_post($current_post->post_parent);

                // If no parent or parent name doesn't match, this path is wrong
                if (!$parent || $parent->post_name !== $path_part) {
                    $path_matches = false;
                    break;
                }

                $current_post = $parent;
            }

            // If we found a matching path, this is our post
            if ($path_matches) {
                $matching_post = $post;
                break;
            }
        }

        if ($matching_post) {
            $query->set('post__in', [$matching_post->ID]);
        } else {
            $query->set_404();
        }
    }

    /**
     * Modifies WordPress's default slug behavior to only append numbers for posts with the same parent
     * Priority order: dynamic pages first, then regular pages, then OptiPub post types, then oldest posts by date
     */
    public function optipub_unique_post_slug($slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug)
    {
        global $wpdb;

        if ($this->cached_optipub_post_types === null) {
            $this->cached_optipub_post_types = \OptiPub\Api\WpDatabaseQuery::getAllOptiPubPostTypes([], false, false, true);
            $this->cached_optipub_post_types[] = 'page';
        }

        if (!in_array($post_type, $this->cached_optipub_post_types, true)) {
            return;
        }

        $publicStatuses = parent::getPublicPostStatuses();

        // Generate placeholders for IN() dynamically
        $statusPlaceholders = implode(',', array_fill(0, count($publicStatuses), '%s'));
        $postTypePlaceholders = implode(',', array_fill(0, count($this->cached_optipub_post_types), '%s'));

        // Build argument array for prepare
        $args = [$original_slug, $post_parent, $post_ID];
        $args = array_merge($args, $publicStatuses, $this->cached_optipub_post_types);

        $sql = "SELECT p.ID, p.post_type, p.post_date_gmt, pm.meta_id AS is_dynamic_page
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm 
                ON p.ID = pm.post_id AND pm.meta_key = '_optipub_plugin_page'
            WHERE p.post_name = %s
            AND p.post_parent = %d
            AND p.ID != %d
            AND p.post_status IN ({$statusPlaceholders})
            AND p.post_type IN ({$postTypePlaceholders})
            ORDER BY
                CASE WHEN pm.meta_id IS NOT NULL THEN 0 ELSE 1 END,
                CASE WHEN p.post_type = 'page' THEN 0 ELSE 1 END,
                CASE WHEN p.post_type IN ({$postTypePlaceholders}) THEN 0 ELSE 1 END,
                p.post_date_gmt ASC
            LIMIT 1";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $existing_post = $wpdb->get_row($wpdb->prepare($sql, ...$args));

        if (!$existing_post) {
            return $original_slug;
        }

        // Suffix loop
        $suffix = 2;
        do {
            $new_slug = $original_slug . '-' . $suffix;

            $check_args = array_merge([$new_slug, $post_parent, $post_ID], $publicStatuses);

            $count_sql = "SELECT COUNT(*)
                FROM {$wpdb->posts}
                WHERE post_name = %s
                AND post_parent = %d
                AND ID != %d
                AND post_status IN ({$statusPlaceholders})";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
            $exists = $wpdb->get_var($wpdb->prepare($count_sql, ...$check_args));
            $suffix++;
        } while ($exists > 0);

        return $new_slug;
    }
}
