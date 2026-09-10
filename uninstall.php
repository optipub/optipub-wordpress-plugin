<?php

/**
 * Uninstall OptiPub Plugin
 *
 * @package optipub
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if we're in a multisite installation
if (is_multisite()) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        uninstall_for_site();
        restore_current_blog();
    }
} else {
    uninstall_for_site();
}

/**
 * Handles the uninstall process for a single site
 */
function uninstall_for_site()
{
    global $wpdb;

    // Delete posts created by plugin
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $wpdb->query($wpdb->prepare(
        "DELETE posts FROM {$wpdb->posts} posts 
         JOIN {$wpdb->postmeta} postmeta ON posts.id = postmeta.post_id 
         WHERE postmeta.meta_value = %s",
        'optipub-plugin'
    ));

    // Clean up orphaned postmeta
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $wpdb->query(
        "DELETE postmeta FROM {$wpdb->postmeta} postmeta 
         WHERE post_id NOT IN (SELECT id FROM {$wpdb->posts})"
    );

    // Clean up orphaned term relationships
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $wpdb->query(
        "DELETE relationships FROM {$wpdb->term_relationships} relationships 
         WHERE object_id NOT IN (SELECT id FROM {$wpdb->posts})"
    );

    // Delete terms created by plugin
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->terms} 
         WHERE term_id IN (
             SELECT DISTINCT term_id 
             FROM {$wpdb->termmeta} 
             WHERE meta_value = %s
         )",
        'optipub-plugin'
    ));

    // Clean up orphaned termmeta
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $wpdb->query(
        "DELETE FROM {$wpdb->termmeta} 
         WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->terms})"
    );

    // Clean up orphaned taxonomies
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $wpdb->query(
        "DELETE FROM {$wpdb->term_taxonomy} 
         WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->terms})"
    );

    // Delete custom content options
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND autoload='no'",
            $wpdb->esc_like('optipub_custom_') . '%'
        )
    );

    // Delete plugin options and widgets
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reason: No relevant caches.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s 
             OR option_name LIKE %s",
            $wpdb->esc_like('optipub_') . '%',
            $wpdb->esc_like('widget_optipub_') . '%',
            $wpdb->esc_like('widget_widget_optipub_') . '%'
        )
    );

    // Drop redirects table if it exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Reason: No relevant caches, schema change.
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}optipub_redirects");

    // Clear scheduled hooks
    wp_clear_scheduled_hook('optipub_sync');

    // Clear any transients
    delete_transient('doing_cron');

    // Clear plugin upload directory if it exists
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/optipub';

    if (is_dir($plugin_dir)) {
        recursive_rmdir($plugin_dir);
    }
}

/**
 * Helper function to recursively remove a directory using WordPress file system
 */
function recursive_rmdir($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    // Initialize WordPress file system
    global $wp_filesystem;

    if (empty($wp_filesystem)) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
    }

    $files = array_diff(scandir($dir), ['.', '..']);

    foreach ($files as $file) {
        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            recursive_rmdir($path);
        } else {
            wp_delete_file($path);
        }
    }

    // Use WordPress file system to remove directory
    if ($wp_filesystem && $wp_filesystem->is_dir($dir)) {
        $wp_filesystem->rmdir($dir);
    }
}
