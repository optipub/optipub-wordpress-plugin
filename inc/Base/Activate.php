<?php

namespace OptiPub\Base;

use Defuse\Crypto\Key;
use OptiPub\Utilities\WPConfigTransformer;

class Activate
{
    public static function activate($network_wide)
    {
        // Prevent network-wide activation
        if ($network_wide) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                'OptiPub cannot be network activated. Please activate it on individual sites only.',
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }

        // Check if this is a multisite installation
        if (is_multisite()) {
            // Get current blog ID
            $blog_id = get_current_blog_id();

            // Switch to the current blog to ensure all operations are performed in correct context
            switch_to_blog($blog_id);

            self::activate_for_site();

            // Restore original blog
            restore_current_blog();
        } else {
            // Single site activation
            self::activate_for_site();
        }

        // Flush rewrite rules on activation
        flush_rewrite_rules();
    }

    public static function changePermalinkStructure()
    {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/%postname%/');
    }

    private static function activate_for_site()
    {
        flush_rewrite_rules();

        self::overrideWpConfigCronLockTimeoutConstant();
        self::addEncryptionKey();
        self::addPostRevision();
        self::changePermalinkStructure();
        self::createRedirectsTable();

        // Add an option to track that plugin has been activated for this site
        add_option('optipub_activated_for_site', true);
    }

    private static function overrideWpConfigCronLockTimeoutConstant(): void
    {
        if (!defined('WP_CRON_LOCK_TIMEOUT') || WP_CRON_LOCK_TIMEOUT < Constants::CRON_LOCK_TIMEOUT_IN_SECONDS) {
            try {
                $path = Helper::locate_wp_config();
                $wpConfiTransformer = new WPConfigTransformer($path);
                $args = [
                    'add'       => true,
                    'raw'       => true,
                    'normalize' => false,
                ];
                $result = $wpConfiTransformer->update('constant', 'WP_CRON_LOCK_TIMEOUT', strval(Constants::CRON_LOCK_TIMEOUT_IN_SECONDS), $args);

                if ($result) {
                    update_option(Constants::OPTIPUB_SUMMARY_LOG, 'A constant of WP_CRON_LOCK_TIMEOUT with value of ' . Constants::CRON_LOCK_TIMEOUT_IN_SECONDS . ' seconds was added to wp-config.php file for overriding Cron Job default lock timeout.<br>');
                }
            } catch (\Exception $exception) {
                $message = '<br>Failed adding WP_CRON_LOCK_TIMEOUT constant to wp-config.php. Please check write permissions.<br>Error message:' . $exception->getMessage() . '<br>';
                $summaryLog = get_option(Constants::OPTIPUB_SUMMARY_LOG);
                $existing = empty($summaryLog) ? $message : $summaryLog . $message;
                update_option(Constants::OPTIPUB_SUMMARY_LOG, $existing . $message);
            }
        }
    }

    private static function addEncryptionKey()
    {
        try {
            $definedKey = null;

            // Check if the key constant is already defined in wp-config.php
            if (defined(Constants::OPTIPUB_KEY_NAME)) {
                $definedKey = constant(Constants::OPTIPUB_KEY_NAME);
                BaseController::logStaticError('Using existing encryption key from wp-config.php');
            } else {
                // Key not defined, so let's create and add it
                $path = Helper::locate_wp_config();
                $wpConfigTransformer = new WPConfigTransformer($path);
                $args = [
                    'add'       => true,
                    'raw'       => false,
                    'normalize' => false,
                ];

                $key = Key::createNewRandomKey();
                $definedKey = $key->saveToAsciiSafeString();

                // Add the key to wp-config.php
                $result = $wpConfigTransformer->update('constant', Constants::OPTIPUB_KEY_NAME, $definedKey, $args);

                if ($result) {
                    BaseController::logStaticError('Successfully added encryption key to wp-config.php');
                } else {
                    throw new \Exception('Failed to add encryption key to wp-config.php');
                }
            }

            // For multisite, store the key in the current site's options table
            // This ensures each site has the proper reference to the key
            $optionKey = get_option(Constants::OPTIPUB_KEY_NAME);

            // Update the option if it is not set or different from the defined constant
            if ($optionKey !== $definedKey) {
                $updated = update_option(Constants::OPTIPUB_KEY_NAME, $definedKey);

                if ($updated) {
                    BaseController::logStaticError('Updated site-specific encryption key option');
                } else {
                    BaseController::logStaticError('Failed to update site-specific encryption key option');
                }
            }
        } catch (\Exception $exception) {
            BaseController::logStaticError('<br>Failed adding or updating ' . Constants::OPTIPUB_KEY_NAME . ' constant in wp-config.php or in the options. Please check write permissions.<br>Error message: ' . $exception->getMessage() . '<br>');
        }
    }

    /**
     * Disable Wordpress overwriting custom post statuses.
     */
    private static function addPostRevision()
    {
        if (! defined(WP_POST_REVISIONS)) {
            try {
                $path = Helper::locate_wp_config();
                $wpConfiTransformer = new WPConfigTransformer($path);
                $args = [
                    'add'       => true,
                    'raw'       => true,
                    'normalize' => false,
                ];
                $wpConfiTransformer->update('constant', 'WP_POST_REVISIONS', 'false', $args);
            } catch (\Exception $exception) {
                BaseController::logStaticError('<br>Failed adding WP_POST_REVISIONS constant to wp-config.php. Please check write permissions.<br>Error message:' . $exception->getMessage() . '<br>');
            }
        }
    }

    private static function createRedirectsTable()
    {
        if (defined(Constants::OPTIPUB_KEY_NAME)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'optipub_redirects';

            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                id MEDIUMINT(8) NOT NULL,
                domain_id SMALLINT(8) NOT NULL,
                from_url VARCHAR(255) NOT NULL,
                to_url VARCHAR(255) NOT NULL,
                code SMALLINT(3) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                regex TINYINT(1) NOT NULL,
                UNIQUE KEY id(id)
            );";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            return dbDelta($sql);
        }
    }
}
