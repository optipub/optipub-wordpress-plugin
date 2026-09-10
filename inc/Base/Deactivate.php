<?php

namespace OptiPub\Base;

use OptiPub\Utilities\WPConfigTransformer;

class Deactivate
{
    public static function deactivate()
    {
        // Check if this is a multisite installation
        if (is_multisite()) {
            // Get current blog ID
            $blog_id = get_current_blog_id();

            // Switch to the current blog to ensure all operations are performed in correct context
            switch_to_blog($blog_id);

            self::deactivate_for_site();

            // Restore original blog
            restore_current_blog();
        } else {
            // Single site deactivation
            self::deactivate_for_site();
        }
    }

    private static function deactivate_for_site()
    {
        // Clear scheduled hooks for this site
        wp_clear_scheduled_hook(Constants::getOptiPubCronJobName());
        self::clear_legacy_favicon_sync_state();

        // Flush rewrite rules for this site
        flush_rewrite_rules();

        // Remove post revision constant if it exists
        self::removePostRevision();

        // Clean up site-specific options
        self::cleanup_site_options();

        // Remove the activation flag for this site
        delete_option('optipub_activated_for_site');
    }

    private static function clear_legacy_favicon_sync_state(): void
    {
        wp_clear_scheduled_hook('optipub_favicon_sync_retry');
        wp_clear_scheduled_hook('optipub_favicon_sync_health_check');

        delete_option('optipub_synced_favicon_url');
        delete_option('optipub_synced_favicon_path');
        delete_option('optipub_favicon_sync_last_attempt');
        delete_option('optipub_favicon_sync_retry_count');
        delete_option('optipub_favicon_sync_last_error');
    }

    private static function removePostRevision()
    {
        if (defined('WP_POST_REVISIONS')) {
            try {
                $path = Helper::locate_wp_config();
                $wpConfiTransformer = new WPConfigTransformer($path);
                $wpConfiTransformer->remove('constant', 'WP_POST_REVISIONS');
            } catch (\Exception $exception) {
                BaseController::logStaticError('<br>Failed Removing WP_POST_REVISIONS constant from wp-config.php. Please check write permissions.<br>Error message:' . $exception->getMessage() . '<br>');
            }
        }
    }

    private static function cleanup_site_options()
    {
        /*
        // Clean up plugin-specific options
        $options_to_delete = [
            Constants::OPTIPUB_LAST_RUN,
            Constants::OPTIPUB_SYNC_HAS_RUN,
            Constants::OPTIPUB_SYNC_LOCK,
            Constants::OPTIPUB_BASE_API_URL,
            Constants::OPTIPUB_API_TOKEN,
            Constants::OPTIPUB_KEY_NAME,
            Constants::OPTIPUB_SELECTED_PUBLICATION_ID,
            Constants::OPTIPUB_SELECTED_PUBLICATION_NAME,
            Constants::OPTIPUB_SELECTED_PUBLICATION_DATA,
            Constants::OPTIPUB_SELECTED_PUBLICATION_DOMAIN_ID,
            Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS,
            Constants::OPTIPUB_ADDITIONAL_PUBLICATIONS_PREFIX,
            Constants::OPTIPUB_TOKEN_VERIFIED,
            Constants::OPTIPUB_PUBLICATION_CHANGED,
            Constants::OPTIPUB_WIDGET_OPTION,
            Constants::OPTIPUB_METADATA,
            Constants::OPTIPUB_SUMMARY_LOG,
            Constants::OPTIPUB_COPY_FILE_LOG,
            Constants::OPTIPUB_HOME_TEMPLATE,
            Constants::OPTIPUB_SITE_LOGO_SRC,
            Constants::OPTIPUB_SITE_FAVICON_SRC
        ];
        */

        /**
         * Delete all options that start with 'optipub'
         */
        global $wpdb;

        // Check cache first
        $cache_key = 'optipub_options_to_delete';
        $options_to_delete = wp_cache_get($cache_key, 'optipub');

        if (false === $options_to_delete) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Need to use direct database query.
            $options_to_delete = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE LOWER(option_name) LIKE LOWER(%s)",
                    'optipub%'
                )
            );

            // Cache the results for a short time
            wp_cache_set($cache_key, $options_to_delete, 'optipub', 300);
        }

        foreach ($options_to_delete as $option) {
            delete_option($option);
        }

        // Clean up any custom tables for this site
        $table_name = $wpdb->prefix . 'optipub_redirects';

        // Only drop if table exists - using WordPress database API
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reason: Need to use direct database query.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) == $table_name) {
            // Use WordPress database API for schema changes
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Reason: Need to use direct database query, schema change.
            $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %s', $table_name));

            // Clear any related cache
            wp_cache_delete('optipub_redirects_table_exists', 'optipub');
        }
    }
}
