<?php

namespace OptiPubWP\PubAccess;

use OptiPub\Api\WpDatabaseQuery;
use OptiPub\Base\Constants;
use OptiPubWP\Log;
use WP_Error;

class PublicationAccess
{
    private static $instance = null;

    /**
     * Get or create the global instance of PublicationAccess
     *
     * @return PublicationAccess
     */
    public static function getInstance(): PublicationAccess
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function return_optipub_publication_primary_term(): \WP_Term
    {
        $primary_publication_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        // get taxonomy
        $primary_term = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'publication_id',
                    'value'   => $primary_publication_id,
                    'compare' => '=',
                ],
            ],
        ]);

        $term_meta = get_term_meta($primary_term[0]->term_id);
        $primary_term[0]->meta = $term_meta;

        return $primary_term[0] ?? null;
    }

    /**
     * Get publication term data by publication ID
     *
     * @param int    $publication_id The OptiPub publication ID
     * @param string $meta_key       Optional specific meta key to retrieve
     * @return mixed Term object with meta, specific meta value if key provided, or false if not found
     */
    public function return_optipub_all_publication_terms($paid_only = false, $gated_only = false): array
    {
        // get taxonomy
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            return [];
        }

        $all_terms = [];

        foreach ($terms as $term) {
            $term->meta = get_term_meta($term->term_id);
            $publication_id = $term->meta['publication_id'][0];
            $is_paid = $term->meta['publication_paid'][0] === '1';
            $is_gated = $term->meta['publication_gated'][0] === '1';

            switch (true) {
                case $paid_only && $is_paid && $gated_only && $is_gated:
                case $paid_only && $is_paid:
                case $gated_only && $is_gated:
                default:
                    $all_terms[$publication_id] = $term;
                    break;
            }
        }

        return $all_terms;
    }

    /**
     * Retrieves the publication term for the current page.
     *
     * This function:
     * 1. Gets the current page ID
     * 2. Retrieves the publication taxonomy terms for the page
     * 3. Returns the first term with its meta data
     *
     * @return \WP_Term|null The publication term with meta data, or null if not found
     */
    public function return_optipub_current_page_publication_term($post_id = null): ?\WP_Term
    {
        try {
            if ($post_id === null) {
                $current_page_id = $this->getCurrentPageId();
            } else {
                $current_page_id = $post_id;
            }

            if (!$current_page_id) {
                return null;
            }

            $terms = $this->getPublicationTerms($current_page_id);

            if (!$terms) {
                return null;
            }

            return $this->enrichTermWithMeta($terms[0]);
        } catch (\Exception $e) {
            // Only log actual errors, not normal "not found" cases
            if (!($e instanceof WP_Error)) {
            }

            return null;
        }
    }

    /**
     * Determines if the current page's publication term is gated.
     *
     * This function:
     * 1. Gets the current page's publication term
     * 2. Checks if the term exists and has gated status
     * 3. Returns the gated status (true/false)
     *
     * @return bool True if the current page's publication is gated, false otherwise
     */
    public function return_optipub_current_page_publication_term_is_gated($post_id = null): bool
    {
        if ($post_id === null) {
            $current_page_term = $this->return_optipub_current_page_publication_term();
        } else {
            $current_page_term = $this->return_optipub_current_page_publication_term($post_id);
        }

        // Return false if no term exists or if gated status is not set
        if (!$current_page_term || !isset($current_page_term->meta['publication_gated'][0])) {
            return false;
        }

        // Return the gated status, ensuring it's a boolean
        return (bool) $current_page_term->meta['publication_gated'][0];
    }

    /**
     * Get all public posts of the current publication or the query args
     *
     * @param array|string $post_types Optional array of post types to retrieve
     * @param bool $return_query_args Optional return the query arguments instead of posts
     * @return array Array of posts or query arguments
     */
    public function return_optipub_current_publication_all_posts_of_type($post_types = null, $return_query_args = false): array
    {
        $current_page_term = $this->return_optipub_current_page_publication_term();

        if (!$current_page_term) {
            return [];
        }

        $public_post_statuses = get_post_stati(['public' => true], 'names', 'or');

        if (empty($post_types)) {
            $post_types = WpDatabaseQuery::getAllOptiPubPostTypes(['editors'], true, false, true);
        } else {
            // Ensure post_types is always an array
            $post_types = (array) $post_types;
        }

        $query_args = [
            'post_type'   => $post_types,
            'post_status' => array_keys($public_post_statuses),
            'numberposts' => -1,
            'meta_query'  => [
                [
                    'key'     => 'publication_id',
                    'value'   => $current_page_term->meta['publication_id'][0],
                    'compare' => '=',
                ],
            ],
        ];

        if ($return_query_args) {
            return $query_args;
        }

        // Get all public posts of the current publication
        return get_posts($query_args);
    }

    /**
     * Get publication term data by publication ID
     *
     * @param int    $publication_id The OptiPub publication ID
     * @param string $meta_key       Optional specific meta key to retrieve
     * @return mixed Term object with meta, specific meta value if key provided, or false if not found
     *
     * no meta key - return data is $term->name and $term->meta['meta_key'][0]
     * meta key - return data is $termmeta
     */
    public function return_optipub_publication_term_by_id($publication_id, string $meta_key = ''): mixed
    {
        $terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'publication_id',
                    'value'   => $publication_id,
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        $term = $terms[0];

        // If meta_key is specified, check if it's a standard term property
        if (!empty($meta_key)) {
            // Check if it's a standard term property
            if (property_exists($term, $meta_key)) {
                return $term->$meta_key;
            }

            // If not, get it from term meta
            return get_term_meta($term->term_id, $meta_key, true);
        }

        // Return full term object with all properties and meta
        $term->meta = get_term_meta($term->term_id);

        return $term;
    }

    public function return_optipub_get_publication_template_page_url(int $publication_id, string $template_name = '')
    {
        $publication = $this->return_optipub_get_publication_terms_by_pub_id($publication_id);

        if (!$publication || is_wp_error($publication)) {
            return new WP_Error('publication_not_found', 'Publication not found');
        }

        // If no template name provided, use subscribers template
        if (empty($template_name)) {
            $template_name = get_option(Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE);
        }

        if ($template_name) {
            // Query for pages using this template
            $pages = get_pages([
                'meta_key'    => '_wp_page_template',
                'meta_value'  => $template_name,
                'number'      => 1,
                'sort_order'  => 'ASC',
                'sort_column' => 'menu_order',
            ]);

            if (!empty($pages)) {
                return get_permalink($pages[0]->ID);
            }
        }

        return false;
    }

    /**
     * Gets the current page ID
     *
     * @return int|null The current page ID or null if not found
     */
    private function getCurrentPageId(): ?int
    {
        $page_id = get_queried_object_id();

        return $page_id ?: null;
    }

    /**
     * Gets the publication terms for a page
     *
     * @param int $page_id The page ID to get terms for
     * @return array|null Array of terms or null if not found
     */
    private function getPublicationTerms(int $page_id): ?array
    {
        $terms = get_the_terms($page_id, Constants::OPTIPUB_PUBLICATION_TAXONOMY);

        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        return $terms;
    }

    /**
     * Adds meta data to a term
     *
     * @param \WP_Term $term The term to enrich with meta data
     * @return \WP_Term The term with meta data
     */
    private function enrichTermWithMeta(\WP_Term $term): \WP_Term
    {
        $term->meta = get_term_meta($term->term_id);

        return $term;
    }
}
