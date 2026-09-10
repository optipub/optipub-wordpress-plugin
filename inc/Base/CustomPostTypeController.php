<?php

namespace OptiPub\Base;

if (!defined('ABSPATH')) {
    exit;
}

class CustomPostTypeController extends BaseController
{
    public function register()
    {
        add_action('init', [$this, 'cpt_rewrite_rules']);
        add_action('init', [$this, 'register_all_resources_custom_post_type']);
        add_action('init', [$this, 'featured_post_status']);
        add_action('init', [$this, 'add_page_excerpt_support']);
        add_action('restrict_manage_posts', [$this, 'add_publication_filter']);
        add_filter('parse_query', [$this, 'filter_posts_by_publication']);
    }

    public function add_page_excerpt_support(): void
    {
        add_post_type_support('page', 'excerpt');
    }

    public function cpt_rewrite_rules(): void
    {
        /* add_rewrite_tag('%optipub_page_id%', '([0-9]+)', 'optipub_page_id='); */
    }

    public function register_all_resources_custom_post_type(): void
    {
        $optiPubCpt = get_option(Constants::OPTIPUB_METADATA);

        if (!$optiPubCpt) {
            return;
        }

        foreach ($optiPubCpt as $cpt => $cptOptions) {
            if (!$cptOptions['createCpt']) {
                continue;
            }

            $singularName = $cptOptions['cptSingularName'];
            $pluralName = $cptOptions['cptPluralName'];

            $labels = $this->getCustomPostTypeLabels($singularName, $pluralName);
            $args = $this->getCustomPostTypeArgs($labels, $pluralName, $cpt, $cptOptions);

            register_post_type($cpt, $args);
        }
    }

