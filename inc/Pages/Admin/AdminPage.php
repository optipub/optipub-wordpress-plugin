<?php

namespace OptiPub\Pages\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use OptiPub\Api\WpDatabaseQuery;
use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;
use OptiPub\Base\EncryptDecryptUtil;
use OptiPub\Pages\Admin\Handlers\CronHandler;
use OptiPub\Pages\Admin\Handlers\FileHandler;
use OptiPub\Pages\Admin\Handlers\LogHandler;
use OptiPub\Pages\Admin\Handlers\PostHandler;
use OptiPub\Pages\Admin\Handlers\SecurityHandler;
use OptiPub\Pages\Admin\Handlers\SettingsHandler;
use OptiPub\Pages\Admin\Handlers\WidgetHandler;
use OptiPub\Pages\Admin\Views\AdminDashboard;

class AdminPage extends BaseController
{
    private $security_handler;

    private $widget_handler;

    private $file_handler;

    private $settings_handler;

    private $cron_handler;

    private $log_handler;

    private $post_handler;

    private $dashboard;

    private $wpDbQuery;

    private $optiPubApi;

    public function __construct()
    {
        parent::__construct();
        $this->wpDbQuery = new WpDatabaseQuery();
        $this->optiPubApi = parent::createOptiPubApiInstance();

        $this->security_handler = new SecurityHandler();
        $this->widget_handler = new WidgetHandler();
        $this->file_handler = new FileHandler();
        $this->settings_handler = new SettingsHandler();
        $this->cron_handler = new CronHandler();
        $this->log_handler = new LogHandler();
        $this->post_handler = new PostHandler();
        $this->dashboard = new AdminDashboard();

        // Register cron schedule and hook once during initialization
        add_filter('cron_schedules', [$this->cron_handler, 'addCronIntervals']);
        add_filter(Constants::getOptiPubCronJobName(), [$this->cron_handler, 'syncContent']);
    }

    /**
     * Register all hooks and handlers
     */
    public function register(): void
    {
        // Add nopriv AJAX handler for external save changes
        add_action('wp_ajax_nopriv_optipub_ext_save_changes', [$this, 'handleExtSaveChanges']);

        if (!$this->security_handler->userCanAccess()) {
            return;
        }

        // Register all hooks
        add_action('transition_post_status', [$this->post_handler, 'handlePostStatusTransition'], 10, 3);
        $this->registerAjaxHandlers();
        $this->registerAdminHooks();
    }

    public function addOptiPubResourcesMenu(): void
    {
        // Get global settings as default
        $global_settings = get_option(Constants::OPTIPUB_METADATA);

        $should_add_menu = false;

        if ($global_settings) {
            foreach ($global_settings as $key => $val) {
                if (isset($val['cptShowInMenu']) && $val['cptShowInMenu'] === 'optipub-resources') {
                    $should_add_menu = true;
                    break;
                }
            }
        }

        if ($should_add_menu) {
            add_menu_page(
                'OptiPub Resources',
                'OptiPub Resources',
                'manage_options',
                'optipub-resources',
                '',
                'dashicons-text',
                26
            );
        }
    }

    // AJAX Handlers
    public function handleResync(): void
    {
        try {
            $this->settings_handler->handleResync();
            wp_die();
        } catch (Exception $e) {
            echo esc_html($e->getMessage());
            wp_die();
        }
    }

