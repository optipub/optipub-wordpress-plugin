<?php

namespace OptiPub\Subscribers;

use Exception;
use OptiPub\Api\OptiPubApi;
use OptiPub\Base\Constants;
use OptiPubWP\Log;
use OptiPubWP\Publications\Publications;
use WP_Error;
use WP_User;

class OptiPubWP_LoginControl
{
    private $api;

    private $users_service;

    private $subscription_service;

    private $subscribers_service;

    private $encrypt_control;

    private $publications_service;

    public function __construct(
        OptiPubApi $api,
        OptiPubWP_Users $users_service,
        OptiPubWP_Subscriptions $subscription_service,
        OptiPubWP_Subscribers $subscribers_service,
        OptiPubWP_EncryptControl $encrypt_control,
        Publications $publications_service
    ) {
        $this->api = $api;
        $this->users_service = $users_service;
        $this->subscription_service = $subscription_service;
        $this->subscribers_service = $subscribers_service;
        $this->encrypt_control = $encrypt_control;
        $this->publications_service = $publications_service;
    }

    /**
     * Handle AJAX login requests
     */
    public function handleAjaxLogin()
    {
        // Verify nonce
        check_ajax_referer('ajax-login-nonce', 'security');

        $username = isset($_POST['username']) ? sanitize_email(wp_unslash($_POST['username'])) : '';
        $password = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : '';
        $redirect_to = isset($_POST['redirect_to']) ? trim(esc_url_raw(wp_unslash($_POST['redirect_to']))) : '';
        $remember = !empty($_POST['rememberme']);

        if (empty($password)) {
            wp_send_json_error(
                [
                    'message' => __('Password is required.', 'optipub'),
                ],
                400 // Bad Request
            );

            return;
        }

        // Use our custom authentication instead of wp_authenticate
        $user = $this->interceptAuthentication(null, $username, $password);

        if (is_wp_error($user)) {
            wp_send_json_error(
                [
                    'message' => __('Invalid credentials. Please try again.', 'optipub'),
                ],
                401 // Unauthorized
            );

            return;
        }

        // Add user meta to hide admin bar
        update_user_meta($user->ID, 'show_admin_bar_front', 'false');

        // Log the user in
        wp_set_auth_cookie($user->ID, $remember);
        wp_set_current_user($user->ID);

        // move email address from user meta to cookie
        $this->users_service->moveEmailToCookie($user, $remember);

        // Get redirect URL
        $redirect_url = apply_filters('login_redirect', '', $redirect_to, $user);

        wp_send_json_success(
            [
                'message'  => __('Login successful.', 'optipub'),
                'redirect' => $redirect_url,
            ],
            200 // OK
        );
    }

