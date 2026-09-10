<?php

namespace OptiPub\Subscribers;

use DateTime;
use Exception;
use WP_User;

class OptiPubWP_PreauthControl
{
    private $users_service;

    private $subscribers_service;

    private $encrypt_control;

    public function __construct(
        OptiPubWP_Users $users_service,
        OptiPubWP_Subscribers $subscribers_service,
        OptiPubWP_EncryptControl $encrypt_control
    ) {
        $this->users_service = $users_service;
        $this->subscribers_service = $subscribers_service;
        $this->encrypt_control = $encrypt_control;
    }

    /**
     * Handle pre-authentication
     *
     * @return void
     */
    public function handlePreAuth(): void
    {
        // Skip if this is wp-login.php, REST API request or CLI
        $php_self = isset($_SERVER['PHP_SELF']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])) : '';

        if (strpos($php_self, 'wp-login.php') !== false || defined('REST_REQUEST') || defined('WP_CLI')) {
            return;
        }

        // Validate and sanitize preauth parameter
        $preauth = isset($_GET['preauth']) ? sanitize_text_field(wp_unslash($_GET['preauth'])) : '';

        if (empty($preauth)) {
            return;
        }

        // Sanitize nonce before verification
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'preauth_action')) {
            // Invalid request, redirect to home
            wp_safe_redirect(home_url());
            exit;
        }

        // Create the redirect url
        $current_url = remove_query_arg(['preauth', '_wpnonce'], home_url(add_query_arg(null, null)));

        // Handle the preauth
        try {
            // decrypt the preauth data
            $preauth_data = $this->encrypt_control->wp_laravel_encrypt_decrypt($preauth, false);

            $preauth_data = unserialize($preauth_data);

            [$expiration, $email] = array_pad(explode(';', $preauth_data, 2), 2, '');

            // check expiration and email are set
            if (!$expiration || !$email) {
                throw new Exception('Invalid preauth data format');
            }

            // check email is an email
            if (!is_email($email)) {
                throw new Exception('Invalid email in preauth token');
            }

            // check expiration is a timestamp and is in the past
            if (!$this->isTimestamp($expiration) || $expiration < time()) {
                throw new Exception('Preauth token has expired');
            }

            $encrypted_email = $this->encrypt_control->wp_laravel_encrypt_decrypt($email, true);

            // Get subscriber data from OptiPub with preauth flag
            $optipub_subscriber = $this->subscribers_service->getOptiPubSubscriber($email, $encrypted_email, ['preauth' => true]);

            if (is_wp_error($optipub_subscriber)) {
                throw new Exception('Failed to authenticate with OptiPub: ' . $optipub_subscriber->get_error_message());
            }

            $email_id = $optipub_subscriber->email->id;

            // Get or create WordPress user
            $wp_user = $this->users_service->getOrCreateWPUser($email_id, $optipub_subscriber);

            if (!$wp_user instanceof WP_User) {
                throw new Exception('Failed to get or create WordPress user');
            }

            // Log the user in
            wp_set_auth_cookie($wp_user->ID);
            wp_set_current_user($wp_user->ID);

            // move email address from user meta to cookie
            $this->users_service->moveEmailToCookie($wp_user);

            // Extract and sanitize the redirect_to parameter
            $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';

            // Redirect to the appropriate page
            $redirect_url = apply_filters('login_redirect', $current_url, $redirect_to, $wp_user);

            wp_safe_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            // Handle the preauth error
            $current_url = add_query_arg('preauth_error', '', $current_url);

            wp_safe_redirect($current_url);
            exit;
        }
    }

    /**
     * @param string $string
     * @return bool
     */
    private function isTimestamp($string)
    {
        try {
            new DateTime('@' . $string);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