    public function handleExtSaveChanges(): void
    {
        try {
            // Handle nonce validation for external save changes
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

            if (!wp_verify_nonce($nonce, 'optipub_api_request')) {
                wp_die('Invalid nonce', 'Nonce Verification Failed', ['response' => 403]);
            }

            $this->handleSaveChanges();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleSaveChanges(): void
    {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('optipub_ajax_admin', 'nonce', false)) {
                wp_die('Invalid security token.');
            }

            $url = $this->validateUrl(isset($_POST['url']) ? sanitize_text_field(wp_unslash($_POST['url'])) : '');
            $token = $this->validateToken(isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '', 'api');

            if (!$this->validateAdminForm($url, $token)) {
                throw new Exception('API Token validation failed');
            }

            if (isset($_POST['wp_auth_token']) && !empty($_POST['wp_auth_token'])) {
                $token = $this->validateToken(sanitize_text_field(wp_unslash($_POST['wp_auth_token'])), 'wp_auth');
            }

            $allowed_keys = [
                'optipub_page_cagetory_id_home',
                'optipub_home_template',
                'optipub_page_cagetory_id_subscribers',
                'optipub_subscribers_template',
                'optipub_page_cagetory_id_login',
                'optipub_login_template',
                'optipub_tags_as_tags',
                'optipub_tags_as_categories',
                'optipub_opengraph',
                'optipub_publication_id',
                'optipub_additional_publications',
                'optipub_additional_publications_prefix',
            ];
            $filtered_data = [];

            foreach ($allowed_keys as $key) {
                if (isset($_POST[$key])) {
                    $filtered_data[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
                }
            }

            $this->settings_handler->handleSaveChanges($filtered_data);
            $this->cron_handler->scheduleCronJob();

            echo esc_textarea($this->getSummaryLog());
            wp_die();
        } catch (Exception $e) {
            echo esc_html($e->getMessage());
            wp_die();
        }
    }

    public function handleProgress(): void
    {
        echo esc_textarea($this->getSummaryLog());
        wp_die();
    }

    public function handleClearContent(): void
    {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('optipub_ajax_admin', 'nonce', false)) {
                wp_die('Invalid security token.');
            }

            $url = $this->validateUrl(isset($_POST['url']) ? sanitize_text_field(wp_unslash($_POST['url'])) : '');
            $token = $this->validateToken(isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '', 'api');

            if (!$this->validateAdminForm($url, $token)) {
                throw new Exception('API Token validation failed');
            }

            $allowed_keys = [
                'pause_after_clear',
                'clear_publication_terms',
                'optipub_page_cagetory_id_home',
                'optipub_home_template',
                'optipub_page_cagetory_id_subscribers',
                'optipub_subscribers_template',
                'optipub_page_cagetory_id_login',
                'optipub_login_template',
                'optipub_tags_as_tags',
                'optipub_tags_as_categories',
                'optipub_opengraph',
                'optipub_publication_id',
                'optipub_additional_publications',
                'optipub_additional_publications_prefix',
            ];
            $filtered_data = [];

            foreach ($allowed_keys as $key) {
                if (isset($_POST[$key])) {
                    $filtered_data[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
                }
            }

            $this->settings_handler->handleClearContent($filtered_data);

            echo esc_textarea($this->getSummaryLog());
            wp_die();
        } catch (Exception $e) {
            echo esc_html($e->getMessage());
            wp_die();
        }
    }

    public function handleCopyFiles(): void
    {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('optipub_ajax_admin', 'nonce', false)) {
                wp_die('Invalid security token.');
            }

            $allowed_keys = [
                'src',
                'dst',
                'page-templates',
                'template-parts',
                'widget-templates',
                'helper-functions',
                'copy-previous-theme',
                'og_src',
            ];
            $filtered_data = [];

