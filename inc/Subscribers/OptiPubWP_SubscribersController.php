<?php

namespace OptiPub\Subscribers;

if (!defined('ABSPATH')) {
    exit;
}

use OptiPub\Api\OptiPubApi;
use OptiPub\Base\Constants;
use OptiPub\Base\EncryptDecryptUtil;
use OptiPubWP\Publications\Publications;
use WP_Error;
use WP_User;

class OptiPubWP_SubscribersController
{
    private $api;

    private $plugin_path;

    private $subscription_service;

    private $users_service;

    private $login_control;

    private $subscribers_service;

    private $preauth_control;

    private $encrypt_control;

    private $publications_service;

    private $access_control;

    public function __construct()
    {
        $this->api = self::createOptiPubApiInstance();
        $this->plugin_path = OPTIPUB_PLUGIN_DIR;
        $this->initializeServices();
    }

    public static function createOptiPubApiInstance(): OptiPubApi
    {
        $apiBaseUrl = get_option(Constants::OPTIPUB_BASE_API_URL);
        $apiToken = EncryptDecryptUtil::getDecryptedToken('api');
        $publicationId = get_option(Constants::OPTIPUB_SELECTED_PUBLICATION_ID);

        return new OptiPubApi($apiBaseUrl, $apiToken, $publicationId);
    }

    /**
     * Register authentication hooks
     */
    public function register(): void
    {
        // Authentication hooks
        add_filter('authenticate', [$this->login_control, 'interceptAuthentication'], 20, 3);
        //add_filter('authenticate', [$this->login_control, 'preventWpAuth'], 10, 3);

        // Redirect hooks
        add_filter('login_redirect', [$this->login_control, 'handleLoginRedirect'], 10, 3);
        add_filter('logout_redirect', [$this->login_control, 'handleLogoutRedirect'], 10, 3);

        // URL handling hooks
        add_filter('login_url', [$this->login_control, 'handleLoginPageUrl'], 10, 3);
        add_filter('lostpassword_url', [$this->login_control, 'handleLostPasswordUrl'], 10);

        // Add session capabilities
        add_filter('user_has_cap', [$this->users_service, 'addSessionCapabilities'], 10, 4);

        // AJAX handlers
        add_action('wp_ajax_optipub_ajax_logout', [$this->login_control, 'handleAjaxLogout']);
        add_action('wp_ajax_optipub_ajax_login', [$this->login_control, 'handleAjaxLogin']);
        add_action('wp_ajax_nopriv_optipub_ajax_login', [$this->login_control, 'handleAjaxLogin']);
        add_action('wp_ajax_optipub_ajax_lostpassword', [$this->login_control, 'handleAjaxLostPassword']);
        add_action('wp_ajax_nopriv_optipub_ajax_lostpassword', [$this->login_control, 'handleAjaxLostPassword']);
        add_action('wp_ajax_optipub_ajax_resetpassword', [$this->login_control, 'handleAjaxResetPassword']);
        add_action('wp_ajax_nopriv_optipub_ajax_resetpassword', [$this->login_control, 'handleAjaxResetPassword']);

        // Preauth handler
        add_action('template_redirect', [$this->preauth_control, 'handlePreAuth']);

        // Clear email cookie on logout
        add_action('clear_auth_cookie', [$this->users_service, 'clearEmailCookie']);
    }

    /**
     * Authentication facade methods
     */
    public function interceptAuthentication($user, $username, $password): WP_User|WP_Error
    {
        return $this->login_control->interceptAuthentication($user, $username, $password);
    }

    /**
     * Encryption facade methods
     */
    public function wp_laravel_encrypt_decrypt($value, bool $encrypt = true)
    {
        return $this->encrypt_control->wp_laravel_encrypt_decrypt($value, $encrypt);
    }

    public function laravelCompatibleEncrypt($value, $key)
    {
        return $this->encrypt_control->laravelCompatibleEncrypt($value, $key);
    }

    public function laravelCompatibleDecrypt($encrypted, $key)
    {
        return $this->encrypt_control->laravelCompatibleDecrypt($encrypted, $key);
    }

