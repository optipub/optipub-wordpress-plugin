<?php

/**
 * Dynamic sidebar handler for AJAX requests
 *
 * @package optipub
 */

if (! defined('ABSPATH')) {
    exit;
}

// Include WordPress functionality
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    require_once sanitize_text_field(wp_unslash($_SERVER['DOCUMENT_ROOT'])) . '/wp-load.php';
}

// Verify nonce and user capabilities
if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'optipub_ajax_admin')) {
    wp_die('Invalid nonce');
}

if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

// Handle sidebar request
if (isset($_POST['itemId']) && !empty($_POST['itemId'])) {
    $sidebar_id = sanitize_text_field(wp_unslash($_POST['itemId']));

    ob_start();
    dynamic_sidebar($sidebar_id);
    $output = ob_get_clean();

    echo wp_kses_post($output);
    exit;
}

wp_die('Invalid request');
