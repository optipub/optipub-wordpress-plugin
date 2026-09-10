<?php

namespace OptiPub\Subscribers;

use OptiPub\Api\OptiPubApi;
use OptiPub\Base\Constants;

class OptiPubWP_Subscriptions
{
    private $api;

    private $users_service;

    public function __construct(
        OptiPubApi $api,
        OptiPubWP_Users $users_service
    ) {
        $this->api = $api;
        $this->users_service = $users_service;
    }

    /**
     * Get active subscriptions from OptiPub subscriber's subscriptions
     *
     * @param array $subscriber_subscriptions The subscriber's subscriptions
     * @param bool $active Whether to get active or inactive subscriptions
     * @return array The active subscriptions
     */
    public function getSubscriptionsByStatus($subscriber_subscriptions, string $status = 'active'): array
    {
        $optipub_subscriptions = [];

        foreach ($subscriber_subscriptions as $key => $subscription) {
            if (isset($subscription->active) && !empty($subscription->publication_id)) {
                switch ($status) {
                    case 'active':
                        if ($subscription->active) {
                            // Add the main subscription
                            $optipub_subscriptions[$subscription->publication_id] = $subscription;

                            // Add mapped publications as separate subscriptions
                            if (isset($subscription->publication->mappings) && is_array($subscription->publication->mappings)) {
                                foreach ($subscription->publication->mappings as $mapping) {
                                    // Skip if this publication_id already exists in subscriptions
                                    if (isset($mapping->publication_mapping_id) && !isset($optipub_subscriptions[$mapping->publication_mapping_id])) {
                                        // Create a new subscription object for the mapped publication
                                        $mapped_subscription = unserialize(serialize($subscription));

                                        // Set the publication_id to the mapped publication_id
                                        $mapped_subscription->publication_id = $mapping->publication_mapping_id;
                                        $mapped_subscription->publication_mapping_id = $subscription->publication_id;

                                        // Replace publication object with the mapped publication object
                                        $mapped_subscription->publication = $mapping->publication;
                                        $mapped_subscription->publication->mappings = [];

                                        // Add the mapped subscription to the subscriptions array
                                        $optipub_subscriptions[$mapped_subscription->publication_id] = $mapped_subscription;
                                    }
                                }
                            }
                        }
                        break;
                    case 'inactive':
                        if (!$subscription->active) {
                            $optipub_subscriptions[$subscription->publication_id] = $subscription;
                        }
                        break;
                    default:
                        $optipub_subscriptions[$subscription->publication_id] = $subscription;
                        break;
                }
            }
        }

        return $optipub_subscriptions;
    }

    /**
     * Compare subscriber's active subscriptions with available publications
     * and update user roles accordingly
     *
     * @param array $subscriber_object The subscriber data from OptiPub API
     * @param \WP_User $wp_user The WordPress user object
     * @return array Array of processed subscriptions with role assignment results
     */
    public function activateSubscriptionRoles(\WP_User $wp_user, $active_subscriptions)
    {
        $results = [];

        // Get all registered publications from the taxonomy
        $publications = get_terms([
            'taxonomy'   => Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            'hide_empty' => false,
        ]);

        if (is_wp_error($publications) || empty($publications)) {
            return $results;
        }

        // Process each publication
        foreach ($publications as $publication) {
            $publication_id = get_term_meta($publication->term_id, 'publication_id', true);
            $publication_name = $publication->name;

            // Create role slug based on publication_id to match UserRoleManager
            $role_slug = Constants::OPTIPUB_ROLE_PREFIX . sanitize_title($publication_id);

            // Set up result tracking
            $result = [
                'publication_id'   => $publication_id,
                'publication_name' => $publication_name,
                'role'             => $role_slug,
                'has_subscription' => false,
                'role_updated'     => false,
            ];

            // Check if user has an active subscription for this publication
            $has_subscription = array_key_exists($publication_id, $active_subscriptions);

            $result['has_subscription'] = $has_subscription;

            if ($has_subscription) {
                // Add role for this publication
                $role_added = $this->users_service->addUserRole($wp_user, $role_slug, $publication_name);
                $result['role_updated'] = $role_added;
            } else {
                // Remove role for this publication if it exists
                $role_removed = $this->users_service->removeUserRole($wp_user, $role_slug);
                $result['role_updated'] = $role_removed;
            }

            $results[] = $result;
        }

        return $results;
    }
}
