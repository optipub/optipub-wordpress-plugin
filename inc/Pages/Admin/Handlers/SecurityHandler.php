<?php

namespace OptiPub\Pages\Admin\Handlers;

class SecurityHandler
{
    private $nonce_action = 'optipub_ajax_admin';

    private $capability = 'manage_options';

    /**
     * Validate security for AJAX requests
     *
     * @return bool Whether security checks passed
     */
    public function validateAjaxSecurity(): bool
    {
        // Ensure WordPress is loaded
        if (!function_exists('wp_get_current_user')) {
            require_once(ABSPATH . 'wp-includes/pluggable.php');
        }

        if (!check_ajax_referer($this->nonce_action, 'nonce', false)) {
            wp_send_json_error('Invalid security token.');

            return false;
        }

        if (!current_user_can($this->capability)) {
            wp_send_json_error('Unauthorized access.');

            return false;
        }

        return true;
    }

    /**
     * Validate if user has required capability
     *
     * @return bool
     */
    public function userCanAccess(): bool
    {
        // Ensure WordPress is loaded
        if (!function_exists('wp_get_current_user')) {
            require_once(ABSPATH . 'wp-includes/pluggable.php');
        }

        return current_user_can($this->capability);
    }

    /**
     * Get required capability
     *
     * @return string
     */
    public function getRequiredCapability(): string
    {
        return $this->capability;
    }
}
