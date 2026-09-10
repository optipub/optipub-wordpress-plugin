<?php

namespace OptiPubWP\PubAccess;

use OptiPub\Base\Constants;
use OptiPub\Subscribers\OptiPubWP_EncryptControl;
use OptiPubWP\Log;
use WP_Query;
use WP_User;

class UserAccess
{
    private static $instance = null;

    /**
     * Get or create the global instance of UserAccess
     *
     * @return UserAccess
     */
    public static function getInstance(): UserAccess
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Check if the current user has access to a post based on publication capabilities
     *
     * @param int $post_id The post ID to check
     * @return bool True if the user has access, false otherwise
     */
    public function return_optipub_current_user_has_page_capabilites(int $post_id = null): bool
    {
        // Only run on single posts
        if (!is_singular()) {
            return true;
        }

        // Get the current post ID
        if ($post_id === null) {
            $current_page_id = get_queried_object_id();
        } else {
            $current_page_id = $post_id;
        }

        // Check if the publication term is gated
        $is_gated = (bool) PublicationAccess::getInstance()->return_optipub_current_page_publication_term_is_gated($post_id);

        if (!$is_gated) {
            return true;
        }

        // Get the current user
        $user = wp_get_current_user();

        // If no user is logged in, they don't have access
        if (!$user || !$user->exists()) {
            return false;
        }

        // Get the publication term for this post
        $publication_term = PublicationAccess::getInstance()->return_optipub_current_page_publication_term($post_id);

        // If the post doesn't have a publication term, it's not restricted
        if (empty($publication_term) || is_wp_error($publication_term)) {
            return true;
        }

        // Get the publication ID from the term meta
        $publication_id = get_term_meta($publication_term->term_id, 'publication_id', true);

        if (empty($publication_id)) {
            return true;
        }

        // Check if the user has the capability for this publication
        $capability = 'read_' . Constants::OPTIPUB_ROLE_PREFIX . $publication_id;

        if ($user->has_cap($capability)) {
            return true;
        }

        // User doesn't have access to any of the publication terms
        return false;
    }

    public function return_optipub_current_user_email_address(): string
    {
        $user = wp_get_current_user();

        $token = wp_get_session_token();
        $key = 'session_optipub_email_' . md5($user->ID . '_' . $token);

        return get_transient($key);
    }

    public function return_optipub_current_user_email_object_from_cookie($full_object = false): object|null
    {
        $user = wp_get_current_user();

        if (!$user instanceof WP_User) {
            return null;
        }

        $cookie_name = 'optipub_enc_email_' . md5($user->user_login);
        $email_object = null;

        if (isset($_COOKIE[$cookie_name])) {
            // unslash the cookie before using it
            $raw_cookie_value = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));

            // we are not saving the email object in user meta right now
            // so defaulting to just email address
            // decrypt email object from cookie
            $encrypt_control = new OptiPubWP_EncryptControl();
            $decrypted_email_address = $encrypt_control->wp_laravel_encrypt_decrypt($raw_cookie_value, false) ?? false;

