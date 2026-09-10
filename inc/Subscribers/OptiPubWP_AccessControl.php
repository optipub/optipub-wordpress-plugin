<?php

namespace OptiPub\Subscribers;

use OptiPub\Base\Constants;
use WP_Query;
use WP_User;

/**
 * Handle publication access control and content restrictions
 */
class OptiPubWP_AccessControl
{
    /**
     * Filter main query to only show content from accessible publications
     *
     * @param WP_Query $query The WordPress query object
     */
    public function filterQueryByUserAccess(WP_Query $query): void
    {
        // Only modify the main query on the front end
        if (is_admin() || !$query->is_main_query() || is_singular()) {
            return;
        }

        // Get current user
        $user = wp_get_current_user();

        if (!$user->ID) {
            // For logged-out users, only show ungated content
            $this->restrictToUngatedPublications($query);

            return;
        }

        // Get all publication terms
        $publications = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
        ]);

        if (empty($publications) || is_wp_error($publications)) {
            return;
        }

        // Check which publications the user has access to
        $accessible_terms = [];

        foreach ($publications as $publication) {
            $publication_id = get_term_meta($publication->term_id, 'publication_id', true);
            $is_gated = get_term_meta($publication->term_id, 'publication_gated', true);

            // Add ungated publications by default
            if ($is_gated !== '1') {
                $accessible_terms[] = $publication->term_id;
                continue;
            }

            // Check if user has access to this publication
            $role_slug = Constants::OPTIPUB_ROLE_PREFIX . sanitize_title($publication_id);

            if (in_array($role_slug, $user->roles)) {
                $accessible_terms[] = $publication->term_id;
            }
        }

        // Filter query to only show accessible publications
        if (!empty($accessible_terms)) {
            $tax_query = $query->get('tax_query') ?: [];
            $tax_query[] = [
                'taxonomy' => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                'field'    => 'term_id',
                'terms'    => $accessible_terms,
                'operator' => 'IN',
            ];
            $query->set('tax_query', $tax_query);
        }
    }

    /**
     * Check if current user has access to current post's publication
     */
    public function checkPublicationAccess(): void
    {
        // Only check on single post/page views
        if (!is_singular()) {
            return;
        }

        // Get current post
        $post = get_post();

        if (!$post) {
            return;
        }

        // Allow access to users with admin privileges
        if (is_admin()) {
            return;
        }

        // Get publication terms for this post
        $terms = wp_get_post_terms($post->ID, Constants::OPTIPUB_PUBLICATION_TAXONOMY);

        if (empty($terms) || is_wp_error($terms)) {
            return;
        }

        // Get the publication term
        $publication = $terms[0];

        // Get publication data
        $is_gated = get_term_meta($publication->term_id, 'publication_gated', true);

        // Allow access to ungated content
        if ($is_gated !== '1') {
            return;
        }

        // Get publication ID
        $publication_id = get_term_meta($publication->term_id, 'publication_id', true);

        if (!$publication_id) {
            return;
        }

        // Check if user has the capability to read this publication
        $capability = 'read_optipub_publication_' . $publication_id;

        if (!current_user_can($capability)) {
            // Handle access denial
            $this->denyAccess($publication);
        }
    }

    /**
     * Add dynamic session capabilities based on user roles
     *
     * This dynamically adds capabilities without storing them in the database
     * Capabilities only exist during the current user session
     *
     * @param array $allcaps All capabilities of the user
     * @param array $caps Capabilities being checked
     * @param array $args [0]=capability, [1]=user id, [2]=object ID
     * @param WP_User $user The user object
     * @return array Modified capabilities
     */
    public function addSessionCapabilities($allcaps, $caps, $args, $user): array
    {
        // Check for read_publication_[publication_id] capabilities
        if (isset($args[0]) && strpos($args[0], 'read_publication_') === 0) {
            $publication_id = str_replace('read_publication_', '', $args[0]);
            $role_slug = 'read_' . Constants::OPTIPUB_ROLE_PREFIX . sanitize_title($publication_id);

            // If user has the role for this publication, grant the capability
            if (in_array($role_slug, $user->roles)) {
                $allcaps[$args[0]] = true;
            }
        }

        return $allcaps;
    }

    /**
     * Check if a user has access to a specific publication
     *
     * @param int $user_id User ID
     * @param int $publication_id Publication ID
     * @return bool True if user has access, false otherwise
     */
    public function userHasPublicationAccess(int $user_id, int $publication_id): bool
    {
        if (!$user_id || !$publication_id) {
            return false;
        }

        // Get user
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return false;
        }

        // Check if user has the role for this publication
        $role_slug = Constants::OPTIPUB_ROLE_PREFIX . sanitize_title($publication_id);

        return in_array($role_slug, $user->roles);
    }

    /**
     * Restrict query to only show ungated publications
     *
     * @param WP_Query $query The WordPress query object
     */
    private function restrictToUngatedPublications(WP_Query $query): void
    {
        // Get ungated publication terms
        $ungated_terms = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'publication_gated',
                    'value'   => '0',
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ]);

        if (empty($ungated_terms) || is_wp_error($ungated_terms)) {
            return;
        }

        // Filter query to only show ungated publications
        $tax_query = $query->get('tax_query') ?: [];
        $tax_query[] = [
            'taxonomy' => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'field'    => 'term_id',
            'terms'    => $ungated_terms,
            'operator' => 'IN',
        ];
        $query->set('tax_query', $tax_query);
    }

    /**
     * Deny access and show appropriate template
     *
     * @param object $publication The publication term object
     */
    private function denyAccess($publication): void
    {
        // Set status code to 403
        status_header(403);
        nocache_headers();

        // Store the publication data in global for template use
        global $optipub_gated_publication;
        $optipub_gated_publication = $publication;

        // Look for theme's 403 template
        $template_403 = locate_template('optipub_login.php');

        if ($template_403) {
            // If theme has a 403.php template, use it
            include($template_403);
        } else {
            // Try to load subscription template
            $subscription_template = locate_template('optipub_subscribe.php');

            if ($subscription_template) {
                include($subscription_template);
            } else {
                // Fall back to 404 template which most themes have
                include(get_query_template('404'));
            }
        }
        exit;
    }
}
