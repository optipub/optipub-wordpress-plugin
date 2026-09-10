<?php

/** REMOVE THIS FILE IF NOT USED, AND INIT CALL */

namespace OptiPub\Api;

if (!defined('ABSPATH')) {
    exit;
}

use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;

class OptiPubWebhook extends BaseController
{
    public function register()
    {
        add_action('rest_api_init', [$this, 'registerEndpoints']);
    }

    /**
     * Register webhook endpoints
     */
    public function registerEndpoints()
    {
        register_rest_route('optipub/v1', '/subscription', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSubscriptionWebhook'],
            'permission_callback' => [$this, 'verifyWebhookRequest'],
        ]);
    }

    /**
     * Verify webhook request with shared secret
     */
    public function verifyWebhookRequest(\WP_REST_Request $request)
    {
        // Get the Authorization header
        $auth_header = $request->get_header('X-OptiPub-Signature');

        if (!$auth_header) {
            return false;
        }

        // Get the webhook secret from options
        $webhook_secret = get_option(Constants::OPTIPUB_WEBHOOK_SECRET, '');

        if (empty($webhook_secret)) {
            return false;
        }

        // Get request body for signature verification
        $payload = $request->get_body();

        // Calculate expected signature (HMAC with SHA256)
        $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);

        // Compare signatures (constant time comparison)
        return hash_equals($expected_signature, $auth_header);
    }

    /**
     * Handle subscription data
     */
    public function handleSubscriptionWebhook(\WP_REST_Request $request)
    {
        $data = $request->get_json_params();

        // Validate required fields
        if (empty($data['email']) || empty($data['publications'])) {
            return new \WP_Error('missing_data', 'Required data is missing', ['status' => 400]);
        }

        $email = sanitize_email($data['email']);
        $first_name = sanitize_text_field($data['first_name'] ?? '');
        $last_name = sanitize_text_field($data['last_name'] ?? '');
        $publications = $data['publications'];

        // Check if user exists
        $user = get_user_by('email', $email);

        if (!$user) {
            // Create new user
            $username = $this->generateUsername($first_name, $last_name, $email);
            $password = wp_generate_password(16, true, true);

            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                return new \WP_Error('user_creation_failed', $user_id->get_error_message(), ['status' => 500]);
            }

            $user = get_user_by('id', $user_id);

            // Set user metadata
            wp_update_user([
                'ID'           => $user_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => $first_name . ' ' . $last_name,
            ]);

            // Set default subscriber role
            $user->set_role('subscriber');
        }

        // Update publication access roles
        $this->updateUserPublicationRoles($user, $publications);

        return [
            'success' => true,
            'user_id' => $user->ID,
            'message' => 'User subscription processed successfully',
        ];
    }

    /**
     * Generate a username from name or email
     */
    private function generateUsername($first_name, $last_name, $email)
    {
        if (!empty($first_name) && !empty($last_name)) {
            $username = strtolower($first_name . '.' . $last_name);
            $username = preg_replace('/[^a-z0-9.]/', '', $username);
        } else {
            // Use email prefix
            $parts = explode('@', $email);
            $username = $parts[0];
        }

        // Ensure username is unique
        $base_username = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Update user's publication roles
     */
    private function updateUserPublicationRoles($user, $publications)
    {
        // Get existing non-optipub roles
        $existing_wp_roles = [];

        foreach ($user->roles as $role) {
            if (strpos($role, 'optipub_') !== 0) {
                $existing_wp_roles[] = $role;
            }
        }

        // Remove all roles
        $user->set_role('');

        // Re-add non-optipub roles
        foreach ($existing_wp_roles as $role) {
            $user->add_role($role);
        }

        // Add publication roles
        foreach ($publications as $pub_code) {
            $role_name = 'optipub_' . sanitize_title($pub_code);
            $user->add_role($role_name);
        }
    }
}