            if ($full_object) {
                $email_object_meta = get_user_meta($user->ID, 'optipub_email', true);
                $decrypted_email_object_meta = $encrypt_control->wp_laravel_encrypt_decrypt($email_object_meta, false) ?? false;

                if ($decrypted_email_object_meta) {
                    $email_object = unserialize($decrypted_email_object_meta);
                }

                // write over the email object with the decrypted email address
                if (is_object($email_object)) {
                    // If email is a serialized string, unserialize it
                    if (is_string($email_object->email) && preg_match('/^s:\d+:"[^"]*";$/', $email_object->email)) {
                        $email_object->email = unserialize($email_object->email);
                    }
                    $email_object->email = $decrypted_email_address;
                }
            } else {
                $decrypted_email_address = unserialize($decrypted_email_address);
                $email_object = (object) [
                    'email' => $decrypted_email_address,
                ];
            }
        }

        if (is_object($email_object) && is_email($email_object->email)) {
            return $email_object;
        }

        return null;
    }

    /**
     * Determines if the current user has access to the current page.
     *
     * This function checks:
     * 1. If there is a current post
     * 2. If the post is gated
     * 3. If the user is logged in
     * 4. If the user has an active subscription to the post's publication
     *
     * @param bool $admin_model Whether to use the admin subscription model (true) or user's actual subscriptions (false)
     * @return bool True if user has access, false otherwise
     */
    public function return_optipub_current_user_has_page_access($admin_model = true): bool
    {
        try {
            // Early return if no post exists
            if (!$this->hasCurrentPost()) {
                return true;
            }

            $current_page_publication_term = PublicationAccess::getInstance()->return_optipub_current_page_publication_term();

            // Check if the current post is gated
            if (isset($current_page_publication_term->meta['publication_gated'][0])) {
                $current_post_is_gated = !empty($current_page_publication_term->meta['publication_gated'][0]) ? true : false;
            } else {
                $current_post_is_gated = false;
            }

            // Early return if post is not gated
            if (!$current_post_is_gated) {
                return true;
            }

            // Early return if user is not logged in
            if (!is_user_logged_in()) {
                return false;
            }

            // Get the current user access based on admin model
            $is_admin = $this->return_optipub_user_is_admin();
            $current_user_access = $this->return_optipub_user_subscriptions($is_admin && $admin_model);

            // Check if user has access to the gated publication
            return isset($current_user_access['active'][$current_page_publication_term->meta['publication_id'][0]]);
        } catch (\Exception $e) {
            // Log the error but don't expose it to the user
            // Default to denying access on error
            return false;
        }
    }

    /**
     * Get user is admin
     *
     * @return bool True if user is admin, false otherwise
     */
    public function return_optipub_user_is_admin(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        if ($user instanceof WP_User) {
            $is_admin = is_super_admin() || in_array('administrator', (array) $user->roles);

            if ($is_admin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user subscriptions
     *
     * @param bool $admin_model Whether to return the admin model
     * @return array Array of subscriptions with 'active' and 'inactive' keys
     */
    public function return_optipub_user_subscriptions($admin_model = true): array
    {
        // Initialize the subscriptions array
        $user_subscriptions = [
            'active'   => [],
            'inactive' => [],
        ];

        if (!is_user_logged_in()) {
            return $user_subscriptions;
        }

        $user_id = get_current_user_id();

        if (!$user_id) {
            return $user_subscriptions;
        }

        $is_admin = $this->return_optipub_user_is_admin();

        if ($admin_model) {
            if ($is_admin) {
                return $this->return_optipub_admin_subscriptions_model();
            }
        }

        // Get user subscriptions from user meta
        $meta_subscriptions = get_user_meta($user_id, 'optipub_subscriptions', true);

        // If we have valid subscriptions from meta, use them
        if (is_array($meta_subscriptions)) {
            $user_subscriptions = $meta_subscriptions;
        }

        // Ensure both active and inactive arrays exist
        if (!isset($user_subscriptions['active'])) {
            $user_subscriptions['active'] = [];
        }

        if (!isset($user_subscriptions['inactive'])) {
            $user_subscriptions['inactive'] = [];
        }

        return $user_subscriptions;
    }

    public function return_optipub_user_publication_terms($admin_model = true, $paid_only = false, $gated_only = false): array
    {
        if (!is_user_logged_in()) {
            return [];
        }

        $is_admin = $this->return_optipub_user_is_admin();
        $all_terms = PublicationAccess::getInstance()->return_optipub_all_publication_terms() ?? [];
        $user_publication_terms = [];

        if ($admin_model && $is_admin) {
            // For admin users, get all publication terms with paid/gated filtering
            $user_subscriptions = $this->return_optipub_admin_subscriptions_model();
        } else {
            // For regular users, get terms based on their subscriptions
            $user_subscriptions = $this->return_optipub_user_subscriptions(false);
        }

        // Ensure subscriptions array has the expected structure
        if (!isset($user_subscriptions['active'])) {
            $user_subscriptions['active'] = [];
        }

        foreach ($user_subscriptions['active'] as $publication_id => $subscription) {
            if (isset($all_terms[$publication_id])) {
                $term = $all_terms[$publication_id];
                $is_paid = $term->meta['publication_paid'][0] === '1';
                $is_gated = $term->meta['publication_gated'][0] === '1';

                switch (true) {
                    case $paid_only && $is_paid && $gated_only && $is_gated:
                    case $paid_only && $is_paid:
                    case $gated_only && $is_gated:
                    case !$paid_only && !$gated_only:
                        $user_publication_terms[$publication_id] = $term;
                        break;
                }
            }
        }

        return $user_publication_terms;
    }

    public function return_optipub_user_accessible_publication_terms(): array
    {
        $all_publication_terms = PublicationAccess::getInstance()->return_optipub_all_publication_terms();
        $user_publication_terms = $this->return_optipub_user_publication_terms();

        $user_accessible_publication_terms = [];

        foreach ($all_publication_terms as $publication_id => $term) {
            if (isset($user_publication_terms[$publication_id]) || !$term->meta['publication_gated'][0]) {
                $user_accessible_publication_terms[$publication_id] = $term;
            }
        }

        return $user_accessible_publication_terms;
    }

    /**
     * Get active subscriptions for the current user
     *
     * @return array Array of subscriptions with 'active' and 'inactive' keys
     */
    public function return_optipub_admin_subscriptions_model(): array
    {
        if (!is_user_logged_in()) {
            return ['active' => [], 'inactive' => []];
        }

        $user = wp_get_current_user();
        $is_admin = $this->return_optipub_user_is_admin();

        $subscriptions = ['active' => [], 'inactive' => []];

        if ($is_admin) {
            // For admin users, create subscriptions array with all gated publications
            $publication_terms = PublicationAccess::getInstance()->return_optipub_all_publication_terms();
            $current_time = current_time('mysql');

            foreach ($publication_terms as $publication_id => $term) {
                $subscriptions['active'][$publication_id] = (object) [
                    'id'                    => 1,
                    'active'                => 1,
                    'email_id'              => 1,
                    'publication_id'        => $publication_id,
                    'subscriber_id'         => 1,
                    'started_at'            => $current_time,
                    'ended_at'              => null,
                    'deleted_at'            => null,
                    'created_at'            => $current_time,
                    'updated_at'            => $current_time,
                    'source_effort_id'      => 1,
                    'effort_id'             => 1,
                    'unsubscribe_reason_id' => null,
                    'publication'           => (object) [
                        'id'                     => $publication_id,
                        'name'                   => $term->meta['publication_name'][0] ?? null,
                        'active'                 => 1,
                        'code'                   => $term->meta['publication_code'][0] ?? null,
                        'created_at'             => $current_time,
                        'updated_at'             => $current_time,
                        'binding_id'             => null,
                        'logo_asset_id'          => $term->meta['publication_logo_asset_id'][0] ?? null,
                        'domain_id'              => $term->meta['publication_domain_id'][0] ?? null,
                        'paid'                   => $term->meta['publication_paid'][0] ?? null,
                        'is_division'            => null,
                        'division_id'            => null,
                        'default_template_id'    => null,
                        'show_online'            => 1,
                        'fav_icon_asset_id'      => $term->meta['publication_fav_icon_asset_id'][0] ?? null,
                        'mobile_pricing_plan_id' => null,
                        'mobile_effort_id'       => null,
                        'asset_path'             => $term->meta['publication_logo_asset_url'][0] ?? null,
                        'fav_icon_asset_path'    => $term->meta['publication_fav_icon_asset_url'][0] ?? null,
                    ],
                ];
            }
        } else {
            // For regular users, get their subscriptions from user meta
            $user_subscriptions = get_user_meta($user->ID, 'optipub_subscriptions', true);

            if (is_array($user_subscriptions)) {
                $subscriptions = $user_subscriptions;
            }
        }

        // Sort active subscriptions by updated_at while preserving keys
        if (!empty($subscriptions['active'])) {
            uasort($subscriptions['active'], function ($a, $b) {
                return strtotime($b->updated_at) - strtotime($a->updated_at);
            });
        }

        return $subscriptions;
    }

    /**
     * Filter posts based on user publication capabilities
     *
     * @param WP_Query $query The WordPress query object
     * @return void
     */
    public function filterPostsByPublicationAccess(WP_Query $query): void
    {
        // Only run on the frontend and for main queries
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Get the current user
        $user = wp_get_current_user();

        // If no user is logged in, they don't have access to restricted content
        if (!$user || !$user->exists()) {
            // Get all publication terms
            $publication_terms = get_terms([
                'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                'hide_empty' => false,
            ]);

            if (empty($publication_terms) || is_wp_error($publication_terms)) {
                return;
            }

            // Get all publication IDs
            $publication_ids = [];

            foreach ($publication_terms as $term) {
                $publication_id = get_term_meta($term->term_id, 'publication_id', true);

                if (!empty($publication_id)) {
                    $publication_ids[] = $publication_id;
                }
            }

            // If there are no publication IDs, no need to filter
            if (empty($publication_ids)) {
                return;
            }

            // Exclude posts with publication terms
            $tax_query = $query->get('tax_query');

            if (!is_array($tax_query)) {
                $tax_query = [];
            }

            $tax_query[] = [
                'taxonomy' => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                'field'    => 'term_id',
                'terms'    => wp_list_pluck($publication_terms, 'term_id'),
                'operator' => 'NOT IN',
            ];

            $query->set('tax_query', $tax_query);
        } else {
            // User is logged in, check their capabilities
            $publication_terms = get_terms([
                'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                'hide_empty' => false,
            ]);

            if (empty($publication_terms) || is_wp_error($publication_terms)) {
                return;
            }

            // Get publication IDs the user has access to
            $accessible_publication_ids = [];

            foreach ($publication_terms as $term) {
                $publication_id = get_term_meta($term->term_id, 'publication_id', true);

                if (!empty($publication_id)) {
                    $capability = 'read_' . Constants::OPTIPUB_ROLE_PREFIX . $publication_id;

                    if ($user->has_cap($capability)) {
                        $accessible_publication_ids[] = $publication_id;
                    }
                }
            }

            // If the user has access to all publications, no need to filter
            if (count($accessible_publication_ids) === count($publication_terms)) {
                return;
            }

            // Get terms for publications the user has access to
            $accessible_terms = [];

            foreach ($publication_terms as $term) {
                $publication_id = get_term_meta($term->term_id, 'publication_id', true);

                if (in_array($publication_id, $accessible_publication_ids)) {
                    $accessible_terms[] = $term->term_id;
                }
            }

            // If there are no accessible terms, exclude all publication posts
            if (empty($accessible_terms)) {
                $tax_query = $query->get('tax_query');

                if (!is_array($tax_query)) {
                    $tax_query = [];
                }

                $tax_query[] = [
                    'taxonomy' => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => wp_list_pluck($publication_terms, 'term_id'),
                    'operator' => 'NOT IN',
                ];

                $query->set('tax_query', $tax_query);
            } else {
                // Include only posts with accessible publication terms
                $tax_query = $query->get('tax_query');

                if (!is_array($tax_query)) {
                    $tax_query = [];
                }

                $tax_query[] = [
                    'taxonomy' => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => $accessible_terms,
                    'operator' => 'IN',
                ];

                $query->set('tax_query', $tax_query);
            }
        }
    }

    /**
     * Checks if there is a current post
     *
     * @return bool True if post exists, false otherwise
     */
    private function hasCurrentPost(): bool
    {
        global $post;

        return !empty($post);
    }

    /**
     * Checks if the current post is gated
     *
     * @return bool True if post is gated, false otherwise
     */
    private function isCurrentPostGated(): bool
    {
        return PublicationAccess::getInstance()
            ->return_optipub_current_page_publication_term_is_gated();
    }

    /**
     * Checks if the current user is logged in
     *
     * @return bool True if user is logged in, false otherwise
     */
    private function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
