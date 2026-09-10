<?php

namespace OptiPub\Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue all admin scripts and styles
 */
class Enqueue extends BaseController
{
    /**
     * Register hooks for admin enqueues
     */
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    public function enqueueAdmin(): void
    {
        // Always load styling script on admin pages
        wp_enqueue_script(
            'optipub-admin-styling',
            $this->plugin_url . 'admin/js/ajax-admin-styling.js',
            ['jquery'],
            filemtime($this->plugin_path . 'admin/js/ajax-admin-styling.js'),
            true
        );

        // Add CSS
        wp_enqueue_style(
            'optipub-dashicons',
            $this->plugin_url . 'admin/css/optipub-dashicons.css',
            [],
            filemtime($this->plugin_path . 'admin/css/optipub-dashicons.css')
        );

        // Get current screen to check if we're on our taxonomy page
        $screen = get_current_screen();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reason: We are not processing form information.
        $is_optipub_page = isset($_GET['page']) && $_GET['page'] === 'optipub';
        $is_optipub_taxonomy = $screen && $screen->taxonomy === Constants::OPTIPUB_PUBLICATION_TAXONOMY;

        // Only load on OptiPub pages
        if (!$is_optipub_page && !$is_optipub_taxonomy) {
            return;
        }

        // Add CSS
        wp_enqueue_style(
            'optipub-admin',
            $this->plugin_url . 'admin/css/optipub-style.css',
            [],
            filemtime($this->plugin_path . 'admin/css/optipub-style.css')
        );

        // Add JavaScript
        wp_enqueue_script(
            'optipub-admin',
            $this->plugin_url . 'admin/js/ajax-admin.js',
            ['jquery'],
            filemtime($this->plugin_path . 'admin/js/ajax-admin.js'),
            true
        );

        // Localize the script with new data
        wp_localize_script('optipub-admin', 'optipub_ajax_admin', [
            'nonce'   => wp_create_nonce('optipub_ajax_admin'),
            'ajaxurl' => admin_url('admin-ajax.php'),
        ]);
    }
}
