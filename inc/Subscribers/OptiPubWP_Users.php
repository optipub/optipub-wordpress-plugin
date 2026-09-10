<?php

namespace OptiPub\Subscribers;

use OptiPub\Base\Constants;
use WP_Error;
use WP_User;

class OptiPubWP_Users
{
    private $subscription_service;

    private $encrypt_control;

    public function __construct()
    {
        $this->encrypt_control = new OptiPubWP_EncryptControl();
    }

    /**
     * Set the subscription service
     *
     * @param OptiPubWP_Subscriptions $subscription_service
     * @return void
     */
    public function setSubscriptionService(OptiPubWP_Subscriptions $subscription_service): void
    {
        $this->subscription_service = $subscription_service;
    }

    /**
     * Get a WordPress user by email
     *
     * @param string $email The user's email address
     * @return WP_User|false WP_User object or false if not found
     */
    public function getUserByEmail($email_address)
    {
        // Skip if this is wp-login.php
        $php_self = isset($_SERVER['PHP_SELF']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])) : '';

        if (strpos($php_self, 'wp-login.php') !== false) {
            return;
        }
        $user = get_user_by('email', $email_address);

        return $user;
    }

    /**
     * Get or create a WordPress user
     *
     * @param string $email_id User's OptiPub email ID
     * @param array $user_data Additional user data
     * @return WP_User|WP_Error WP_User object if successful, WP_Error otherwise
     */
    public function getOrCreateWPUser($email_id, $optipub_subscriber = null, $user_data = [])
    {
        // Ensure email ID is an integer
        $email_id = intval($email_id) ?? null;

        if (is_null($email_id)) {
            return new WP_Error('authentication_failed', __('Unable to verify credentials. Please try again.', 'optipub'));
        }

        $placeholder_email = 'nodeliver-' . $email_id . '@e.optipub.com';
        $user = get_user_by('email', $placeholder_email);

        if (!$user instanceof WP_User) {
            $user = $this->createUser($email_id, $user_data);
        }

        if ($user instanceof WP_User) {
            // Fill in missing subscriber data
            if (empty($optipub_subscriber) || !is_object($optipub_subscriber)) {
                // Create a default subscriber object structure
                $optipub_subscriber = new \stdClass();
                $optipub_subscriber->email = new \stdClass();
                $optipub_subscriber->email->subscriptions['active'] = [];
                $optipub_subscriber->email->subscriptions['inactive'] = [];
            }

            // Ensure email object exists
            if (!isset($optipub_subscriber->email)) {
                $optipub_subscriber->email = new \stdClass();
                $optipub_subscriber->email->email = $email_id;
            }

            // Ensure subscriptions array exists
            if (!isset($optipub_subscriber->email->subscriptions)) {
                $optipub_subscriber->email->subscriptions['active'] = [];
                $optipub_subscriber->email->subscriptions['inactive'] = [];
            }

            $this->updateUserData($user, $optipub_subscriber);
        } else {
            return new WP_Error('authentication_failed', __('Unable to verify credentials. Please try again.', 'optipub'));
        }

        return $user;
    }

    /**
     * Create a new WordPress user
     *
     * @param string $email_id User's OptiPub email ID
     * @param array $user_data Additional user data
     * @return WP_User|WP_Error WP_User object if successful, WP_Error otherwise
     */
    public function createUser($email_id, $user_data = [])
    {
        // Generate a random string for user_login
        $random_username = wp_generate_password(12, false);

        // Ensure the random string is unique
        $user_login = $random_username;
        $counter = 1;

        while (username_exists($user_login)) {
            $user_login = $random_username . $counter;
            $counter++;
        }

        $placeholder_email = 'nodeliver-' . $email_id . '@e.optipub.com';

        // Default user data
        $default_data = [
            'user_login'    => $user_login, // Use random string as login
            'user_email'    => $placeholder_email,
            'user_nicename' => $email_id,
            'display_name'  => $email_id,
            'user_pass'     => wp_generate_password(),
            'role'          => 'subscriber',
        ];

        // Merge with provided data, allowing overrides
        $userdata = array_merge($default_data, $user_data);

        // Create the user
        $user_id = wp_insert_user($userdata);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Add user meta to hide admin bar
        update_user_meta($user_id, 'show_admin_bar_front', 'false');
        update_user_meta($user_id, 'optipub_first_login', 'true');

        return get_user_by('id', $user_id);
    }

    public function updateUserData($user, $optipub_subscriber)
    {
        $user_id = $user->ID;

        // Get the email object and subscriptions
        $email_object = $optipub_subscriber->email;
        $all_subscriptions = $email_object->subscriptions;

        // Remove subscriptions from email object
        unset($email_object->subscriptions);

        $optipub_subscriptions = [];
        $active_subscriptions = [];
        $inactive_subscriptions = [];

        if (isset($all_subscriptions) && is_array($all_subscriptions)) {
            $active_subscriptions = $this->subscription_service->getSubscriptionsByStatus($all_subscriptions, 'active');
            $inactive_subscriptions = $this->subscription_service->getSubscriptionsByStatus($all_subscriptions, 'inactive');
        }

        // Activate publication User Roles for active subscriptions
        if (!empty($active_subscriptions)) {
            $matched_subscriptions = $this->subscription_service->activateSubscriptionRoles($user, $active_subscriptions);
        }

        // Combine all subscriptions for user meta
        $optipub_subscriptions['active'] = $active_subscriptions;
        $optipub_subscriptions['inactive'] = $inactive_subscriptions;

        // Get subscriber_id from first active subscription if available
        $subscriber_id = null;

        if (!empty($active_subscriptions)) {
            $first_subscription = reset($active_subscriptions);

            if (isset($first_subscription->subscriber_id)) {
                $subscriber_id = $first_subscription->subscriber_id;
            }
        }

        // Update User Meta
        $usermeta_email = update_user_meta($user_id, 'optipub_email', $email_object);
        $usermeta_optipub_subscriptions = update_user_meta($user_id, 'optipub_subscriptions', $optipub_subscriptions);

        if ($subscriber_id) {
            $usermeta_subscriber_id = update_user_meta($user_id, 'optipub_subscriber_id', $subscriber_id);
        }
    }

    /**
     * Move email address from user meta to cookie
     *
     * @param int $user_id The user ID
     * @param bool $remember Whether to remember the email address
     */
    public function moveEmailToCookie($user, $remember = false)
    {
        // Store email object in var
        $email_object = get_user_meta($user->ID, 'optipub_email', true);

        if (is_object($email_object) && isset($email_object->email)) {
            // Remove email object from user meta
            $email_address = $email_object->email;
            $email_object->email = $user->user_email;

            // encrypt email address
            $encrypted_email_address = $this->encrypt_control->wp_laravel_encrypt_decrypt($email_address, true);

            // encrypt email object
            // $encrypted_email_object = $this->encrypt_control->wp_laravel_encrypt_decrypt($email_object, true);
            // Update user meta with placeholder email
            // update_user_meta($user->ID, 'optipub_email', $encrypted_email_object);
            update_user_meta($user->ID, 'optipub_email', '');

            // Set encrypted email address in cookie
            $cookie_name = 'optipub_enc_email_' . md5($user->user_login);
            $cookie_expiry = $remember ? time() + (14 * DAY_IN_SECONDS) : time() + (2 * DAY_IN_SECONDS);
            setcookie($cookie_name, $encrypted_email_address, $cookie_expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
    }

    /**
     * Clear the email cookie for a user
     *
     * @param WP_User $user The user object
     */
    public function clearEmailCookie($user = null)
    {
        // If no user provided, get current user
        if (!$user) {
            $user = wp_get_current_user();
        }

        if ($user instanceof WP_User) {
            // Remove email object from user meta
            update_user_meta($user->ID, 'optipub_email', '');

            // Remove encrypted email address from cookie
            $cookie_name = 'optipub_enc_email_' . md5($user->user_login);

            if (isset($_COOKIE[$cookie_name])) {
                $result = setcookie($cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                unset($_COOKIE[$cookie_name]);
            }
        }
    }

    /**
     * Add a role to a user safely
     *
     * @param WP_User $user The user object
     * @param string $role The role to add (sanitized publication ID)
     * @param string $display_name Display name for the role
     * @return bool True if role was added, false otherwise
     */
    public function addUserRole(WP_User $user, string $role, string $display_name = ''): bool
    {
        // Ensure role has proper prefix
        if (strpos($role, Constants::OPTIPUB_ROLE_PREFIX) !== 0) {
            $role = Constants::OPTIPUB_ROLE_PREFIX . $role;
        }

        // Set default display name if not provided
        if (empty($display_name)) {
            $display_name = ucfirst($role);
        }

        // Extract the publication ID from the role
        $pub_id = substr($role, strlen(Constants::OPTIPUB_ROLE_PREFIX));
        $capability_name = 'read_' . Constants::OPTIPUB_ROLE_PREFIX . $pub_id;

        // Check if role exists
        $existing_role = get_role($role);

        if (!$existing_role) {
            // Create the role if it doesn't exist
            add_role(
                $role,
                $display_name,
                [
                    'read'           => true,
                    $capability_name => true,
                ]
            );
        } else {
            // Role exists, check if it has the correct capability
            $capability = 'read_' . Constants::OPTIPUB_ROLE_PREFIX . $pub_id;

            if (!isset($existing_role->capabilities[$capability])) {
                // Add the missing capability
                $existing_role->add_cap($capability, true);
            }
        }

        // Add the role to the user
        $user->add_role($role);

        // Verify role was added
        return in_array($role, $user->roles);
    }

    /**
     * Remove a role from a user safely
     *
     * @param WP_User $user The user object
     * @param string $role The role to remove
     * @return bool True if role was removed or didn't exist, false if failed
     */
    public function removeUserRole(WP_User $user, string $role): bool
    {
        // Ensure role has proper prefix
        if (strpos($role, Constants::OPTIPUB_ROLE_PREFIX) !== 0) {
            $role = Constants::OPTIPUB_ROLE_PREFIX . $role;
        }

        // Check if user has the role before trying to remove it
        if (in_array($role, $user->roles)) {
            $user->remove_role($role);

            // Verify role was removed
            return !in_array($role, $user->roles);
        }

        // Role didn't exist for this user, so consider it "removed"
        return true;
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

    public function setTransientSessionData($user_id, $type, $value, $remember = false)
    {
        $expiration = $remember ? 14 * DAY_IN_SECONDS : 2 * DAY_IN_SECONDS;

        $token = wp_get_session_token();
        // Prefix transient key to avoid conflicts with other plugins
        $key = 'optipub_session_' . $type . '_' . md5($user_id . '_' . $token);
        set_transient($key, $value, $expiration);
    }

    public function getTransientSessionData($user_id, $type)
    {
        $token = wp_get_session_token();
        // Prefix transient key to avoid conflicts with other plugins
        $key = 'optipub_session_' . $type . '_' . md5($user_id . '_' . $token);

        return get_transient($key);
    }
}