    /**
     * Prevent WordPress default authentication
     *
     * @param mixed $user The user object
     * @param string $username The username
     * @param string $password The password
     * @return mixed The user object or null to continue authentication
     */
    public function preventWPAuth($user, $username, $password)
    {
        // Skip if this is wp-login.php, REST API request or CLI
        $php_self = isset($_SERVER['PHP_SELF']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])) : '';

        if (strpos($php_self, 'wp-login.php') !== false || defined('REST_REQUEST') || defined('WP_CLI')) {
            return $user;
        }

        // If we've already authenticated the user, don't prevent it
        if ($user instanceof WP_User) {
            return $user;
        }

        // If username or password is empty, let WordPress handle the error
        if (empty($username) || empty($password)) {
            return $user;
        }

        // Prevent WordPress default authentication
        // Return null to stop the authentication process
    }

    /**
     * Intercept WordPress authentication process
     *
     * user is null if authentication has not yet been attempted
     */
    public function interceptAuthentication($user, $username, $password): WP_User|WP_Error
    {
        // Skip if this is wp-login.php, REST API request or CLI
        $php_self = isset($_SERVER['PHP_SELF']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])) : '';

        if (strpos($php_self, 'wp-login.php') !== false || defined('REST_REQUEST') || defined('WP_CLI')) {
            return $user;
        }

        // Skip if password is empty
        if (empty($password)) {
            return new WP_Error('empty_password', __('Please enter your password.', 'optipub'));
        }

        // Skip if username/email is empty
        if (empty($username)) {
            return new WP_Error('empty_username', __('Please enter your email address.', 'optipub'));
        }

        // Skip if authentication has already succeeded
        if ($user instanceof WP_User) {
            return $user;
        }

        // Handle both username and email login attempts
        $email = '';

        if (is_email($username)) {
            $email = $username;
        } else {
            $user_data = get_user_by('login', $username);

            if ($user_data) {
                $email = $user_data->user_email;
            }
        }

        $encrypted_password = $this->encrypt_control->wp_laravel_encrypt_decrypt($password, true);

        // Get subscriber data from OptiPub
        $optipub_subscriber = $this->subscribers_service->getOptiPubSubscriber($email, $encrypted_password);

        if (is_wp_error($optipub_subscriber)) {
            return $optipub_subscriber;
        }

        // use optipub subscriber email->id to use as wp_user->user_email
        $email_id = $optipub_subscriber->email->id;

        // Get and process subscriptions, updating user roles accordingly
        $wp_user = $this->users_service->getOrCreateWPUser($email_id, $optipub_subscriber);

        if (!$wp_user instanceof WP_User) {
            return new WP_Error('authentication_failed', __('Unable to verify credentials. Please try again.', 'optipub'));
        }

        delete_user_meta($wp_user->ID, 'optipub_first_login');

        return $wp_user;
    }

    /**
     * Handle login page URL
     *
     * @param string $login_url The login URL
     * @param string $redirect The path to redirect to after login
     * @param bool $force_reauth Whether to force reauthorization
     * @return string Modified login URL
     */
    public function handleLoginPageUrl($login_url, $redirect, $force_reauth)
    {
        // Don't modify login URL for wp-admin requests
        if (is_admin() || (
            !empty($redirect) && strpos($redirect, 'wp-admin') !== false
        )) {
            return $login_url;
        }

        // Get the template name from options
        $template_name = !empty(get_option(Constants::OPTIPUB_LOGIN_TEMPLATE))
            ? get_option(Constants::OPTIPUB_LOGIN_TEMPLATE)
            : 'default';

        if ($template_name !== 'default') {
            // Query for pages using this template
            $pages = get_pages([
                'meta_key'    => '_wp_page_template',
                'meta_value'  => $template_name,
                'number'      => 1,
                'sort_order'  => 'ASC',
                'sort_column' => 'menu_order',
            ]);

            // If we found a page using this template, use its URL
            if (!empty($pages)) {
                $custom_login_url = get_permalink($pages[0]->ID);

                // Add redirect parameter if provided
                if (!empty($redirect)) {
                    $custom_login_url = add_query_arg('redirect_to', urlencode($redirect), $custom_login_url);
                }

                // Add reauth parameter if needed
                if ($force_reauth) {
                    $custom_login_url = add_query_arg('reauth', '1', $custom_login_url);
                }

                return $custom_login_url;
            }
        }

        // Fallback to default WordPress login URL
        return $login_url;
    }

    /**
     * Handle login redirect
     *
     * Intercepts built-in WordPress - add_filter('login_redirect', $redirect_to, $requested_redirect_to, $user)
     *
     * @param string $redirect_to The redirect destination URL
     * @param string $requested_redirect_to The requested redirect destination URL
     * @param WP_User|WP_Error $user WP_User object if login was successful, WP_Error object otherwise
     * @return string Modified redirect URL
     */
    public function handleLoginRedirect($redirect_to, $requested_redirect_to, $user)
    {
        // Update last login timestamp
        if ($user instanceof WP_User) {
            update_user_meta($user->ID, 'last_login', current_time('mysql'));
        }

        // validate and sanitize redirects
        if ($redirect_to) {
            $redirect_to = urldecode($redirect_to);

            if (filter_var($redirect_to, FILTER_VALIDATE_URL)) {
                $redirect_to = esc_url_raw($redirect_to);
            } else {
                $redirect_to = '';
            }
        }

        if ($requested_redirect_to) {
            $requested_redirect_to = urldecode($requested_redirect_to);

            if (filter_var($requested_redirect_to, FILTER_VALIDATE_URL)) {
                $requested_redirect_to = esc_url_raw($requested_redirect_to);
            } else {
                $requested_redirect_to = '';
            }
        }

        // Get plugin template urls
        $primary_publication_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);
        $subscribers_template = get_option(Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE);
        $login_template = get_option(Constants::OPTIPUB_LOGIN_TEMPLATE);

        // subscribers url
        $subscribers_template_url = $this->publications_service->optipub_get_publication_template_page_url(
            $primary_publication_id,
            $subscribers_template
        );

        // login url
        $login_template_url = $this->publications_service->optipub_get_publication_template_page_url(
            $primary_publication_id,
            $login_template
        );

        if ($user instanceof WP_User) {
            // redirect to password reset on first login
            if (get_user_meta($user->ID, 'optipub_first_login', true)) {
                // redirect to reset password page
                $redirect_url = $login_template_url;
                $redirect_url = add_query_arg('action', 'rp', $redirect_url);

                // redirect after password reset
                if ($requested_redirect_to) {
                    $redirect_url = add_query_arg('redirect_to', urlencode($requested_redirect_to), $redirect_url);
                }

                return $redirect_url;
            }
        } else {
            // redirect to login page for unsuccessful logins
            if ($requested_redirect_to) {
                // urlencode the requested redirect to
                $requested_redirect_to = urlencode($requested_redirect_to);
                $redirect_url = add_query_arg('redirect_to', $requested_redirect_to, $login_template_url);
            } else {
                $redirect_url = $login_template_url;
            }

            return $redirect_url;
        }

        // try requested redirect to
        if (isset($requested_redirect_to) && !empty($requested_redirect_to)) {
            return wp_validate_redirect($requested_redirect_to, $subscribers_template_url);
        }

        // try redirect to
        if (isset($redirect_to) && !empty($redirect_to)) {
            return wp_validate_redirect($redirect_to, $subscribers_template_url);
        }

        // redirect to subscribers page
        return $subscribers_template_url;
    }

    /**
     * Handle logout redirect
     *
     * @param string $redirect_to The redirect destination URL
     * @param string $requested_redirect_to The requested redirect destination URL
     * @param WP_User $user WP_User object of the user that's logging out
     * @return string Modified redirect URL
     */
    public function handleLogoutRedirect($redirect_to, $requested_redirect_to, $user)
    {
        // validate and sanitize redirects
        if ($redirect_to) {
            $redirect_to = urldecode($redirect_to);

            if (filter_var($redirect_to, FILTER_VALIDATE_URL)) {
                $redirect_to = esc_url_raw($redirect_to);
            } else {
                $redirect_to = '';
            }
        }

        if ($requested_redirect_to) {
            $requested_redirect_to = urldecode($requested_redirect_to);

            if (filter_var($requested_redirect_to, FILTER_VALIDATE_URL)) {
                $requested_redirect_to = esc_url_raw($requested_redirect_to);
            } else {
                $requested_redirect_to = '';
            }
        }

        // If there's a specific redirect requested, honor it
        if (isset($requested_redirect_to) && !empty($requested_redirect_to)) {
            return wp_validate_redirect($requested_redirect_to, home_url());
        }

        if (isset($redirect_to) && !empty($redirect_to)) {
            return wp_validate_redirect($redirect_to, home_url());
        }

        // Fallback to home page if no login page found
        return home_url();
    }

    /**
     * Handle AJAX lost password requests
     */
    public function handleAjaxLostPassword()
    {
        // Verify nonce
        check_ajax_referer('ajax-lostpassword-nonce', 'security');

        $user_login = isset($_POST['user_login']) ? sanitize_email(wp_unslash($_POST['user_login'])) : '';
        $redirect_to = isset($_POST['redirect_to']) ? trim(esc_url_raw(wp_unslash($_POST['redirect_to']))) : '';

        if (empty($user_login)) {
            wp_send_json_error(
                [
                    'message' => __('Please enter your email address.', 'optipub'),
                ],
                400 // Bad Request
            );

            return;
        }

        // Handle both email and username
        $email = '';

        if (is_email($user_login)) {
            $email = $user_login;
        } else {
            $user_data = get_user_by('login', $user_login);

            if ($user_data) {
                $email = $user_data->user_email;
            }
        }

        if (empty($email)) {
            $email = $user_login;
        }

        // Encrypt email as password for forgot password request
        $encrypted_email = $this->encrypt_control->wp_laravel_encrypt_decrypt($email, true);

        // Get subscriber data from OptiPub with forgot_password flag
        $optipub_subscriber = $this->subscribers_service->getOptiPubSubscriber($email, $encrypted_email, ['forgot_password' => true]);

        if (is_wp_error($optipub_subscriber)) {
            wp_send_json_success(
                [
                    'message' => __('Please check your email. If your email is on file we have sent you a link to reset your password.', 'optipub'),
                ],
                200 // OK
            );

            return;
        }

        // Check if the response contains a success message
        if (isset($optipub_subscriber->message) && $optipub_subscriber->message === 'Auto responder sent.') {
            wp_send_json_success(
                [
                    'message' => __('Please check your email. If your email is on file we have sent you a link to reset your password.', 'optipub'),
                ],
                200 // OK
            );

            return;
        }
        wp_send_json_success(
            [
                'message' => __('Please check your email. If your email is on file we have sent you a link to reset your password.', 'optipub'),
            ],
            200 // OK
        );
    }

    /**
     * Handle AJAX reset password requests
     */
    public function handleAjaxResetPassword()
    {
        // Verify nonce
        check_ajax_referer('ajax-resetpassword-nonce', 'security');

        // Sanitize and validate inputs
        $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
        $login = isset($_POST['login']) ? sanitize_email(wp_unslash($_POST['login'])) : '';
        $new_password = isset($_POST['new_password']) ? sanitize_text_field(wp_unslash($_POST['new_password'])) : '';
        $confirm_password = isset($_POST['confirm_password']) ? sanitize_text_field(wp_unslash($_POST['confirm_password'])) : '';
        $redirect_to = isset($_POST['redirect_to']) ? trim(esc_url_raw(wp_unslash($_POST['redirect_to']))) : '';
        $publication_id = isset($_POST['publication_id']) ? absint($_POST['publication_id']) : '';

        // Validate required fields
        if (empty($new_password) || empty($confirm_password)) {
            wp_send_json_error(
                [
                    'message' => __('Please enter the new password twice.', 'optipub'),
                ],
                400 // Bad Request
            );

            return;
        }

        // Validate password match
        if ($new_password !== $confirm_password) {
            wp_send_json_error(
                [
                    'message' => __('The passwords do not match.', 'optipub'),
                ],
                400 // Bad Request
            );

            return;
        }

        $logged_in_user = wp_get_current_user();

        $reset_key_result = check_password_reset_key($key, $logged_in_user->user_login);

        if (is_wp_error($reset_key_result)) {
            wp_send_json_error(
                [
                    'message' => __('Invalid or expired password reset link.', 'optipub'),
                ],
                400 // Bad Request
            );

            return;
        }

        // Get user's email and encrypt it for the API call
        $email = $logged_in_user->data->user_email;

        $cookie_name = 'optipub_enc_email_' . md5($logged_in_user->user_login);

        $cookie_email = isset($_COOKIE[$cookie_name]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) : '';

        if ($cookie_email) {
            $email = $this->encrypt_control->wp_laravel_encrypt_decrypt($cookie_email, false);

            // Unserialize the email if it's a serialized string
            if (is_string($email) && preg_match('/^s:\d+:"[^"]*";$/', $email)) {
                $email = unserialize($email);
            }
        } else {
            wp_send_json_error(
                [
                    'message' => __('Invalid reset password information.', 'optipub'),
                ],
                400 // Bad Request
            );
        }

        $encrypted_email = $this->encrypt_control->wp_laravel_encrypt_decrypt($email, true);
        $encrypted_password = $this->encrypt_control->wp_laravel_encrypt_decrypt($new_password, true);

        // Update password in OptiPub
        $optipub_subscriber = $this->subscribers_service->getOptiPubSubscriber(
            $email,
            $encrypted_email,
            ['new_password' => $encrypted_password]
        );

        if (is_wp_error($optipub_subscriber)) {
            wp_send_json_error(
                [
                    'message' => __('Unable to reset password. Please try again.', 'optipub'),
                ],
                500 // Internal Server Error
            );

            return;
        }

        // Update WordPress password
        wp_set_password($new_password, $logged_in_user->ID);

        // Log the user in
        wp_set_auth_cookie($logged_in_user->ID);
        wp_set_current_user($logged_in_user->ID);

        delete_user_meta($logged_in_user->ID, 'optipub_first_login');

        if (empty($redirect_to)) {
            $publication_id = $publication_id ?: get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);
            $template_name = get_option(Constants::OPTIPUB_SUBSCRIBERS_TEMPLATE);

            $redirect_to = $this->publications_service->optipub_get_publication_template_page_url(
                $publication_id,
                $template_name
            );
        }

        // Validate redirect URL to prevent open redirect vulnerabilities
        $redirect_to = wp_validate_redirect($redirect_to, home_url());
        $redirect_to = apply_filters('login_redirect', '', $redirect_to, $logged_in_user);

        wp_send_json_success(
            [
                'message'  => __('Your password has been reset.', 'optipub'),
                'redirect' => $redirect_to,
            ],
            200 // OK
        );
    }

    /**
     * Handle lost password URL
     *
     * @param string $lostpassword_url The lost password URL
     * @param string $redirect The path to redirect to after password reset
     * @return string Modified lost password URL
     */
    public function handleLostPasswordUrl($lostpassword_url)
    {
        // Skip if this is wp-login.php
        $php_self = isset($_SERVER['PHP_SELF']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])) : '';

        if (strpos($php_self, 'wp-login.php') !== false) {
            return $lostpassword_url;
        }

        // Get the primary publication ID
        $publication_id = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        if (!$publication_id) {
            return $lostpassword_url;
        }

        // Get the login template name
        $template_name = get_option(Constants::OPTIPUB_LOGIN_TEMPLATE);

        if (empty($template_name) || $template_name === 'default') {
            return $lostpassword_url;
        }

        // Get the login template page URL
        $custom_lostpassword_url = $this->publications_service->optipub_get_publication_template_page_url(
            $publication_id,
            $template_name
        );

        if ($custom_lostpassword_url && !is_wp_error($custom_lostpassword_url)) {
            // Add action parameter for lost password
            $custom_lostpassword_url = add_query_arg('action', 'lostpassword', $custom_lostpassword_url);

            // Add redirect parameter if provided
            if (!empty($redirect)) {
                $custom_lostpassword_url = add_query_arg('redirect_to', urlencode($redirect), $custom_lostpassword_url);
            }

            return $custom_lostpassword_url;
        }

        // Fallback to default WordPress lost password URL
        return $lostpassword_url;
    }

    /**
     * Handle AJAX logout requests
     */
    public function handleAjaxLogout()
    {
        try {
            // Verify nonce
            check_ajax_referer('ajax-logout-nonce', 'security');

            // Get redirect URL if provided
            $redirect_to = isset($_POST['redirect_to']) ? trim(esc_url_raw(wp_unslash($_POST['redirect_to']))) : '';

            // Get current user
            $user = wp_get_current_user();

            // Check if user is actually logged in
            if (!$user->exists()) {
                wp_send_json_error([
                    'message' => __('No user is currently logged in.', 'optipub'),
                    'code'    => 'not_logged_in',
                ], 400);

                return;
            }

            // Log the user out
            wp_logout();

            // Clear all cookies
            wp_clear_auth_cookie();

            // Get redirect URL using the logout_redirect filter
            $redirect_url = apply_filters('logout_redirect', '', $redirect_to, $user);

            // If no redirect URL was set, default to home
            if (empty($redirect_url)) {
                $redirect_url = home_url();
            }

            // Send success response with redirect URL
            wp_send_json_success([
                'message'  => __('You have been logged out successfully.', 'optipub'),
                'redirect' => $redirect_url,
            ]);
        } catch (Exception $e) {
            // Log the error

            // Send error response
            wp_send_json_error([
                'message' => __('An error occurred during logout. Please try again.', 'optipub'),
                'code'    => 'logout_error',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
