<?php

namespace OptiPub\Api;

use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;
use WP_Query;

class WpDatabaseQuery extends BaseController
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Returns post types based on specified criteria
     *
     * @param array  $except_types     Post types to exclude
     * @param bool   $must_have_posts  Only return post types with published posts
     * @param bool   $include_builtin  Include WordPress built-in post types
     * @param bool   $optipub_only    Only return OptiPub post types
     * @return array Array of post type names
     */
    public static function getAllOptiPubPostTypes($except_types = [], $must_have_posts = true, $include_builtin = false, $optipub_only = true): array
    {
        $post_types = [];

        if ($optipub_only) {
            // Get only OptiPub post types from metadata
            $optiPubMetadata = get_option(Constants::OPTIPUB_METADATA, []);

            foreach ($optiPubMetadata as $key => $val) {
                if ($val['createCpt'] ?? false) {
                    $post_types[] = $key;
                }
            }
        } else {
            // Get all non-built-in public post types
            $post_types = get_post_types([
                '_builtin' => false,
            ], 'names', 'and');
        }

        // Add built-in types if requested
        if ($include_builtin) {
            $builtin_types = get_post_types([
                'public'   => true,
                '_builtin' => true,
            ], 'names', 'and');
            $post_types = array_merge($post_types, $builtin_types);
        }

        // Remove excluded types
        $post_types = array_diff($post_types, $except_types);

        // Check for posts if required
        if ($must_have_posts) {
            $public_post_statuses = get_post_stati(['public' => true], 'names', 'or');

            foreach ($post_types as $key => $post_type) {
                $query = new WP_Query([
                    'post_type'      => $post_type,
                    'posts_per_page' => 1,
                    'post_status'    => array_keys($public_post_statuses),
                ]);

                if (!$query->have_posts()) {
                    unset($post_types[$key]);
                }
            }
        }

        return array_values($post_types);
    }

    /**
     * Returns all years of post_type published
     */
    public static function getAllPostTypeYears($post_types = [])
    {
        global $wpdb;

        if (empty($post_types)) {
            $post_types = self::getAllOptiPubPostTypes();
        } elseif (is_string($post_types)) {
            $post_types = [$post_types];
        }

        if (!empty($post_types) && is_array($post_types)) {
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

            $query = "SELECT DISTINCT YEAR(post_date) AS year
                FROM {$wpdb->posts}
                WHERE post_type IN ({$placeholders})
                ORDER BY year";
            $args = $post_types;

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
            return $wpdb->get_results($wpdb->prepare($query, ...$args));
        }

        $query = "SELECT DISTINCT YEAR(post_date) AS year
            FROM {$wpdb->posts}
            WHERE post_type LIKE %s
            ORDER BY year";
        $args = ['%'];

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        return $wpdb->get_results($wpdb->prepare($query, ...$args));
    }

    /**
     * Returns all months of post_type published
     */
    public static function getAllPostTypeMonths($post_types = [])
    {
        global $wpdb;

        if (empty($post_types)) {
            $post_types = self::getAllOptiPubPostTypes();
        } elseif (is_string($post_types)) {
            $post_types = [$post_types];
        }

        if (!empty($post_types) && is_array($post_types)) {
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $query = "SELECT DISTINCT MONTHNAME(post_date) AS month_name, MONTH(post_date) AS month_number
                FROM {$wpdb->posts}
                WHERE post_type IN ({$placeholders})
                ORDER BY month_number";
            $args = $post_types;

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
            return $wpdb->get_results($wpdb->prepare($query, ...$args));
        }

        $query = "SELECT DISTINCT MONTHNAME(post_date) AS month_name, MONTH(post_date) AS month_number
            FROM {$wpdb->posts}
            WHERE post_type LIKE %s
            ORDER BY month_number";
        $args = ['%'];

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        return $wpdb->get_results($wpdb->prepare($query, ...$args));
    }

    public function getAllPostTypeCategories($post_types = [], $year = '', $month = '')
    {
        global $wpdb;

        if (empty($post_types)) {
            $post_types = self::getAllOptiPubPostTypes();
        } elseif (is_string($post_types)) {
            $post_types = [$post_types];
        }

        $sql = "SELECT DISTINCT CONCAT(UCASE(LEFT(terms.name, 1)), SUBSTRING(terms.name, 2)) AS name
            FROM {$wpdb->terms} AS terms
            INNER JOIN {$wpdb->term_taxonomy} AS taxonomy ON terms.term_id = taxonomy.term_id
            INNER JOIN {$wpdb->term_relationships} AS tr ON tr.term_taxonomy_id = taxonomy.term_taxonomy_id
            INNER JOIN {$wpdb->posts} AS posts ON posts.ID = tr.object_id
            WHERE taxonomy.taxonomy = 'category'";

        $params = [];

        if (!empty($post_types) && is_array($post_types)) {
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $sql .= " AND posts.post_type IN ({$placeholders})";
            $params = array_merge($params, $post_types);
        }

        if (!empty($year)) {
            $sql .= ' AND YEAR(posts.post_date) = %d';
            $params[] = (int) $year;
        }

        if (!empty($month)) {
            $sql .= ' AND MONTH(posts.post_date) = %d';
            $params[] = (int) $month;
        }

        $sql .= ' ORDER BY name';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $result = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        if (false === $result) {
            $this->logError("\nFailed getting Categories\n");
        }

        return $result;
    }

    /**
     * Returns all post type editors
     *
     * @param array|string $post_types The post types
     * @param string|int   $year The year
     * @param string|int   $month The month
     * @param bool         $strict_show_on_website Whether to strictly check show on website
     * @return array<int, string> List of editors.
     */
    public function getAllPostTypeEditors($post_types = [], $year = '', $month = '', $strict_show_on_website = true)
    {
        global $wpdb;

        if (empty($post_types)) {
            $post_types = self::getAllOptiPubPostTypes();
        } elseif (is_string($post_types)) {
            $post_types = [$post_types];
        }

        $sql = "SELECT DISTINCT pm.meta_value AS editor
            FROM {$wpdb->postmeta} AS pm
            INNER JOIN {$wpdb->posts} AS p ON pm.post_id = p.ID";

        if ($strict_show_on_website) {
            $sql .= " INNER JOIN {$wpdb->postmeta} AS pm2 
                    ON pm.post_id = pm2.post_id 
                    AND pm2.meta_key = 'editor_show_on_website' 
                    AND pm2.meta_value = '1'";
        }

        $sql .= " WHERE pm.meta_key = 'resource_editor'";

        $params = [];

        if (!empty($post_types)) {
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $sql .= " AND p.post_type IN ({$placeholders})";
            $params = array_merge($params, $post_types);
        }

        if (!empty($year)) {
            $sql .= ' AND YEAR(p.post_date) = %d';
            $params[] = (int) $year;
        }

        if (!empty($month)) {
            $sql .= ' AND MONTH(p.post_date) = %d';
            $params[] = (int) $month;
        }

        $sql .= ' ORDER BY editor';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $result = $wpdb->get_col($wpdb->prepare($sql, ...$params));

        if (false === $result) {
            $this->logError("\nFailed getting Editors\n");
        }

        return $result;
    }

    /**
     * Resets all content
     *
     * @param bool $clear_publication_terms Whether to clear publication terms
     */
    public function resetAllContent(bool $clear_publication_terms = true)
    {
        global $wpdb;

        // Delete posts and postmeta when optipub postmeta matches
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $result = $wpdb->query(
            "DELETE posts, postmeta
            FROM {$wpdb->posts} AS posts
            JOIN {$wpdb->postmeta} postmeta ON posts.id = postmeta.post_id
            WHERE postmeta.meta_value = 'optipub-plugin'"
        );

        if (false === $result) {
            $this->logError("\nFailed deleting posts and postmeta\n");
        }

        // Clean up orphaned postmeta
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $result = $wpdb->query(
            "DELETE FROM {$wpdb->postmeta}
            WHERE post_id NOT IN (SELECT id FROM {$wpdb->posts})"
        );

        if (false === $result) {
            $this->logError("\nFailed cleaning up postmeta\n");
        }

        // Clean up term relationships
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $result = $wpdb->query(
            "DELETE FROM {$wpdb->term_relationships}
            WHERE object_id NOT IN (SELECT id FROM {$wpdb->posts})"
        );

        if (false === $result) {
            $this->logError("\nFailed cleaning up term relationships\n");
        }

        if ($clear_publication_terms) {
            // Delete all terms created by optipub (including those without termmeta)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
            $result = $wpdb->query(
                "DELETE FROM {$wpdb->terms}
                WHERE term_id IN (
                    SELECT DISTINCT term_id
                    FROM {$wpdb->termmeta}
                    WHERE meta_value = 'optipub-plugin'
                )"
            );

            if (false === $result) {
                $this->logError("\nFailed deleting terms\n");
            }

            // Clean up orphaned termmeta
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
            $result = $wpdb->query(
                "DELETE FROM {$wpdb->termmeta}
                WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->terms})"
            );

            if (false === $result) {
                $this->logError("\nFailed cleaning up orphaned termmeta\n");
            }

            // Clean up orphaned taxonomy
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->query(
                "DELETE FROM {$wpdb->term_taxonomy}
                WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->terms})"
            );

            if (false === $result) {
                $this->logError("\nFailed cleaning up orphaned taxonomy\n");
            }
        } else {
            // Delete only non-publication terms created by optipub
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->terms}
                    WHERE term_id IN (
                        SELECT DISTINCT tm.term_id
                        FROM {$wpdb->termmeta} tm
                        JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id
                        WHERE tm.meta_value = 'optipub-plugin'
                        AND tt.taxonomy != %s
                    )",
                    Constants::OPTIPUB_PUBLICATION_TAXONOMY
                )
            );

            if (false === $result) {
                $this->logError("\nFailed deleting non-publication optipub terms\n");
            }

            // Clean up orphaned termmeta
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->query(
                "DELETE FROM {$wpdb->termmeta}
                WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->terms})"
            );

            if (false === $result) {
                $this->logError("\nFailed cleaning up orphaned termmeta\n");
            }

            // Clean up orphaned taxonomy
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
            $result = $wpdb->query(
                "DELETE FROM {$wpdb->term_taxonomy}
                WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->terms})"
            );

            if (false === $result) {
                $this->logError("\nFailed cleaning up orphaned taxonomy\n");
            }
        }

        // Delete custom content options
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s",
                $wpdb->esc_like('{$custom_') . '%'
            )
        );

        if (false === $result) {
            $this->logError("\nFailed deleting custom content\n");
        }

        // Handle redirects table
        $redirects_table = esc_sql($wpdb->prefix . 'optipub_redirects');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query("TRUNCATE TABLE {$redirects_table}");

        if (false === $result) {
            $this->logError("\nFailed truncating redirects table\n");
        }
    }

    public function updatePostModifiedDate($post_id, $postDateModified, $postDateModifiedGMT)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_modified_gmt = %s, post_modified = %s WHERE ID = %d",
            $postDateModifiedGMT,
            $postDateModified,
            $post_id
        ));

        if (false === $result) {
            $this->logError("\nFailed updating post_modified for {$post_id}\n");
        }
    }

    /**
     * Returns the editor for a post
     *
     * @param int $author_id The author ID
     * @param int $post_id The post ID
     * @return object|null The editor object or null if no editor is found
     */
    public static function getEditorForPost($author_id, $post_id = '')
    {
        if (0 == $author_id && $post_id) {
            // Get editor from post meta
            $editor = get_post_meta($post_id, 'resource_editor', true);

            if (!empty($editor)) {
                return (object) [
                    'meta_id'    => null,
                    'post_id'    => $post_id,
                    'meta_key'   => 'resource_editor',
                    'meta_value' => $editor,
                ];
            }
        } else {
            // Get editor post by author
            $args = [
                'post_type'      => 'editors',
                'author'         => $author_id,
                'posts_per_page' => 1,
                'post_status'    => 'any',
            ];

            $query = new WP_Query($args);

            if ($query->have_posts()) {
                return $query->posts[0];
            }
        }
    }

    /**
     * Update custom post type statuses in case WP tries to set a built-in one
     *
     * @param string $postType The post type
     * @param string $postStatus The post status
     */
    public function updateCPTPostStatus($postType, $postStatus)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_status = %s WHERE post_type = %s",
            $postStatus,
            $postType
        ));

        if (false === $result) {
            $this->logError("\nFailed updating post_status for {$postStatus}\n");
        }
    }

    /**
     * Returns the post ID by meta key and value
     *
     * @param string $meta_key The meta key
     * @param string $optipub_id The OptiPub ID
     * @param string $post_type The post type
     */
    public static function get_post_id_by_meta_key_and_value($meta_key, $optipub_id, $post_type = '%', $post_parent = 0)
    {
        // Use WP_Query to find posts with the specified meta key and value
        $args = [
            'post_type'      => $post_type === '%' ? 'any' : $post_type,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => $meta_key,
                    'value'   => $optipub_id,
                    'compare' => '=',
                ],
            ],
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $post = $query->posts[0];

            // Check if the post parent matches the requested parent
            if ($post_parent == $post->post_parent) {
                return $post->ID;
            }
        }

        return false;
    }
}