    /**
     * Redirect handling facade methods
     */
    public function handleLoginRedirect($redirect_to, $requested_redirect_to, $user)
    {
        return $this->login_control->handleLoginRedirect($redirect_to, $requested_redirect_to, $user);
    }

    public function handleLogoutRedirect($redirect_to, $requested_redirect_to, $user)
    {
        return $this->login_control->handleLogoutRedirect($redirect_to, $requested_redirect_to, $user);
    }

    /**
     * URL handling facade methods
     */
    public function handleLoginPageUrl($login_url, $redirect, $force_reauth)
    {
        return $this->login_control->handleLoginPageUrl($login_url, $redirect, $force_reauth);
    }

    public function handleLostPasswordUrl($lostpassword_url, $redirect)
    {
        return $this->login_control->handleLostPasswordUrl($lostpassword_url, $redirect);
    }

    /**
     * AJAX handling facade methods
     */
    public function handleAjaxLogin()
    {
        return $this->login_control->handleAjaxLogin();
    }

    /**
     * Handle AJAX logout requests
     */
    public function handleAjaxLogout()
    {
        return $this->login_control->handleAjaxLogout();
    }

    /**
     * User management facade methods
     */
    public function getUserByEmail(string $email): ?WP_User
    {
        return $this->users_service->getUserByEmail($email);
    }

    public function getOrCreateUser(string $email): ?WP_User
    {
        return $this->users_service->getOrCreateWPUser($email);
    }

    public function createUser(string $email, array $user_data = []): ?WP_User
    {
        return $this->users_service->createUser($email, $user_data);
    }

    public function updateUserData(WP_User $user, $optipub_subscriber)
    {
        return $this->users_service->updateUserData($user, $optipub_subscriber);
    }

    public function addUserRole(WP_User $user, string $role, string $display_name = ''): bool
    {
        return $this->users_service->addUserRole($user, $role, $display_name);
    }

    public function removeUserRole(WP_User $user, string $role): bool
    {
        return $this->users_service->removeUserRole($user, $role);
    }

    /**
     * Subscription management facade methods
     */
    public function matchSubscriptionsToPublications($subscriber, $user): bool
    {
        return $this->subscription_service->matchSubscriptionsToPublications($subscriber, $user);
    }

    public function getOptiPubSubscriber(string $email, string $password, array $options = []): object
    {
        return $this->subscribers_service->getOptiPubSubscriber($email, $password, $options);
    }

    /**
     * Password reset facade methods
     */
    public function handleAjaxLostPassword()
    {
        return $this->login_control->handleAjaxLostPassword();
    }

    public function handleAjaxResetPassword()
    {
        return $this->login_control->handleAjaxResetPassword();
    }

    /**
     * Preauth handling facade methods
     */
    public function handlePreAuth()
    {
        return $this->preauth_control->handlePreAuth();
    }

    private function initializeServices(): void
    {
        // Initialize encryption control
        $this->encrypt_control = new OptiPubWP_EncryptControl();

        // Initialize publications controller
        $this->publications_service = new Publications();

        // Initialize base services
        $this->users_service = new OptiPubWP_Users($this->encrypt_control);
        $this->subscription_service = new OptiPubWP_Subscriptions($this->api, $this->users_service);

        // Set subscription service in users service after it's created
        $this->users_service->setSubscriptionService($this->subscription_service);

        $this->subscribers_service = new OptiPubWP_Subscribers($this->api);

        // Initialize access control
        $this->access_control = new OptiPubWP_AccessControl();

        // Initialize login control with all required services
        $this->login_control = new OptiPubWP_LoginControl(
            $this->api,
            $this->users_service,
            $this->subscription_service,
            $this->subscribers_service,
            $this->encrypt_control,
            $this->publications_service
        );

        // Initialize preauth control with all required services
        $this->preauth_control = new OptiPubWP_PreauthControl(
            $this->users_service,
            $this->subscribers_service,
            $this->encrypt_control
        );
    }
}
