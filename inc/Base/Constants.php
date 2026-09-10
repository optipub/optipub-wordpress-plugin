<?php

namespace OptiPub\Base;

class Constants
{
    /**
     * OptiPub Admin Constants and Options.
     */

    // Plugin options
    public const OPTIPUB_PLUGIN_PATH = 'optipub_wp_plugin_path';
    public const OPTIPUB_REDIRECTS = 'optipub_redirects';

    // Cron job constants
    public const CRON_LOCK_TIMEOUT_IN_SECONDS = 600;
    public const CRON_INTERVAL_IN_SECONDS = 60;
    public const OPTIPUB_CRON_JOB_NAME_BASE = 'optipub_sync';

    // Cron job options - site specific
    public const OPTIPUB_LAST_RUN = 'optipub_last_run';
    public const OPTIPUB_SYNC_HAS_RUN = 'optipub_sync_has_run';
    public const OPTIPUB_SYNC_LOCK = 'optipub_sync_lock';
    /**
     * OPTIPUB_SYNC_LOCK is not actually blocking a new sync during run, WP cron scheduler should do that correctly
     * This option is just a way to determine if the sync is running or not
     * "SELECT * FROM wp_options WHERE option_name="optipub_sync_lock"";
     * 1 = running, 0 = not running.
     */

    // Sync options - site specific
    public const OPTIPUB_KEY_NAME = 'OPTIPUB_KEY';
    public const OPTIPUB_BASE_API_URL = 'optipub_base_api_url';
    public const OPTIPUB_API_TOKEN = 'optipub_api_token';
    public const OPTIPUB_WP_AUTH_TOKEN = 'optipub_wp_auth_token';
    public const OPTIPUB_SELECTED_PUBLICATION_ID = 'optipub_selected_publication_id';
    public const OPTIPUB_SELECTED_PUBLICATION_NAME = 'optipub_selected_publication_name';
    public const OPTIPUB_SELECTED_PUBLICATION_DATA = 'optipub_selected_publication_data';
    public const OPTIPUB_ADDITIONAL_PUBLICATIONS = 'optipub_additional_publications';
    public const OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX = 'optipub_additional_publications_prefix';
    public const OPTIPUB_SELECTED_DOMAIN_WEBSITE_TAGS = 'optipub_selected_domain_website_tags';
    public const OPTIPUB_TAGS_AS_TAGS = 'optipub_tags_as_tags';
    public const OPTIPUB_TAGS_AS_CATEGORIES = 'optipub_tags_as_categories';
    public const OPTIPUB_OPENGRAPH_IMAGE_LINK = 'optipub_opengraph_image_link';

    // Page category constants
    public const OPTIPUB_PAGE_CATEGORY_ID_HOME = 'optipub_page_category_id_home';
    public const OPTIPUB_PAGE_CATEGORY_ID_SUBSCRIBERS = 'optipub_page_category_id_subscribers';
    public const OPTIPUB_PAGE_CATEGORY_ID_LOGIN = 'optipub_page_category_id_login';

    public const OPTIPUB_TOKEN_VERIFIED = 'optipub_token_verified';
    public const OPTIPUB_PUBLICATION_CHANGED = 'optipub_publication_changed';

    // Custom content widget constants - site specific
    public const OPTIPUB_WIDGET_OPTION = 'optipub_cc_widgets';
    public const OPTIPUB_WIDGET_PREFIX = 'widget_optipub_cc_';
    public const OPTIPUB_REGISTER_WIDGET_PREFIX = 'optipub_cc_';

    // Custom post type options - site specific
    public const OPTIPUB_METADATA = 'optipub_metadata';

    // copy to theme constants
    public const OPTIPUB_TEMPLATES_PATH = 'templates';
    public const OPTIPUB_THEME_BASE_PATH = 'inc/optipub';
    public const OPTIPUB_THEME_WIDGET_PATH = 'inc/optipub/widget-templates';
    public const OPTIPUB_THEME_TEMPLATE_PATH = 'inc/optipub/theme-templates';
    public const OPTIPUB_TEMPLATE_PARTS_PATH = 'inc/optipub/template-parts';
    public const OPTIPUB_THEME_HELPER_FUNCTIONS = 'inc/optipub/php';