            foreach ($allowed_keys as $key) {
                if (isset($_POST[$key])) {
                    $filtered_data[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
                }
            }

            $this->file_handler->handleCopyFiles($filtered_data);
            wp_die();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleCopyClear(): void
    {
        $this->log_handler->clearCopyFileLog();
        wp_die();
    }

    public function handleIgnoreThemeChange(): void
    {
        try {
            $theme_directory = rtrim(get_stylesheet_directory(), '/');
            $this->file_handler->handleThemeChange($theme_directory);
            wp_die();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleWidgetSave(): void
    {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('optipub_ajax_admin', 'nonce', false)) {
                wp_die('Invalid security token.');
            }

            $raw_widget_data = isset($_POST['optipub_cc_widgets']) ? wp_unslash($_POST['optipub_cc_widgets']) : [];

            if (!is_array($raw_widget_data)) {
                throw new Exception('Invalid widget data format');
            }

            $widget_data = map_deep($raw_widget_data, 'sanitize_text_field');

            $result = $this->widget_handler->handleSave($widget_data);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleWidgetView(): void
    {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('optipub_ajax_admin', 'nonce', false)) {
                wp_die('Invalid security token.');
            }

            $itemId = sanitize_text_field(isset($_POST['itemId']) ? wp_unslash($_POST['itemId']) : '');
            $result = $this->widget_handler->handleView($itemId);

            // Output both display and source views
            echo '<div class="widget-view-display">' . esc_html($result['display']) . '</div>';
            echo '<div class="widget-view-display" style="display: none;">' . esc_html($result['source']) . '</div>';
            wp_die();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleWidgetEdit(): void
    {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('optipub_ajax_admin', 'nonce', false)) {
                wp_die('Invalid security token.');
            }

            $itemId = sanitize_text_field(isset($_POST['itemId']) ? wp_unslash($_POST['itemId']) : '');
            $result = $this->widget_handler->handleEdit($itemId);

            // Define allowed HTML tags for form elements
            $allowed_html = [
                'p'     => ['style' => [], 'class' => []],
                'br'    => [],
                'code'  => [],
                'form'  => ['id' => [], 'method' => [], 'action' => [], 'class' => []],
                'div'   => ['class' => [], 'id' => [], 'style' => []],
                'label' => ['for' => [], 'class' => []],
                'input' => [
                    'id'          => [],
                    'name'        => [],
                    'type'        => [],
                    'value'       => [],
                    'placeholder' => [],
                    'class'       => [],
                ],
                'textarea' => [
                    'id'          => [],
                    'name'        => [],
                    'class'       => [],
                    'rows'        => [],
                    'placeholder' => [],
                ],
                'span' => ['class' => [], 'data-tooltip' => []],
            ];

            echo wp_kses($result, $allowed_html);
            wp_die();
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleWidgetExport(): void
    {
        try {
            $result = $this->widget_handler->handleExport();
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleWidgetImportView(): void
    {
        try {
            $result = $this->widget_handler->handleImportView();
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function handleWidgetImportSubmit(): void
    {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('optipub_ajax_admin', 'nonce', false)) {
                wp_die('Invalid security token.');
            }

            $import_content = sanitize_textarea_field(isset($_POST['widget-import-content']) ? wp_unslash($_POST['widget-import-content']) : '');
            $result = $this->widget_handler->handleImport($import_content);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Add admin menu pages
     */
    public function addAdminPages(): void
    {
        if (!$this->security_handler->userCanAccess()) {
            return;
        }

        add_menu_page(
            __('OptiPub', 'optipub'),
            __('OptiPub Admin', 'optipub'),
            $this->security_handler->getRequiredCapability(),
            'optipub',
            null,
            'dashicons-admin-generic',
            null
        );

        // Add Dashboard as first submenu item
        add_submenu_page(
            'optipub',
            __('OptiPub Dashboard', 'optipub'),
            __('Dashboard', 'optipub'),
            $this->security_handler->getRequiredCapability(),
            'optipub',
            [$this, 'renderAdminDashboard']
        );

        // Add Publications submenu item
        add_submenu_page(
            'optipub',
            __('OptiPub Publications', 'optipub'),
            __('Publications', 'optipub'),
            $this->security_handler->getRequiredCapability(),
            'edit-tags.php?taxonomy=' . Constants::OPTIPUB_PUBLICATION_TAXONOMY,
            null
        );
    }

    /**
     * Force the OptiPub menu to stay expanded and highlighted when viewing OptiPub pages
     *
     * @param string $parent_file The current parent file passed by WordPress
     * @return string The parent file to use for menu highlighting
     */
    public function forceOptiPubCurrentMenu($parent_file)
    {
        global $submenu_file, $pagenow, $plugin_page;

        if ('admin.php' === $parent_file && 'optipub' === $plugin_page) {
            $pagenow = true;
            $submenu_file = 'edit-tags.php?taxonomy=' . Constants::OPTIPUB_PUBLICATION_TAXONOMY;

            return 'optipub';
        }

        if ('edit-tags.php?taxonomy=' . Constants::OPTIPUB_PUBLICATION_TAXONOMY === $submenu_file) {
            return 'optipub';
        }

        return $parent_file;
    }

    /**
     * Render admin dashboard
     */
    public function renderAdminDashboard(): void
    {
        if (!$this->security_handler->userCanAccess()) {
            $this->dashboard->renderUnauthorized();

            return;
        }
        $this->dashboard->render();
    }

    private function registerAjaxHandlers(): void
    {
        // Register the security check first
        add_action('init', function () {
            // Only proceed if this is an AJAX request
            if (defined('DOING_AJAX') && DOING_AJAX) {
                // Check if this is one of our actions
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reason: Nonce check is happening in validateAjaxSecurity() method below.
                $current_action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
                $our_actions = [
                    'resync_all',
                    'save_changes',
                    'progress_hook',
                    'clearing_hook',
                    'copy_files',
                    'copy_clear',
                    'ignore_theme_change',
                    'widgets_save',
                    'widgets_view',
                    'widgets_edit',
                    'widgets_export',
                    'widgets_import_view',
                    'widgets_import_submit',
                ];

                if (in_array($current_action, $our_actions)) {
                    // Verify security before any of our AJAX handlers run
                    if (!$this->security_handler->validateAjaxSecurity()) {
                        wp_die(-1);
                    }
                }
            }
        }, 1);

        // Then register our AJAX handlers
        $ajax_handlers = [
            'resync_all'            => [$this, 'handleResync'],
            'save_changes'          => [$this, 'handleSaveChanges'],
            'progress_hook'         => [$this, 'handleProgress'],
            'clearing_hook'         => [$this, 'handleClearContent'],
            'copy_files'            => [$this, 'handleCopyFiles'],
            'copy_clear'            => [$this, 'handleCopyClear'],
            'ignore_theme_change'   => [$this, 'handleIgnoreThemeChange'],
            'widgets_save'          => [$this, 'handleWidgetSave'],
            'widgets_view'          => [$this, 'handleWidgetView'],
            'widgets_edit'          => [$this, 'handleWidgetEdit'],
            'widgets_export'        => [$this, 'handleWidgetExport'],
            'widgets_import_view'   => [$this, 'handleWidgetImportView'],
            'widgets_import_submit' => [$this, 'handleWidgetImportSubmit'],
            'clear_log'             => [$this->log_handler, 'clearLogs'],
            'trigger_sync'          => [$this->cron_handler, 'triggerSyncNow'],
        ];

        foreach ($ajax_handlers as $action => $callback) {
            add_action("wp_ajax_{$action}", $callback);
        }
    }

    private function registerAdminHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminPages']);
        add_action('admin_menu', [$this, 'addOptiPubResourcesMenu']);
        add_filter('parent_file', [$this, 'forceOptiPubCurrentMenu']);
    }

    private function registerCronHooks(): void
    {
        add_action(Constants::getOptiPubCronJobName(), [$this->cron_handler, 'syncContent']);
    }

    private function validateUrl(string $url): string
    {
        $url = rtrim($url, '/');
        $url = str_replace('http:', 'https:', $url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid OptiPub Domain URL');
        }

        update_option(Constants::OPTIPUB_BASE_API_URL, $url);

        return $url;
    }

    private function validateToken(string $token, string $type = ''): string
    {
        $token = trim($token);

        if ($type === 'api') {
            if (empty($token)) {
                update_option(Constants::OPTIPUB_API_TOKEN, '');
                $this->logStaticError('API Token Empty');

                throw new Exception('Token is required');
            }
            $current_token = get_option(Constants::OPTIPUB_API_TOKEN);
        } elseif ($type === 'wp_auth') {
            if (empty($token)) {
                update_option(Constants::OPTIPUB_WP_AUTH_TOKEN, '');
            } else {
                $current_token = get_option(Constants::OPTIPUB_WP_AUTH_TOKEN);
            }
        } else {
            throw new Exception('Invalid token type');
        }

        // Encrypt if new token
        if ($token != $current_token) {
            $token_saved = EncryptDecryptUtil::setEncryptedToken($token, $type);

            if ($type === 'api') {
                update_option(Constants::OPTIPUB_TOKEN_VERIFIED, 0);
                $this->logStaticError('API Token Updated');
            } elseif ($type === 'wp_auth') {
                $this->logStaticError('WP Auth Token Updated');
            }

            return $token_saved;
        }

        return true;
    }

    private function validateAdminForm(string $url, string $token): bool
    {
        try {
            // Create API instance with new credentials to test them
            $api = BaseController::createOptiPubApiInstance();

            // Test the connection and get publications
            $publications = $api->getPublications();

            $token_verified = get_option(Constants::OPTIPUB_TOKEN_VERIFIED);

            if ($publications->data && $token_verified !== 1) {
                update_option(Constants::OPTIPUB_TOKEN_VERIFIED, 1);
            } else {
                throw new Exception('No publications found');
            }

            return true;
        } catch (Exception $e) {
            $this->logStaticError('Error validating API Token: ' . $e->getMessage() . "\n");
            update_option(Constants::OPTIPUB_TOKEN_VERIFIED, 0);

            return false;
        }
    }
}
