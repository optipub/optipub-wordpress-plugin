<?php

namespace OptiPubWP\PubAccess;

class PubAccessController
{
    private $api;

    private $plugin_path;

    private $subscription_service;

    private $users_service;

    private $login_control;

    private $subscribers_service;

    private $user_access;

    /**
     * Check if the current page is gated
     *
     * @return bool True if the page is gated, false otherwise
     */
    public function staticCheckPageIsGated(): bool
    {
        // Only run on single posts
        if (!is_singular()) {
            return false;
        }

        // Get the current post ID
        $post_id = get_the_ID();

        // Check if the post's publication term is gated
        return PublicationAccess::getInstance()->return_optipub_current_page_publication_term_is_gated($post_id);
    }

    /**
     * Check if the current user has access to the current post
     *
     * This is a static method that can be called from anywhere, including theme templates
     *
     * @return bool True if user has access, false otherwise
     */
    public static function staticUserHasAccess(): bool
    {
        // Only run on single posts
        if (!is_singular()) {
            return true;
        }

        // Get the current post
        $post_id = get_the_ID();

        // First check if the post's publication term is gated
        $is_gated = (bool) PublicationAccess::getInstance()->return_optipub_current_page_publication_term_is_gated($post_id);

        // If the page is not gated, user has access
        if (!$is_gated) {
            return true;
        }

        // Check if the user has access to this post
        return (bool) UserAccess::getInstance()->return_optipub_current_user_has_page_capabilites($post_id);
    }

    /**
     * Static method to check if the current user has access to the current post
     *
     * This can be called from anywhere, including theme templates
     *
     * @return bool True if user has access, false otherwise
     */
    public static function checkUserHasAccess(): bool
    {
        $instance = new self();

        return $instance->staticUserHasAccess();
    }

    public static function checkPageIsGated(): bool
    {
        $instance = new self();

        return $instance->staticCheckPageIsGated();
    }
}