    // Theme related constants - site specific
    public const OPTIPUB_ACTIVE_THEME_NAME = 'optipub_active_theme_name';
    public const OPTIPUB_ACTIVE_THEME_DIR = 'optipub_active_theme_dir';
    public const OPTIPUB_ACTIVE_THEME_VERSION = 'optipub_active_theme_version';
    public const OPTIPUB_ACTIVE_THEME_TEMPLATE_DIR = 'optipub_active_theme_widget_dir';
    public const OPTIPUB_PREVIOUS_THEME_DIR = 'optipub_previous_theme_dir';

    // Log options
    public const OPTIPUB_SUMMARY_LOG = 'optipub_summary_log';
    public const OPTIPUB_COPY_FILE_LOG = 'optipub_copy_file_log';

    // Custom template options
    public const OPTIPUB_HOME_TEMPLATE = 'optipub_template_home_page';
    public const OPTIPUB_SUBSCRIBERS_TEMPLATE = 'optipub_template_subscribers_page';
    public const OPTIPUB_LOGIN_TEMPLATE = 'optipub_template_login_page';

    //Template file names
    public const HOME_PAGE_TEMPLATE_NAME = 'optipub_home_page.php';
    public const REPORTS_TEMPLATE_NAME = 'optipub_reports.php';
    public const RESOURCES_TEMPLATE_NAME = 'optipub_resources.php';
    public const VIDEOS_TEMPLATE_NAME = 'optipub_videos.php';
    public const FAQS_TEMPLATE_NAME = 'optipub_faqs.php';
    public const EDITOR_TEMPLATE_NAME = 'optipub_editors.php';
    public const SEARCH_TEMPLATE_NAME = 'optipub_search.php';
    public const ARCHIVE_TEMPLATE_NAME = 'optipub_archive.php';
    public const SUBSCRIBERS_TEMPLATE_NAME = 'optipub_subscribers.php';
    public const LOGIN_TEMPLATE_NAME = 'optipub_login.php';

    // Asset related constants - site specific
    public const OPTIPUB_SITE_LOGO_SRC = 'optipub_site_logo';
    public const OPTIPUB_SITE_FAVICON_SRC = 'optipub_site_favicon';

    // Admin Page Log Sizes
    public const OPTIPUB_SUMMARY_LOG_MAX_CHARS = 5000;
    public const OPTIPUB_SUMMARY_LOG_MAX_CHARS_INITIAL = 100000;

    // Add this constant
    public const OPTIPUB_PUBLICATION_TAXONOMY = 'optipub_publication';
    public const OPTIPUB_ROLE_PREFIX = 'optipub_publication_';

    public const OPTIPUB_WEBHOOK_SECRET = 'optipub_webhook_secret';
    public const OPTIPUB_SITE_KEY = 'optipub_site_key';

    // REQUEST PARAMS
    public const OPTIPUB_REQUEST_PARAM_PREAUTH = 'preauth';
    public const OPTIPUB_REQUEST_PARAM_ACTION = 'action';
    public const OPTIPUB_REQUEST_PARAM_EMAIL = 'email';
    public const OPTIPUB_REQUEST_PARAM_PASSWORD = 'password';
    public const OPTIPUB_REQUEST_PARAM_REDIRECT_TO = 'redirect_to';

    /**
     * Get maximum log size based on sync status
     * Smaller size for sites that have completed initial sync
     */
    public static function getSummaryLogMaxChars()
    {
        if (get_option(self::OPTIPUB_LAST_RUN)) {
            return self::OPTIPUB_SUMMARY_LOG_MAX_CHARS;
        }

        return self::OPTIPUB_SUMMARY_LOG_MAX_CHARS_INITIAL;
    }

    /**
     * Get the redirects table name for current site
     * Uses $wpdb->prefix to ensure proper multisite support
     */
    public static function getRedirectsTableName()
    {
        global $wpdb;

        return $wpdb->prefix . 'optipub_redirects';
    }

    public static function getOptiPubCronJobName(): string
    {
        global $wpdb;

        return self::OPTIPUB_CRON_JOB_NAME_BASE . '_' . $wpdb->blogid;
    }
}