    public function featured_post_status(): void
    {
        register_post_status('featured', [
            'label' => _x('Featured', 'post', 'optipub'),
            // translators: %s is the number of featured posts found
            'label_count'               => _n_noop('Featured <span class="count">(%s)</span>', 'Featured <span class="count">(%s)</span>', 'optipub'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
        ]);
    }

    /**
     * Add publication filter dropdown to post type admin tables
     */
    public function add_publication_filter(): void
    {
        global $typenow;

        // Get all OptiPub post types
        $post_types = \OptiPub\Api\WpDatabaseQuery::getAllOptiPubPostTypes(
            ['post'],
            false,
            true,
            true
        );

        // Only add filter to OptiPub post types
        if (in_array($typenow, $post_types)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reason: Admin list filtering doesn't require nonce verification as it's read-only
            $selected = isset($_GET[Constants::OPTIPUB_PUBLICATION_TAXONOMY]) ? sanitize_text_field(wp_unslash($_GET[Constants::OPTIPUB_PUBLICATION_TAXONOMY])) : '';

            wp_dropdown_categories([
                'show_option_all' => __('All Publications', 'optipub'),
                'taxonomy'        => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                'name'            => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                'selected'        => $selected,
                'hide_empty'      => false,
                'value_field'     => 'slug',
            ]);
        }
    }

    /**
     * Filter posts when publication filter is used
     */
    public function filter_posts_by_publication($query): void
    {
        global $pagenow;

        // Only on admin post list pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reason: Admin list filtering doesn't require nonce verification as it's read-only
        if (is_admin() && $pagenow == 'edit.php' && isset($_GET[Constants::OPTIPUB_PUBLICATION_TAXONOMY])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reason: Admin list filtering doesn't require nonce verification as it's read-only
            $publication = sanitize_text_field(wp_unslash($_GET[Constants::OPTIPUB_PUBLICATION_TAXONOMY]));

            if ($publication) {
                $query->query_vars[Constants::OPTIPUB_PUBLICATION_TAXONOMY] = $publication;
            }
        }
    }

    private function getCustomPostTypeLabels(string $singularName, string $pluralName): array
    {
        return [
            'name'          => $pluralName,
            'singular_name' => $singularName,
            // translators: %s is the plural name of the post type
            'menu_name' => sprintf(__('OptiPub %s', 'optipub'), $pluralName),
            'all_items' => $pluralName,
            'add_new'   => __('Add new', 'optipub'),
            // translators: %s is the singular name of the post type
            'add_new_item' => sprintf(__('Add new %s', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'edit_item' => sprintf(__('Edit %s', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'new_item' => sprintf(__('New %s', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'view_item' => sprintf(__('View %s', 'optipub'), $singularName),
            // translators: %s is the plural name of the post type
            'view_items' => sprintf(__('View %s', 'optipub'), $pluralName),
            // translators: %s is the plural name of the post type
            'search_items' => sprintf(__('Search %s', 'optipub'), $pluralName),
            // translators: %s is the plural name of the post type
            'not_found' => sprintf(__('No %s found', 'optipub'), $pluralName),
            // translators: %s is the plural name of the post type
            'not_found_in_trash' => sprintf(__('No %s found in trash', 'optipub'), $pluralName),
            // translators: %s is the singular name of the post type
            'parent' => sprintf(__('Parent %s:', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'featured_image' => sprintf(__('Featured image for this %s', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'set_featured_image' => sprintf(__('Set featured image for this %s', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'remove_featured_image' => sprintf(__('Remove featured image for this %s', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'use_featured_image' => sprintf(__('Use as featured image for this %s', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'archives' => sprintf(__('%s archives', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'insert_into_item' => sprintf(__('Insert into %s', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'uploaded_to_this_item' => sprintf(__('Upload to this %s', 'optipub'), $singularName),
            // translators: %s is the plural name of the post type
            'filter_items_list' => sprintf(__('Filter %s list', 'optipub'), $pluralName),
            // translators: %s is the plural name of the post type
            'items_list_navigation' => sprintf(__('%s list navigation', 'optipub'), $pluralName),
            // translators: %s is the plural name of the post type
            'items_list' => sprintf(__('%s list', 'optipub'), $pluralName),
            // translators: %s is the plural name of the post type
            'attributes'     => sprintf(__('%s attributes', 'optipub'), $pluralName),
            'name_admin_bar' => $singularName,
            // translators: %s is the singular name of the post type
            'item_published' => sprintf(__('%s published', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'item_published_privately' => sprintf(__('%s published privately.', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'item_reverted_to_draft' => sprintf(__('%s reverted to draft.', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'item_scheduled' => sprintf(__('%s scheduled', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'item_updated' => sprintf(__('%s updated.', 'optipub'), $singularName),
            // translators: %s is the singular name of the post type
            'parent_item_colon' => sprintf(__('Parent %s:', 'optipub'), $singularName),
        ];
    }

    private function getCustomPostTypeArgs(array $labels, string $pluralName, string $cpt, array $cptOptions): array
    {
        return [
            'label'                 => $pluralName,
            'labels'                => $labels,
            'description'           => '',
            'public'                => $cptOptions['cptPublic'] ?? true,
            'publicly_queryable'    => $cptOptions['cptPubliclyQueryable'] ?? true,
            'show_ui'               => true,
            'show_in_rest'          => true,
            'rest_base'             => '',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'has_archive'           => $cptOptions['cptHasArchive'] ?? false,
            'show_in_menu'          => $cptOptions['cptShowInMenu'] ?? false,
            'show_in_nav_menus'     => true,
            'delete_with_user'      => false,
            'exclude_from_search'   => $cptOptions['cptExcludeFromSearch'] ?? false,
            'capability_type'       => 'post',
            'map_meta_cap'          => true,
            'hierarchical'          => false,
            'rewrite'               => isset($cptOptions['cptRewrite']) ? ['slug' => $cpt, 'with_front' => false] : false,
            'query_var'             => true,
            'menu_icon'             => $this->getMenuDashIconByType($cpt),
            'menu_position'         => 27,
            'supports'              => ['title', 'editor', 'thumbnail', 'author', 'post-formats', 'excerpt', 'tags'],
            'taxonomies'            => ['post_tag', 'category', 'tags', Constants::OPTIPUB_PUBLICATION_TAXONOMY],
            'show_in_graphql'       => false,
        ];
    }

    private function getMenuDashIconByType(string $type): string
    {
        $icons = [
            'articles'      => 'dashicons-welcome-widgets-menus',
            'reports'       => 'dashicons-format-aside',
            'resources'     => 'dashicons-welcome-write-blog',
            'videos'        => 'dashicons-format-video',
            'faqs'          => 'dashicons-editor-help',
            'editors'       => 'dashicons-buddicons-buddypress-logo',
            'pubs-detailed' => 'dashicons-analytics',
        ];

        return $icons[$type] ?? 'dashicons-coffee';
    }
}
