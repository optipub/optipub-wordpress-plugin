<?php

/**
 * OptiPub Authentication
 *
 * This class creates a shortcode for the login form.
 *
 * Remove everything else from this file.
 */

namespace OptiPub\Api;

if (!defined('ABSPATH')) {
    exit;
}

use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;

class OptiPubAuthentication extends BaseController
{
    public function register()
    {
        // Add authentication filter
        add_filter('authenticate', [$this, 'authenticateWithOptiPub'], 10, 3);

        // Add login form shortcode
        add_shortcode('optipub_login_form', [$this, 'loginFormShortcode']);
    }

    /**
     * Authenticate user against OptiPub API
     */
    public function authenticateWithOptiPub($user, $username, $password)
    {
        // Skip if already authenticated or empty credentials
        if ($user instanceof \WP_User || empty($username) || empty($password)) {
            return $user;
        }

        // Check if this is an email login
        if (is_email($username)) {
            $email = $username;
        } else {
            // Look up user to get email
            $existing_user = get_user_by('login', $username);

            if (!$existing_user) {
                return $user;
            }
            $email = $existing_user->user_email;
        }

        // Attempt to authenticate with OptiPub API
        $auth_result = $this->verifyCredentialsWithOptiPub($email, $password);

        if (!$auth_result['success']) {
            return $user; // Let WordPress continue with normal auth
        }

        // Authentication successful, get or create user
        $wp_user = get_user_by('email', $email);

        if (!$wp_user) {
            // Create new user
            $user_data = $auth_result['user_data'];
            $new_username = $this->generateUsername(
                $user_data['first_name'] ?? '',
                $user_data['last_name'] ?? '',
                $email
            );

            $user_id = wp_create_user($new_username, $password, $email);

            if (is_wp_error($user_id)) {
                return new \WP_Error('optipub_user_creation_failed', 'Could not create user account');
            }

            $wp_user = get_user_by('id', $user_id);

            // Set user metadata
            wp_update_user([
                'ID'           => $user_id,
                'first_name'   => $user_data['first_name'] ?? '',
                'last_name'    => $user_data['last_name'] ?? '',
                'display_name' => $user_data['first_name'] . ' ' . $user_data['last_name'],
            ]);

            // Set default subscriber role
            $wp_user->set_role('subscriber');
        }

        // Update user's publication roles if provided
        if (!empty($auth_result['publications'])) {
            $this->updateUserPublicationRoles($wp_user, $auth_result['publications']);
        }

        return $wp_user;
    }

    /**
     * Login form shortcode
     */
    public function loginFormShortcode($atts)
    {
        $atts = shortcode_atts([
            'redirect' => '',
        ], $atts);

        if (is_user_logged_in()) {
            return '<p>You are already logged in.</p>';
        }

        $redirect = !empty($atts['redirect']) ? $atts['redirect'] : home_url();

        ob_start();
        ?>
        <div class="optipub-login-form">
            <form method="post" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>">
                <p>
                    <label for="user_login">Email Address</label>
                    <input type="text" name="log" id="user_login" class="input" value="" size="20" autocapitalize="off" autocomplete="username">
                </p>
                
                <p>
                    <label for="user_pass">Password</label>
                    <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" autocomplete="current-password">
                </p>
                
                <p class="forgetmenot">
                    <label for="rememberme">
                        <input name="rememberme" type="checkbox" id="rememberme" value="forever"> Remember Me
                    </label>
                </p>
                
                <p class="submit">
                    <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="Log In">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                </p>
            </form>
            
            <p class="optipub-login-links">
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Lost your password?</a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Verify credentials with OptiPub API
     *
     * @return array Authentication result with success flag and user data
     */
    private function verifyCredentialsWithOptiPub($email, $password)
    {
        $api_url = get_option(Constants::OPTIPUB_BASE_API_URL);
        $auth_endpoint = trailingslashit($api_url) . 'auth/verify';

        $response = wp_remote_post($auth_endpoint, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'email'    => $email,
                'password' => $password,
                'site_key' => get_option(Constants::OPTIPUB_SITE_KEY, ''),
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || empty($data['success'])) {
            return ['success' => false];
        }

        return [
            'success'      => true,
            'user_data'    => $data['user'] ?? [],
            'publications' => $data['publications'] ?? [],
        ];
    }

    /**
     * Generate a username from name or email
     */
    private function generateUsername($first_name, $last_name, $email)
    {
        // Same as the implementation in OptiPubWebhook
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
        // Same as the implementation in OptiPubWebhook
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
